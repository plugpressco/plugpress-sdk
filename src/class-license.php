<?php
/**
 * PlugPress SDK — License component.
 *
 * Lite client for the PlugPress Updates worker's license endpoints. Stores the
 * key in an option, validates/activates/deactivates against the worker, and
 * caches the last known status so the SDK (and the updater) can read it cheaply.
 *
 * @package PlugPress\SDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PlugPress_License' ) ) {

	class PlugPress_License {

		private string $slug;
		private string $server;
		private string $option;       // where the key is stored
		private string $status_key;   // transient for cached status

		public function __construct( array $config ) {
			$this->slug       = (string) ( $config['slug'] ?? '' );
			$this->server     = rtrim( (string) ( $config['server'] ?? '' ), '/' );
			$this->option     = (string) ( $config['option'] ?? ( $this->slug . '_license_key' ) );
			$this->status_key = 'pp_lic_' . md5( $this->slug . '|' . $this->server );
		}

		public function get_key(): string {
			return trim( (string) get_option( $this->option, '' ) );
		}

		public function set_key( string $key ): void {
			update_option( $this->option, sanitize_text_field( $key ) );
			delete_transient( $this->status_key );
		}

		/** Cached status object: { valid, reason?, status?, expires_at?, ... }. */
		public function status(): object {
			$cached = get_transient( $this->status_key );
			if ( is_object( $cached ) ) {
				return $cached;
			}

			$key = $this->get_key();
			if ( '' === $key ) {
				return (object) [ 'valid' => false, 'reason' => 'license_missing' ];
			}

			$res    = $this->request( 'GET', '/v1/license/check', [
				'slug'    => $this->slug,
				'license' => $key,
				'site'    => home_url(),
			] );
			$status = is_object( $res ) ? $res : (object) [ 'valid' => false, 'reason' => 'server_unreachable' ];

			set_transient( $this->status_key, $status, 6 * HOUR_IN_SECONDS );
			return $status;
		}

		public function is_valid(): bool {
			return ! empty( $this->status()->valid );
		}

		public function activate(): object {
			$res = $this->request( 'POST', '/v1/license/activate', [
				'slug'    => $this->slug,
				'license' => $this->get_key(),
				'site'    => home_url(),
			] );
			delete_transient( $this->status_key );
			return is_object( $res ) ? $res : (object) [ 'error' => 'server_unreachable' ];
		}

		public function deactivate(): object {
			$res = $this->request( 'POST', '/v1/license/deactivate', [
				'slug'    => $this->slug,
				'license' => $this->get_key(),
				'site'    => home_url(),
			] );
			delete_transient( $this->status_key );
			return is_object( $res ) ? $res : (object) [ 'error' => 'server_unreachable' ];
		}

		private function request( string $method, string $path, array $data ) {
			$url  = $this->server . $path;
			$args = [ 'timeout' => 15, 'headers' => [ 'Accept' => 'application/json' ] ];

			if ( 'GET' === $method ) {
				$resp = wp_remote_get( add_query_arg( array_filter( $data ), $url ), $args );
			} else {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $data );
				$resp                            = wp_remote_post( $url, $args );
			}

			if ( is_wp_error( $resp ) ) {
				return null;
			}
			return json_decode( wp_remote_retrieve_body( $resp ) );
		}
	}
}
