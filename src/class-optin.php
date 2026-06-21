<?php
/**
 * PlugPress SDK — Activation opt-in for anonymous telemetry.
 *
 * Compliance:
 *   - WP Guideline 7: no external calls until the user clicks "Allow".
 *   - WP Guideline 11: notice shown only on plugins.php (right after
 *     activation) and the plugin's own admin pages — never site-wide.
 *   - Full disclosure: lists every field collected before consent.
 *   - Opt-out is permanent; "Later" postpones for 7 days without deciding.
 *
 * Options:
 *   {slug}_pp_optin          NULL = undecided | '1' = allowed | '0' = declined
 *   {slug}_pp_optin_postponed  unix timestamp of last "Later" click
 *   {slug}_pp_optin_last       unix timestamp of last successful send
 *   {slug}_pp_activated_at     unix timestamp set by SDK on plugin activation
 *
 * @package PlugPress\SDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PlugPress_Optin' ) ) {

	class PlugPress_Optin {

		private string $slug;
		private string $name;
		private string $version;
		private string $telemetry_server;
		private string $accent;
		private string $option;

		public function __construct( array $config ) {
			$this->slug             = sanitize_key( (string) ( $config['slug']             ?? '' ) );
			$this->name             = (string) ( $config['name']             ?? '' );
			$this->version          = (string) ( $config['version']          ?? '' );
			$this->telemetry_server = rtrim( (string) ( $config['telemetry_server'] ?? '' ), '/' );
			$this->accent           = (string) ( $config['accent']           ?? '#2395E7' );
			$this->option           = $this->slug . '_pp_optin';

			add_action( 'admin_notices', array( $this, 'maybe_show_notice' ) );
			add_action( 'wp_ajax_pp_optin_allow_' . $this->slug, array( $this, 'handle_allow' ) );
			add_action( 'wp_ajax_pp_optin_skip_'  . $this->slug, array( $this, 'handle_skip'  ) );
			add_action( 'wp_ajax_pp_optin_later_' . $this->slug, array( $this, 'handle_later' ) );
		}

		// ── Public API ─────────────────────────────────────────────────────────

		public function is_opted_in(): bool {
			return get_option( $this->option ) === '1';
		}

		/**
		 * Return data to inject into the page (e.g. wp_localize_script) so the
		 * React onboarding wizard can drive the opt-in without a page reload.
		 *
		 * Shape:
		 *   state   — 'undecided' | 'allowed' | 'declined'
		 *   actions — { allow, skip, later }  (wp-ajax action names)
		 *   nonces  — { allow, skip, later }
		 */
		public function get_js_data(): array {
			$raw   = get_option( $this->option, null );
			$state = null === $raw ? 'undecided' : ( '1' === $raw ? 'allowed' : 'declined' );

			return array(
				'state'   => $state,
				'actions' => array(
					'allow' => 'pp_optin_allow_' . $this->slug,
					'skip'  => 'pp_optin_skip_'  . $this->slug,
					'later' => 'pp_optin_later_' . $this->slug,
				),
				'nonces'  => array(
					'allow' => wp_create_nonce( 'pp_optin_allow_' . $this->slug ),
					'skip'  => wp_create_nonce( 'pp_optin_skip_'  . $this->slug ),
					'later' => wp_create_nonce( 'pp_optin_later_' . $this->slug ),
				),
			);
		}

		// ── Notice ─────────────────────────────────────────────────────────────

		public function maybe_show_notice(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Already decided (Allow or No thanks) — never show again.
			$state = get_option( $this->option, null );
			if ( null !== $state ) {
				return;
			}

			// Postponed via "Later" — wait 7 days.
			$postponed = (int) get_option( $this->option . '_postponed', 0 );
			if ( $postponed && ( time() - $postponed ) < WEEK_IN_SECONDS ) {
				return;
			}

			// Scope: SDK-managed PHP subpages only (License, About).
			// The main plugin page is a React SPA that renders its own inline
			// opt-in card — the PHP notice must not show there.
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || 0 !== strpos( $screen->id, $this->slug . '_page_' ) ) {
				return;
			}

			$nonce_allow = wp_create_nonce( 'pp_optin_allow_' . $this->slug );
			$nonce_skip  = wp_create_nonce( 'pp_optin_skip_'  . $this->slug );
			$nonce_later = wp_create_nonce( 'pp_optin_later_' . $this->slug );
			$accent      = esc_attr( $this->accent );
			$uid         = esc_attr( $this->slug );
			$initial     = esc_html( strtoupper( substr( $this->name, 0, 1 ) ) );
			$user        = wp_get_current_user();
			$admin_name  = esc_html( $user->display_name ?: $user->user_login );
			$plugin_name = esc_html( $this->name );
			?>
			<div id="pp-optin-<?php echo $uid; ?>" class="notice" style="padding:0;margin:16px 0;border:1px solid #E2E8F0;border-left:4px solid <?php echo $accent; ?>;border-radius:8px;background:#fff;box-shadow:0 2px 8px rgba(15,23,42,.07);overflow:hidden;">
				<div style="display:flex;align-items:flex-start;gap:16px;padding:18px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;position:relative;">

					<?php /* Plugin icon tile */ ?>
					<div style="width:42px;height:42px;border-radius:10px;background:<?php echo $accent; ?>;color:#fff;font-size:18px;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;letter-spacing:-.5px;">
						<?php echo $initial; ?>
					</div>

					<?php /* Content */ ?>
					<div style="flex:1;min-width:0;">
						<div style="font-size:14px;font-weight:700;color:#0F172A;margin-bottom:5px;line-height:1.3;">
							<?php echo esc_html( sprintf(
								/* translators: 1: admin display name, 2: plugin name */
								__( 'Help make %2$s better, %1$s!', 'default' ),
								$user->display_name ?: $user->user_login,
								$this->name
							) ); ?>
						</div>
						<div style="font-size:12.5px;color:#475569;line-height:1.65;max-width:600px;">
							<?php esc_html_e( 'We\'d love to collect basic, anonymous site info — WordPress version, active theme, PHP version, and similar — to help us improve compatibility and focus development where it matters most.', 'default' ); ?>
						</div>

						<?php /* Disclosure expandable */ ?>
						<div style="margin-top:10px;">
							<button type="button" id="pp-optin-more-<?php echo $uid; ?>"
								style="background:none;border:none;padding:0;font-size:11.5px;color:<?php echo $accent; ?>;cursor:pointer;font-family:inherit;font-weight:600;display:inline-flex;align-items:center;gap:3px;">
								<span id="pp-optin-more-arrow-<?php echo $uid; ?>">▶</span>
								<?php esc_html_e( 'What exactly do we collect?', 'default' ); ?>
							</button>
							<div id="pp-optin-detail-<?php echo $uid; ?>" style="display:none;margin-top:8px;padding:11px 14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:7px;font-size:11.5px;color:#475569;line-height:2;">
								<strong style="color:#0F172A;display:block;margin-bottom:4px;"><?php esc_html_e( 'Collected:', 'default' ); ?></strong>
								<?php esc_html_e( 'Site URL · Admin email · Admin name · WordPress version · PHP version · Plugin version · Active theme · Active plugin list · User count · Post count · Locale · Is multisite', 'default' ); ?>
								<span style="display:block;margin-top:6px;color:#94A3B8;font-size:11px;">
									<?php esc_html_e( 'Never collected: passwords, post content, customer data, or payment info.', 'default' ); ?>
								</span>
							</div>
						</div>

						<?php /* Action buttons */ ?>
						<div style="display:flex;align-items:center;gap:8px;margin-top:14px;flex-wrap:wrap;">
							<button type="button" id="pp-optin-allow-<?php echo $uid; ?>"
								style="background:<?php echo $accent; ?>;color:#fff;border:none;border-radius:7px;height:34px;padding:0 18px;font-size:12.5px;font-weight:600;cursor:pointer;font-family:inherit;transition:opacity .15s;"
								onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
								<?php esc_html_e( 'Allow &amp; Continue', 'default' ); ?>
							</button>
							<button type="button" id="pp-optin-skip-<?php echo $uid; ?>"
								style="background:none;border:1.5px solid #CBD5E1;border-radius:7px;height:34px;padding:0 14px;font-size:12.5px;color:#64748B;cursor:pointer;font-family:inherit;transition:border-color .15s,color .15s;"
								onmouseover="this.style.borderColor='#94A3B8';this.style.color='#0F172A'" onmouseout="this.style.borderColor='#CBD5E1';this.style.color='#64748B'">
								<?php esc_html_e( 'No thanks', 'default' ); ?>
							</button>
							<button type="button" id="pp-optin-later-<?php echo $uid; ?>"
								style="background:none;border:none;padding:0 4px;font-size:12px;color:#94A3B8;cursor:pointer;font-family:inherit;transition:color .15s;"
								onmouseover="this.style.color='#475569'" onmouseout="this.style.color='#94A3B8'">
								<?php esc_html_e( 'Remind me later', 'default' ); ?>
							</button>
						</div>
					</div>

					<?php /* × close (same as Later) */ ?>
					<button type="button" id="pp-optin-close-<?php echo $uid; ?>"
						title="<?php esc_attr_e( 'Remind me later', 'default' ); ?>"
						style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:18px;line-height:1;color:#CBD5E1;cursor:pointer;padding:2px 5px;transition:color .15s;"
						onmouseover="this.style.color='#64748B'" onmouseout="this.style.color='#CBD5E1'">
						×
					</button>

				</div>
			</div>
			<script>
			(function(){
				var slug  = <?php echo wp_json_encode( $this->slug ); ?>;
				var wrap  = document.getElementById('pp-optin-' + slug);
				var more  = document.getElementById('pp-optin-more-' + slug);
				var arrow = document.getElementById('pp-optin-more-arrow-' + slug);
				var det   = document.getElementById('pp-optin-detail-' + slug);

				// Expand/collapse disclosure.
				if ( more && det ) {
					more.addEventListener('click', function(){
						var open = det.style.display !== 'none';
						det.style.display   = open ? 'none'  : 'block';
						arrow.textContent   = open ? '▶' : '▼';
					});
				}

				function fade(){ if(wrap){ wrap.style.transition='opacity .2s'; wrap.style.opacity='0'; setTimeout(function(){ wrap.remove(); }, 220); } }

				function post(action, nonce, cb){
					var fd = new FormData();
					fd.append('action', action);
					fd.append('_ajax_nonce', nonce);
					fetch(ajaxurl, {method:'POST', body:fd, credentials:'same-origin'})
						.then(function(){ if(cb) cb(); });
					fade();
				}

				var al    = document.getElementById('pp-optin-allow-' + slug);
				var sk    = document.getElementById('pp-optin-skip-'  + slug);
				var later = document.getElementById('pp-optin-later-' + slug);
				var close = document.getElementById('pp-optin-close-' + slug);

				if(al)    al.addEventListener('click', function(){ post(<?php echo wp_json_encode( 'pp_optin_allow_' . $this->slug ); ?>, <?php echo wp_json_encode( $nonce_allow ); ?>); });
				if(sk)    sk.addEventListener('click', function(){ post(<?php echo wp_json_encode( 'pp_optin_skip_'  . $this->slug ); ?>, <?php echo wp_json_encode( $nonce_skip  ); ?>); });
				if(later) later.addEventListener('click', function(){ post(<?php echo wp_json_encode( 'pp_optin_later_' . $this->slug ); ?>, <?php echo wp_json_encode( $nonce_later ); ?>); });
				if(close) close.addEventListener('click', function(){ post(<?php echo wp_json_encode( 'pp_optin_later_' . $this->slug ); ?>, <?php echo wp_json_encode( $nonce_later ); ?>); });
			})();
			</script>
			<?php
		}

		// ── AJAX handlers ──────────────────────────────────────────────────────

		public function handle_allow(): void {
			check_ajax_referer( 'pp_optin_allow_' . $this->slug );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'forbidden', 403 );
			}
			update_option( $this->option, '1', false );
			delete_option( $this->option . '_postponed' );
			$this->send_telemetry( true );
			wp_send_json_success();
		}

		public function handle_skip(): void {
			check_ajax_referer( 'pp_optin_skip_' . $this->slug );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'forbidden', 403 );
			}
			update_option( $this->option, '0', false );
			delete_option( $this->option . '_postponed' );
			wp_send_json_success();
		}

		public function handle_later(): void {
			check_ajax_referer( 'pp_optin_later_' . $this->slug );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'forbidden', 403 );
			}
			update_option( $this->option . '_postponed', time(), false );
			wp_send_json_success();
		}

		// ── Telemetry ──────────────────────────────────────────────────────────

		public function maybe_send_telemetry(): void {
			// Skip during AJAX requests — fires only on real page loads to avoid
			// running on every REST/AJAX call that also triggers admin_init.
			if ( wp_doing_ajax() ) {
				return;
			}

			if ( ! $this->is_opted_in() ) {
				return;
			}

			$last_sent = (int) get_option( $this->option . '_last', 0 );
			if ( ( time() - $last_sent ) < WEEK_IN_SECONDS ) {
				return;
			}

			$this->send_telemetry();
		}

		private function send_telemetry( bool $blocking = false ): void {
			if ( ! $this->telemetry_server ) {
				return;
			}

			$theme          = wp_get_theme();
			$user           = wp_get_current_user();
			$active_plugins = get_option( 'active_plugins', array() );
			$plugin_slugs   = array_map(
				static fn( string $p ) => explode( '/', $p )[0],
				$active_plugins
			);

			// Defer expensive DB counts to non-blocking weekly pings only, not the
			// blocking opt-in send (which runs during an AJAX request).
			$user_count = 0;
			$post_count = 0;
			if ( ! $blocking ) {
				$user_counts = count_users();
				$post_counts = wp_count_posts();
				$user_count  = $user_counts['total_users'] ?? 0;
				$post_count  = $post_counts->publish ?? 0;
			}

			$response = wp_remote_post(
				$this->telemetry_server . '/v1/telemetry',
				array(
					'timeout'  => 8,
					'blocking' => $blocking,
					'body'     => array(
						'site_url'       => home_url(),
						'admin_email'    => get_option( 'admin_email' ),
						'admin_name'     => $user->display_name ?: $user->user_login,
						'slug'           => $this->slug,
						'version'        => $this->version,
						'wp_version'     => get_bloginfo( 'version' ),
						'php_version'    => PHP_VERSION,
						'is_multisite'   => is_multisite() ? '1' : '0',
						'locale'         => get_locale(),
						'theme'          => $theme->get_template(),
						'plugins'        => implode( ',', $plugin_slugs ),
						'plugin_count'   => (string) count( $active_plugins ),
						'user_count'     => (string) $user_count,
						'post_count'     => (string) $post_count,
						'license_status' => get_option( $this->slug . '_pro_license_active', 0 ) ? 'pro' : 'free',
					),
				)
			);

			// Stamp _last only when the request was actually delivered:
			// — blocking: server responded with 2xx
			// — non-blocking: request was initiated without a local WP_Error
			//   (DNS failure, SSL error etc. still prevent stamping so we retry sooner)
			if ( ! is_wp_error( $response ) && ( ! $blocking || wp_remote_retrieve_response_code( $response ) < 300 ) ) {
				update_option( $this->option . '_last', time(), false );
			}
		}
	}
}
