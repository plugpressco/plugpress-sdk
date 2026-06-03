<?php
/**
 * PlugPress SDK — lite, drop-in toolkit shared by every PlugPress product.
 *
 * One include, one call. Provides updates, licensing/validation, and shared
 * admin UI (admin notices, License + About pages) against the PlugPress
 * Updates worker.
 *
 *   require_once __DIR__ . '/plugpress-sdk/class-sdk.php';
 *   $sdk = PlugPress_SDK::init( [
 *       'slug'        => 'inbees',
 *       'name'        => 'Inbees',
 *       'file'        => INBEES_PLUGIN_FILE,           // main plugin file
 *       'version'     => INBEES_VERSION,
 *       'server'      => 'https://updates.plugpress.co',
 *       'textdomain'  => 'inbees',                      // for translatable chrome (defaults to slug)
 *       'pro'         => false,                         // true => License page + gated updates
 *       'menu_parent' => 'inbees',                      // top-level menu to hang pages under
 *       'about'       => [
 *           'tagline' => 'Shared inbox for WordPress.',
 *           'links'   => [ 'Documentation' => 'https://inbees.co/docs' ],
 *       ],
 *   ] );
 *
 *   $sdk->notices()->flash( 'Saved!', 'success' );      // shared admin notices
 *
 * @package PlugPress\SDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PlugPress_SDK' ) ) {

	class PlugPress_SDK {

		/** @var array<string,PlugPress_SDK> one instance per product slug. */
		private static array $instances = [];

		private array $cfg;
		private ?PlugPress_License $license = null;
		private PlugPress_Notices $notices;

		public static function init( array $config ): PlugPress_SDK {
			$slug = (string) ( $config['slug'] ?? '' );
			if ( isset( self::$instances[ $slug ] ) ) {
				return self::$instances[ $slug ];
			}
			$sdk                      = new self( $config );
			self::$instances[ $slug ] = $sdk;
			return $sdk;
		}

		private function __construct( array $config ) {
			$dir = __DIR__ . '/';
			require_once $dir . 'class-updater.php';
			require_once $dir . 'class-license.php';
			require_once $dir . 'class-notices.php';

			$this->cfg = wp_parse_args( $config, [
				'slug'        => '',
				'name'        => '',
				'file'        => '',
				'version'     => '0.0.0',
				'server'      => 'https://updates.plugpress.co',
				'textdomain'  => (string) ( $config['slug'] ?? '' ),
				'pro'         => false,
				'capability'  => 'manage_options',
				'menu_parent' => '',
				'about'       => [],
			] );

			$this->notices = new PlugPress_Notices( $this->cfg['slug'] );

			// License component (pro only).
			if ( $this->cfg['pro'] ) {
				$this->license = new PlugPress_License( [
					'slug'   => $this->cfg['slug'],
					'server' => $this->cfg['server'],
					'option' => $this->cfg['slug'] . '_license_key',
				] );
			}

			// Updater (always). Pro passes the stored key so the worker can gate.
			$license = $this->license;
			new PlugPress_Updater( [
				'slug'        => $this->cfg['slug'],
				'plugin_file' => plugin_basename( $this->cfg['file'] ),
				'version'     => $this->cfg['version'],
				'server'      => $this->cfg['server'],
				'license'     => $license ? fn() => $license->get_key() : null,
			] );

			add_action( 'admin_menu', [ $this, 'register_pages' ], 80 );
			add_action( 'admin_init', [ $this, 'handle_license_post' ] );

			if ( $this->cfg['pro'] ) {
				add_action( 'admin_notices', [ $this, 'maybe_license_nudge' ] );
			}
		}

		/** Shared admin-notice creator — see PlugPress_Notices. */
		public function notices(): PlugPress_Notices {
			return $this->notices;
		}

		public function license(): ?PlugPress_License {
			return $this->license;
		}

		/** Translate SDK chrome under the host plugin's text domain. */
		private function t( string $text ): string {
			return translate( $text, $this->cfg['textdomain'] );
		}

		// ── Admin notices ─────────────────────────────────────────────────────

		/** Sticky nudge to activate the license (pro, when not valid). */
		public function maybe_license_nudge(): void {
			if ( ! $this->license || $this->license->is_valid() ) {
				return;
			}
			// Don't nag on the license page itself.
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && str_contains( (string) $screen->id, $this->cfg['slug'] . '-license' ) ) {
				return;
			}
			$url     = admin_url( 'admin.php?page=' . $this->cfg['slug'] . '-license' );
			$message = sprintf(
				/* translators: 1: product name, 2: opening link tag, 3: closing link tag */
				$this->t( '%1$s needs an active license to receive updates. %2$sActivate now%3$s.' ),
				'<strong>' . esc_html( $this->cfg['name'] ) . '</strong>',
				'<a href="' . esc_url( $url ) . '">',
				'</a>'
			);
			$this->notices->persistent( 'activate-license', $message, 'warning' );
		}

		// ── Admin pages ────────────────────────────────────────────────────────

		public function register_pages(): void {
			$parent = $this->cfg['menu_parent'];
			$cap    = $this->cfg['capability'];

			if ( $this->cfg['pro'] && $parent ) {
				add_submenu_page(
					$parent,
					$this->t( 'License' ),
					$this->t( 'License' ),
					$cap,
					$this->cfg['slug'] . '-license',
					[ $this, 'render_license_page' ]
				);
			}

			if ( ! empty( $this->cfg['about'] ) && $parent ) {
				add_submenu_page(
					$parent,
					sprintf( $this->t( 'About %s' ), $this->cfg['name'] ),
					$this->t( 'About' ),
					$cap,
					$this->cfg['slug'] . '-about',
					[ $this, 'render_about_page' ]
				);
			}
		}

		public function handle_license_post(): void {
			if ( ! $this->license || empty( $_POST['pp_license_action'] ) ) {
				return;
			}
			if ( ! current_user_can( $this->cfg['capability'] ) ) {
				return;
			}
			check_admin_referer( 'pp_license_' . $this->cfg['slug'] );

			$action = sanitize_text_field( wp_unslash( $_POST['pp_license_action'] ) );

			if ( 'save_activate' === $action ) {
				$this->license->set_key( sanitize_text_field( wp_unslash( $_POST['pp_license_key'] ?? '' ) ) );
				$res = $this->license->activate();
				if ( empty( $res->error ) ) {
					$this->notices->flash( $this->t( 'License activated.' ), 'success' );
				} else {
					$this->notices->flash( $this->t( 'Could not activate — check the key and try again.' ), 'error' );
				}
			} elseif ( 'deactivate' === $action ) {
				$this->license->deactivate();
				$this->notices->flash( $this->t( 'License deactivated.' ), 'warning' );
			}

			wp_safe_redirect( remove_query_arg( 'pp_notice' ) );
			exit;
		}

		public function render_license_page(): void {
			$status = $this->license->status();
			$valid  = ! empty( $status->valid );
			$key    = $this->license->get_key();

			echo '<div class="wrap"><h1>' . esc_html( $this->cfg['name'] . ' — ' . $this->t( 'License' ) ) . '</h1>';

			$badge = $valid
				? '<span style="color:#16A34A;font-weight:600;">● ' . esc_html( $this->t( 'Active' ) ) . '</span>'
				: '<span style="color:#DC2626;font-weight:600;">● ' . esc_html( $this->reason_label( $status->reason ?? 'inactive' ) ) . '</span>';

			echo '<p>' . esc_html( $this->t( 'Status:' ) ) . ' ' . wp_kses_post( $badge ) . '</p>';

			echo '<form method="post" action="">';
			wp_nonce_field( 'pp_license_' . $this->cfg['slug'] );
			echo '<table class="form-table"><tr><th scope="row"><label for="pp_license_key">' . esc_html( $this->t( 'License key' ) ) . '</label></th><td>';
			echo '<input name="pp_license_key" id="pp_license_key" type="text" class="regular-text" value="' . esc_attr( $key ) . '" placeholder="PP-XXXX-XXXX-XXXX" />';
			if ( $valid && ! empty( $status->expires_at ) ) {
				echo '<p class="description">' . esc_html( sprintf( $this->t( 'Expires %s' ), $status->expires_at ) ) . '</p>';
			}
			echo '</td></tr></table>';

			echo '<input type="hidden" name="pp_license_action" value="' . ( $valid ? 'deactivate' : 'save_activate' ) . '" />';
			submit_button( $valid ? $this->t( 'Deactivate' ) : $this->t( 'Activate' ) );
			echo '</form></div>';
		}

		public function render_about_page(): void {
			$about    = $this->cfg['about'];
			$accent   = '#16A34A';
			$tagline  = (string) ( $about['tagline'] ?? '' );
			$links    = ( ! empty( $about['links'] ) && is_array( $about['links'] ) ) ? $about['links'] : [];

			echo '<div class="wrap">';
			echo '<h1>' . esc_html( sprintf( $this->t( 'About %s' ), $this->cfg['name'] ) ) . '</h1>';

			// Card
			echo '<div style="max-width:760px;margin-top:12px;background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:24px 26px;">';

			// Header row: name + version pill
			echo '<div style="display:flex;align-items:center;gap:12px;">';
			echo '<span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:9px;background:' . esc_attr( $accent ) . ';color:#fff;font-weight:700;">P</span>';
			echo '<div><div style="font-size:18px;font-weight:700;color:#0F172A;">' . esc_html( $this->cfg['name'] ) . '</div>';
			echo '<span style="display:inline-block;margin-top:2px;font-size:12px;font-weight:600;color:#475569;background:#F1F5F9;border-radius:999px;padding:2px 10px;">v' . esc_html( $this->cfg['version'] ) . '</span></div>';
			echo '</div>';

			if ( '' !== $tagline ) {
				echo '<p style="font-size:14px;color:#475569;line-height:1.6;margin:18px 0 0;">' . esc_html( $tagline ) . '</p>';
			}

			// Links as buttons
			if ( $links ) {
				echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:20px;">';
				foreach ( $links as $label => $href ) {
					echo '<a class="button" href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
				}
				echo '</div>';
			}

			echo '</div>'; // card

			echo '<p style="margin-top:18px;color:#94A3B8;font-size:12px;">'
				. '<span style="display:inline-block;width:16px;height:16px;border-radius:5px;background:' . esc_attr( $accent ) . ';color:#fff;font-size:10px;font-weight:700;line-height:16px;text-align:center;vertical-align:middle;margin-right:6px;">P</span>'
				. esc_html( $this->t( 'A PlugPress product' ) ) . '</p>';
			echo '</div>';
		}

		private function reason_label( string $reason ): string {
			$map = [
				'license_missing'          => $this->t( 'No license' ),
				'not_activated'            => $this->t( 'Not activated' ),
				'license_invalid'          => $this->t( 'Invalid key' ),
				'license_expired'          => $this->t( 'Expired' ),
				'license_disabled'         => $this->t( 'Disabled' ),
				'activation_limit_reached' => $this->t( 'Site limit reached' ),
				'wrong_product'            => $this->t( 'Key is for another product' ),
				'server_unreachable'       => $this->t( 'Server unreachable' ),
				'inactive'                 => $this->t( 'Inactive' ),
			];
			return $map[ $reason ] ?? $reason;
		}
	}
}
