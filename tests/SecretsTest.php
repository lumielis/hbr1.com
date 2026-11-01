<?php

namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * No tracked file may contain a credential.
 *
 * The repository is public, and it already leaked once: `cgi-bin` carried a
 * MySQL password in two config files and an Icecast `user:password@` URL, and
 * `htdocs/admin/.htpasswd` carried four crypt-des hashes. Moving the values
 * out is not enough on its own - a later edit can put one back, and nothing
 * else in the suite would notice.
 *
 * These tests read the *tracked* files, so a secret in `env.local.php` (which
 * is git-ignored) is correctly not a failure, while one copied into a
 * committed file is.
 */
class SecretsTest extends TestCase
{
    /**
     * Paths that must never contain a password assignment.
     *
     * `env.php` is included: it holds the placeholder defaults and the lookup
     * logic, and a real value there would be committed. `config/config.php` is
     * the application's own config, which takes everything from constants.
     */
    public static function trackedConfigFiles(): array
    {
        return [
            'legacy php config'  => ['cgi-bin/include/config.php'],
            'legacy perl config' => ['cgi-bin/include/config.pab.pl'],
            'env defaults'       => ['env.php'],
            'app config'         => ['config/config.php'],
            'env example'        => ['deploy/env.example.php'],
        ];
    }

    #[DataProvider('trackedConfigFiles')]
    public function testNoTrackedConfigFileCarriesAPasswordLiteral(string $path): void
    {
        $source = $this->tracked($path);

        // An assignment whose right-hand side is a non-empty quoted literal.
        // Placeholders and constants are fine; 'hunter2' is not.
        preg_match_all(
            '/(?:DB_PASSWORD|DB_Auth|DB_Music_Auth|ICECAST_STATS_PASSWORD)\s*[\'"]?\s*=>?\s*\'([^\']*)\'/',
            $source,
            $matches
        );

        $allowed = ['', 'CHANGE-ME', 'PASSWORD'];

        $offending = array_values(array_diff($matches[1], $allowed));

        self::assertSame(
            [],
            $offending,
            "{$path} assigns a literal password. Read it from the environment instead."
        );
    }

    /**
     * The legacy files must actually consult the environment, not merely avoid
     * hardcoding. Without this, deleting the assignment would pass the test
     * above while leaving the code with no credentials at all.
     */
    public function testTheLegacyTreesReadTheEnvironment(): void
    {
        self::assertStringContainsString('DB_PASSWORD', $this->tracked('cgi-bin/include/config.php'));
        self::assertStringContainsString("__DIR__ . '/../../env.php'", $this->tracked('cgi-bin/include/config.php'));

        $perl = $this->tracked('cgi-bin/include/config.pab.pl');

        self::assertStringContainsString('HBR1_DB_PASSWORD', $perl);
        self::assertStringContainsString('HBR1_ICECAST_URL', $perl);
        self::assertStringContainsString("'/etc/hbr1/env.php'", $perl);
    }

    /**
     * A URL that carries `user:password@` is a credential wherever it appears.
     * The Icecast admin endpoint did exactly this, in a tracked file.
     */
    public static function trackedSources(): array
    {
        return [
            'perl config'   => ['cgi-bin/include/config.pab.pl'],
            'php config'    => ['cgi-bin/include/config.php'],
            'legacy main'   => ['cgi-bin/main.php'],
        ];
    }

    #[DataProvider('trackedSources')]
    public function testNoTrackedFileEmbedsCredentialsInAUrl(string $path): void
    {
        self::assertDoesNotMatchRegularExpression(
            '#[a-z][a-z0-9+.-]*://[^/\s\'"]+:[^/\s\'"@]+@#i',
            $this->tracked($path),
            "{$path} embeds a credential in a URL."
        );
    }

    /**
     * The admin gate must not be tracked. Crypt-des is reversible at the speeds
     * it was chosen for, so the hashes are credentials too. The file still
     * belongs on disk - the dev vhost and production both read it - it just
     * must not be published.
     */
    public function testTheAdminPasswordFileIsNotTracked(): void
    {
        $listed = $this->git(['ls-files', 'htdocs/admin/.htpasswd']);

        self::assertSame('', trim($listed), 'htdocs/admin/.htpasswd must not be tracked.');

        // And it must be ignored, so `git add -A` cannot pick it up again.
        self::assertSame(
            'htdocs/admin/.htpasswd',
            trim($this->git(['check-ignore', 'htdocs/admin/.htpasswd'])),
            'htdocs/admin/.htpasswd must be git-ignored.'
        );
    }

    /**
     * The lookup order env.php documents has to be the order it implements, or
     * an operator will install a file nobody reads.
     */
    public function testTheEnvironmentFileNameCollapses(): void
    {
        $template = $this->tracked('deploy/env.example.php');

        self::assertStringContainsString('/etc/hbr1/env.php', $template);
        self::assertStringContainsString('env.local.php', $template);
        self::assertStringContainsString('HBR1_ENV', $template);
    }

    /** Contents of a tracked file; untracked paths are refused. */
    private function tracked(string $path): string
    {
        $absolute = BASE_DIR . '/' . $path;

        self::assertFileExists($absolute, "{$path} is missing.");

        return (string) file_get_contents($absolute);
    }

    /** @param array<int, string> $arguments */
    private function git(array $arguments): string
    {
        $command = 'git -C ' . escapeshellarg(BASE_DIR) . ' ' . implode(' ', array_map('escapeshellarg', $arguments));

        exec($command . ' 2>&1', $output, $status);

        if ($status !== 0) {
            self::fail('git ' . implode(' ', $arguments) . ' failed: ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }
}
