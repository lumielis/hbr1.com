<?php

namespace App\Services;

/**
 * The party calendar.
 *
 * Replaces `include/client/party.pl` (reached through
 * `/run/main.xp?action=party` and `/cgi-bin/main.xp?action=party`). 846 rows,
 * status 1 = published, and submissions were still arriving - this is a live
 * feature, not an archive.
 *
 * The legacy page had a three-level calendar (years / months / days) that was
 * rebuilt from a single `time_from` timestamp on every request. The maths below
 * is that arithmetic, kept faithfully, because the *output* is what the page
 * looks like: the selected year is a plain `calendar_current` entry, every
 * other year is `calendar_found` when it contains a party and `calendar`
 * otherwise, and the same three colours repeat for months and days.
 *
 * Two things are deliberately not reproduced:
 *
 *  - `DELETE FROM party WHERE create_timestamp < time - 86400 AND status = 0`
 *    ran on every page load. A read request should not delete rows; it is
 *    `pruneDrafts()`, called from the console.
 *
 *  - the legacy code fed Berlin wall-clock values to `timegm`/`timelocal` in an
 *    inconsistent mix. Dates here go through one `DateTimeZone`, so a day is
 *    the local day and the timestamps in the links round-trip through
 *    `requestedTimestamp()` unchanged.
 */
class PartyService
{
    /**
     * How many parties one page of the list shows. The legacy default was
     * `$form{'count'} <= 0 ? 5`, and the `count` parameter was never set by any
     * link the site generated.
     */
    public const PAGE_SIZE = 5;

    /**
     * The four options `<SELECT_PARTY_TYPE>` offered, written as
     * `Indoor\xFEOpen Air\xFEIndoor & Open Air\xFEClub`. Stored verbatim in
     * `party.type`, so the strings are part of the data contract.
     */
    public const TYPES = ['Indoor', 'Open Air', 'Indoor & Open Air', 'Club'];

    /** The flyer cap the legacy handler enforced: 100 KB. */
    public const FLYER_MAX_BYTES = 100000;

    /**
     * The columns a submitter may set, in the order {@see columns()} returns
     * them. `submitter`, `password`, the timestamps and `flyer` are handled
     * separately because they are not free text.
     */
    private const EDITABLE = [
        'title', 'start_timestamp', 'end_timestamp', 'type', 'country', 'city',
        'djs', 'live', 'chillout', 'deco', 'location', 'hotline', 'organizer',
        'email', 'url', 'memo',
    ];

    /** A party may run for at most three weeks - `86400 * 21` in the legacy code. */
    public const MAX_DURATION = 1814400;

    /** image type => the extension the legacy uploader would have derived. */
    private const FLYER_IMAGE_TYPES = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
    ];

    /** The extensions `<.FE>` accepted: `jpg|jpeg|gif|png`. */
    private const FLYER_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];

    private \DateTimeZone $timezone;

    public function __construct(
        private \Azera\Db\Database $db,
        string $timezone = 'Europe/Berlin',
        private ?string $flyerDir = null
    ) {
        $this->timezone = new \DateTimeZone($timezone);
    }

    /**
     * Everything the calendar page needs for one month.
     *
     * @return array{years: array, months: array, days: array, rows: array, current: array}
     */
    public function calendar(int $timestamp): array
    {
        $zone = $this->timezone;
        $base = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($zone);

        $year  = (int) $base->format('Y');
        $month = (int) $base->format('n');
        $day   = (int) $base->format('j');

        // The list is `load_parties()`: `end_timestamp >= time_from`, so a party
        // that started before the selected day but is still running stays on
        // the page.
        $parties = $this->partiesFrom($this->startOfDay($year, $month, $day));

        // One scan answers every calendar colour. The legacy page ran a
        // `parties_in_time()` query per cell - six years, twelve months and up
        // to thirty-one days - but the result was only ever used as a boolean
        // for the link's colour. That query had no status filter, so drafts
        // counted; see `partiesInRange()`.
        $marks = $this->marks($year - 3, $year + 2);

        return [
            'years'   => $this->years($year, $month, $day, $marks),
            'months'  => $this->months($year, $month, $day, $marks),
            'days'    => $this->days($year, $month, $day, $marks),
            'rows'    => $this->rows(array_map([$this, 'hydrate'], $parties)),
            'current' => [
                'year'      => $year,
                'month'     => $month,
                'day'       => $day,
                'timestamp' => $timestamp,
            ],
        ];
    }

    /**
     * Which years, months and days hold at least one party.
     *
     * Three lookup sets over one result set. The legacy page asked the same
     * question per cell with `parties_in_time()`; the only difference that
     * matters is the status filter, which is passed through.
     *
     * @return array{years: array<int, true>, months: array<string, true>, days: array<string, true>}
     */
    private function marks(int $fromYear, int $toYear, bool $publishedOnly = false): array
    {
        $from = $this->startOfDay($fromYear, 1, 1);
        $to   = $this->startOfDay($toYear + 1, 1, 1) - 1;

        $rows = $this->partiesInRange($from, $to, $publishedOnly);

        $years  = [];
        $months = [];
        $days   = [];

        foreach ($rows as $row) {
            $local = (new \DateTimeImmutable('@' . (int) $row['start_timestamp']))
                ->setTimezone($this->timezone);

            $years[(int) $local->format('Y')]        = true;
            $months[$local->format('Y-n')]          = true;
            $days[$local->format('Y-m-d')]          = true;
        }

        return ['years' => $years, 'months' => $months, 'days' => $days];
    }

    /**
     * The year strip: `current-3 .. current+2`.
     *
     * Every year links into the same day of the same month, clamped into
     * February, so following a year link keeps the listener roughly where they
     * were. `id` is the legacy link id, which is what carries the colour:
     * `calendar_current` for the selected year (`#0DBD43`), `calendar_found`
     * for a year that holds at least one party (`#ccee00`), `calendar`
     * otherwise (`#999999`).
     *
     * The colours matter more than they look: on production 2026 is green
     * because it is selected, 2023-2025 are yellow because they hold parties,
     * and 2027/2028 are grey because they hold none.
     *
     * @param  array{years: array<int, true>, months: array<string, true>, days: array<string, true>} $marks
     * @return array<int, array>
     */
    public function years(int $year, int $month, int $day, array $marks): array
    {
        $out = [];

        for ($y = $year - 3; $y < $year + 3; $y++) {
            $current = $y === $year;
            $found   = isset($marks['years'][$y]);

            $out[] = [
                'year'      => $y,
                'timestamp' => $this->startOfDay($y, $month, min($day, $this->daysIn($y, $month))),
                'current'   => $current,
                'found'     => $found,
                'id'        => $this->linkId($current, $found),
            ];
        }

        return $out;
    }

    /**
     * The twelve month names. The legacy markup broke the row every six cells
     * (`<IF_TIMES=6></tr><tr></IF>`), so `rowStart` marks cells 6 and 12 here.
     *
     * @param  array{years: array<int, true>, months: array<string, true>, days: array<string, true>} $marks
     * @return array<int, array>
     */
    public function months(int $year, int $month, int $day, array $marks): array
    {
        $out = [];

        for ($m = 1; $m <= 12; $m++) {
            $current = $m === $month;
            $found   = isset($marks['months'][$year . '-' . $m]);

            $out[] = [
                'month'     => $this->monthName($m),
                'number'    => $m,
                'timestamp' => $this->startOfDay($year, $m, min($day, $this->daysIn($year, $m))),
                'current'   => $current,
                'found'     => $found,
                'id'        => $this->linkId($current, $found),
                'rowStart'  => ($m - 1) % 6 === 0,
            ];
        }

        return $out;
    }

    /**
     * Every day of the selected month, as `01. Sun`, with the same six-cell row
     * breaks. `found` is true when a party starts that day.
     *
     * @param  array{years: array<int, true>, months: array<string, true>, days: array<string, true>} $marks
     * @return array<int, array>
     */
    public function days(int $year, int $month, int $day, array $marks): array
    {
        $out   = [];
        $count = $this->daysIn($year, $month);

        for ($d = 1; $d <= $count; $d++) {
            $start   = $this->startOfDay($year, $month, $d);
            $current = $d === $day;
            $found   = isset($marks['days'][sprintf('%04d-%02d-%02d', $year, $month, $d)]);

            $out[] = [
                'date'      => sprintf('%02d', $d),
                'day'       => (new \DateTimeImmutable('@' . $start))->setTimezone($this->timezone)->format('D'),
                'timestamp' => $start,
                'current'   => $current,
                'found'     => $found,
                'id'        => $this->linkId($current, $found),
                'rowStart'  => ($d - 1) % 6 === 0,
            ];
        }

        return $out;
    }

    /** The three legacy link ids, in the order the template tested them. */
    private function linkId(bool $current, bool $found): string
    {
        if ($current) {
            return 'calendar_current';
        }

        return $found ? 'calendar_found' : 'calendar';
    }

    /**
     * One page of the party list, each entry tagged with whether a new date
     * header is needed before it.
     *
     * The legacy `PARTY_TIMER` loop printed a `#200080` day header whenever the
     * start *day* changed and a `#000066` hairline whenever it did not, so two
     * events on the same day are separated by a rule instead of repeating the
     * date.
     *
     * @param  array<int, array> $parties
     * @return array<int, array>
     */
    public function rows(array $parties, int $offset = 0): array
    {
        $page = array_slice($parties, max(0, $offset), self::PAGE_SIZE);
        $out  = [];
        $last = null;

        foreach ($page as $party) {
            $date = $party['start_timestamp'] > 0
                ? (new \DateTimeImmutable('@' . $party['start_timestamp']))
                    ->setTimezone($this->timezone)->format('D, d. M Y')
                : '';

            $out[] = [
                'date'   => $date,
                'newDay' => $date !== $last,
                'party'  => $party,
            ];

            $last = $date;
        }

        return $out;
    }

    /**
     * Remove abandoned drafts: unpublished rows with no title, created more
     * than a day ago.
     *
     * The legacy page ran this `DELETE` on every request, which is why the
     * table has a handful of blank rows created minutes apart - each one is a
     * visit that started the "new party" form and walked away. Doing that
     * inside a GET handler is wrong, so this is an explicit console call
     * (`party:clean`) instead.
     *
     * Returns the number of rows removed.
     */
    public function pruneDrafts(int $olderThanSeconds = 86400): int
    {
        $statement = $this->db->query(
            'DELETE FROM `party` WHERE `status` = 0 AND `title` = ? AND `create_timestamp` < ?',
            ['', time() - $olderThanSeconds]
        );

        return $statement === false ? 0 : $statement->rowCount();
    }

    public function find(int $id): ?array
    {
        $row = $this->db->query('SELECT * FROM `party` WHERE `id` = ?', [$id])
            ->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Parties that start inside a date range.
     *
     * This is `parties_in_time()`, used only for the calendar cell colour. It
     * is the one query in the legacy code with **no `status` filter** - drafts
     * light up a day, a month and a year exactly like published parties do, and
     * the page's `DELETE` of abandoned drafts ran before the calendar was built,
     * so in practice it filtered them out. The port keeps that `DELETE` out of
     * the request path (see `pruneDrafts()`), so this method takes the status
     * flag and lets the caller decide.
     *
     * @return array<int, array>
     */
    public function partiesInRange(int $from, ?int $to = null, bool $publishedOnly = false): array
    {
        $to = $to === null || $to <= $from ? $from + 86399 : $to;

        return $this->db->query(
            'SELECT * FROM `party` WHERE `start_timestamp` >= ? AND `start_timestamp` <= ?'
            . ($publishedOnly ? ' AND `status` <> 0' : '')
            . ' ORDER BY `start_timestamp`, `end_timestamp` DESC',
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * The list under the grid: `load_parties()`.
     *
     * `status <> 0 AND end_timestamp >= time_from`, ordered by start with the
     * longest-running first among equal starts. Note the `end_timestamp`
     * filter rather than a range on `start_timestamp` - a party that began
     * before the selected month and is still running is expected to appear.
     *
     * @return array<int, array>
     */
    public function partiesFrom(int $from): array
    {
        return $this->db->query(
            'SELECT * FROM `party` WHERE `status` <> 0 AND `end_timestamp` >= ?'
            . ' ORDER BY `start_timestamp`, `end_timestamp` DESC',
            [$from]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * The four `<SELECT_PARTY_TYPE>` options, in the order the legacy select
     * listed them.
     *
     * @return array<int, string>
     */
    public function types(): array
    {
        return self::TYPES;
    }

    /**
     * Every country, ordered by name.
     *
     * The legacy `<SELECT_PARTY_COUNTRY>` ran `SELECT id, name FROM country
     * ORDER BY name` and emitted one option per row, so Afghanistan is the
     * first entry and - because nothing is marked `selected` - the one a
     * browser preselects on a fresh form. That wart is reproduced rather than
     * fixed: see the note in `views/web/party_form.clarity.html`.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function countries(): array
    {
        return $this->db->query('SELECT `id`, `name` FROM `country` ORDER BY `name`')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * The field values a blank form starts with.
     *
     * The legacy code reached a "new party" by inserting a draft row and then
     * reading its defaults back, so both timestamps were "now" and `url` came
     * from the column default. Those are the values reproduced here, without
     * the INSERT.
     *
     * @return array{present: bool}
     */
    public function blankForm(): array
    {
        $now = time();

        return $this->formValues([
            'start_timestamp' => $now,
            'end_timestamp'   => $now,
            'url'             => 'http://',
        ]);
    }

    /**
     * A stored party, expressed as the values the form controls echo back.
     *
     * Timestamps become the separate `_date` / `_time` strings the legacy
     * `to_form()` produced (`m/d/y` and `H:i`), and the text columns are run
     * back through `to_input()` so `<br />` shows as a newline in a textarea
     * instead of as literal markup.
     */
    public function editForm(array $party): array
    {
        return $this->formValues($party);
    }

    /**
     * Validate a submitted form.
     *
     * The rules are `party.pl`'s `step == 1` block, in the same order, so the
     * same first message surfaces. Two of them are relaxed:
     *
     *  - the legacy "future start" check could be bypassed with a `su` form
     *    field that any client could send. It is gone; an update whose stored
     *    start is unchanged keeps that start, which is what `su` was standing
     *    in for (editing a party that has already happened).
     *  - the "alpha numeric only" wording never matched the code - the check was
     *    a length test - so this is a length test too, and a name like
     *    `Captn Goa` keeps working.
     *
     * @return array<string, string> field => message, empty when valid
     */
    public function validate(array $input, ?array $existing = null): array
    {
        $errors = [];

        if (mb_strlen($this->text($input, 'title')) < 3) {
            $errors['title'] = 'The title needs at least three characters.';
        }

        $start = $this->timestamp($input, 'start_timestamp');
        $end   = $this->timestamp($input, 'end_timestamp');

        if ($start === null) {
            $errors['start_timestamp'] = 'Please enter the start as a date like 11/01/26 and a time like 22:00.';
        }

        if ($end === null) {
            $errors['end_timestamp'] = 'Please enter the end as a date like 11/01/26 and a time like 06:00.';
        }

        if ($start !== null) {
            $previous = $existing === null ? null : (int) $existing['start_timestamp'];

            // A start in the past is only allowed when it is the start the
            // party already has - otherwise every edit of a past event would
            // be rejected.
            if ($start < time() - 86400 && $start !== $previous) {
                $errors['start_timestamp'] = 'The party cannot start in the past.';
            }
        }

        if ($start !== null && $end !== null) {
            if ($end <= $start) {
                $errors['end_timestamp'] = 'The party has to end after it starts.';
            } elseif ($end > $start + self::MAX_DURATION) {
                $errors['end_timestamp'] = 'A party cannot run longer than three weeks.';
            }
        }

        if (mb_strlen($this->text($input, 'city')) < 3) {
            $errors['city'] = 'Please enter the city.';
        }

        if (mb_strlen($this->text($input, 'location')) < 3) {
            $errors['location'] = 'Please describe where the party takes place.';
        }

        $submitter = $this->text($input, 'submitter');

        if (mb_strlen($submitter) < 3) {
            $errors['submitter'] = 'Your name needs at least three characters.';
        } elseif (mb_strlen($submitter) > 32) {
            $errors['submitter'] = 'Your name may have at most 32 characters.';
        }

        $password = $this->text($input, 'password');

        if (mb_strlen($password) < 3) {
            $errors['password'] = 'The password needs at least three characters.';
        } elseif (mb_strlen($password) > 32) {
            $errors['password'] = 'The password may have at most 32 characters.';
        } elseif ($existing === null && $this->text($input, 'password2') !== $password) {
            // Only a new party is asked to confirm. The legacy template printed
            // the `Re:` field only while `PARTY_STATUS` was 0, i.e. only for a
            // draft - and a published party therefore could never change its
            // password at all. Keeping that: on an edit the field authenticates
            // the visitor, it does not set anything.
            $errors['password'] = 'The two passwords do not match.';
        }

        $country = $this->text($input, 'country');

        if ($country !== '' && preg_match('/^[A-Za-z]{2}$/', $country) !== 1) {
            $errors['country'] = 'Please choose a country from the list.';
        }

        $type = $this->text($input, 'type');

        if ($type !== '' && !in_array($type, self::TYPES, true)) {
            $errors['type'] = 'Please choose a type from the list.';
        }

        $email = $this->text($input, 'email');

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'This e-mail address does not look valid.';
        }

        $url = $this->text($input, 'url');

        // The column default is the literal `http://`, so an untouched field is
        // not an error - it just has nothing after the scheme.
        if ($url !== '' && $url !== 'http://' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors['url'] = 'This web address does not look valid.';
        }

        return $errors;
    }

    /**
     * Insert a new party and return its id.
     *
     * The legacy flow did this on *every* page view of the form (`party_id=new`
     * inserted a draft, then left it behind if the visitor walked away), which
     * is why the table holds a scatter of blank rows. This runs on POST only.
     *
     * `status = 1` publishes immediately, which is what the legacy form did -
     * there is no review queue and never was.
     *
     * @param array<string, string> $input
     */
    public function create(array $input): int
    {
        $now = time();

        $this->db->query(
            'INSERT INTO `party` (`status`, ' . implode(', ', array_map(
                static fn(string $c): string => '`' . $c . '`',
                self::EDITABLE
            )) . ', `submitter`, `create_timestamp`, `edit_timestamp`, `flyer`, `password`)'
            . ' VALUES (1, ' . implode(', ', array_fill(0, count(self::EDITABLE), '?')) . ', ?, ?, ?, ?, ?)',
            array_merge(
                $this->columns($input),
                [
                    $this->text($input, 'submitter'),
                    $now,
                    $now,
                    '',
                    $this->hash($this->text($input, 'password')),
                ]
            )
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Overwrite an existing party.
     *
     * Two columns are deliberately left alone:
     *
     *  - `submitter`, because the caller has already proved it knows the stored
     *    name - changing it here would let one visitor take over another's row.
     *  - `password`, because the caller may have left the field empty and
     *    re-hashing a blank would lock the owner out. Changing it goes through
     *    `changePassword()`.
     *
     * @param array<string, string> $input
     */
    public function update(int $id, array $input): void
    {
        $assignments = implode(', ', array_map(
            static fn(string $c): string => '`' . $c . '` = ?',
            self::EDITABLE
        ));

        $this->db->query(
            'UPDATE `party` SET ' . $assignments . ', `edit_timestamp` = ?, `status` = 1 WHERE `id` = ?',
            array_merge($this->columns($input), [time(), $id])
        );
    }

    /**
     * Check the name and password a submitter uses to edit their party.
     *
     * Rows written before this port hold the password in clear, because the
     * legacy form stored it verbatim in a `varchar(32)`. A hash cannot be
     * compared to clear text, so the shape of the stored value decides:
     * anything that looks like a `password_hash()` output goes through
     * `password_verify()`, everything else is compared with `hash_equals()`
     * and upgraded to a hash on success. That way the 800-odd existing parties
     * stay editable, and the first edit of each one silently fixes it.
     */
    public function verifyPassword(array $party, string $password): bool
    {
        $stored = (string) ($party['password'] ?? '');

        if ($stored === '') {
            return false;
        }

        if (password_get_info($stored)['algo'] !== null) {
            return password_verify($password, $stored);
        }

        if (!hash_equals($stored, $password)) {
            return false;
        }

        $this->changePassword((int) $party['id'], $password);

        return true;
    }

    /** Replace the stored password hash. */
    public function changePassword(int $id, string $password): void
    {
        $this->db->query(
            'UPDATE `party` SET `password` = ? WHERE `id` = ?',
            [$this->hash($password), $id]
        );
    }

    /**
     * Whether an existing row can be edited by this name and password.
     *
     * An empty stored name means the row predates the submitter column; those
     * accept any name, matching the legacy `if ($password)` test.
     */
    public function mayEdit(array $party, string $submitter, string $password): bool
    {
        $stored = trim((string) ($party['submitter'] ?? ''));

        if ($stored !== '' && $stored !== trim($submitter)) {
            return false;
        }

        return $this->verifyPassword($party, $password);
    }

    /**
     * Take a submitted flyer and put it somewhere it can be inspected.
     *
     * Returns the absolute path of a scratch file, or null with `$error` set to
     * the message the legacy page printed.
     *
     * The file has to reach the disk before it can be judged: the image type is
     * a property of the bytes, and `UploadedFile` deliberately does not expose
     * the temporary path. Staging under a `.upload` name in the flyer directory
     * is not publishing anything - the web server will not serve it, and
     * `commitFlyer()` renames it into place only once a row exists to name it
     * after. A rejected file is removed again.
     *
     * The check runs *before* the party is inserted, which is the order
     * `party.pl` used: it called `upload_file()` before `$party->save()`, so a
     * bad image never published the party.
     */
    public function stageFlyer(\Azera\Http\UploadedFile $file, ?string &$error = null): ?string
    {
        $error = null;

        if (!$file->isValid() || $file->size() <= 0) {
            $error = 'Unknown error has occured.';
            return null;
        }

        // `upload_file()` refused anything over 100 KB.
        if ($file->size() > self::FLYER_MAX_BYTES) {
            $error = 'The flyer is to big - 100k maximal';
            return null;
        }

        // The suffix is what `<.FE>` used to pick the stored extension, so it is
        // still checked - but it no longer decides anything: the extension comes
        // from the image itself below.
        $ext = strtolower(pathinfo($file->clientFilename(), PATHINFO_EXTENSION));

        if (!in_array($ext, self::FLYER_EXTENSIONS, true)) {
            $error = 'The filetype of the fyler is not permitted.';
            return null;
        }

        $target = $this->flyerDirectory();

        if ($target === null) {
            $error = 'Unknown error has occured.';
            return null;
        }

        $scratch = $target . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.upload';

        try {
            $file->moveTo($scratch);
        } catch (\RuntimeException $e) {
            error_log('party: flyer move ' . $e->getMessage());

            $error = 'Unknown error has occured.';
            return null;
        }

        $info = @getimagesize($scratch);

        if ($info === false || !isset(self::FLYER_IMAGE_TYPES[$info[2]])) {
            @unlink($scratch);

            $error = 'The filetype of the fyler is not permitted.';
            return null;
        }

        return $scratch;
    }

    /**
     * Move a staged flyer to `<id>.<extension>` and return the name to write
     * into `party.flyer`.
     *
     * The extension comes from the image, which is why `evil.php` renamed to
     * `evil.jpg` never lands under a name the web server serves.
     */
    public function commitFlyer(int $id, string $scratch, ?string &$error = null): string
    {
        $error = null;

        $info = @getimagesize($scratch);

        if ($info === false || !isset(self::FLYER_IMAGE_TYPES[$info[2]])) {
            @unlink($scratch);

            $error = 'The filetype of the fyler is not permitted.';
            return '';
        }

        $directory = $this->flyerDirectory();

        if ($directory === null) {
            @unlink($scratch);

            $error = 'Unknown error has occured.';
            return '';
        }

        $name = $id . '.' . self::FLYER_IMAGE_TYPES[$info[2]];

        if (!@rename($scratch, $directory . DIRECTORY_SEPARATOR . $name)) {
            @unlink($scratch);

            $error = 'Unknown error has occured.';
            return '';
        }

        return $name;
    }

    /** Throw away a staged flyer - the request did not get as far as a row. */
    public function discardFlyer(?string $scratch): void
    {
        if ($scratch !== null && is_file($scratch)) {
            @unlink($scratch);
        }
    }

    /**
     * Point `party.flyer` at a file, removing the flyer it replaced.
     *
     * The legacy `unlink` ran on the old name whenever the new one differed,
     * which is why switching a flyer from `.jpg` to `.gif` does not leave the
     * old image behind. The name is validated before unlinking: a `flyer`
     * column edited by hand must not be able to delete a file outside the
     * flyer directory.
     */
    public function setFlyer(int $id, string $name): void
    {
        $current = (string) $this->db->query(
            'SELECT `flyer` FROM `party` WHERE `id` = ?',
            [$id]
        )->fetchColumn();

        $this->db->query('UPDATE `party` SET `flyer` = ? WHERE `id` = ?', [$name, $id]);

        if ($current !== '' && $current !== $name && preg_match('/^\d+\.(jpg|jpeg|gif|png)$/', $current) === 1) {
            $directory = $this->flyerDirectory();

            if ($directory !== null) {
                @unlink($directory . DIRECTORY_SEPARATOR . $current);
            }
        }
    }

    /**
     * The stored flyer as a URL, or an empty string.
     *
     * Kept as a static helper so a view can ask without the service. The path
     * matches `path['images'] . '/party/flyer'` in the legacy settings.
     */
    public static function flyerUrl(string $flyer): string
    {
        return $flyer === '' ? '' : '/images/party/flyer/' . rawurlencode($flyer);
    }

    private function startOfDay(int $year, int $month, int $day): int
    {
        return (new \DateTimeImmutable('now', $this->timezone))
            ->setDate($year, $month, $day)->setTime(0, 0)->getTimestamp();
    }

    private function daysIn(int $year, int $month): int
    {
        return (int) (new \DateTimeImmutable('now', $this->timezone))
            ->setDate($year, $month, 1)->format('t');
    }

    /**
     * The ordered column list shared by `create()` and `update()`.
     *
     * `party.pl` assigned each field a `data_type` and `ssms::dbi::table::from_form()`
     * dispatched on it: `tagout` fields went through `to_sql()`, everything else
     * was stored as typed. `title`, `city`, `hotline` and `organizer` are
     * `tagout`, which is why the table holds `M&uuml;nchen` entities and `&lt;`
     * in those columns. Reproduced here so a new row is indistinguishable from
     * the 800 that are already there.
     *
     * @param  array<string, string> $input
     * @return array<int, mixed>
     */
    private function columns(array $input): array
    {
        return [
            $this->tagout($input, 'title'),
            $this->timestamp($input, 'start_timestamp') ?? 0,
            $this->timestamp($input, 'end_timestamp') ?? 0,
            $this->text($input, 'type'),
            strtoupper($this->text($input, 'country')),
            $this->tagout($input, 'city'),
            $this->tagout($input, 'djs'),
            $this->tagout($input, 'live'),
            $this->tagout($input, 'chillout'),
            $this->tagout($input, 'deco'),
            $this->tagout($input, 'location'),
            $this->tagout($input, 'hotline'),
            $this->tagout($input, 'organizer'),
            $this->text($input, 'email'),
            $this->text($input, 'url'),
            $this->tagout($input, 'memo'),
        ];
    }

    /** A trimmed, single-line-ish value from the submitted form. */
    private function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * A `tagout` column, stored the way `ssms::system::to_sql()` stored it.
     *
     * That function escaped `<`, `>`, and `"`, deliberately left `&` alone -
     * which is why rows in the table hold `M&uuml;nchen` as literal text and it
     * still renders as `München` - and turned every newline into `<br />`.
     *
     * The escaping has to happen on the way in: the detail page prints these
     * columns with `|raw`, so an unescaped `<script>` would run for every
     * visitor. The German-entity replacements `to_sql()` also did are not
     * reproduced; they existed because the Perl strings were latin1 and these
     * columns round-trip UTF-8 (`Müller`, `€`) intact now.
     *
     * A browser submits `\r\n`; that is normalised first, exactly as the legacy
     * CGI loader did.
     */
    private function tagout(array $input, string $key): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $this->text($input, $key));

        // `to_sql()` touched exactly three characters, and `htmlspecialchars()`
        // would escape `&` too - turning an existing `M&uuml;nchen` into
        // `M&amp;uuml;nchen`, which is the double-escaping the calendar already
        // suffers from.
        $value = str_replace(['<', '>', '"'], ['&lt;', '&gt;', '&quot;'], $value);

        return str_replace("\n", '<br />', $value);
    }

    /**
     * A `*_timestamp` pair of controls as a unix timestamp, or null when the
     * two do not parse.
     *
     * The legacy `from_form()` accepted `mm/dd/yy` (or `dd.mm.yy` in a German
     * setup) plus `H:i`, defaulted a two-digit year to 20xx, and compared the
     * result to the server's local midnight. `App::TIMEZONE` is the same zone
     * the calendar renders in, so a value typed here is the value the calendar
     * shows back.
     */
    public function timestamp(array $input, string $key, ?string $defaultDate = null): ?int
    {
        $date = $this->text($input, $key . '_date');
        $time = $this->text($input, $key . '_time');

        if ($date === '' && $defaultDate !== null) {
            $date = $defaultDate;
        }

        if (preg_match('#^(\d{1,2})[/.](\d{1,2})[/.](\d{2}|\d{4})$#', $date, $m) !== 1) {
            return null;
        }

        // The legacy setup uses `m/d/y`, so the first number is the month.
        $month = (int) $m[1];
        $day   = (int) $m[2];
        $year  = (int) $m[3];

        if ($year < 100) {
            $year += 2000;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $t) !== 1) {
            return null;
        }

        $hour   = (int) $t[1];
        $minute = (int) $t[2];

        if ($month < 1 || $month > 12 || $hour > 23 || $minute > 59) {
            return null;
        }

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return (new \DateTimeImmutable('now', $this->timezone))
            ->setDate($year, $month, $day)
            ->setTime($hour, $minute)
            ->getTimestamp();
    }

    /**
     * The values the form controls echo back.
     *
     * Dates are `n/j/yy` and times `H:i`, which is what `to_form()` produced
     * with `m/d/y` / `H:m - the `m` in the time format is minutes, not months.
     *
     * @return array<string, string>
     */
    private function formValues(array $row): array
    {
        $values = [
            'title'    => (string) ($row['title'] ?? ''),
            'type'     => (string) ($row['type'] ?? ''),
            'country'  => (string) ($row['country'] ?? ''),
            'city'     => (string) ($row['city'] ?? ''),
            'hotline'  => (string) ($row['hotline'] ?? ''),
            'organizer' => (string) ($row['organizer'] ?? ''),
            'email'    => (string) ($row['email'] ?? ''),
            'url'      => (string) ($row['url'] ?? 'http://'),
            'submitter' => (string) ($row['submitter'] ?? ''),
        ];

        foreach (['djs', 'live', 'chillout', 'deco', 'location', 'memo'] as $key) {
            $values[$key] = $this->toInput((string) ($row[$key] ?? ''));
        }

        $now = time();

        foreach (['start_timestamp', 'end_timestamp'] as $key) {
            $timestamp = (int) ($row[$key] ?? 0);

            if ($timestamp <= 0) {
                $timestamp = $now;
            }

            $local = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($this->timezone);

            // `n/j/y`, exactly as `to_form()` printed it for the `m/d/y` setup:
            // no leading zeros on month and day, two-digit year.
            $values[$key . '_date'] = $local->format('n/j/y');
            $values[$key . '_time'] = $local->format('H:i');
        }

        return $values;
    }

    /**
     * The inverse of `tagout()`: stored `<br />` back to a newline.
     *
     * `to_input()` also turned the German entities back into characters, and so
     * does this. The effect is that a textarea shows `München` rather than
     * `M&uuml;nchen`, exactly as the legacy form did.
     *
     * Consequence worth knowing: saving that form writes `München` back, so
     * editing a legacy row **normalises its entities once**. The rendered output
     * is identical either way - the detail page decodes on read - and the
     * columns are cp1252 (`latin1`), so `ü`, `ö`, `ä`, `ß` and `€` all store
     * losslessly. Characters outside cp1252 still degrade to `?`, which is a
     * property of the schema and unchanged from the Perl era.
     */
    private function toInput(string $value): string
    {
        return str_replace('<br />', "\n", $this->decodeEntities($value));
    }

    /** A `password_hash()` output; Argon2id where the build has it. */
    private function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Where flyers live: `path['images'] . '/party/flyer'`.
     *
     * Resolved from the configured legacy document root rather than from the
     * hardcoded production path, so the dev tree writes into its own copy.
     */
    private function flyerDirectory(): ?string
    {
        $base = $this->flyerDir;

        if ($base === null) {
            return null;
        }

        if (!is_dir($base) && !@mkdir($base, 0775, true) && !is_dir($base)) {
            return null;
        }

        return is_writable($base) ? $base : null;
    }

    private function monthName(int $month): string
    {
        return ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'][$month - 1];
    }

    /**
     * Normalise a row for the views: resolved country name, formatted dates,
     * comment count, and a safe flyer path.
     */
    private function hydrate(array $row): array
    {
        $row['id']               = (int) $row['id'];
        $row['status']           = (int) $row['status'];
        $row['start_timestamp']  = (int) $row['start_timestamp'];
        $row['end_timestamp']    = (int) $row['end_timestamp'];
        $row['create_timestamp'] = (int) $row['create_timestamp'];

        $row['countryName'] = (string) $row['country'] === ''
            ? ''
            : (string) ($this->db->query(
                'SELECT `name` FROM `country` WHERE `id` = ?',
                [$row['country']]
            )->fetchColumn() ?: $row['country']);

        $tz = $this->timezone;

        $row['startDate'] = $row['start_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['start_timestamp']))->setTimezone($tz)->format('D, d. M Y')
            : '';
        $row['startTime'] = $row['start_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['start_timestamp']))->setTimezone($tz)->format('H:i')
            : '';
        $row['endDate'] = $row['end_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['end_timestamp']))->setTimezone($tz)->format('D, d. M Y')
            : '';
        $row['endTime'] = $row['end_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['end_timestamp']))->setTimezone($tz)->format('H:i')
            : '';

        // The detail page's `Update` row, `PARTY_EDIT_TIMESTAMP_DATE, _TIME`.
        $row['edit_timestamp'] = (int) ($row['edit_timestamp'] ?? 0);
        $row['editDate'] = $row['edit_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['edit_timestamp']))->setTimezone($tz)->format('D, d. M Y')
            : '';
        $row['editTime'] = $row['edit_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['edit_timestamp']))->setTimezone($tz)->format('H:i')
            : '';

        // Imported rows carry the source they came from; `IF_PARTY_SOURCE_ID`
        // gated the whole footer on it.
        $row['sourceId']   = (int) ($row['source_id'] ?? 0);
        $row['sourceName'] = (string) ($row['source_name'] ?? '');
        $row['sourceUrl']  = (string) ($row['source_url'] ?? '');

        // A flyer is only a filename in the DB; it lives under the legacy tree.
        $flyer = (string) ($row['flyer'] ?? '');
        $row['flyerUrl'] = $flyer === ''
            ? ''
            : '/images/party/flyer/' . rawurlencode($flyer);

        $row['commentCount'] = (int) $this->db->query(
            'SELECT COUNT(*) FROM `party_comment` WHERE `party_id` = ?',
            [$row['id']]
        )->fetchColumn();

        // The legacy third row printed these flags with a trailing space each,
        // only when set, so `Flyer Live DJs Chill Deco` is the literal output.
        $flags = '';
        foreach ([['flyer', 'Flyer'], ['live', 'Live'], ['djs', 'DJs'], ['chillout', 'Chill'], ['deco', 'Deco']] as [$key, $label]) {
            if (!empty($row[$key]) && (string) $row[$key] !== '0') {
                $flags .= $label . ' ';
            }
        }
        $row['flags'] = $flags === '' ? '' : substr($flags, 0, -1);

        // `title_data_type => 'tagout'` in the model, so the column holds
        // `Sven V&auml;th` rather than `Sven Väth`. The legacy template printed
        // that raw, which the browser then decoded; this template auto-escapes,
        // so the value is decoded here and escaped on output. The result is the
        // same `Väth` on screen, and no double-escaped `V&amp;auml;th`.
        //
        // Only the `tagout` columns the templates print through `{{ }}` are
        // decoded. `djs`, `live`, `chillout`, `deco`, `location` and `memo` are
        // printed with `|raw` because they carry the `<br />` markers this
        // format puts there - decoding those would turn a stored
        // `&lt;script&gt;` into live markup.
        foreach (['title', 'city', 'organizer', 'hotline', 'type'] as $field) {
            $row[$field] = $this->decodeEntities((string) ($row[$field] ?? ''));
        }

        $title = trim($row['title']);
        $row['title'] = $title;
        $row['titleShort'] = mb_strlen($title) > 30 ? mb_substr($title, 0, 27) . '...' : $title;

        return $row;
    }

    /**
     * Turn the entities `to_sql()` left in a column back into characters.
     *
     * Only the entities the legacy code could have produced are decoded, plus
     * the three it escaped (`&lt;`, `&gt;`, `&quot;`) and `&amp;`. Anything else
     * - a stray `&nbsp;` a submitter typed - is left alone, so a value that was
     * never encoded is not mangled.
     */
    private function decodeEntities(string $value): string
    {
        return str_replace(
            [
                '&uuml;', '&ouml;', '&auml;', '&Uuml;', '&Ouml;', '&Auml;',
                '&szlig;', '&euro;', '&quot;', '&lt;', '&gt;', '&amp;',
            ],
            [
                'ü', 'ö', 'ä', 'Ü', 'Ö', 'Ä',
                'ß', '€', '"', '<', '>', '&',
            ],
            $value
        );
    }
}
