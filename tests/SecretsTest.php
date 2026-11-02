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
            'legacy php config'   => ['legacy/archive/cgi-bin/include/config.php'],
            'legacy perl config'  => ['legacy/archive/cgi-bin/include/config.pab.pl'],
            'legacy perl settings' => ['legacy/archive/cgi-bin/include/settings.inc'],
            'env defaults'        => ['env.php'],
            'app config'          => ['config/config.php'],
            'env example'         => ['deploy/env.example.php'],
            'phase 0 runbook'     => ['deploy/PHASE-0-RUNBOOK.md'],
        ];
    }

    #[DataProvider('trackedConfigFiles')]
    public function testNoTrackedConfigFileCarriesAPasswordLiteral(string $path): void
    {
        $source = $this->tracked($path);

        // An assignment whose right-hand side is a non-empty quoted literal.
        // Placeholders and constants are fine; 'hunter2' is not. `qq(...)` is
        // included because the Perl tree writes its values that way.
        preg_match_all(
            '/(?:DB_PASSWORD|DB_Auth|DB_Music_Auth|ICECAST_STATS_PASSWORD)\s*[\'"]?\s*=>?\s*(?:qq\()?\'([^\']*)\'/',
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
        self::assertStringContainsString('DB_PASSWORD', $this->tracked('legacy/archive/cgi-bin/include/config.php'));
        self::assertStringContainsString("__DIR__ . '/../../env.php'", $this->tracked('legacy/archive/cgi-bin/include/config.php'));

        $perl = $this->tracked('legacy/archive/cgi-bin/include/config.pab.pl');

        self::assertStringContainsString('HBR1_DB_PASSWORD', $perl);
        self::assertStringContainsString('HBR1_ICECAST_URL', $perl);
        self::assertStringContainsString("'/etc/hbr1/env.php'", $perl);    }

    /**
     * A URL that carries `user:password@` is a credential wherever it appears.
     * The Icecast admin endpoint did exactly this, in a tracked file.
     */
    public static function trackedSources(): array
    {
        return [
            'perl config'    => ['legacy/archive/cgi-bin/include/config.pab.pl'],
            'perl settings'  => ['legacy/archive/cgi-bin/include/settings.inc'],
            'php config'     => ['legacy/archive/cgi-bin/include/config.php'],
            'legacy main'    => ['legacy/archive/cgi-bin/main.php'],
            'phase 0 runbook' => ['deploy/PHASE-0-RUNBOOK.md'],
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
     * The Shoutcast/Icecast admin password reached the repository three ways:
     * as a `pass=` query parameter on five urls in `settings.inc`, inside the
     * Icecast userinfo in `config.pab.pl`, and as a literal in the Phase 0
     * runbook, which is prose and so escaped the config-file checks above.
     *
     * A `?pass=` parameter is plaintext by definition, so any value that is not
     * the environment lookup is a leak.
     */
    public static function trackedPasswordParams(): array
    {
        return [
            'perl settings'   => ['legacy/archive/cgi-bin/include/settings.inc'],
            'perl config'     => ['legacy/archive/cgi-bin/include/config.pab.pl'],
            'phase 0 runbook' => ['deploy/PHASE-0-RUNBOOK.md'],
            'db connections'  => ['deploy/DB-CONNECTIONS.md'],
        ];
    }

    #[DataProvider('trackedPasswordParams')]
    public function testNoTrackedFileHardcodesAPasswordParameter(string $path): void
    {
        preg_match_all(
            '/(?:pass|passwd|password|auth|pwd)=([A-Za-z0-9._-]{4,})/i',
            $this->tracked($path),
            $matches
        );

        // `auth=plain` is a RadioDJ command parameter, not a secret.
        $offending = array_values(array_diff($matches[1], ['plain']));

        self::assertSame(
            [],
            $offending,
            "{$path} hardcodes a password in a query parameter."
        );
    }

    /**
     * The admin gate must not be tracked. Crypt-des is reversible at the speeds
     * it was chosen for, so the hashes are credentials too.
     *
     * The file belongs on disk - the old vhost read it - and it moved with the
     * rest of the legacy admin into `legacy/archive/`. That made it *more*
     * important that git ignores it: an archived path is exactly what a
     * `git add -A` after a cleanup would sweep back into the repository.
     */
    public function testTheAdminPasswordFileIsNotTracked(): void
    {
        $path = 'legacy/archive/htmldocs/admin/.htpasswd';

        self::assertSame(
            '',
            trim($this->git(['ls-files', $path])),
            "{$path} must not be tracked."
        );

        // And it must be ignored, so `git add -A` cannot pick it up again.
        self::assertSame(
            $path,
            trim($this->git(['check-ignore', $path])),
            "{$path} must be git-ignored."
        );
    }

    /**
     * `public/` is the document root, so anything tracked there is published.
     *
     * The cleanup moved the live static tree into `public/`. That makes every
     * file under it web-reachable by definition - there is no alias list left
     * to keep a stray `.inc`, `.pl` or `.htpasswd` out of reach. The extensions
     * below were all reachable as plain text in the old tree and are exactly
     * what Phase 0 closed.
     */
    public function testNoExecutableOrSecretExtensionIsTrackedUnderPublic(): void
    {
        $listed = $this->git(['ls-files', 'public']);

        $offending = [];

        foreach (preg_split('/\r?\n/', trim($listed)) ?: [] as $path) {
            if ($path === '') {
                continue;
            }

            if (preg_match('/\.(htpasswd|htaccess|inc|pl|pm|pab|cgi|xp|phtml|sh|bak|old|orig|swp)$/i', $path) === 1) {
                $offending[] = $path;
            }

            // A dotfile anywhere under the document root is either a
            // credential (.htpasswd) or editor/VCS droppings.
            if (preg_match('#(^|/)\.#', $path) === 1) {
                $offending[] = $path;
            }
        }

        self::assertSame(
            [],
            $offending,
            "These tracked files under public/ would be published: \n" . implode("\n", $offending)
        );
    }

    /**
     * The only PHP file in the document root may be the front controller.
     *
     * A second `.php` under `public/` is executed by the web server with no
     * routing in front of it - which is how the old tree ended up serving
     * uploaded webshells from `/webtv/ticker/images/`. Naming the single allowed
     * path, rather than counting, means an added file has to be justified.
     */
    public function testOnlyTheFrontControllerIsPhpUnderPublic(): void
    {
        $php = [];

        foreach (preg_split('/\r?\n/', trim($this->git(['ls-files', 'public']))) ?: [] as $path) {
            if ($path !== '' && str_ends_with(strtolower($path), '.php')) {
                $php[] = $path;
            }
        }

        self::assertSame(
            ['public/index.php'],
            $php,
            'public/ must contain exactly one PHP file, the front controller.'
        );
    }

    /**
     * The archive keeps the Perl tree, credentials included, out of the
     * published root - but git still tracks those files, so they must not be
     * re-added to an executable location by a careless `git mv`.
     *
     * This pins the archive's shape so the guard above cannot be satisfied by
     * putting the tree back where it was.
     */
    public function testThePerlTreeIsArchivedAndNotServed(): void
    {
        self::assertDirectoryDoesNotExist(BASE_DIR . '/cgi-bin', 'cgi-bin/ must live under legacy/archive/.');
        self::assertDirectoryDoesNotExist(BASE_DIR . '/htdocs', 'htdocs/ must no longer exist.');

        self::assertFileExists(BASE_DIR . '/legacy/archive/cgi-bin/include/config.pab.pl');
        self::assertFileExists(BASE_DIR . '/legacy/README.md');
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
