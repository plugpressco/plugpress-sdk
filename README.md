# PlugPress SDK

Drop-in SDK for WordPress plugins. Provides self-hosted updates, license activation, telemetry opt-in, deactivation feedback, and an About / Beta Hub admin page.

```bash
composer require plugpressco/plugpress-sdk
```

---

## Components

| Config key | Class | What it does |
|---|---|---|
| `updater` | `PlugPress_Updater` | Checks `updates.plugpress.co` for new versions |
| `pro` + `updater` | `PlugPress_License` | License key activation / validation |
| `optin` | `PlugPress_Optin` | GDPR-compliant telemetry opt-in (WP Guideline 7) |
| `feedback` | `PlugPress_Feedback` | Deactivation reason modal on plugins.php |
| `about` | `PlugPress_About` | About / Beta Hub admin page |

Every component is individually toggleable — disable what your distribution channel restricts.

---

## Distribution channel configs

### 1. PlugPress direct (full SDK)

Plugins sold at plugpress.co / outbees.co / inbees.co — SDK owns everything.

```php
add_action( 'init', function () {
    if ( ! class_exists( 'PlugPress_SDK' ) ) return;

    PlugPress_SDK::init( [
        'slug'              => 'your-plugin',
        'name'              => 'Your Plugin',
        'file'              => __FILE__,
        'version'           => YOUR_PLUGIN_VERSION,
        'server'            => 'https://updates.plugpress.co',
        'telemetry_server'  => 'https://analytics.plugpress.co',
        'activate_redirect' => admin_url( 'admin.php?page=your-plugin#/onboarding/welcome' ),
        'pro'               => false,          // true for pro plugins
        // updater, optin, feedback all default to true
        'menu_parent'       => 'your-plugin',
        'accent'            => '#4F46E5',
        'about'             => [
            'tagline' => 'One-line description.',
            'links'   => [
                'Documentation' => 'https://yourplugin.co/docs',
                'Support'       => 'https://yourplugin.co/support',
            ],
        ],
    ] );
} );
```

---

### 2. DiviPeople self-hosted (Freemius lite — no updater/license)

Plugins sold at divipeople.com that use Freemius lite for opt-in/feedback but NOT for updates. SDK adds About page + analytics.

```php
add_action( 'init', function () {
    if ( ! class_exists( 'PlugPress_SDK' ) ) return;

    PlugPress_SDK::init( [
        'slug'             => 'divi-blog-pro',
        'name'             => 'Divi Blog Pro',
        'file'             => __FILE__,
        'version'          => DBP_VERSION,
        'telemetry_server' => 'https://analytics.plugpress.co',
        'updater'          => false,   // Freemius handles updates + license
        'optin'            => true,
        'feedback'         => true,
        'menu_parent'      => 'divi-people',
        'accent'           => '#7747FF',
        'about'            => [
            'tagline' => 'Beautiful blog layouts for Divi.',
            'links'   => [
                'Documentation' => 'https://divipeople.com/docs/divi-blog-pro',
                'Support'       => 'https://divipeople.com/support',
            ],
        ],
    ] );
} );
```

---

### 3. Full Freemius SDK (Divi Torque Pro and similar)

Plugins that use the full Freemius SDK — Freemius already handles opt-in and feedback. SDK adds only the About page.

```php
add_action( 'init', function () {
    if ( ! class_exists( 'PlugPress_SDK' ) ) return;

    PlugPress_SDK::init( [
        'slug'             => 'divitorque',
        'name'             => 'Divi Torque Pro',
        'file'             => __FILE__,
        'version'          => DTP_VERSION,
        'telemetry_server' => 'https://analytics.plugpress.co',
        'updater'          => false,   // Freemius
        'optin'            => false,   // Freemius has its own opt-in
        'feedback'         => false,   // Freemius has its own feedback
        'menu_parent'      => 'divitorque',
        'accent'           => '#7747FF',
        'about'            => [
            'tagline' => 'Powerful Divi modules to create exceptional websites.',
            'links'   => [
                'Documentation' => 'https://divitorque.com/docs',
                'Support'       => 'https://divitorque.com/support',
                'Changelog'     => 'https://divitorque.com/changelog',
            ],
        ],
    ] );
} );
```

---

### 4. ET Marketplace version

Elegant Themes marketplace restricts all external HTTP calls. SDK adds only the About page — zero external calls.

```php
add_action( 'init', function () {
    if ( ! class_exists( 'PlugPress_SDK' ) ) return;

    PlugPress_SDK::init( [
        'slug'        => 'divi-blog-pro',
        'name'        => 'Divi Blog Pro',
        'file'        => __FILE__,
        'version'     => DBP_VERSION,
        'updater'     => false,   // ET handles
        'optin'       => false,   // no external calls on ET
        'feedback'    => false,   // no external calls on ET
        'menu_parent' => 'divi-people',
        'accent'      => '#7747FF',
        'about'       => [
            'tagline' => 'Beautiful blog layouts for Divi.',
            'links'   => [
                'Documentation' => 'https://divipeople.com/docs',
                'Support'       => 'https://divipeople.com/support',
            ],
        ],
    ] );
} );
```

---

### 5. Free plugins (WordPress.org)

WP.org handles updates — no updater or license needed. Opt-in and feedback are allowed.

```php
add_action( 'init', function () {
    if ( ! class_exists( 'PlugPress_SDK' ) ) return;

    PlugPress_SDK::init( [
        'slug'             => 'your-free-plugin',
        'name'             => 'Your Free Plugin',
        'file'             => __FILE__,
        'version'          => YOUR_PLUGIN_VERSION,
        'telemetry_server' => 'https://analytics.plugpress.co',
        'updater'          => false,   // WP.org handles updates
        'pro'              => false,
        'menu_parent'      => 'your-free-plugin',
        'accent'           => '#4F46E5',
        'about'            => [
            'tagline' => 'One-line description.',
            'links'   => [
                'Documentation' => 'https://...',
                'Support'       => 'https://wordpress.org/support/plugin/your-free-plugin',
                'Rate us'       => 'https://wordpress.org/plugins/your-free-plugin/#reviews',
            ],
        ],
    ] );
} );
```

---

## Toggle cheatsheet

| Channel | `updater` | `optin` | `feedback` |
|---|---|---|---|
| PlugPress direct | `true` | `true` | `true` |
| DiviPeople (Freemius lite) | `false` | `true` | `true` |
| Full Freemius SDK | `false` | `false` | `false` |
| ET Marketplace | `false` | `false` | `false` |
| WordPress.org free | `false` | `true` | `true` |

---

## Full config reference

```php
PlugPress_SDK::init( [
    // Required
    'slug'              => '',        // plugin text-domain slug
    'name'              => '',        // human-readable plugin name
    'file'              => __FILE__,  // path to main plugin file
    'version'           => '1.0.0',

    // Update server (only used when updater: true)
    'server'            => 'https://updates.plugpress.co',

    // Analytics endpoint (only used when optin: true, empty = disabled)
    'telemetry_server'  => 'https://analytics.plugpress.co',

    // Redirect to onboarding after first activation (only when optin: true)
    'activate_redirect' => '',

    // Pro license gate (only when updater: true)
    'pro'               => false,

    // Component toggles
    'updater'           => true,   // self-hosted update checker + license
    'optin'             => true,   // telemetry opt-in notice + weekly ping
    'feedback'          => true,   // deactivation feedback modal

    // Admin UI
    'menu_parent'       => '',        // parent menu slug for About/License pages
    'accent'            => '#2395E7', // brand colour for buttons and highlights
    'textdomain'        => '',        // defaults to slug
    'capability'        => 'manage_options',

    // About page content
    'about'             => [
        'tagline' => '',
        'links'   => [],  // [ 'Label' => 'https://...' ]
    ],
] );
```

---

## Installation

```bash
composer require plugpressco/plugpress-sdk
```

Load the autoloader before calling `PlugPress_SDK::init()`:

```php
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}
```

> **Shipping tip:** end-user sites don't run `composer install`, so commit the
> built `vendor/` into your plugin's release zip, or run
> `composer install --no-dev -o` in your build step. The classes are
> `class_exists`-guarded so multiple PlugPress plugins each carrying their own
> copy won't collide.

---

## Releasing updates

```bash
cd plugpress-sdk/
git commit -m "fix: ..."
git tag v1.2.2
git push && git push --tags
# Packagist auto-updates via GitHub webhook
```

Update in each plugin:

```bash
composer update plugpressco/plugpress-sdk
git add composer.lock && git commit -m "chore: bump plugpress-sdk to v1.2.2"
```
