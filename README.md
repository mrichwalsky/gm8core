# GM8 Core

WordPress plugin for hosted client sites: **admin cleanup** (dashboard widgets, welcome panel, selective notice suppression) and **silent updates** from [GitHub Releases](https://github.com/mrichwalsky/gm8core/releases).

- **Repository:** [github.com/mrichwalsky/gm8core](https://github.com/mrichwalsky/gm8core)
- **Plugin slug:** `gm8-core` (install path on a site: `wp-content/plugins/gm8-core/`)

## Repository layout

The plugin lives **one level** under the repo root so it matches what WordPress expects inside a release zip:

```text
gm8core/
  gm8-core/
    gm8-core.php    ← plugin main file
  README.md
  LICENSE
  .gitignore
```

Do **not** nest the plugin under `wp-content/plugins/` in this repository. That would produce zips like `wp-content/plugins/gm8-core/...`, and the WordPress upgrader would install or upgrade the plugin in the wrong place.

### Building `gm8-core.zip` for a GitHub Release

Create a zip whose **root folder** is `gm8-core/` (containing `gm8-core.php`). For example, from the repo root:

- **Windows (PowerShell):** `Compress-Archive -Path gm8-core -DestinationPath gm8-core.zip`
- **macOS/Linux:** `zip -r gm8-core.zip gm8-core`

Upload **`gm8-core.zip`** as a release asset (not the GitHub “Source code” zipball).

## Requirements

- WordPress **5.8+**
- PHP **7.4+**
- Outbound HTTPS allowed to `api.github.com` (for release checks)

## Install

1. Copy the `gm8-core` folder into `wp-content/plugins/`:

   ```text
   wp-content/plugins/gm8-core/gm8-core.php
   ```

2. Activate **GM8 Core** in **Plugins**.

Or install via WP-CLI from a release zip (see [Releases](#silent-updates-github)):

```bash
wp plugin install /path/to/gm8-core.zip --activate
```

## Configuration (`wp-config.php`)

All options use constants. Define them **before** `require_once ABSPATH . 'wp-settings.php';`.

| Constant | Default | Purpose |
|----------|---------|---------|
| `GM8_CLEANUP_ENABLED` | `true` | Master switch for plugin behavior. |
| `GM8_CLEANUP_DASHBOARD_ENABLED` | `true` | Remove configured dashboard meta boxes. |
| `GM8_CLEANUP_DASHBOARD_REMOVE` | *(built-in list)* | Override which meta boxes to remove. Supports list of `id`/`context`, associative `id => context`, or simple string IDs (default context `normal`). |
| `GM8_CLEANUP_REMOVE_WELCOME_PANEL` | `true` | Hide the welcome panel. |
| `GM8_CLEANUP_NOTICES_MODE` | `'allow'` | `'allow'` \| `'block_non_admins'` \| `'block_except_user_ids'`. |
| `GM8_CLEANUP_NOTICES_ALLOW_USER_IDS` | `[1]` | When mode is `block_except_user_ids`, these users still see notices on allowed screens. |
| `GM8_CLEANUP_NOTICES_SCREEN_IDS` | `['dashboard']` | Admin screen IDs where notice pruning may run (e.g. `dashboard`). |
| `GM8_CLEANUP_STRIP_GENERATOR` | `false` | Strip `generator` meta via `the_generator`. |
| `GM8_CLEANUP_STRIP_ASSET_VER` | `false` | Remove `?ver=` from enqueued scripts/styles (can affect cache-busting). |
| `GM8_CLEANUP_HIDE_MENU_ITEMS` | `[]` | Admin menu slugs to remove via `remove_menu_page`. |
| `GM8_CLEANUP_HIDE_ADMIN_BAR_ITEMS` | `[]` | Admin bar node IDs to remove. |
| `GM8_CLEANUP_GITHUB_REPO` | `'mrichwalsky/gm8core'` | `owner/repo` for GitHub Releases API. Set to `''` to disable silent updates. |

Example:

```php
define('GM8_CLEANUP_NOTICES_MODE', 'block_except_user_ids');
define('GM8_CLEANUP_NOTICES_ALLOW_USER_IDS', [1, 12]);
define('GM8_CLEANUP_NOTICES_SCREEN_IDS', ['dashboard']);
```

## Silent updates (GitHub)

The plugin checks **`/releases/latest`** on the configured repo and, when a newer **semantic version** is found (from the release tag), downloads the zip and upgrades **without showing UI** to end users.

- **Schedule:** WordPress cron, **twicedaily** (hook: `gm8_cleanup_silent_update_check`).
- **Caching:** Release JSON is cached (site transient) to limit API calls.

### Releases

1. Bump the plugin header `Version:` in `gm8-core/gm8-core.php` to match the release (e.g. `0.2.0`).
2. Create a **GitHub Release** with a tag like `v0.2.0` (or `0.2.0`). The tag is parsed (leading `v` is stripped).
3. **Attach a release asset** named **`gm8-core.zip`**, built as [above](#building-gm8-corezip-for-a-github-release) so the archive contains `gm8-core/gm8-core.php` at the top level.

   Do **not** rely on GitHub’s auto-generated “Source code (zip)” archives for upgrades; folder names are not stable for the WordPress upgrader.

4. Publish the release. Sites with the plugin will pick up the update on a future cron run.

To **turn off** remote updates for a site:

```php
define('GM8_CLEANUP_GITHUB_REPO', '');
```

## Development

- Main file: [`gm8-core/gm8-core.php`](gm8-core/gm8-core.php)

```bash
php -l gm8-core/gm8-core.php
```

## License

Add a `LICENSE` file at the repository root if you distribute the plugin (your [gm8core](https://github.com/mrichwalsky/gm8core) repo may already include one).
