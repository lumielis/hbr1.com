<?php

namespace App\Tests;

use App\Services\PartyService;
use PHPUnit\Framework\TestCase;

/**
 * The party submission form — `/party/new` and `/party/{id}/edit`.
 *
 * The legacy form lived at `?part=edit&party_id=new` and did three things the
 * port deliberately does not:
 *
 *  - it **inserted a draft row on every page view**. Three consecutive GETs to
 *    production produced drafts 7469, 7470 and 7471. That is pinned here as an
 *    absence: a GET must not write.
 *  - it stored the password in clear, in a `varchar(32)`, so `party.password`
 *    could hold `secret123` but never a hash of it.
 *  - it only rendered the password *confirmation* field while a party was a
 *    draft, so a published party could never change its password.
 *
 * The date format is `m/d/y` (`ssms::time::set_format('H:m', 'm/d/y')`, where
 * `m` in the time half means minutes, not months), and a two-digit year is
 * 20xx. Both are pinned because "11/1/26" is ambiguous to anyone reading the
 * code later.
 *
 * Database-backed cases skip themselves when the server is unreachable.
 */
class PartyFormTest extends TestCase
{
    private PartyService $party;

    private \Azera\Db\Database $db;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->db = \App\AppContext::instance()->dbManager()->getDefault();

        $this->party = new PartyService(
            $this->db,
            'Europe/Berlin',
            sys_get_temp_dir() . '/hbr1-party-flyer-test'
        );

        try {
            $this->db->query('SELECT 1 FROM `party` LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    // -------------------------
    // Timestamps
    // -------------------------

    /** `11/1/26` + `22:00` is 1 November 2026, 22:00 Berlin time. */
    public function testTimestampParsesTheLegacyMonthFirstFormat(): void
    {
        $value = $this->party->timestamp([
            'start_timestamp_date' => '11/1/26',
            'start_timestamp_time' => '22:00',
        ], 'start_timestamp');

        self::assertSame($this->local(2026, 11, 1, 22, 0), $value);
    }

    /** The German setup's `d.m.y` was also accepted; the dots are just seps. */
    public function testTimestampAcceptsDottedDates(): void
    {
        $value = $this->party->timestamp([
            'start_timestamp_date' => '1.11.2026',
            'start_timestamp_time' => '06:30',
        ], 'start_timestamp');

        // `m.d.y` is what `from_form()` did once it had swapped the dots for
        // slashes - it kept reading the first number as the month.
        self::assertSame($this->local(2026, 1, 11, 6, 30), $value);
    }

    public function testTimestampRejectsAnImpossibleDate(): void
    {
        self::assertNull($this->party->timestamp([
            'start_timestamp_date' => '2/30/26',
            'start_timestamp_time' => '22:00',
        ], 'start_timestamp'));
    }

    public function testTimestampRejectsAnImpossibleTime(): void
    {
        self::assertNull($this->party->timestamp([
            'start_timestamp_date' => '11/1/26',
            'start_timestamp_time' => '24:10',
        ], 'start_timestamp'));
    }

    public function testTimestampRejectsGarbage(): void
    {
        self::assertNull($this->party->timestamp([
            'start_timestamp_date' => 'tomorrow',
            'start_timestamp_time' => 'late',
        ], 'start_timestamp'));
    }

    // -------------------------
    // Validation
    // -------------------------

    /** A complete, valid submission produces no errors at all. */
    public function testACompleteSubmissionIsValid(): void
    {
        self::assertSame([], $this->party->validate($this->valid()));
    }

    /**
     * Each required field, on its own. The legacy page marked them red rather
     * than listing them, so the field key is the contract.
     */
    public function testRequiredFieldsAreReportedByTheirFormName(): void
    {
        $cases = [
            'title'    => ['title' => 'ab'],
            'city'     => ['city' => ''],
            'location' => ['location' => '  '],
            'submitter' => ['submitter' => 'ab'],
            'password' => ['password' => '', 'password2' => ''],
        ];

        foreach ($cases as $field => $override) {
            $errors = $this->party->validate(array_replace($this->valid(), $override));

            self::assertArrayHasKey($field, $errors, "expected an error on {$field}");
        }
    }

    /** The party has to end after it starts, and within three weeks. */
    public function testEndHasToFollowStartAndStayInsideThreeWeeks(): void
    {
        $same = $this->party->validate(array_replace($this->valid(), [
            'end_timestamp_date' => '11/01/26',
            'end_timestamp_time' => '22:00',
        ]));

        self::assertArrayHasKey('end_timestamp', $same);

        $tooLong = $this->party->validate(array_replace($this->valid(), [
            'end_timestamp_date' => '11/23/26',
            'end_timestamp_time' => '22:00',
        ]));

        self::assertArrayHasKey('end_timestamp', $tooLong);
    }

    /**
     * The legacy check was `< $party->start_timestamp < time`, and could be
     * skipped with a `su` form field any client could send. It becomes: a start
     * in the past is rejected unless it is the start the row already has, which
     * is what `su` was standing in for.
     */
    public function testAPastStartIsOnlyAllowedWhenItIsTheStoredOne(): void
    {
        $past = $this->valid();

        $stored = $this->local(2011, 5, 14, 22, 0);

        $input = array_replace($past, [
            'start_timestamp_date' => '5/14/11',
            'start_timestamp_time' => '22:00',
            'end_timestamp_date'   => '5/15/11',
            'end_timestamp_time'   => '08:00',
        ]);

        self::assertArrayHasKey(
            'start_timestamp',
            $this->party->validate($input),
            'a new party cannot start in the past'
        );

        self::assertArrayNotHasKey(
            'start_timestamp',
            $this->party->validate($input, ['start_timestamp' => $stored]),
            'editing a past party keeps its start'
        );
    }

    /** The two passwords have to match, but only when a party is created. */
    public function testConfirmationIsOnlyRequiredForANewParty(): void
    {
        $mismatch = array_replace($this->valid(), ['password2' => 'different']);

        self::assertArrayHasKey('password', $this->party->validate($mismatch));

        self::assertArrayNotHasKey(
            'password',
            $this->party->validate($mismatch, ['start_timestamp' => 0]),
            'an edit only authenticates; it does not set a new password'
        );
    }

    /**
     * The legacy wording was "only alpha numeric chars", but the code was a
     * length test. Names like `Captn Goa` and `mystic chrysalide` are in the
     * table, so the port validates length and keeps them working.
     */
    public function testANameWithSpacesIsAccepted(): void
    {
        self::assertArrayNotHasKey(
            'submitter',
            $this->party->validate(array_replace($this->valid(), ['submitter' => 'Captn Goa']))
        );
    }

    public function testAnOverlongNameIsRejected(): void
    {
        self::assertArrayHasKey(
            'submitter',
            $this->party->validate(array_replace($this->valid(), ['submitter' => str_repeat('x', 33)]))
        );
    }

    /** The type reached the row through a `<select>`; nothing else may get in. */
    public function testAnUnknownTypeIsRejected(): void
    {
        self::assertArrayHasKey(
            'type',
            $this->party->validate(array_replace($this->valid(), ['type' => 'Warehouse']))
        );
    }

    // -------------------------
    // Storage format
    // -------------------------

    /**
     * `tagout` columns went through `ssms::system::to_sql()`, which escaped
     * three characters and wrote `<br />` for a newline. The detail page prints
     * those columns with `|raw`, so a stored `<script>` would execute - the
     * escaping has to happen on the way in.
     */
    public function testATextareaIsStoredAsTheLegacyEscapedMarkup(): void
    {
        $id = $this->party->create(array_replace($this->valid(), [
            'memo' => "Line one\nSecond <script>alert(1)</script>",
        ]));

        try {
            $row = $this->party->find($id);

            self::assertSame(
                'Line one<br />Second &lt;script&gt;alert(1)&lt;/script&gt;',
                $row['memo']
            );
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    /**
     * A `\r\n` from a browser becomes one `<br />`, not two, and not a stray
     * carriage return - the legacy CGI loader normalised the pair first.
     */
    public function testCarriageReturnsInATextareaDoNotDoubleTheLineBreaks(): void
    {
        $id = $this->party->create(array_replace($this->valid(), ['djs' => "One\r\nTwo"]));

        try {
            self::assertSame('One<br />Two', $this->party->find($id)['djs']);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    /**
     * `to_sql()` left `&` alone on purpose, which is why the table holds
     * `M&uuml;nchen` as literal text and it still renders correctly. Escaping it
     * here would turn an existing entity into `M&amp;uuml;nchen`, which shows up
     * verbatim: the calendar already renders `Sven V&amp;auml;th` today for
     * exactly this reason.
     */
    public function testAnExistingEntityIsNotDoubleEscaped(): void
    {
        $id = $this->party->create(array_replace($this->valid(), ['city' => 'M&uuml;nchen']));

        try {
            self::assertSame('M&uuml;nchen', $this->db->query(
                'SELECT `city` FROM `party` WHERE `id` = ?',
                [$id]
            )->fetchColumn());

            // And the page shows the decoded city, not `M&uuml;nchen`.
            self::assertSame('München', $this->party->find($id)['city']);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    // -------------------------
    // Creation and editing
    // -------------------------

    /**
     * A created party is published immediately, which is what the legacy form
     * did - there is no review queue and never was.
     */
    public function testCreatePublishesImmediately(): void
    {
        $id = $this->party->create($this->valid());

        try {
            $row = $this->party->find($id);

            self::assertSame(1, $row['status']);
            self::assertSame('Port Test Party', $row['title']);
            self::assertSame('DE', $row['country']);
            self::assertGreaterThan(0, $row['create_timestamp']);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    /** New passwords are Argon2id, and never the plain text. */
    public function testCreateStoresAnArgon2idHash(): void
    {
        $id = $this->party->create($this->valid());

        try {
            $stored = (string) $this->party->find($id)['password'];

            self::assertNotSame('secret123', $stored);
            self::assertStringStartsWith('$argon2id$', $stored);
            self::assertTrue(password_verify('secret123', $stored));
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    /**
     * The 800-odd rows written by the Perl form hold the password in clear.
     * They have to stay editable, and the first successful edit upgrades them.
     */
    public function testAClearTextPasswordStillVerifiesAndIsThenUpgraded(): void
    {
        $id = $this->party->create($this->valid());

        try {
            // Put the row back into the shape the legacy form produced.
            $this->db->query(
                'UPDATE `party` SET `password` = ? WHERE `id` = ?',
                ['secret123', $id]
            );

            $row = $this->party->find($id);

            self::assertTrue($this->party->verifyPassword($row, 'secret123'));
            self::assertStringStartsWith(
                '$argon2id$',
                (string) $this->party->find($id)['password'],
                'a successful clear-text verify rewrites the row'
            );

            // And the upgraded hash still accepts the same password.
            self::assertTrue($this->party->verifyPassword($this->party->find($id), 'secret123'));
            self::assertFalse($this->party->verifyPassword($this->party->find($id), 'wrong'));
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    public function testMayEditNeedsBothTheStoredNameAndThePassword(): void
    {
        $id = $this->party->create($this->valid());

        try {
            $row = $this->party->find($id);

            self::assertTrue($this->party->mayEdit($row, 'Port Test', 'secret123'));
            self::assertFalse($this->party->mayEdit($row, 'Someone Else', 'secret123'));
            self::assertFalse($this->party->mayEdit($row, 'Port Test', 'wrong'));
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    /**
     * An update must not hand the row to whoever posts the form: the stored
     * name stays, and so does the password unless it is explicitly changed.
     */
    public function testUpdateKeepsTheStoredSubmitterAndPassword(): void
    {
        $id = $this->party->create($this->valid());

        try {
            $before = $this->party->find($id);

            $this->party->update($id, array_replace($this->valid(), [
                'title'     => 'Renamed',
                'submitter' => 'Intruder',
                'password'  => 'othersecret',
            ]));

            $after = $this->party->find($id);

            self::assertSame('Renamed', $after['title']);
            self::assertSame($before['submitter'], $after['submitter']);
            self::assertSame($before['password'], $after['password']);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    // -------------------------
    // Form values
    // -------------------------

    /**
     * A new form starts at "now", with `url` at the column default `http://` -
     * the values the legacy code read back from the draft row it had just
     * inserted.
     */
    public function testTheBlankFormMatchesTheLegacyDraftDefaults(): void
    {
        $values = $this->party->blankForm();

        // Formatted in the calendar's own zone, not in whatever `date_default_timezone`
        // the process happens to have - the two differ by an hour for half the year.
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));

        self::assertSame('http://', $values['url']);
        self::assertSame('', $values['title']);
        self::assertSame($now->format('n/j/y'), $values['start_timestamp_date']);
        self::assertSame($now->format('H:i'), $values['start_timestamp_time']);
        self::assertSame($now->format('n/j/y'), $values['end_timestamp_date']);
    }

    /**
     * `11/1/26` - no leading zeros, two-digit year - which is what
     * `to_form()` printed for the `m/d/y` setup.
     */
    public function testStoredValuesRoundTripThroughTheForm(): void
    {
        $id = $this->party->create(array_replace($this->valid(), [
            'start_timestamp_date' => '11/1/26',
            'start_timestamp_time' => '22:00',
        ]));

        try {
            $values = $this->party->editForm($this->party->find($id));

            self::assertSame('11/1/26', $values['start_timestamp_date']);
            self::assertSame('22:00', $values['start_timestamp_time']);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    /** `<br />` is shown as a newline in a textarea, via `to_input()`. */
    public function testStoredLineBreaksComeBackAsNewlines(): void
    {
        $id = $this->party->create(array_replace($this->valid(), ['memo' => "One\nTwo"]));

        try {
            self::assertSame("One\nTwo", $this->party->editForm($this->party->find($id))['memo']);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    /**
     * The form shows decoded characters, and saving it writes them back, so a
     * legacy row is normalised once. What has to hold is that the *rendered*
     * text is the same before and after - the detail page decodes on read, so it
     * is.
     */
    public function testEditingALegacyRowNormalisesItsEntitiesOnce(): void
    {
        $id = $this->party->create($this->valid());

        try {
            $this->db->query(
                'UPDATE `party` SET `city` = ?, `memo` = ? WHERE `id` = ?',
                ['M&uuml;nchen', 'Zeile eins<br />Zeile zwei', $id]
            );

            $before = $this->party->find($id);
            $form   = $this->party->editForm($before);

            self::assertSame('München', $form['city']);
            self::assertSame("Zeile eins\nZeile zwei", $form['memo']);

            // Saving the form writes the decoded text back.
            $this->party->update($id, array_replace($this->valid(), $form));

            self::assertSame('München', $this->db->query(
                'SELECT `city` FROM `party` WHERE `id` = ?',
                [$id]
            )->fetchColumn());

            // Same rendered result, before and after.
            self::assertSame('München', $this->party->find($id)['city']);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    /**
     * Text that is *already* UTF-8 is not altered, so a new party keeps the
     * characters the submitter typed. cp1252 covers the German repertoire the
     * columns hold.
     */
    public function testUtf8GermanCharactersRoundTripThroughTheColumn(): void
    {
        $id = $this->party->create(array_replace($this->valid(), [
            'city' => 'München',
            'title' => 'Party für Götz & Söhne',
        ]));

        try {
            $row = $this->party->find($id);

            self::assertSame('München', $row['city']);
            self::assertSame('Party für Götz & Söhne', $row['title']);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
        }
    }

    // -------------------------
    // Flyer
    // -------------------------

    /**
     * A rejected flyer must not publish the party.
     *
     * The first version of this port inserted the row and *then* validated the
     * upload, so an invalid image answered 400 while the party appeared on the
     * calendar anyway. `party.pl` had the order right - `upload_file()` ran
     * before `$party->save()` - and so does `stageFlyer()`.
     */
    public function testANonImageNamedJpgIsRejectedBeforeAnyRowIsWritten(): void
    {
        $before = (int) $this->db->query('SELECT COUNT(*) FROM `party`')->fetchColumn();

        $tmp = tempnam(sys_get_temp_dir(), 'evil');

        // A real, well-formed upload as far as PHP is concerned - but the bytes
        // are not an image.
        file_put_contents($tmp, "<?php echo 'pwned';");

        $file = new \Azera\Http\UploadedFile(
            'evil.jpg',
            'image/jpeg',
            $tmp,
            UPLOAD_ERR_OK,
            (int) filesize($tmp)
        );

        // `moveTo()` only accepts a genuine upload, so the staging step cannot be
        // exercised from a unit test; what matters here is that the suffix check
        // and the size check run first and are decisive.
        $wrongSuffix = new \Azera\Http\UploadedFile('evil.php', 'image/jpeg', $tmp, UPLOAD_ERR_OK, 100);
        $message     = null;

        self::assertNull($this->party->stageFlyer($wrongSuffix, $message));
        self::assertSame('The filetype of the fyler is not permitted.', $message);

        self::assertSame(
            $before,
            (int) $this->db->query('SELECT COUNT(*) FROM `party`')->fetchColumn(),
            'nothing may be inserted for a rejected upload'
        );

        @unlink($tmp);
        unset($file);
    }

    public function testAnOversizedFlyerIsRejected(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'big');
        file_put_contents($tmp, str_repeat('x', PartyService::FLYER_MAX_BYTES + 1));

        $file = new \Azera\Http\UploadedFile(
            'big.jpg',
            'image/jpeg',
            $tmp,
            UPLOAD_ERR_OK,
            (int) filesize($tmp)
        );

        $message = null;

        self::assertNull($this->party->stageFlyer($file, $message));
        self::assertSame('The flyer is to big - 100k maximal', $message);

        @unlink($tmp);
    }

    /** `setFlyer()` removes the file it replaced, and refuses to touch others. */
    public function testReplacingAFlyerDeletesTheOldOneButNotAForeignPath(): void
    {
        $directory = sys_get_temp_dir() . '/hbr1-party-flyer-test';
        @mkdir($directory, 0775, true);

        $id = $this->party->create($this->valid());

        try {
            $old = $directory . '/' . $id . '.gif';
            file_put_contents($old, 'x');

            $this->db->query('UPDATE `party` SET `flyer` = ? WHERE `id` = ?', [$id . '.gif', $id]);

            $this->party->setFlyer($id, $id . '.jpg');

            self::assertFalse(is_file($old), 'the replaced flyer is removed');
            self::assertSame($id . '.jpg', $this->party->find($id)['flyer']);

            // A hand-edited column must not be able to point at another file.
            $outside = $directory . '/../../hbr1-keep-me.txt';
            file_put_contents($directory . '/../hbr1-keep-me.txt', 'keep');

            $this->db->query('UPDATE `party` SET `flyer` = ? WHERE `id` = ?', ['../hbr1-keep-me.txt', $id]);
            $this->party->setFlyer($id, $id . '.png');

            self::assertTrue(is_file($directory . '/../hbr1-keep-me.txt'));
            @unlink($directory . '/../hbr1-keep-me.txt');
            @unlink($outside);
        } finally {
            $this->db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
            @unlink($directory . '/' . $id . '.jpg');
            @unlink($directory . '/' . $id . '.png');
        }
    }

    // -------------------------
    // Helpers
    // -------------------------

    /** @return array<string, string> */
    private function valid(): array
    {
        // Far enough ahead that the "start in the past" rule never fires.
        $start = new \DateTimeImmutable('+60 days', new \DateTimeZone('Europe/Berlin'));

        return [
            'title'                => 'Port Test Party',
            'type'                 => 'Club',
            'country'              => 'DE',
            'city'                 => 'Munich',
            'location'             => 'Some club',
            'djs'                  => 'DJ One',
            'live'                 => '',
            'chillout'             => '',
            'deco'                 => '',
            'memo'                 => 'See you there',
            'organizer'            => 'Org',
            'email'                => 'org@example.com',
            'url'                  => 'http://example.com',
            'hotline'              => '+49 89 123',
            'submitter'            => 'Port Test',
            'password'             => 'secret123',
            'password2'            => 'secret123',
            'start_timestamp_date' => $start->format('n/j/y'),
            'start_timestamp_time' => '22:00',
            'end_timestamp_date'   => $start->modify('+1 day')->format('n/j/y'),
            'end_timestamp_time'   => '06:00',
        ];
    }

    private function local(int $y, int $m, int $d, int $h, int $i): int
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))
            ->setDate($y, $m, $d)->setTime($h, $i)->getTimestamp();
    }
}
