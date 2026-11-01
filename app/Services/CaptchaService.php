<?php

namespace App\Services;

use Azera\Http\Session;

/**
 * The security image on the song request form.
 *
 * The legacy form used `cgi-bin/lib/securimage.php` 3.2RC2 (2012), which does
 * not parse on PHP 8.3 at all - it uses curly-brace string offsets, removed in
 * PHP 8.0. So the library cannot simply be kept; this is a purpose-built
 * replacement for the one thing the site actually used it for.
 *
 * The drawing pipeline is deliberately the same as securimage's, because the
 * point is the *picture the listener sees*, not a new design:
 *
 *   1. a palette image at `SCALE` times the final size, plus a final image,
 *   2. random arcs on the oversized one (the noise),
 *   3. two wandering lines drawn on the final image,
 *   4. the digits rendered into the oversized image with the legacy TTF,
 *   5. a lens distortion sampling the oversized image back into the final one.
 *
 * Colours are allocated on the final image and copied into the working image
 * with `imagepalettecopy()`, because step 5 compares palette *indices*: the
 * background is recognised by its index, and only letter pixels are copied, so
 * the noise and lines survive. Allocating the same colour separately in each
 * image would give two different indices and the whole letter would vanish.
 *
 * The settings reproduce `include/tracklist.php`'s `show=secimg` branch:
 * 100x30, five digits from `1234567890`, #100060 background, #33ccff text,
 * #0077ff lines, two lines, noise level 1, perturbation 0.15.
 */
class CaptchaService
{
    /** Session key holding `['code' => string, 'issued' => int]`. */
    public const SESSION_KEY = 'captcha_code';

    public const CODE_LENGTH = 5;

    /** The legacy charset: digits only, which is why the image is readable. */
    private const CHARSET = '1234567890';

    public const WIDTH = 100;
    public const HEIGHT = 30;

    /** securimage's `$iscale`: the supersampling factor. */
    private const SCALE = 5;

    /** Digits are drawn at `height * SCALE * 0.4` points, as securimage did. */
    private const FONT_RATIO = 0.4;

    private const NUM_LINES = 2;

    private const NOISE_LEVEL = 1;

    private const PERTURBATION = 0.15;

    /** securimage's `expiry_time` default. */
    public const EXPIRY_SECONDS = 900;

    public function __construct(
        private readonly string $fontPath,
    ) {
    }

    /**
     * Generate a code and bind it to the session.
     *
     * Returns the plain code so the caller can draw it. Every call replaces
     * the previous one, so reloading the form invalidates the old image.
     */
    public function issue(?Session $session): string
    {
        $code = $this->generateCode();

        $session?->set(self::SESSION_KEY, [
            'code'   => $code,
            'issued' => time(),
        ]);

        return $code;
    }

    /**
     * Check a submitted code against the session.
     *
     * A correct code is removed, so a captcha cannot be replayed. A wrong one
     * is left in place: the legacy page redirected back to a freshly drawn
     * image on failure, so the retry used a new code anyway, but keeping the
     * stored value means a double submit cannot fail for the wrong reason.
     */
    public function verify(?Session $session, string $submitted): bool
    {
        if ($session === null) {
            return false;
        }

        $stored = $session->get(self::SESSION_KEY);

        if (!is_array($stored) || !isset($stored['code'], $stored['issued'])) {
            return false;
        }

        if (time() - (int) $stored['issued'] > self::EXPIRY_SECONDS) {
            $session->remove(self::SESSION_KEY);

            return false;
        }

        $submitted = trim($submitted);

        if ($submitted === '' || !hash_equals((string) $stored['code'], $submitted)) {
            return false;
        }

        $session->remove(self::SESSION_KEY);

        return true;
    }

    /** The code currently bound to the session, for tests and diagnostics. */
    public function peek(?Session $session): ?string
    {
        $stored = $session?->get(self::SESSION_KEY);

        return is_array($stored) && isset($stored['code']) ? (string) $stored['code'] : null;
    }

    private function generateCode(): string
    {
        $max    = strlen(self::CHARSET) - 1;
        $length = self::CODE_LENGTH;
        $code   = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::CHARSET[random_int(0, $max)];
        }

        return $code;
    }

    /** Render the PNG bytes for one code. */
    public function render(string $code): string
    {
        $width  = self::WIDTH;
        $height = self::HEIGHT;
        $scale  = self::SCALE;

        $image  = imagecreate($width, $height);
        $source = imagecreate($width * $scale, $height * $scale);

        $background = imagecolorallocate($image, 0x10, 0x00, 0x60);
        $textColour = imagecolorallocate($image, 0x33, 0xcc, 0xff);
        $lineColour = imagecolorallocate($image, 0x00, 0x77, 0xff);

        imagepalettecopy($source, $image);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledrectangle($source, 0, 0, $width * $scale, $height * $scale, $background);

        $this->drawNoise($source, $lineColour);
        $this->drawLines($image, $lineColour);
        $this->drawCode($source, $code, $textColour);
        $this->distort($image, $source);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        imagedestroy($image);
        imagedestroy($source);

        return $png;
    }

    private function drawNoise(\GdImage $image, int $colour): void
    {
        // securimage multiplied its 1-10 "level" by 125; at level 1 that is
        // 125 arcs scattered over the supersampled image.
        $points = self::NOISE_LEVEL * 125;
        $width  = self::WIDTH * self::SCALE;
        $height = self::HEIGHT * self::SCALE;

        for ($i = 0; $i < $points; $i++) {
            $x    = random_int(10, $width);
            $y    = random_int(10, $height);
            $size = random_int(7, 10);

            imagefilledarc($image, $x, $y, $size, $size, 0, 360, $colour, IMG_ARC_PIE);
        }
    }

    private function drawLines(\GdImage $image, int $colour): void
    {
        $width  = self::WIDTH;
        $height = self::HEIGHT;

        for ($line = 0; $line < self::NUM_LINES; $line++) {
            $x = $width * (1 + $line) / (self::NUM_LINES + 1);
            $x += (0.5 - $this->frand()) * $width / self::NUM_LINES;
            $y  = random_int((int) ($height * 0.1), (int) ($height * 0.9));

            $theta = ($this->frand() - 0.5) * M_PI * 0.7;
            $length = random_int((int) ($width * 0.4), (int) ($width * 0.7));
            $thickness = random_int(0, 2);

            $k    = $this->frand() * 0.6 + 0.2;
            $k    = $k * $k * 0.5;
            $phi  = $this->frand() * 6.28;
            $step = 0.5;
            $dx   = $step * cos($theta);
            $dy   = $step * sin($theta);
            $n    = $length / $step;
            $amp  = 1.5 * $this->frand() / ($k + 5.0 / $length);
            $x0   = $x - 0.5 * $length * cos($theta);
            $y0   = $y - 0.5 * $length * sin($theta);

            for ($i = 0; $i < $n; $i++) {
                $px = $x0 + $i * $dx + $amp * $dy * sin($k * $i * $step + $phi);
                $py = $y0 + $i * $dy - $amp * $dx * sin($k * $i * $step + $phi);

                imagefilledrectangle(
                    $image,
                    (int) $px,
                    (int) $py,
                    (int) $px + $thickness,
                    (int) $py + $thickness,
                    $colour
                );
            }
        }
    }

    private function drawCode(\GdImage $image, string $code, int $colour): void
    {
        $width     = self::WIDTH * self::SCALE;
        $height    = self::HEIGHT * self::SCALE;
        $fontSize  = $height * self::FONT_RATIO;

        if (!is_file($this->fontPath) || !is_readable($this->fontPath)) {
            // securimage drew "Failed to load TTF font file!" in this case.
            // A missing font must not take the request form down, so fall
            // back to the built-in bitmap font and say so in the log.
            error_log('captcha: font not readable: ' . $this->fontPath);

            $textWidth = imagefontwidth(5) * strlen($code);
            $x = (int) ($width / 2 - $textWidth / 2);
            $y = (int) ($height / 2 - imagefontheight(5) / 2);

            imagestring($image, 5, $x, $y, $code, $colour);

            return;
        }

        $box = imagettfbbox($fontSize, 0, $this->fontPath, $code);

        if ($box === false) {
            error_log('captcha: imagettfbbox failed for ' . $this->fontPath);

            return;
        }

        $textWidth  = $box[4] - $box[0];
        $textHeight = $box[5] - $box[1];
        $x = (int) floor($width / 2 - $textWidth / 2 - $box[0]);
        $y = (int) round($height / 2 - $textHeight / 2 - $box[1]);

        imagettftext($image, $fontSize, 0, $x, $y, $colour, $this->fontPath, $code);
    }

    /**
     * Lens distortion: sample the supersampled image back into the final one
     * through three attractor points, copying only pixels that are not the
     * background so the noise and lines underneath stay visible.
     */
    private function distort(\GdImage $image, \GdImage $source): void
    {
        $poles = 3;
        $width  = self::WIDTH;
        $height = self::HEIGHT;
        $scale  = self::SCALE;

        $px  = [];
        $py  = [];
        $rad = [];
        $amp = [];

        for ($i = 0; $i < $poles; $i++) {
            $px[$i]  = random_int((int) ($width * 0.2), (int) ($width * 0.8));
            $py[$i]  = random_int((int) ($height * 0.2), (int) ($height * 0.8));
            $rad[$i] = random_int((int) ($height * 0.2), (int) ($height * 0.8));
            $amp[$i] = self::PERTURBATION * ((-$this->frand()) * 0.15 - 0.15);
        }

        $background = imagecolorat($source, 0, 0);
        $scaledWidth  = $width * $scale;
        $scaledHeight = $height * $scale;

        imagepalettecopy($image, $source);

        for ($ix = 0; $ix < $width; $ix++) {
            for ($iy = 0; $iy < $height; $iy++) {
                $x = (float) $ix;
                $y = (float) $iy;

                for ($i = 0; $i < $poles; $i++) {
                    $dx = $ix - $px[$i];
                    $dy = $iy - $py[$i];

                    if ($dx === 0 && $dy === 0) {
                        continue;
                    }

                    $r = sqrt($dx * $dx + $dy * $dy);

                    if ($r > $rad[$i]) {
                        continue;
                    }

                    $rscale = $amp[$i] * sin(3.14 * $r / $rad[$i]);
                    $x += $dx * $rscale;
                    $y += $dy * $rscale;
                }

                $colour = $background;
                $sx = (int) ($x * $scale);
                $sy = (int) ($y * $scale);

                if ($sx >= 0 && $sx < $scaledWidth && $sy >= 0 && $sy < $scaledHeight) {
                    $colour = imagecolorat($source, $sx, $sy);
                }

                if ($colour !== $background) {
                    imagesetpixel($image, $ix, $iy, $colour);
                }
            }
        }
    }

    /** securimage's `frand()`: a float in [0, 1) from the global RNG. */
    private function frand(): float
    {
        return 0.0001 * random_int(0, 9999);
    }
}
