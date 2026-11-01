# TDD evidence — chat terminal parity

**Date:** 2026-11-01
**Branch:** `chat-parity`
**Source:** the user's *"please look into http://hbr1.evo/chat vs
https://hbr1.com/chat/"*.

## Finding

The port served `/chat` as a single page. Production `/chat/` is a frameset
(`rows="26,*,26,29"`, `cols="64,*,100,64"`) whose message pane is the generated
`/html/chat_content.html`, whose user pane is the empty
`apax.cgi?Part=Chat&Action=listUsers`, and whose submit bar is
`Action=submit`. The four chrome frames (`top`, `left`, `right`, `bottom`) were
already byte-identical to production.

## RED

```text
php vendor/bin/phpunit --filter ChatPageTest
Tests: 6, Assertions: 16, Failures: 5.
```

`/chat` still rendered the single page; `/chat/`, `/chat/content`,
`/chat/users` and `/chat/submit` had no routes.

## GREEN

```text
php vendor/bin/phpunit            # OK (344 tests, 1641 assertions)
php tools/lint.php                # 139 files parse
php tools/smoke.php               # 47 checks, 0 failed
XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text
                                  # overall 75.86 % lines (1933/2548)
                                  # ChatController 100 %, ChatService 71.83 %
```

Browser measurements at a 996 px viewport:

| Element | Local | Production |
|---|---|---|
| frameset rows / cols | 26,*,26,29 / 64,*,100,64 | same |
| submit columns | 798 / 198 | 798 / 198 |
| name / message / OK left | 355 / 446 / 728 | 355 / 446 / 728 |
| content line | `11/01 02:56 p: test` | same |

The earlier screenshot difference in the frames was a capture-timing artifact;
with subframes painted, the chrome, message pane and submit bar match.

## Test specification

| # | Guarantee | Test | Type | Result |
|---|---|---|---|---|
| 1 | `/chat` and `/chat/` serve the production frameset and canonical frames | `ChatPageTest::testFramesetMatchesProduction` | integration | PASS |
| 2 | Message pane keeps the legacy line, colours and Berlin `m/d H:i` clock | `…::testContentFrameUsesTheLegacyLineFormat` | integration | PASS |
| 3 | Users pane is the legacy empty shell | `…::testUsersFrameIsTheLegacyEmptyShell` | integration | PASS |
| 4 | Submit bar has the legacy fields and sizes | `…::testSubmitFrameHasTheLegacyForm` | integration | PASS |
| 5 | A successful post returns the submit bar, not the frameset | `…::testSuccessfulPostReturnsTheSubmitBarNotTheFrameset` | integration (fake service) | PASS |
| 6 | Empty post re-renders the bar with the error | `…::testEmptyPostRendersTheSubmitBarWithAnError` | integration | PASS |
| 7 | The three dynamic frames are well-formed documents | `…::testContentAndSubmitFramesAreWellFormed` | integration | PASS |
| 8 | The four chrome frames still resolve | `…::testChromeFramesStillResolve` | static | PASS |
| 9 | `Part=Chat&Action=…` maps to canonical frame routes | `LegacyUrlMapperTest` (3 cases) | unit | PASS |

## Known gaps

- `ChatService::post()` is exercised through a fake in the controller test;
  the real INSERT is not run (it writes to the live table). `submit`'s coverage
  gap is intentional.
- The frameset itself is HTML 4 (`frameset`), an explicit exception to the
  content-page HTML5 rule; HTML5 has no frameset element.
- `/html/chat_content.html` and `/chat/index.html` remain stale static copies
  for direct bookmarks; the frameset no longer loads them.
- The RED run was not committed separately; it is recorded here and in the
  session transcript.
