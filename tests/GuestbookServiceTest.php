<?php

namespace App\Tests;

use App\AppContext;
use App\Services\GuestbookService;
use PHPUnit\Framework\TestCase;

/**
 * The guestbook's pure decisions: field validation and the date format.
 *
 * The write path (`submit()`) and the filter table (`matchesFilter()`) are not
 * exercised here - they touch the live database. The listing and form
 * rendering are covered by GuestbookPageTest.
 */
class GuestbookServiceTest extends TestCase
{
    private GuestbookService $service;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        try {
            $this->service = AppContext::instance()->get(GuestbookService::class);
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    public function testValidInputHasNoErrors(): void
    {
        self::assertSame([], $this->service->validate([
            'submitter' => 'A Visitor',
            'email'     => 'visitor@example.com',
            'content'   => 'Hello from the outside.',
        ]));
    }

    public function testNameRejectsSymbols(): void
    {
        $errors = $this->service->validate([
            'submitter' => 'a<b>',
            'email'     => 'visitor@example.com',
            'content'   => 'x',
        ]);

        self::assertArrayHasKey('submitter', $errors);
    }

    public function testNameLengthIsBounded(): void
    {
        $errors = $this->service->validate([
            'submitter' => str_repeat('a', 256),
            'email'     => 'visitor@example.com',
            'content'   => 'x',
        ]);

        self::assertArrayHasKey('submitter', $errors);
    }

    public function testEmailMustLookLikeAnAddress(): void
    {
        $errors = $this->service->validate([
            'submitter' => 'A Visitor',
            'email'     => 'not-an-email',
            'content'   => 'x',
        ]);

        self::assertArrayHasKey('email', $errors);
    }

    public function testContentLengthIsBounded(): void
    {
        $errors = $this->service->validate([
            'submitter' => 'A Visitor',
            'email'     => 'visitor@example.com',
            'content'   => str_repeat('x', 10001),
        ]);

        self::assertArrayHasKey('content', $errors);
    }

    /** Production printed localtime (Europe/Berlin) under a "(utc)" label. */
    public function testFormatDateUsesTheSiteTimezone(): void
    {
        self::assertSame('Sat, 03 Jan 2026 05:40', $this->service->formatDate(1767415256));
    }

    public function testZeroTimestampHasNoDate(): void
    {
        self::assertSame('', $this->service->formatDate(0));
    }
}
