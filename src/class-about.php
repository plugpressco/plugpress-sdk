<?php
/**
 * PlugPress SDK — About page renderer.
 *
 * All dynamic values (accent colour) are passed through a single CSS custom
 * property (--pp-accent) set on the page container. No inline style attributes
 * are used on individual elements — every visual rule lives in the <style> block
 * output by render_css(), scoped under #pp-about-page.
 *
 * @package PlugPress\SDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PlugPress_About' ) ) {

	class PlugPress_About {

		/**
		 * Render the full About page.
		 *
		 * @param array $cfg  SDK config (slug, name, version, accent, about).
		 */
		public static function render( array $cfg ): void {
			$slug    = (string) ( $cfg['slug']    ?? '' );
			$name    = (string) ( $cfg['name']    ?? '' );
			$version = (string) ( $cfg['version'] ?? '' );
			$accent  = (string) ( $cfg['accent']  ?? '#2395E7' );
			$about   = $cfg['about'] ?? array();
			$tagline = (string) ( $about['tagline'] ?? '' );
			$links   = is_array( $about['links'] ?? null ) ? $about['links'] : array();
			$is_divi = self::is_divi_active();

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins = get_plugins();
			$products    = self::pp_products();

			// Output scoped CSS (single <style> block, no inline style attrs).
			self::render_css( $accent );
			?>
			<div class="wrap" id="pp-about-page">
			<div class="pp-about-container">

				<?php /* ── Hero ──────────────────────────────────────────── */ ?>
				<div class="pp-about-hero">
					<div class="pp-about-hero-icon"><?php echo esc_html( strtoupper( substr( $name, 0, 1 ) ) ); ?></div>
					<div class="pp-about-hero-body">
						<div class="pp-about-hero-name">
							<?php echo esc_html( $name ); ?>
							<span class="pp-about-version">v<?php echo esc_html( $version ); ?></span>
						</div>
						<?php if ( $tagline ) : ?>
							<div class="pp-about-hero-tagline"><?php echo esc_html( $tagline ); ?></div>
						<?php endif; ?>
						<?php if ( $links ) : ?>
							<div class="pp-about-hero-links">
								<?php foreach ( $links as $label => $url ) : ?>
									<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="pp-about-link">
										<?php echo esc_html( $label ); ?> &rarr;
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<?php /* ── About PlugPress + Founder ────────────────────── */ ?>
				<div class="pp-about-row">

					<div class="pp-about-card">
						<h3 class="pp-about-card-title"><?php esc_html_e( 'About PlugPress', 'default' ); ?></h3>
						<p><?php esc_html_e( 'PlugPress builds focused, well-crafted WordPress tools for people who send email, manage conversations, and grow businesses online. We care about simplicity, reliability, and doing one thing really well.', 'default' ); ?></p>
						<p><?php esc_html_e( 'Every PlugPress product stays WordPress-native — no forced SaaS, no opaque black boxes.', 'default' ); ?></p>
						<div class="pp-about-company-links">
							<a href="https://plugpress.co" target="_blank" rel="noopener noreferrer" class="pp-about-ext-link">
								<?php echo self::icon_external(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								plugpress.co
							</a>
							<a href="https://x.com/plugpreesco" target="_blank" rel="noopener noreferrer" class="pp-about-ext-link">
								<?php echo self::icon_x(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								@plugpreesco
							</a>
						</div>
					</div>

					<div class="pp-about-card">
						<div class="pp-about-founder">
							<img
								src="https://github.com/ifahimreza.png"
								alt="Fahim Reza"
								class="pp-about-founder-img"
								width="64"
								height="64"
								loading="lazy"
							>
							<div class="pp-about-founder-body">
								<div class="pp-about-founder-name">Fahim Reza</div>
								<div class="pp-about-founder-role"><?php esc_html_e( 'Founder &amp; Developer, PlugPress', 'default' ); ?></div>
								<p class="pp-about-founder-bio"><?php esc_html_e( 'Building WordPress tools since 2016. I believe every plugin should do one thing well, ship without bloat, and feel native to WordPress.', 'default' ); ?></p>
								<div class="pp-about-founder-links">
									<a href="https://x.com/ifahimreza" target="_blank" rel="noopener noreferrer" class="pp-about-social-link" title="X / Twitter">
										<?php echo self::icon_x(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									</a>
									<a href="https://github.com/ifahimreza" target="_blank" rel="noopener noreferrer" class="pp-about-social-link" title="GitHub">
										<?php echo self::icon_github(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									</a>
									<a href="https://linkedin.com/in/ifahimreza" target="_blank" rel="noopener noreferrer" class="pp-about-social-link" title="LinkedIn">
										<?php echo self::icon_linkedin(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									</a>
								</div>
							</div>
						</div>
					</div>

				</div>

				<?php /* ── Divi suggestion banner ───────────────────────── */ ?>
				<?php if ( $is_divi ) : ?>
					<div class="pp-about-divi-banner">
						<div class="pp-about-divi-text">
							<strong><?php esc_html_e( 'You\'re using Divi', 'default' ); ?></strong>
							<span><?php esc_html_e( 'These PlugPress plugins are built specifically for the Divi Builder.', 'default' ); ?></span>
						</div>
						<div class="pp-about-divi-actions">
							<?php foreach ( $products as $p ) : ?>
								<?php if ( empty( $p['divi'] ) ) continue; ?>
								<div class="pp-about-divi-item">
									<span class="pp-about-divi-pname"><?php echo esc_html( $p['name'] ); ?></span>
									<?php echo self::action_button( $p, self::plugin_status( $p, $all_plugins ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php /* ── Products grid ─────────────────────────────────── */ ?>
				<h2 class="pp-about-section-title"><?php esc_html_e( 'PlugPress Products', 'default' ); ?></h2>
				<div class="pp-about-grid">
					<?php foreach ( $products as $p ) : ?>
						<?php self::render_product_card( $p, $all_plugins, $slug ); ?>
					<?php endforeach; ?>
				</div>

			</div><?php /* .pp-about-container */ ?>
			</div><?php /* .wrap */ ?>
			<?php
		}

		// ── Sub-renderers ──────────────────────────────────────────────────

		private static function render_product_card( array $p, array $all_plugins, string $current_slug ): void {
			$status     = self::plugin_status( $p, $all_plugins );
			$is_current = $p['slug'] === $current_slug;
			$color_cls  = 'pp-icon-' . esc_attr( $p['color_key'] ?? 'blue' );
			?>
			<div class="pp-about-card pp-about-product-card<?php echo $is_current ? ' pp-about-card-current' : ''; ?>">
				<div class="pp-about-product-icon <?php echo $color_cls; ?>">
					<?php echo esc_html( strtoupper( substr( $p['name'], 0, 1 ) ) ); ?>
				</div>
				<div class="pp-about-product-body">
					<div class="pp-about-product-header">
						<span class="pp-about-product-name"><?php echo esc_html( $p['name'] ); ?></span>
						<?php if ( ! empty( $p['divi'] ) ) : ?>
							<span class="pp-about-badge pp-about-badge-divi"><?php esc_html_e( 'Divi', 'default' ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $p['free'] ) ) : ?>
							<span class="pp-about-badge pp-about-badge-free"><?php esc_html_e( 'Free', 'default' ); ?></span>
						<?php endif; ?>
						<?php if ( $is_current ) : ?>
							<span class="pp-about-badge pp-about-badge-current"><?php esc_html_e( 'Active', 'default' ); ?></span>
						<?php endif; ?>
					</div>
					<p class="pp-about-product-tagline"><?php echo esc_html( $p['tagline'] ); ?></p>
					<div class="pp-about-product-foot">
						<?php echo self::action_button( $p, $status ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php if ( ! empty( $p['url'] ) ) : ?>
							<a href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="pp-about-text-link">
								<?php esc_html_e( 'Learn more', 'default' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
		}

		private static function plugin_status( array $p, array $all_plugins ): string {
			if ( empty( $p['file'] ) ) {
				return 'external';
			}
			if ( is_plugin_active( $p['file'] ) ) {
				return 'active';
			}
			foreach ( array_keys( $all_plugins ) as $installed ) {
				if ( $installed === $p['file'] || 0 === strpos( $installed, dirname( $p['file'] ) . '/' ) ) {
					return 'inactive';
				}
			}
			return 'not_installed';
		}

		private static function action_button( array $p, string $status ): string {
			if ( 'active' === $status ) {
				return '<span class="pp-about-status-active">&#10003; ' . esc_html__( 'Active', 'default' ) . '</span>';
			}

			if ( 'external' === $status || empty( $p['free'] ) ) {
				return '<a href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener noreferrer" class="pp-about-btn pp-about-btn-outline">'
					. esc_html__( 'Get it', 'default' ) . '</a>';
			}

			if ( 'inactive' === $status && ! empty( $p['file'] ) ) {
				$url = wp_nonce_url(
					admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $p['file'] ) ),
					'activate-plugin_' . $p['file']
				);
				return '<a href="' . esc_url( $url ) . '" class="pp-about-btn pp-about-btn-accent">'
					. esc_html__( 'Activate', 'default' ) . '</a>';
			}

			if ( 'not_installed' === $status && ! empty( $p['wp_org'] ) ) {
				$url = wp_nonce_url(
					admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $p['wp_org'] ) ),
					'install-plugin_' . $p['wp_org']
				);
				return '<a href="' . esc_url( $url ) . '" class="pp-about-btn pp-about-btn-accent">'
					. esc_html__( 'Install Free', 'default' ) . '</a>';
			}

			return '';
		}

		private static function is_divi_active(): bool {
			$theme = wp_get_theme();
			return in_array( strtolower( $theme->get_template() ), array( 'divi', 'extra' ), true );
		}

		// ── Icons (inline SVG, no external requests) ───────────────────────

		private static function icon_external(): string {
			return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
		}

		private static function icon_x(): string {
			return '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
		}

		private static function icon_github(): string {
			return '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844a9.59 9.59 0 0 1 2.504.337c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0 0 22 12.017C22 6.484 17.522 2 12 2z"/></svg>';
		}

		private static function icon_linkedin(): string {
			return '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
		}

		// ── CSS ────────────────────────────────────────────────────────────

		private static function render_css( string $accent ): void {
			?>
			<style>
			/* Scoped to this page — all rules use #pp-about-page as the root */
			#pp-about-page {
				--pp-accent: <?php echo esc_attr( $accent ); ?>;
				--pp-accent-soft: color-mix(in srgb, var(--pp-accent) 12%, transparent);
				--pp-text: #0F172A;
				--pp-muted: #64748B;
				--pp-border: #E2E8F0;
				--pp-surface: #fff;
				--pp-bg: #F8FAFC;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			}

			/* Container */
			.pp-about-container {
				max-width: 860px;
				padding-bottom: 48px;
			}

			/* Hero */
			.pp-about-hero {
				display: flex;
				align-items: center;
				gap: 16px;
				margin: 20px 0 24px;
				padding: 20px 24px;
				background: var(--pp-surface);
				border: 1px solid var(--pp-border);
				border-radius: 10px;
			}
			.pp-about-hero-icon {
				display: flex;
				align-items: center;
				justify-content: center;
				width: 48px;
				height: 48px;
				border-radius: 12px;
				background: var(--pp-accent);
				color: #fff;
				font-size: 22px;
				font-weight: 800;
				flex-shrink: 0;
			}
			.pp-about-hero-name {
				font-size: 20px;
				font-weight: 800;
				color: var(--pp-text);
				display: flex;
				align-items: center;
				gap: 8px;
				margin-bottom: 2px;
			}
			.pp-about-version {
				font-size: 11px;
				font-weight: 600;
				color: var(--pp-muted);
				background: var(--pp-bg);
				border-radius: 999px;
				padding: 2px 9px;
				border: 1px solid var(--pp-border);
			}
			.pp-about-hero-tagline {
				font-size: 13px;
				color: var(--pp-muted);
				margin-bottom: 8px;
			}
			.pp-about-hero-links {
				display: flex;
				flex-wrap: wrap;
				gap: 14px;
			}
			.pp-about-link {
				font-size: 12.5px;
				font-weight: 600;
				color: var(--pp-accent);
				text-decoration: none;
			}
			.pp-about-link:hover {
				text-decoration: underline;
			}

			/* Two-column row */
			.pp-about-row {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 16px;
				margin-bottom: 24px;
			}

			/* Cards */
			.pp-about-card {
				background: var(--pp-surface);
				border: 1px solid var(--pp-border);
				border-radius: 10px;
				padding: 20px 22px;
			}
			.pp-about-card-current {
				border-color: var(--pp-accent);
				box-shadow: 0 0 0 3px var(--pp-accent-soft);
			}
			.pp-about-card-title {
				margin: 0 0 10px;
				font-size: 14px;
				font-weight: 700;
				color: var(--pp-text);
			}
			.pp-about-card p {
				font-size: 13px;
				color: var(--pp-muted);
				line-height: 1.7;
				margin: 0 0 8px;
			}
			.pp-about-company-links {
				display: flex;
				flex-wrap: wrap;
				gap: 14px;
				margin-top: 12px;
			}

			/* Links */
			.pp-about-ext-link {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				font-size: 12px;
				color: var(--pp-muted);
				text-decoration: none;
			}
			.pp-about-ext-link:hover { color: var(--pp-accent); }
			.pp-about-text-link {
				font-size: 12px;
				color: var(--pp-muted);
				text-decoration: none;
			}
			.pp-about-text-link:hover { color: var(--pp-accent); text-decoration: underline; }

			/* Founder */
			.pp-about-founder {
				display: flex;
				gap: 16px;
				align-items: flex-start;
			}
			.pp-about-founder-img {
				width: 64px;
				height: 64px;
				border-radius: 50%;
				object-fit: cover;
				flex-shrink: 0;
				border: 2px solid var(--pp-border);
			}
			.pp-about-founder-name {
				font-size: 14px;
				font-weight: 700;
				color: var(--pp-text);
				margin-bottom: 1px;
			}
			.pp-about-founder-role {
				font-size: 11.5px;
				color: var(--pp-muted);
				margin-bottom: 8px;
			}
			.pp-about-founder-bio {
				font-size: 12.5px;
				color: var(--pp-muted);
				line-height: 1.6;
				margin: 0 0 10px;
			}
			.pp-about-founder-links {
				display: flex;
				gap: 6px;
			}
			.pp-about-social-link {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 28px;
				height: 28px;
				border-radius: 7px;
				background: var(--pp-bg);
				color: var(--pp-muted);
				text-decoration: none;
				border: 1px solid var(--pp-border);
				transition: background .12s, color .12s;
			}
			.pp-about-social-link:hover {
				background: var(--pp-border);
				color: var(--pp-text);
			}

			/* Divi banner */
			.pp-about-divi-banner {
				display: flex;
				align-items: center;
				gap: 20px;
				flex-wrap: wrap;
				margin-bottom: 24px;
				padding: 16px 20px;
				background: #F0FDF4;
				border: 1px solid #BBF7D0;
				border-radius: 10px;
			}
			.pp-about-divi-text {
				flex: 1;
				min-width: 0;
				font-size: 13px;
				color: #166534;
			}
			.pp-about-divi-text strong {
				display: block;
				font-size: 13px;
				font-weight: 700;
				color: #14532D;
				margin-bottom: 2px;
			}
			.pp-about-divi-actions {
				display: flex;
				gap: 12px;
				flex-wrap: wrap;
				align-items: center;
			}
			.pp-about-divi-item {
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 5px;
			}
			.pp-about-divi-pname {
				font-size: 11px;
				font-weight: 600;
				color: #14532D;
			}

			/* Section heading */
			.pp-about-section-title {
				font-size: 15px;
				font-weight: 700;
				color: var(--pp-text);
				margin: 0 0 14px;
			}

			/* Products grid */
			.pp-about-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
				gap: 14px;
			}
			.pp-about-product-card {
				display: flex;
				gap: 12px;
				align-items: flex-start;
			}
			.pp-about-product-icon {
				width: 36px;
				height: 36px;
				border-radius: 9px;
				color: #fff;
				font-size: 15px;
				font-weight: 800;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
			}
			.pp-about-product-body { flex: 1; min-width: 0; }
			.pp-about-product-header {
				display: flex;
				align-items: center;
				gap: 5px;
				flex-wrap: wrap;
				margin-bottom: 3px;
			}
			.pp-about-product-name { font-size: 13px; font-weight: 700; color: var(--pp-text); }
			.pp-about-product-tagline { margin: 0 0 8px; font-size: 12px; color: var(--pp-muted); line-height: 1.5; }
			.pp-about-product-foot { display: flex; align-items: center; gap: 10px; }

			/* Product icon colours */
			.pp-icon-blue    { background: #2395E7; }
			.pp-icon-violet  { background: #7C3AED; }
			.pp-icon-sky     { background: #0EA5E9; }
			.pp-icon-pink    { background: #EC4899; }
			.pp-icon-amber   { background: #F59E0B; }
			.pp-icon-emerald { background: #10B981; }
			.pp-icon-indigo  { background: #6366F1; }

			/* Badges */
			.pp-about-badge {
				display: inline-block;
				font-size: 10px;
				font-weight: 700;
				padding: 1px 7px;
				border-radius: 999px;
				line-height: 18px;
			}
			.pp-about-badge-free    { background: #DCFCE7; color: #16A34A; }
			.pp-about-badge-divi    { background: #F0FDF4; color: #15803D; }
			.pp-about-badge-current { background: #EFF6FF; color: #2563EB; }

			/* Buttons */
			.pp-about-btn {
				display: inline-flex;
				align-items: center;
				height: 27px;
				padding: 0 11px;
				border-radius: 6px;
				font-size: 12px;
				font-weight: 600;
				text-decoration: none;
				white-space: nowrap;
			}
			.pp-about-btn-accent {
				background: var(--pp-accent);
				color: #fff;
			}
			.pp-about-btn-accent:hover { opacity: .88; color: #fff; }
			.pp-about-btn-outline {
				border: 1px solid var(--pp-border);
				color: var(--pp-muted);
				background: var(--pp-surface);
			}
			.pp-about-btn-outline:hover { border-color: #CBD5E1; color: var(--pp-text); }
			.pp-about-status-active { font-size: 12px; font-weight: 600; color: #16A34A; }

			/* Responsive */
			@media ( max-width: 700px ) {
				.pp-about-row  { grid-template-columns: 1fr; }
				.pp-about-grid { grid-template-columns: 1fr; }
			}
			</style>
			<?php
		}

		// ── Product catalogue ──────────────────────────────────────────────

		private static function pp_products(): array {
			return array(
				array(
					'slug'      => 'outbees',
					'name'      => 'Outbees',
					'tagline'   => 'Broadcast email to your customers from WordPress.',
					'url'       => 'https://outbees.co',
					'color_key' => 'blue',
					'free'      => false,
					'wp_org'    => '',
					'file'      => '',
					'divi'      => false,
				),
				array(
					'slug'      => 'inbees',
					'name'      => 'Inbees',
					'tagline'   => 'Shared inbox for WordPress — email collaboration for teams.',
					'url'       => 'https://inbees.co',
					'color_key' => 'violet',
					'free'      => false,
					'wp_org'    => '',
					'file'      => '',
					'divi'      => false,
				),
				array(
					'slug'      => 'mailyard',
					'name'      => 'Mailyard',
					'tagline'   => 'Smart SMTP delivery with failover for WordPress.',
					'url'       => 'https://mailyard.co',
					'color_key' => 'sky',
					'free'      => false,
					'wp_org'    => '',
					'file'      => '',
					'divi'      => false,
				),
				array(
					'slug'      => 'flypops',
					'name'      => 'Flypops',
					'tagline'   => 'Popups, notification bars, and lead capture for WordPress.',
					'url'       => 'https://plugpress.co',
					'color_key' => 'pink',
					'free'      => false,
					'wp_org'    => '',
					'file'      => '',
					'divi'      => false,
				),
				array(
					'slug'      => 'cf7-mate',
					'name'      => 'CF7 Mate',
					'tagline'   => 'Power-ups for Contact Form 7 — conditional logic, file uploads &amp; more.',
					'url'       => 'https://plugpress.co/cf7mate',
					'color_key' => 'amber',
					'free'      => true,
					'wp_org'    => 'cf7-mate',
					'file'      => 'cf7-mate/cf7-mate.php',
					'divi'      => false,
				),
				array(
					'slug'      => 'divi-carousel-free',
					'name'      => 'Divi Carousel',
					'tagline'   => 'Beautiful carousel module built natively for the Divi Builder.',
					'url'       => 'https://plugpress.co/divi-carousel',
					'color_key' => 'emerald',
					'free'      => true,
					'wp_org'    => 'divi-carousel-free',
					'file'      => 'divi-carousel-free/divi-carousel-free.php',
					'divi'      => true,
				),
				array(
					'slug'      => 'divitorque-lite',
					'name'      => 'Divi Torque Lite',
					'tagline'   => '30+ powerful modules and extensions for the Divi Builder.',
					'url'       => 'https://divitorque.co',
					'color_key' => 'indigo',
					'free'      => true,
					'wp_org'    => 'divitorque-lite',
					'file'      => 'divitorque-lite/divitorque.php',
					'divi'      => true,
				),
			);
		}
	}
}
