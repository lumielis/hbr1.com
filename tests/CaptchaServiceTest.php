<?php

namespace App\Tests;

use App\Services\CaptchaService;
use Azera\Http\Session;
use PHPUnit\Framework\TestCase;

/**
 * The request form's security image.
 *
 * The legacy securimage library cannot run on PHP 8.3 (it uses curly-brace
 * string offsets), so this is a replacement and needs its own pinning: the
 * code must be digits only at the legacy length, single use, expire, and the
 * rendered image must be a real 100x30 PNG rather than a broken-image icon.
 *
 * The image itself is random by design; these tests assert its shape and that
 * it is not blank, not what it looks like.
 */
class CaptchaServiceTest extends TestCase
{
    private CaptchaService $captcha;
    private Session $session;

    protected function setUp(): void
    {
        $this->captcha = new CaptchaService(self::fontPath());

        $store = [];
        $this->session = new Session($store);
    }

    public function testIssuedCodeIsFiveDigits(): void
    {
        $code = $this->captcha->issue($this->session);

        self::assertSame(5, strlen($code), 'the legacy image showed five digits');
        self::assertMatchesRegularExpression('/^[0-9]{5}$/', $code, 'the legacy charset was 1234567890');
        self::assertSame($code, $this->captcha->peek($this->session));
    }

    public function testIssuingAgainReplacesTheStoredCode(): void
    {
        $this->captcha->issue($this->session);
        $second = $this->captcha->issue($this->session);

        self::assertSame($second, $this->captcha->peek($this->session));
    }

    public function testCorrectCodeVerifies(): void
    {
        $code = $this->captcha->issue($this->session);

        self::assertTrue($this->captcha->verify($this->session, $code));
    }

    public function testVerificationTrimsSurroundingWhitespace(): void
    {
        $code = $this->captcha->issue($this->session);

        self::assertTrue($this->captcha->verify($this->session, '  ' . $code . ' '));
    }

    public function testCodeIsSingleUse(): void
    {
        $code = $this->captcha->issue($this->session);

        self::assertTrue($this->captcha->verify($this->session, $code));
        self::assertFalse($this->captcha->verify($this->session, $code), 'a consumed code must not verify twice');
        self::assertNull($this->captcha->peek($this->session));
    }

    public function testWrongCodeFailsAndKeepsTheStoredValue(): void
    {
        $code = $this->captcha->issue($this->session);
        $wrong = $code === '11111' ? '22222' : '11111';

        self::assertFalse($this->captcha->verify($this->session, $wrong));
        self::assertSame($code, $this->captcha->peek($this->session), 'a typo must not invalidate the image');
    }

    public function testWrongLengthCodeFails(): void
    {
        $this->captcha->issue($this->session);

        self::assertFalse($this->captcha->verify($this->session, '1234'));
        self::assertFalse($this->captcha->verify($this->session, '123456'));
    }

    public function testEmptyCodeFails(): void
    {
        $this->captcha->issue($this->session);

        self::assertFalse($this->captcha->verify($this->session, ''));
        self::assertFalse($this->captcha->verify($this->session, '   '));
    }

    public function testVerificationWithoutAnIssuedCodeFails(): void
    {
        self::assertFalse($this->captcha->verify($this->session, '12345'));
        self::assertFalse($this->captcha->verify(null, '12345'));
    }

    /**
     * securimage's `expiry_time` default was 900 seconds; the port has to keep
     * that, or a form left open over lunch stops submitting. The party form asks
     * for its own, longer lifetime - see {@see testThePartyKeyHasItsOwnLifetime()}.
     */
    public function testExpiredCodeFails(): void
    {
        $this->captcha->issue($this->session);

        $this->session->set(CaptchaService::SESSION_KEY, [
            'code'   => $this->captcha->peek($this->session),
            'issued' => time() - CaptchaService::EXPIRY_SECONDS - 1,
        ]);

        $code = (string) $this->captcha->peek($this->session);

        self::assertFalse($this->captcha->verify($this->session, $code));
        self::assertNull($this->captcha->peek($this->session), 'an expired code is discarded');
    }

    /**
     * Two forms draw the same picture, and each writes its code when its
     * `<img>` is fetched. One shared key would let the party form silently
     * replace the code the song request form is showing.
     */
    public function testTheTwoFormsKeepSeparateCodes(): void
    {
        $request = $this->captcha->issue($this->session);
        $party   = $this->captcha->issue($this->session, CaptchaService::PARTY_SESSION_KEY);

        self::assertSame($request, $this->captcha->peek($this->session));
        self::assertSame($party, $this->captcha->peek($this->session, CaptchaService::PARTY_SESSION_KEY));

        self::assertTrue($this->captcha->verify($this->session, $party, CaptchaService::PARTY_SESSION_KEY));
        self::assertSame(
            $request,
            $this->captcha->peek($this->session),
            'consuming the party code must leave the request code in place'
        );
    }

    /**
     * The party form is a dozen fields plus a flyer upload; fifteen minutes is
     * not enough to fill it in, so the party key gets an hour.
     */
    public function testThePartyKeyHasItsOwnLifetime(): void
    {
        $this->captcha->issue(
            $this->session,
            CaptchaService::PARTY_SESSION_KEY,
            CaptchaService::PARTY_EXPIRY_SECONDS
        );

        $stored = $this->session->get(CaptchaService::PARTY_SESSION_KEY);

        self::assertIsArray($stored);
        self::assertSame(
            CaptchaService::PARTY_EXPIRY_SECONDS,
            (int) $stored['expires'] - (int) $stored['issued']
        );

        // Still valid where the request form's code would already have expired.
        $this->session->set(CaptchaService::PARTY_SESSION_KEY, [
            'code'    => $stored['code'],
            'issued'  => time() - CaptchaService::EXPIRY_SECONDS - 60,
            'expires' => time() + 60,
        ]);

        self::assertTrue($this->captcha->verify(
            $this->session,
            (string) $stored['code'],
            CaptchaService::PARTY_SESSION_KEY
        ));
    }

    public function testUnreadableFontDoesNotThrow(): void
    {
        $captcha = new CaptchaService(__DIR__ . '/no-such-font.ttf');

        $png = @$captcha->render('12345');

        self::assertNotSame('', $png, 'a missing font must still produce an image');
        self::assertSame("\x89PNG", substr($png, 0, 4));
    }

    public function testRendersA100x30Png(): void
    {
        $png = $this->captcha->render('12345');
        $info = getimagesizefromstring($png);

        self::assertIsArray($info);
        self::assertSame(100, $info[0], 'the legacy img tag asked for width=100');
        self::assertSame(30, $info[1], 'the legacy img tag asked for height=30');
        self::assertSame(IMAGETYPE_PNG, $info[2]);
    }

    /**
     * A blank image would still be a valid 100x30 PNG, and a captcha nobody can
     * read is worse than no captcha: it blocks the listener and nobody else.
     * The background is a single colour, so any real render has many distinct
     * colours from the digits, lines and noise.
     */
    public function testRenderedImageIsNotBlank(): void
    {
        $image = imagecreatefromstring($this->captcha->render('12345'));

        self::assertNotFalse($image);

        $colours = [];

        for ($x = 0; $x < 100; $x++) {
            for ($y = 0; $y < 30; $y++) {
                $colours[imagecolorat($image, $x, $y)] = true;
            }
        }

        imagedestroy($image);

        self::assertGreaterThan(2, count($colours), 'background plus at least two more colours');
    }

    private static function fontPath(): string
    {
        $path = dirname(__DIR__) . '/data/security.ttf';

        self::assertFileExists($path, 'the legacy security.ttf must stay in the tree');

        return $path;
    }
}
