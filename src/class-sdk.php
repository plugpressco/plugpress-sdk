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
		private ?PlugPress_Feedback $feedback = null;
		private ?PlugPress_Optin $optin = null;

		public static function init( array $config ): PlugPress_SDK {
			$slug = (string) ( $config['slug'] ?? '' );
			if ( isset( self::$instances[ $slug ] ) ) {
				return self::$instances[ $slug ];
			}
			$sdk                      = new self( $config );
			self::$instances[ $slug ] = $sdk;
			return $sdk;
		}

		public static function get_instance( string $slug ): ?PlugPress_SDK {
			return self::$instances[ $slug ] ?? null;
		}

		private function __construct( array $config ) {
			$dir = __DIR__ . '/';
			require_once $dir . 'class-updater.php';
			require_once $dir . 'class-license.php';
			require_once $dir . 'class-notices.php';
			require_once $dir . 'class-feedback.php';
			require_once $dir . 'class-optin.php';
			require_once $dir . 'class-about.php';

			$this->cfg = wp_parse_args( $config, [
				'slug'              => '',
				'name'              => '',
				'file'              => '',
				'version'           => '0.0.0',
				'server'            => 'https://updates.plugpress.co',
				'telemetry_server'  => '', // separate from update server; empty = telemetry disabled
				'activate_redirect' => '', // URL to redirect to after first activation (e.g. onboarding)
				'textdomain'        => (string) ( $config['slug'] ?? '' ),
				'pro'               => false,
				'updater'           => true,  // set false when another system (e.g. Freemius) handles updates
				'capability'        => 'manage_options',
				'menu_parent'       => '',
				'accent'            => '#2395E7',
				'about'             => [],
			] );

			$this->notices = new PlugPress_Notices( $this->cfg['slug'] );

			// License component (pro only, and only when the SDK owns updates).
			if ( $this->cfg['pro'] && $this->cfg['updater'] ) {
				$this->license = new PlugPress_License( [
					'slug'   => $this->cfg['slug'],
					'server' => $this->cfg['server'],
					'option' => $this->cfg['slug'] . '_license_key',
				] );
			}

			// Updater — skip when another system (Freemius, WP.org) handles updates.
			if ( $this->cfg['updater'] ) {
				$license = $this->license;
				new PlugPress_Updater( [
					'slug'        => $this->cfg['slug'],
					'plugin_file' => plugin_basename( $this->cfg['file'] ),
					'version'     => $this->cfg['version'],
					'server'      => $this->cfg['server'],
					'license'     => $license ? fn() => $license->get_key() : null,
				] );
			}

			// Deactivation feedback modal (plugins.php).
			$this->feedback = new PlugPress_Feedback( $this->cfg );

			// Opt-in notice for anonymous telemetry.
			$this->optin = new PlugPress_Optin( $this->cfg );

			// Weekly telemetry ping — fires on admin_init, throttled inside the method.
			add_action( 'admin_init', array( $this->optin, 'maybe_send_telemetry' ) );

			// After first activation: redirect to the onboarding URL (if configured)
			// and record the activation time. Uses a short-lived transient so the
			// redirect fires exactly once on the very next admin page load.
			$plugin_basename  = plugin_basename( $this->cfg['file'] );
			$redirect_url     = $this->cfg['activate_redirect'];
			$redirect_key     = $this->cfg['slug'] . '_pp_do_redirect';
			$activated_opt    = $this->cfg['slug'] . '_pp_activated_at';

			add_action( 'activated_plugin', static function ( string $plugin ) use ( $plugin_basename, $redirect_url, $redirect_key, $activated_opt ) {
				if ( $plugin !== $plugin_basename ) {
					return;
				}
				update_option( $activated_opt, time(), false );
				if ( $redirect_url ) {
					set_transient( $redirect_key, '1', 30 );
				}
			} );

			// Perform the redirect on the next admin request (skip bulk-activation).
			if ( $redirect_url ) {
				add_action( 'admin_init', function () use ( $redirect_key, $redirect_url ) {
					if ( get_transient( $redirect_key ) && empty( $_GET['activate-multi'] ) ) {
						delete_transient( $redirect_key );
						wp_safe_redirect( $redirect_url );
						exit;
					}
				} );
			}

			add_action( 'admin_menu', [ $this, 'register_pages' ], 80 );
			add_action( 'admin_init', [ $this, 'handle_license_post' ] );

			if ( $this->cfg['pro'] && $this->cfg['updater'] ) {
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

		public function optin(): ?PlugPress_Optin {
			return $this->optin;
		}

		/** Convenience: optin JS data for wp_localize_script, or null when telemetry is disabled. */
		public function get_optin_js_data(): ?array {
			return $this->optin ? $this->optin->get_js_data() : null;
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
			PlugPress_About::render( $this->cfg );
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
