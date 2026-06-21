<?php
/**
 * PlugPress SDK — Deactivation feedback modal.
 *
 * Intercepts the "Deactivate" link on plugins.php and shows a clean modal.
 * Deactivation is NEVER blocked — "Skip" always works instantly.
 *
 * Feedback is sent only when the user clicks "Submit & Deactivate".
 * Endpoint: telemetry_server config key (separate from the update server).
 * Disabled when telemetry_server is empty.
 *
 * @package PlugPress\SDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PlugPress_Feedback' ) ) {

	class PlugPress_Feedback {

		private string $slug;
		private string $name;
		private string $version;
		private string $plugin_file;
		private string $telemetry_server;
		private string $accent;

		public function __construct( array $config ) {
			$this->slug             = sanitize_key( (string) ( $config['slug']             ?? '' ) );
			$this->name             = (string) ( $config['name']             ?? '' );
			$this->version          = (string) ( $config['version']          ?? '' );
			$this->plugin_file      = plugin_basename( (string) ( $config['file'] ?? '' ) );
			$this->telemetry_server = rtrim( (string) ( $config['telemetry_server'] ?? '' ), '/' );
			$this->accent           = (string) ( $config['accent']           ?? '#2395E7' );

			add_action( 'admin_footer-plugins.php', array( $this, 'render' ) );
			add_action( 'wp_ajax_pp_fb_' . $this->slug, array( $this, 'handle_ajax' ) );
		}

		public function render(): void {
			if ( ! current_user_can( 'deactivate_plugins' ) ) {
				return;
			}

			$reasons = array(
				'not_needed'    => __( 'I no longer need it', 'default' ),
				'better_plugin' => __( 'I found a better plugin', 'default' ),
				'temporary'     => __( 'Temporary deactivation', 'default' ),
				'not_working'   => __( 'It\'s not working as expected', 'default' ),
				'other'         => __( 'Other reason', 'default' ),
			);

			$nonce   = wp_create_nonce( 'pp_fb_' . $this->slug );
			$uid     = esc_attr( $this->slug );
			$accent  = esc_attr( $this->accent );
			$initial = esc_html( strtoupper( substr( $this->name, 0, 1 ) ) );
			?>
			<style>
			#pp-fb-<?php echo $uid; ?>{
				--fb-accent: <?php echo $accent; ?>;
				--fb-accent-bg: color-mix(in srgb, var(--fb-accent) 8%, transparent);
				display: none;
				position: fixed;
				inset: 0;
				background: rgba(15,23,42,.5);
				z-index: 999999;
				align-items: center;
				justify-content: center;
				padding: 16px;
				backdrop-filter: blur(2px);
			}
			#pp-fb-<?php echo $uid; ?>.pp-open { display: flex; }
			#pp-fb-<?php echo $uid; ?> .pp-fb-modal {
				background: #fff;
				border-radius: 14px;
				box-shadow: 0 32px 80px rgba(15,23,42,.2), 0 0 0 1px rgba(15,23,42,.06);
				width: 440px;
				max-width: 100%;
				overflow: hidden;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				animation: pp-fb-in .18s ease;
			}
			@keyframes pp-fb-in {
				from { opacity: 0; transform: translateY(8px) scale(.98); }
				to   { opacity: 1; transform: translateY(0) scale(1); }
			}

			/* Header */
			#pp-fb-<?php echo $uid; ?> .pp-fb-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 18px 20px 0;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-plugin {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-icon {
				width: 32px;
				height: 32px;
				border-radius: 8px;
				background: var(--fb-accent);
				color: #fff;
				font-size: 14px;
				font-weight: 800;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-plugin-name {
				font-size: 13px;
				font-weight: 700;
				color: #0F172A;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-version {
				font-size: 10.5px;
				color: #94A3B8;
				background: #F1F5F9;
				border-radius: 999px;
				padding: 1px 7px;
				font-weight: 600;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-close {
				width: 28px;
				height: 28px;
				border-radius: 7px;
				border: none;
				background: none;
				color: #94A3B8;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 18px;
				line-height: 1;
				transition: background .12s, color .12s;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-close:hover { background: #F1F5F9; color: #0F172A; }

			/* Body */
			#pp-fb-<?php echo $uid; ?> .pp-fb-body { padding: 14px 20px 0; }
			#pp-fb-<?php echo $uid; ?> .pp-fb-title {
				font-size: 15px;
				font-weight: 700;
				color: #0F172A;
				margin: 0 0 3px;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-desc {
				font-size: 12px;
				color: #94A3B8;
				margin: 0 0 14px;
			}

			/* Options */
			#pp-fb-<?php echo $uid; ?> .pp-fb-options { display: flex; flex-direction: column; gap: 4px; }
			#pp-fb-<?php echo $uid; ?> .pp-fb-opt {
				display: flex;
				align-items: center;
				gap: 10px;
				padding: 9px 12px;
				border-radius: 8px;
				border: 1.5px solid #F1F5F9;
				cursor: pointer;
				transition: border-color .12s, background .12s;
				user-select: none;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-opt:hover { border-color: #E2E8F0; background: #F8FAFC; }
			#pp-fb-<?php echo $uid; ?> .pp-fb-opt:has(input:checked) {
				border-color: var(--fb-accent);
				background: var(--fb-accent-bg);
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-opt input {
				width: 15px;
				height: 15px;
				accent-color: var(--fb-accent);
				flex-shrink: 0;
				margin: 0;
				cursor: pointer;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-opt label {
				font-size: 13px;
				color: #334155;
				cursor: pointer;
				line-height: 1.4;
			}

			/* Comment textarea */
			#pp-fb-<?php echo $uid; ?> .pp-fb-comment-wrap {
				display: none;
				margin-top: 8px;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-comment-wrap.pp-show { display: block; }
			#pp-fb-<?php echo $uid; ?> .pp-fb-comment-wrap textarea {
				width: 100%;
				height: 64px;
				border: 1.5px solid #E2E8F0;
				border-radius: 8px;
				padding: 8px 12px;
				font-size: 12.5px;
				font-family: inherit;
				color: #0F172A;
				resize: none;
				box-sizing: border-box;
				outline: none;
				transition: border-color .12s;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-comment-wrap textarea:focus { border-color: var(--fb-accent); }

			/* Footer */
			#pp-fb-<?php echo $uid; ?> .pp-fb-footer {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 16px 20px 18px;
				margin-top: 14px;
				border-top: 1px solid #F1F5F9;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-btn-skip {
				font-size: 12px;
				color: #94A3B8;
				background: none;
				border: none;
				cursor: pointer;
				padding: 0;
				font-family: inherit;
				transition: color .12s;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-btn-skip:hover { color: #475569; }
			#pp-fb-<?php echo $uid; ?> .pp-fb-btn-submit {
				background: var(--fb-accent);
				color: #fff;
				border: none;
				border-radius: 8px;
				height: 34px;
				padding: 0 16px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				font-family: inherit;
				transition: opacity .15s;
			}
			#pp-fb-<?php echo $uid; ?> .pp-fb-btn-submit:hover { opacity: .88; }
			#pp-fb-<?php echo $uid; ?> .pp-fb-btn-submit:disabled { opacity: .4; cursor: default; }
			</style>

			<div id="pp-fb-<?php echo $uid; ?>" role="dialog" aria-modal="true" aria-labelledby="pp-fb-title-<?php echo $uid; ?>">
				<div class="pp-fb-modal">

					<div class="pp-fb-header">
						<div class="pp-fb-plugin">
							<div class="pp-fb-icon"><?php echo $initial; ?></div>
							<span class="pp-fb-plugin-name"><?php echo esc_html( $this->name ); ?></span>
							<?php if ( $this->version ) : ?>
								<span class="pp-fb-version">v<?php echo esc_html( $this->version ); ?></span>
							<?php endif; ?>
						</div>
						<button type="button" class="pp-fb-close" id="pp-fb-close-<?php echo $uid; ?>" aria-label="<?php esc_attr_e( 'Close', 'default' ); ?>">&#x2715;</button>
					</div>

					<div class="pp-fb-body">
						<h2 class="pp-fb-title" id="pp-fb-title-<?php echo $uid; ?>"><?php esc_html_e( 'Before you go…', 'default' ); ?></h2>
						<p class="pp-fb-desc"><?php esc_html_e( 'Why are you deactivating? (optional — takes 10 seconds)', 'default' ); ?></p>

						<div class="pp-fb-options">
							<?php foreach ( $reasons as $val => $label ) : ?>
								<?php $rid = 'pp-fb-r-' . $uid . '-' . $val; ?>
								<label class="pp-fb-opt" for="<?php echo esc_attr( $rid ); ?>">
									<input
										type="radio"
										id="<?php echo esc_attr( $rid ); ?>"
										name="pp_fb_r_<?php echo $uid; ?>"
										value="<?php echo esc_attr( $val ); ?>"
										<?php if ( 'other' === $val ) : ?>
										onchange="document.querySelector('#pp-fb-<?php echo esc_js( $uid ); ?> .pp-fb-comment-wrap').classList.add('pp-show')"
										<?php else : ?>
										onchange="document.querySelector('#pp-fb-<?php echo esc_js( $uid ); ?> .pp-fb-comment-wrap').classList.remove('pp-show')"
										<?php endif; ?>
									>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>

						<div class="pp-fb-comment-wrap">
							<textarea id="pp-fb-comment-<?php echo $uid; ?>" placeholder="<?php esc_attr_e( 'Tell us more (optional)…', 'default' ); ?>"></textarea>
						</div>
					</div>

					<div class="pp-fb-footer">
						<button type="button" class="pp-fb-btn-skip" id="pp-fb-skip-<?php echo $uid; ?>"><?php esc_html_e( 'Skip &amp; Deactivate', 'default' ); ?></button>
						<button type="button" class="pp-fb-btn-submit" id="pp-fb-submit-<?php echo $uid; ?>"><?php esc_html_e( 'Submit &amp; Deactivate', 'default' ); ?></button>
					</div>

				</div>
			</div>

			<script>
			(function(){
				var uid      = <?php echo wp_json_encode( $this->slug ); ?>;
				var pfile    = <?php echo wp_json_encode( $this->plugin_file ); ?>;
				var nonce    = <?php echo wp_json_encode( $nonce ); ?>;
				var ajaxAct  = <?php echo wp_json_encode( 'pp_fb_' . $this->slug ); ?>;
				var overlay  = document.getElementById('pp-fb-' + uid);
				var deactUrl = '';

				function getLink(){
					var row = document.querySelector('tr[data-plugin="' + pfile + '"]');
					if(row){ var a = row.querySelector('a[href*="action=deactivate"]'); if(a) return a; }
					var all = document.querySelectorAll('a[href*="action=deactivate"]');
					for(var i=0;i<all.length;i++){
						var h=all[i].href;
						if(h.indexOf(encodeURIComponent(pfile))!==-1||h.indexOf(pfile)!==-1) return all[i];
					}
					return null;
				}

				var link = getLink();
				if(!link) return;

				link.addEventListener('click',function(e){
					e.preventDefault();
					deactUrl = link.href;
					overlay.classList.add('pp-open');
					document.body.style.overflow='hidden';
				});

				function dismiss(){ document.body.style.overflow=''; window.location.href=deactUrl; }

				function submit(){
					var sel = document.querySelector('input[name="pp_fb_r_'+uid+'"]:checked');
					var comment = (document.getElementById('pp-fb-comment-'+uid)||{}).value||'';
					if(sel){
						var fd=new FormData();
						fd.append('action',ajaxAct); fd.append('nonce',nonce);
						fd.append('reason',sel.value); fd.append('comment',comment);
						if(navigator.sendBeacon) navigator.sendBeacon(ajaxurl,fd);
					}
					dismiss();
				}

				document.getElementById('pp-fb-skip-'+uid).addEventListener('click',dismiss);
				document.getElementById('pp-fb-close-'+uid).addEventListener('click',dismiss);
				document.getElementById('pp-fb-submit-'+uid).addEventListener('click',function(){ this.disabled=true; submit(); });
				overlay.addEventListener('click',function(e){ if(e.target===overlay) dismiss(); });
				document.addEventListener('keydown',function(e){ if(overlay.classList.contains('pp-open')&&(e.key==='Escape'||e.keyCode===27)) dismiss(); });
			})();
			</script>
			<?php
		}

		public function handle_ajax(): void {
			check_ajax_referer( 'pp_fb_' . $this->slug, 'nonce' );

			$reason  = sanitize_text_field( wp_unslash( $_POST['reason']  ?? '' ) );
			$comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

			if ( $reason && $this->telemetry_server ) {
				wp_remote_post(
					$this->telemetry_server . '/v1/feedback',
					array(
						'timeout'  => 5,
						'blocking' => false,
						'body'     => array(
							'site_url' => home_url(),
							'slug'     => $this->slug,
							'version'  => $this->version,
							'reason'   => $reason,
							'comment'  => $comment,
						),
					)
				);
			}

			wp_send_json_success();
		}
	}
}
