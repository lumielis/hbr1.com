<?php

namespace App\Services;

/**
 * The guestbook.
 *
 * Replaces `include/client/gbms.pab.pl` (the live one, reached through
 * `/perl/apax.cgi?run=gbms`). The older `guestbook.pab.pl` is dead code: its
 * first statement after the setup is `die 'this guestbook has been closed'`.
 *
 * Behaviour preserved from `gbms`:
 *  - only `status = 1` entries are listed, newest first, 33 per page;
 *  - a submission is run through `guestbook_filter` and stored with
 *    `status = 2` when it matches (2 = "pending review", never listed);
 *  - an empty User-Agent is treated the same way, which is how the legacy
 *    code dropped bot posts;
 *  - `remote_addr` is recorded for the admin.
 *
 * Not preserved: the `sid` handshake. It was a reversible XOR of the timestamp
 * (`PAB::Crypt::Xor`), so it authenticated nothing - it only proved the client
 * had been here in the last hour. The replacement is CSRF plus the same
 * per-IP rate limiting the song requests use.
 */
class GuestbookService
{
    public const PAGE_SIZE = 33;

    /** Entry states, as used by the legacy schema. */
    public const STATUS_HIDDEN  = 0;
    public const STATUS_PUBLIC  = 1;
    public const STATUS_PENDING = 2;

    public function __construct(private \Azera\Db\Database $db)
    {
    }

    /**
     * One page of published entries.
     *
     * @return array{entries: array<int, array>, total: int, offset: int, limit: int}
     */
    public function page(int $offset = 0, int $limit = self::PAGE_SIZE): array
    {
        $limit  = max(1, min($limit, 200));
        $offset = max(0, $offset);

        $total = (int) $this->db
            ->query('SELECT COUNT(*) FROM `guestbook` WHERE `status` = ?', [self::STATUS_PUBLIC])
            ->fetchColumn();

        $rows = $this->db->query(
            'SELECT `id`, `timestamp`, `submitter`, `email`, `content`, `remote_addr`'
            . ' FROM `guestbook` WHERE `status` = ?'
            . ' ORDER BY `timestamp` DESC, `id` DESC'
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
            [self::STATUS_PUBLIC]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $entries = [];

        foreach ($rows as $row) {
            $entries[] = [
                'id'        => (int) $row['id'],
                'timestamp' => (int) $row['timestamp'],
                'date'      => $row['timestamp'] > 0
                    ? date('D, d M Y H:i', (int) $row['timestamp'])
                    : '',
                'submitter' => (string) $row['submitter'],
                'email'     => (string) $row['email'],
                'content'   => (string) $row['content'],
                'remoteAddr' => (string) $row['remote_addr'],
            ];
        }

        return ['entries' => $entries, 'total' => $total, 'offset' => $offset, 'limit' => $limit];
    }

    /**
     * Store a submission.
     *
     * Returns the new id and the status it was given. A `status` of
     * STATUS_PENDING means it will not be shown without an admin edit.
     *
     * @param array{submitter: string, email: string, content: string} $input
     */
    public function submit(array $input, string $remoteAddr, string $userAgent): array
    {
        $status = $this->matchesFilter($input) || trim($userAgent) === ''
            ? self::STATUS_PENDING
            : self::STATUS_PUBLIC;

        $this->db->query(
            'INSERT INTO `guestbook` (`timestamp`, `email`, `submitter`, `content`, `status`, `remote_addr`)'
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            [
                time(),
                $input['email'],
                $input['submitter'],
                $input['content'],
                $status,
                // varchar(15) in the legacy schema - IPv6 does not fit, so keep
                // it inside the column rather than letting MySQL truncate.
                substr($remoteAddr, 0, 15),
            ]
        );

        return ['id' => (int) $this->db->lastInsertId(), 'status' => $status];
    }

    /**
     * Validation, matching the legacy field rules.
     *
     * `submitter` was `^[0-9a-zA-ZöüäÖÜÄß \-_]+$`; the rest were plain
     * required/email checks.
     *
     * @return array<string, string> field => message
     */
    public function validate(array $input): array
    {
        $errors = [];
        $name   = trim((string) ($input['submitter'] ?? ''));
        $email  = trim((string) ($input['email'] ?? ''));
        $body   = trim((string) ($input['content'] ?? ''));

        if ($name === '') {
            $errors['submitter'] = 'Please enter your name.';
        } elseif (preg_match('/^[0-9a-zA-ZöüäÖÜÄß \-_]+$/u', $name) !== 1) {
            $errors['submitter'] = 'Your name may contain only letters, digits, spaces, - and _.';
        } elseif (mb_strlen($name) > 255) {
            $errors['submitter'] = 'Your name is too long.';
        }

        if ($email === '') {
            $errors['email'] = 'Please enter your e-mail address.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'This e-mail address does not look valid.';
        }

        if ($body === '') {
            $errors['content'] = 'Please enter a message.';
        } elseif (mb_strlen($body) > 10000) {
            $errors['content'] = 'Your message is too long.';
        }

        return $errors;
    }

    /**
     * The spam filter: `guestbook_filter` rows are regular expressions applied
     * to one column. `Case` is the legacy flag - 1 means case-sensitive.
     *
     * A row whose pattern does not compile is skipped rather than throwing;
     * these patterns are edited by hand in the admin and one bad row must not
     * block the guestbook.
     */
    public function matchesFilter(array $input): bool
    {
        $rows = $this->db->query('SELECT `Field`, `Match`, `Case` FROM `guestbook_filter`')
            ->fetchAll(\PDO::FETCH_ASSOC);

        // Only the columns a submission actually carries.
        $fields = [
            'submitter' => (string) ($input['submitter'] ?? ''),
            'email'     => (string) ($input['email'] ?? ''),
            'content'   => (string) ($input['content'] ?? ''),
        ];

        foreach ($rows as $row) {
            $field = strtolower((string) $row['Field']);
            $value = $fields[$field] ?? null;

            if ($value === null) {
                continue;
            }

            $pattern = (string) $row['Match'];
            $flags   = ((int) $row['Case']) === 1 ? '' : 'i';
            $regex   = '!' . str_replace('!', '\!', $pattern) . '!' . $flags . 'u';

            if (@preg_match($regex, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
