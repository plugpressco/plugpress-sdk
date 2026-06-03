<?php
/**
 * PlugPress SDK — Admin notices.
 *
 * A small, reusable notice creator every PlugPress plugin can use:
 *
 *   $notices = PlugPress_SDK::init( $cfg )->notices();
 *
 *   // one-time "flash" notice — shown on the next admin load, then cleared
 *   $notices->flash( __( 'Settings saved.', 'your-textdomain' ), 'success' );
 *
 *   // sticky, dismissible notice — keeps showing until THIS user dismisses it
 *   $notices->persistent(
 *       'activate-license',
 *       __( 'Enter your license key to receive updates.', 'your-textdomain' ),
 *       'warning'
 *   );
 *
 * Flash notices survive the post/redirect/get cycle (stored in a short-lived
 * per-user transient). Persistent notices render on every admin page until the
 * current user clicks "Dismiss" (stored in user meta, nonce-protected).
 *
 * @package PlugPress\SDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PlugPress_Notices' ) ) {

	class PlugPress_Notices {

		private const TYPES = [ 'success', 'error', 'warning', 'info' ];

		private string $slug;

		public function __construct( string $slug ) {
			$this->slug = sanitize_key( $slug );
			add_action( 'admin_notices', [ $this, 'render_flash' ] );
			add_action( 'admin_init', [ $this, 'handle_dismiss' ] );
		}

		/** Queue a one-time notice for the next admin page load. */
		public function flash( string $message, string $type = 'success' ): void {
			$items   = get_transient( $this->flash_key() );
			$items   = is_array( $items ) ? $items : [];
			$items[] = [ 'type' => $this->normalize_type( $type ), 'message' => $message ];
			set_transient( $this->flash_key(), $items, MINUTE_IN_SECONDS );
		}

		/**
		 * Render a sticky dismissible notice now, unless the current user has
		 * dismissed this $id before. Call it whenever the condition holds.
		 */
		public function persistent( string $id, string $message, string $type = 'info' ): void {
			$id = sanitize_key( $id );
			if ( '' === $id || $this->is_dismissed( $id ) ) {
				return;
			}
			$this->print_notice( $this->normalize_type( $type ), $message, $id );
		}

		/** admin_notices: flush any queued flash notices, then clear them. */
		public function render_flash(): void {
			$items = get_transient( $this->flash_key() );
			if ( empty( $items ) || ! is_array( $items ) ) {
				return;
			}
			delete_transient( $this->flash_key() );
			foreach ( $items as $n ) {
				$this->print_notice( $n['type'] ?? 'info', (string) ( $n['message'] ?? '' ) );
			}
		}

		/** admin_init: process a "Dismiss" click for a persistent notice. */
		public function handle_dismiss(): void {
			if ( empty( $_GET['pp_dismiss'] ) || ( $_GET['pp_slug'] ?? '' ) !== $this->slug ) {
				return;
			}
			$id    = sanitize_key( wp_unslash( $_GET['pp_dismiss'] ) );
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! $id || ! wp_verify_nonce( $nonce, $this->nonce_action( $id ) ) ) {
				return;
			}
			$this->mark_dismissed( $id );
			wp_safe_redirect( remove_query_arg( [ 'pp_dismiss', 'pp_slug', '_wpnonce' ] ) );
			exit;
		}

		// ── internals ─────────────────────────────────────────────────────────

		private function print_notice( string $type, string $message, string $dismiss_id = '' ): void {
			$dismiss = '';
			if ( '' !== $dismiss_id ) {
				$url = wp_nonce_url(
					add_query_arg( [ 'pp_dismiss' => $dismiss_id, 'pp_slug' => $this->slug ] ),
					$this->nonce_action( $dismiss_id )
				);
				$dismiss = ' &nbsp;<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dismiss', 'default' ) . '</a>';
			}
			// $dismiss is pre-escaped; $message allows safe inline HTML (links/bold).
			printf(
				'<div class="notice notice-%1$s"><p>%2$s%3$s</p></div>',
				esc_attr( $type ),
				wp_kses_post( $message ),
				$dismiss // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		private function normalize_type( string $type ): string {
			return in_array( $type, self::TYPES, true ) ? $type : 'info';
		}

		private function flash_key(): string {
			return 'pp_notices_' . $this->slug . '_' . get_current_user_id();
		}

		private function nonce_action( string $id ): string {
			return 'pp_dismiss_' . $this->slug . '_' . $id;
		}

		private function dismissed_meta(): string {
			return 'pp_dismissed_' . $this->slug;
		}

		private function is_dismissed( string $id ): bool {
			$dismissed = (array) get_user_meta( get_current_user_id(), $this->dismissed_meta(), true );
			return in_array( $id, $dismissed, true );
		}

		private function mark_dismissed( string $id ): void {
			$dismissed = (array) get_user_meta( get_current_user_id(), $this->dismissed_meta(), true );
			if ( ! in_array( $id, $dismissed, true ) ) {
				$dismissed[] = $id;
				update_user_meta( get_current_user_id(), $this->dismissed_meta(), $dismissed );
			}
		}
	}
}
