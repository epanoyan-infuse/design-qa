# design-qa

A Claude Code plugin for developers. It compares a Figma design with a live web page on every screen size (the mobile menu too, opened on the page) and reports every text whose wording, font or color doesn't match.

**Checks:**
- 🔴 Critical: text content (typos, different wording, missing or extra texts), font family (also a web font that did not load), font size, font weight, font style (italic), text color
- 🟡 Non-critical: line height, letter spacing
---

## Before you start

You need all four of these:

| # | What | Why |
|---|---|---|
| 1 | Claude Code | Runs the plugin's skill and shows you the results |
| 2 | PHP 8.2+ with Composer, `ext-json`, `ext-mbstring` | Runs the engine that measures and compares |
| 3 | Google Chrome | Loads the live page headlessly to read its real styles |
| 4 | A read-only Figma personal access token | Lets the engine read the design |

---

## Step 1 — Install PHP and Composer

Skip this step if `php -v` already prints 8.2 or higher.

**No admin rights (macOS, Apple Silicon):**
```
mkdir -p ~/.local/bin && cd ~/.local/bin
curl -fL https://dl.static-php.dev/static-php-cli/common/php-8.4.23-cli-macos-aarch64.tar.gz | tar -xz
curl -fL -o composer https://getcomposer.org/download/latest-stable/composer.phar && chmod +x composer
grep -q '.local/bin' ~/.zshrc || echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc
```
Open a new terminal tab afterwards so the `PATH` change takes effect. On an Intel Mac, use the `macos-x86_64` file from the same [static-php-cli releases](https://dl.static-php.dev/static-php-cli/common/) instead.

**With admin rights (any OS with Homebrew):**
```
brew install php composer
```

Check it worked:
```
php -v && composer -V
```

---

## Step 2 — Install the plugin in Claude Code

**From GitHub:**
```
/plugin marketplace add epanoyan-infuse/design-qa
/plugin install design-qa@design-qa
```

**From a local clone (while developing on the plugin itself):**
```
/plugin marketplace add ~/Projects/design-qa
/plugin install design-qa@design-qa
```

---

## Step 3 — Install the engine's PHP dependencies

```
cd plugin/engine
composer install
```

---

## Step 4 — Connect your Figma key (one time)

1. In Figma: **Settings → Security → Personal access tokens → Generate new token.**
   - Name it `design-qa`.
   - Set a 90-day expiry (or shorter).
   - Scope: **File content: read-only** — nothing else.
2. Save it to your machine. In Claude Code, ask:
   > design-qa login

   Claude runs `php bin/design-qa login`, which asks you to paste the key (hidden input) and stores it in the macOS keychain. You can also run that command yourself in a terminal.
3. **No keychain on this machine?** Set an environment variable instead:
   ```
   export FIGMA_TOKEN=your-key-here
   ```
4. **Never** paste the key into a chat message, a ticket, or any file in this repo.

**Seat matters:** use a key from an account with a **Dev or Full seat** in the team that owns the Figma file. Keys from a **View or Collab seat** get only a few full design reads per month (Figma's own limit, not this tool's). design-qa caches each design until the Figma file's version changes, so repeated checks of the same design don't use up reads. If you're only on a View/Collab seat for now, you can still develop against the recorded Kesler fixture without spending reads — see [Development](#development) below.

---

## Step 5 — Confirm everything is ready

In Claude Code, ask:
> is design-qa ready?

Claude runs `php bin/design-qa doctor` and shows ✅/❌ for: PHP version, PHP extensions, Chrome, the Figma key, and the check-rules config. If anything shows ❌, apply the `Fix:` line printed under it and ask again.

---

## Step 6 — Check a page

In Claude Code, ask:
> check https://invisible-buyer.kesler.com/ against https://www.figma.com/design/…

(Paste your own page URL and Figma link — the Figma link must point at the frame or section that holds the screens; in Figma, right-click it and choose **Copy link to selection**.)

About 30 seconds later, Claude shows:
- The 🔴 critical and 🟡 non-critical differences, grouped per screen size.
- What needs a manual look (⚠️ check manually — text found in two equally-likely spots).
- What couldn't be compared (e.g. a screen whose menu wouldn't open).
- The path to a saved HTML report, in `plugin/engine/design-qa-reports/`, with tabs for Desktop / Tablet / Mobile and, inside each, Issues / Don't match / Missing / Extra / Check by hand.

After you've fixed something, ask:
> check again

To see the raw texts and styles design-qa reads, without comparing anything:
> show me what design-qa reads from <page> and <Figma link>

---

## Using the CLI directly (without Claude)

```
cd plugin/engine
php bin/design-qa doctor
php bin/design-qa login
php bin/design-qa read <page-url> <figma-url> [--screen=390] [--json]
php bin/design-qa check <page-url> <figma-url> [--screen=390] [--json] [--refresh] [--report=path.html] [--no-report]
```
`--screen` can be repeated and matches by screen name or width. `--refresh` re-reads the full design from Figma even if the cached version looks unchanged.

---

## Troubleshooting

| Message | What to do |
|---|---|
| `design-qa is not ready yet` | Apply the `Fix:` line printed under each ❌ from `doctor` |
| `the engine's dependencies are not installed yet` | Claude runs the printed `composer install` command; or run it yourself in `plugin/engine` |
| `Figma read limit reached … View or Collab seat` | That account gets few design reads a month. Wait, or use a key from a Dev/Full seat account. Checks of an unchanged design don't use reads |
| `Figma rejected the key` | Create a new read-only key and save it again (Step 4) |
| `No design screen matches` | The Figma link must point at the frame or the section that holds the screens ("Copy link to selection") |
| `Not a web page address` | Use the page's full `https://` address |
| ⚠️ check manually | The text is in two equally likely places on the page; compare it by eye |
| Not compared: form placeholders, "Lorem ipsum" | Placeholders are read as page texts, but text with completely different wording than Figma can't be automatically matched |

---

## Development

```
cd plugin/engine
composer install
composer qa                  # code style + PHPStan + tests — must pass before any commit
php bin/design-qa doctor
php bin/design-qa read <page-url> <figma-url> --figma-response=tests/Fixtures/kesler/figma-nodes.json   # no Figma read
php bin/design-qa read <page-url> <figma-url> --save-raw=tests/Fixtures/<name>                           # record fixtures
php bin/design-qa check <page-url> <figma-url> --figma-response=tests/Fixtures/kesler/figma-nodes.json
```
