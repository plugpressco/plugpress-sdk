<?php
/**
 * PlugPress SDK — lite, drop-in toolkit shared by every PlugPress product.
 *
 * One include, one call. Handles updates, licensing/validation, and shared
 * admin UI (License + About pages) against the PlugPress Updates worker.
 *
 *   require_once __DIR__ . '/plugpress-sdk/class-sdk.php';
 *   PlugPress_SDK::init( [
 *       'slug'        => 'inbees',
 *       'name'        => 'Inbees',
 *       'file'        => INBEES_PLUGIN_FILE,           // main plugin file
 *       'version'     => INBEES_VERSION,
 *       'server'      => 'https://updates.plugpress.co',
 *       'pro'         => false,                         // true => requires a license
 *       'menu_parent' => 'inbees',                      // top-level menu to hang pages under
 *       'about'       => [
 *           'tagline' => 'Shared inbox for WordPress.',
 *           'links'   => [ 'Documentation' => 'https://inbees.co/docs' ],
 *       ],
 *   ] );
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

		public static function init( array $config ): PlugPress_SDK {
			$slug = (string) ( $config['slug'] ?? '' );
			if ( isset( self::$instances[ $slug ] ) ) {
				return self::$instances[ $slug ];
			}
			$sdk = new self( $config );
			self::$instances[ $slug ] = $sdk;
			return $sdk;
		}

		private function __construct( array $config ) {
			$dir = __DIR__ . '/';
			require_once $dir . 'class-updater.php';
			require_once $dir . 'class-license.php';

			$this->cfg = wp_parse_args( $config, [
				'slug'        => '',
				'name'        => '',
				'file'        => '',
				'version'     => '0.0.0',
				'server'      => 'https://updates.plugpress.co',
				'pro'         => false,
				'capability'  => 'manage_options',
				'menu_parent' => '',
				'about'       => [],
			] );

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
		}

		public function license(): ?PlugPress_License {
			return $this->license;
		}

		// ── Admin pages ──────────────────────────────────────────────────────

		public function register_pages(): void {
			$parent = $this->cfg['menu_parent'];
			$cap    = $this->cfg['capability'];

			if ( $this->cfg['pro'] && $parent ) {
				add_submenu_page(
					$parent,
					__( 'License', 'inbees' ),
					__( 'License', 'inbees' ),
					$cap,
					$this->cfg['slug'] . '-license',
					[ $this, 'render_license_page' ]
				);
			}

			if ( ! empty( $this->cfg['about'] ) && $parent ) {
				add_submenu_page(
					$parent,
					/* translators: %s product name */ sprintf( __( 'About %s', 'inbees' ), $this->cfg['name'] ),
					__( 'About', 'inbees' ),
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
				$res    = $this->license->activate();
				$notice = empty( $res->error ) ? 'activated' : 'error';
			} elseif ( 'deactivate' === $action ) {
				$this->license->deactivate();
				$notice = 'deactivated';
			} else {
				$notice = '';
			}

			wp_safe_redirect( add_query_arg( 'pp_notice', $notice, remove_query_arg( 'pp_notice' ) ) );
			exit;
		}

		public function render_license_page(): void {
			$status = $this->license->status();
			$valid  = ! empty( $status->valid );
			$key    = $this->license->get_key();
			$notice = isset( $_GET['pp_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['pp_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			echo '<div class="wrap"><h1>' . esc_html( $this->cfg['name'] . ' — ' . __( 'License', 'inbees' ) ) . '</h1>';

			if ( 'activated' === $notice ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'License activated.', 'inbees' ) . '</p></div>';
			} elseif ( 'deactivated' === $notice ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'License deactivated.', 'inbees' ) . '</p></div>';
			} elseif ( 'error' === $notice ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not activate — check the key and try again.', 'inbees' ) . '</p></div>';
			}

			$badge = $valid
				? '<span style="color:#16A34A;font-weight:600;">● ' . esc_html__( 'Active', 'inbees' ) . '</span>'
				: '<span style="color:#DC2626;font-weight:600;">● ' . esc_html( $this->reason_label( $status->reason ?? 'inactive' ) ) . '</span>';

			echo '<p>' . esc_html__( 'Status:', 'inbees' ) . ' ' . wp_kses_post( $badge ) . '</p>';

			echo '<form method="post" action="">';
			wp_nonce_field( 'pp_license_' . $this->cfg['slug'] );
			echo '<table class="form-table"><tr><th scope="row"><label for="pp_license_key">' . esc_html__( 'License key', 'inbees' ) . '</label></th><td>';
			echo '<input name="pp_license_key" id="pp_license_key" type="text" class="regular-text" value="' . esc_attr( $key ) . '" placeholder="PP-XXXX-XXXX-XXXX" />';
			if ( $valid && ! empty( $status->expires_at ) ) {
				echo '<p class="description">' . esc_html( sprintf( __( 'Expires %s', 'inbees' ), $status->expires_at ) ) . '</p>';
			}
			echo '</td></tr></table>';

			echo '<input type="hidden" name="pp_license_action" value="' . ( $valid ? 'deactivate' : 'save_activate' ) . '" />';
			submit_button( $valid ? __( 'Deactivate', 'inbees' ) : __( 'Activate', 'inbees' ) );
			echo '</form></div>';
		}

		public function render_about_page(): void {
			$about = $this->cfg['about'];
			echo '<div class="wrap"><h1>' . esc_html( sprintf( __( 'About %s', 'inbees' ), $this->cfg['name'] ) ) . '</h1>';
			echo '<p style="font-size:14px;color:#475569;max-width:640px;">' . esc_html( $about['tagline'] ?? '' ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Version', 'inbees' ) . ':</strong> ' . esc_html( $this->cfg['version'] ) . '</p>';

			if ( ! empty( $about['links'] ) && is_array( $about['links'] ) ) {
				echo '<p>';
				$out = [];
				foreach ( $about['links'] as $label => $href ) {
					$out[] = '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
				}
				echo wp_kses_post( implode( ' &nbsp;·&nbsp; ', $out ) );
				echo '</p>';
			}

			echo '<p style="margin-top:28px;color:#94A3B8;font-size:12px;">'
				. '<span style="display:inline-block;width:16px;height:16px;border-radius:5px;background:#16A34A;color:#fff;font-size:10px;font-weight:700;line-height:16px;text-align:center;vertical-align:middle;margin-right:6px;">P</span>'
				. esc_html__( 'A PlugPress product', 'inbees' ) . '</p>';
			echo '</div>';
		}

		private function reason_label( string $reason ): string {
			$map = [
				'license_missing'           => __( 'No license', 'inbees' ),
				'license_invalid'           => __( 'Invalid key', 'inbees' ),
				'license_expired'           => __( 'Expired', 'inbees' ),
				'license_disabled'          => __( 'Disabled', 'inbees' ),
				'activation_limit_reached'  => __( 'Site limit reached', 'inbees' ),
				'server_unreachable'        => __( 'Server unreachable', 'inbees' ),
				'inactive'                  => __( 'Inactive', 'inbees' ),
			];
			return $map[ $reason ] ?? $reason;
		}
	}
}
