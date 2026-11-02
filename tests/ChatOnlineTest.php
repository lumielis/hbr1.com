<?php

namespace App\Tests;

use App\Services\ChatService;
use Azera\Db\Database;
use PHPUnit\Framework\TestCase;

/**
 * The user list (`/chat/users`).
 *
 * Legacy reference: `Action=listUsers` in cgi-bin/include/client/chat.pab.pl.
 * It selects every message newer than `$Setup{'ChatOnlineTime'}` (600 s,
 * cgi-bin/include/config.pab.pl) ordered by `timestamp ASC`, then keeps the
 * first row it sees for each nickname, so a nickname appears once and carries
 * its earliest in-window line.
 *
 * The service takes an injected Database, so this runs against an in-memory
 * SQLite table instead of the live `chat` table.
 */
class ChatOnlineTest extends TestCase
{
    private Database $db;
    private ChatService $service;

    protected function setUp(): void
    {
        $this->db = new Database('sqlite::memory:');
        $this->db->query(
            'CREATE TABLE `chat` ('
            . '`id` INTEGER PRIMARY KEY AUTOINCREMENT,'
            . '`timestamp` INTEGER NOT NULL DEFAULT 0,'
            . '`remote_addr` TEXT NOT NULL DEFAULT "",'
            . '`name` TEXT NOT NULL DEFAULT "",'
            . '`content` TEXT NOT NULL DEFAULT "",'
            . '`color` TEXT NOT NULL DEFAULT "CCCCCC")'
        );

        $this->service = new ChatService($this->db);
    }

    public function testOnlyNicksInsideTheOnlineWindowAreListed(): void
    {
        $now = time();

        $this->post($now - 60, 'Present', '96B8FF');
        $this->post($now - ChatService::ONLINE_WINDOW - 60, 'Gone', 'AAAAAA');

        $users = $this->service->online();

        self::assertSame(['Present'], array_column($users, 'name'));
    }

    public function testEachNickIsListedOnceInArrivalOrder(): void
    {
        $now = time();

        $this->post($now - 300, 'Second', 'B9BCDC');
        $this->post($now - 120, 'Third', 'C0BFD5');
        $this->post($now - 400, 'First', 'D5BDC0');
        // A repeat of an already-listed nick must not add a second row.
        $this->post($now - 30, 'First', 'D5BDC0');

        self::assertSame(
            ['First', 'Second', 'Third'],
            array_column($this->service->online(), 'name')
        );
    }

    /**
     * The listed row is the *oldest* in-window message, which is what the
     * legacy `if (!$Users{$row->[0]})` loop produced.
     */
    public function testTheRowCarriesTheEarliestInWindowMessage(): void
    {
        $now = time();

        $this->post($now - 500, 'Alice', 'D5BDC0');
        $this->post($now - 20, 'Alice', 'D5BDC0');

        $users = $this->service->online();

        self::assertCount(1, $users);
        self::assertSame(
            (new \DateTimeImmutable('@' . $now - 500))
                ->setTimezone(new \DateTimeZone('Europe/Berlin'))
                ->format('h:i:s A'),
            $users[0]['time']
        );
    }

    public function testTheStoredColourIsReproducedAndValidated(): void
    {
        $now = time();

        $this->post($now - 60, 'Painter', 'd5bdc0');
        $this->post($now - 50, 'Bad', 'red;background:url(x)');

        $users = $this->service->online();

        self::assertSame('D5BDC0', $users[0]['color'], 'a stored colour is normalised to upper case');
        self::assertSame('CCCCCC', $users[1]['color'], 'a colour that is not six hex digits falls back');
    }

    public function testAnEmptyNicknameIsNotListed(): void
    {
        $this->post(time() - 60, '', '96B8FF');

        self::assertSame([], $this->service->online());
    }

    public function testAnEmptyRoomRendersAnEmptyList(): void
    {
        self::assertSame([], $this->service->online());
    }

    private function post(int $timestamp, string $name, string $color): void
    {
        $this->db->query(
            'INSERT INTO `chat` (`timestamp`, `remote_addr`, `name`, `content`, `color`)'
            . ' VALUES (?, ?, ?, ?, ?)',
            [$timestamp, '127.0.0.1', $name, 'hello', $color]
        );
    }
}
