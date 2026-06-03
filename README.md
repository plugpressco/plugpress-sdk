# PlugPress SDK

A **lite, drop-in toolkit** shared by every PlugPress plugin. One include, one
call — handles **self-hosted updates, licensing/validation, and shared admin UI**
(License + About pages) against the [PlugPress Updates worker](https://github.com/plugpressco/plugin-update-workers).

## Components

| File | Class | Responsibility |
|------|-------|----------------|
| `src/class-updater.php` | `PlugPress_Updater` | WordPress-native auto-updates (free + pro) |
| `src/class-license.php` | `PlugPress_License` | key storage + validate / activate / deactivate (cached) |
| `src/class-sdk.php` | `PlugPress_SDK` | entry point — wires the above + admin pages |

All classes are `class_exists`-guarded so multiple PlugPress plugins can ship
the SDK on the same site without colliding.

## Install

### Option A — Composer (recommended for development)

Not on Packagist, so add the VCS repo to your plugin's `composer.json`:

```jsonc
{
  "repositories": [
    { "type": "vcs", "url": "git@github.com:plugpressco/plugpress-sdk.git" }
  ],
  "require": {
    "plugpressco/plugpress-sdk": "^1.0"
  }
}
```

```bash
composer require plugpressco/plugpress-sdk
```

It installs to `vendor/plugpressco/plugpress-sdk/` and autoloads via classmap.
Then bootstrap with Composer's autoloader:

```php
require_once __DIR__ . '/vendor/autoload.php';
// PlugPress_SDK / PlugPress_Updater / PlugPress_License are now autoloaded.
```

> **Shipping tip:** since end-user sites don't run `composer install`, commit
> the built `vendor/` into your plugin's release zip (or run `composer install
> --no-dev -o` in your build step). The classes are `class_exists`-guarded, so
> multiple PlugPress plugins each carrying their own copy won't collide.

### Option B — Copy the folder (zero tooling)

Copy `src/` into your plugin and require the entry point directly:

```
your-plugin/
  includes/plugpress-sdk/   ← copy of this repo's src/
```

```php
require_once __DIR__ . '/includes/plugpress-sdk/class-sdk.php';

add_action( 'plugins_loaded', function () {
    PlugPress_SDK::init( [
        'slug'        => 'your-plugin',
        'name'        => 'Your Plugin',
        'file'        => YOURPLUGIN_FILE,        // main plugin file (__FILE__)
        'version'     => YOURPLUGIN_VERSION,
        'server'      => 'https://updates.plugpress.co',
        'pro'         => false,                   // true → License page + gated updates
        'menu_parent' => 'your-plugin',           // top-level menu slug to hang pages under
        'about'       => [
            'tagline' => 'What your plugin does.',
            'links'   => [ 'Documentation' => 'https://…', 'Support' => 'https://…' ],
        ],
    ] );
} );
```

- **Free** (`pro: false`): auto-updates + an **About** page.
- **Pro** (`pro: true`): adds a **License** page (enter key → Activate/Deactivate,
  live status), and the updater sends the key so the worker gates the download.

## How it talks to the backend

The SDK only ever calls the PlugPress Updates worker:

```
/v1/update                     → latest version + signed package URL (license-checked for pro)
/v1/license/check|activate|deactivate
```

Zips live in R2, licenses in D1 — see the worker repo. Swapping the license
backend later (Lemon Squeezy, WooCommerce, …) never touches plugins.

## Versioning

Tag releases (`v1.0.0`). When you update the SDK, re-vendor `src/` into each
product and ship it with that product's next release.
