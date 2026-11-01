<?php

namespace App\Services;

/**
 * The chat room.
 *
 * Replaces `cgi-bin/chat.cgi`. The state is the `chat` table (25,029 rows,
 * last message 2026-10-29) - *not* a file. The `jsm*` scripts that used
 * `data/chat/` were a different, file-based transport whose state directory
 * was never configured; see deploy/CHAT-STATE.md.
 *
 * `color_by_name()` is reproduced exactly. It is a deterministic hash of the
 * nickname into an RGB triple, and regulars recognise each other by their
 * colour, so changing the arithmetic would be a visible regression.
 */
class ChatService
{
    public const MAX_MESSAGES = 100;
    public const MAX_NAME = 50;
    public const MAX_CONTENT = 255;

    public function __construct(
        private \Azera\Db\Database $db,
        private string $timezone = 'Europe/Berlin'
    ) {
    }

    /**
     * The frame's timestamp: `formatTime('%m/%d %H:%M')` in the server's
     * timezone, which is what production printed.
     */
    public function formatClock(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone($this->timezone))
            ->format('m/d H:i');
    }

    /**
     * The most recent messages, oldest first (the legacy page printed the
     * newest 50 and reversed nothing - it ordered `id DESC` and rendered the
     * list, which put the newest at the top).
     *
     * @return array<int, array>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min($limit, self::MAX_MESSAGES));

        // id DESC is the legacy order and it is also a stable tie-breaker for
        // messages posted inside the same second.
        $rows = $this->db->query(
            'SELECT `id`, `timestamp`, `name`, `content`, `color`'
            . ' FROM `chat` ORDER BY `id` DESC LIMIT ' . $limit
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            fn(array $row): array => [
                'id'        => (int) $row['id'],
                'timestamp' => (int) $row['timestamp'],
                'time'      => $row['timestamp'] > 0 ? date('H:i', (int) $row['timestamp']) : '',
                'clock'     => $this->formatClock((int) $row['timestamp']),
                'name'      => (string) $row['name'],
                // The legacy form stored its input HTML-encoded (`&#63;`,
                // `&#58;`), and the template engine escapes on output, so the
                // raw value renders as visible entity text. Decode once here
                // and let the engine escape once on the way out: correct text,
                // and still no HTML injection from an unmoderated field.
                'content'   => html_entity_decode((string) $row['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'color'     => self::safeColor((string) $row['color']),
            ],
            $rows
        );
    }

    /** @return array<string, string> field => message */
    public function validate(array $input): array
    {
        $errors  = [];
        $name    = trim((string) ($input['name'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Please enter a nickname.';
        } elseif (mb_strlen($name) > self::MAX_NAME) {
            $errors['name'] = 'Your nickname is too long.';
        }

        if ($content === '') {
            $errors['content'] = 'Please enter a message.';
        } elseif (mb_strlen($content) > self::MAX_CONTENT) {
            $errors['content'] = 'Your message is too long.';
        }

        return $errors;
    }

    public function post(array $input, string $remoteAddr): int
    {
        $name = trim((string) $input['name']);

        // Store the same way the legacy form did: encoded once. `recent()`
        // decodes it again, so the read path is symmetric with the old data.
        $content = htmlspecialchars(
            mb_substr(trim((string) $input['content']), 0, self::MAX_CONTENT),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $this->db->query(
            'INSERT INTO `chat` (`timestamp`, `remote_addr`, `name`, `content`, `color`)'
            . ' VALUES (?, ?, ?, ?, ?)',
            [
                time(),
                substr($remoteAddr, 0, 15),
                mb_substr($name, 0, self::MAX_NAME),
                $content,
                self::colorByName($name),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Deterministic nickname colour - a direct port of `color_by_name`.     *
     * Vowels drive the red channel, consonants the blue channel, and every
     * code point feeds green. The `150 +` floor keeps text readable on the
     * black background.
     */
    public static function colorByName(string $name): string
    {
        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $length = count($chars);

        if ($length === 0) {
            return 'CCCCCC';
        }

        $vowels = ['A' => 1, 'E' => 1, 'I' => 1, 'O' => 1, 'a' => 1, 'e' => 1, 'i' => 1, 'o' => 1];

        $red   = 0;
        $green = 0;
        $blue  = 0;

        foreach ($chars as $char) {
            if (isset($vowels[$char])) {
                $red++;
            } else {
                $blue++;
            }

            $green += mb_ord($char);
        }

        $red   = 150 + (int) (105 / $length * $red);
        $green = 150 + (int) (105 / ($length * 255) * $green);
        $blue  = 150 + (int) (105 / $length * $blue);

        return sprintf('%02X%02X%02X', min(255, $red), min(255, $green), min(255, $blue));
    }

    /** A stored colour is rendered into a style attribute, so validate it. */
    private static function safeColor(string $color): string
    {
        return preg_match('/^[0-9A-Fa-f]{6}$/', $color) === 1 ? strtoupper($color) : 'CCCCCC';
    }
}
