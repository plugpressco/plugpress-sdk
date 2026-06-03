<?php
/**
 * PlugPress SDK — Updater component.
 *
 * Wires WordPress's native update UI to the PlugPress Updates worker so any
 * product updates from wp-admin. Free products send no license; pro products
 * pass a `license` callback (the SDK supplies one from the License component).
 *
 * @package PlugPress\SDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PlugPress_Updater' ) ) {

	class PlugPress_Updater {

		private string $slug;
		private string $plugin_file;
		private string $version;
		private string $server;
		/** @var callable|null */
		private $license_cb;
		private string $cache_key;

		public function __construct( array $config ) {
			$this->slug        = (string) ( $config['slug'] ?? '' );
			$this->plugin_file = (string) ( $config['plugin_file'] ?? '' );
			$this->version     = (string) ( $config['version'] ?? '0.0.0' );
			$this->server      = rtrim( (string) ( $config['server'] ?? '' ), '/' );
			$this->license_cb  = $config['license'] ?? null;
			$this->cache_key   = 'pp_upd_' . md5( $this->slug . '|' . $this->server );

			if ( ! $this->slug || ! $this->plugin_file || ! $this->server ) {
				return;
			}

			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
			add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
			add_action( 'upgrader_process_complete', [ $this, 'flush_cache' ], 10, 0 );
			add_action( 'load-plugins.php', [ $this, 'maybe_force_check' ] );
		}

		private function license(): string {
			return is_callable( $this->license_cb ) ? (string) call_user_func( $this->license_cb ) : '';
		}

		private function remote(): ?object {
			$cached = get_transient( $this->cache_key );
			if ( false !== $cached ) {
				return is_object( $cached ) ? $cached : null;
			}

			$url = add_query_arg(
				array_filter( [
					'slug'    => $this->slug,
					'version' => $this->version,
					'license' => $this->license(),
					'site'    => home_url(),
				] ),
				$this->server . '/v1/update'
			);

			$response = wp_remote_get( $url, [ 'timeout' => 15, 'headers' => [ 'Accept' => 'application/json' ] ] );

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				set_transient( $this->cache_key, '', 2 * HOUR_IN_SECONDS );
				return null;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ) );
			set_transient( $this->cache_key, $data ?: '', 12 * HOUR_IN_SECONDS );

			return is_object( $data ) ? $data : null;
		}

		public function inject_update( $transient ) {
			if ( empty( $transient ) || empty( $transient->checked ) ) {
				return $transient;
			}

			$info = $this->remote();
			if ( ! $info || empty( $info->version ) ) {
				return $transient;
			}

			$item = (object) [
				'id'           => $this->plugin_file,
				'slug'         => $this->slug,
				'plugin'       => $this->plugin_file,
				'new_version'  => (string) $info->version,
				'url'          => $info->homepage ?? '',
				'package'      => $info->package ?? '',
				'tested'       => $info->tested ?? '',
				'requires'     => $info->requires ?? '',
				'requires_php' => $info->requires_php ?? '',
				'icons'        => isset( $info->icons ) ? (array) $info->icons : [],
				'banners'      => isset( $info->banners ) ? (array) $info->banners : [],
			];

			if ( version_compare( $this->version, (string) $info->version, '<' ) && ! empty( $info->package ) ) {
				$transient->response[ $this->plugin_file ] = $item;
			} else {
				unset( $item->package );
				$transient->no_update[ $this->plugin_file ] = $item;
			}

			return $transient;
		}

		public function plugin_info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
				return $result;
			}

			$info = $this->remote();
			if ( ! $info ) {
				return $result;
			}

			return (object) [
				'name'          => $info->name ?? $this->slug,
				'slug'          => $this->slug,
				'version'       => $info->version ?? $this->version,
				'author'        => $info->author ?? '',
				'homepage'      => $info->homepage ?? '',
				'requires'      => $info->requires ?? '',
				'tested'        => $info->tested ?? '',
				'requires_php'  => $info->requires_php ?? '',
				'download_link' => $info->package ?? '',
				'sections'      => [
					'description' => $info->description ?? '',
					'changelog'   => $info->changelog ?? '',
				],
				'banners'       => isset( $info->banners ) ? (array) $info->banners : [],
				'icons'         => isset( $info->icons ) ? (array) $info->icons : [],
			];
		}

		public function flush_cache(): void {
			delete_transient( $this->cache_key );
		}

		public function maybe_force_check(): void {
			if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->flush_cache();
			}
		}
	}
}
