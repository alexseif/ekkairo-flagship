<?php
/**
 * EKK Flagship Theme Functions and Definitions
 *
 * @package EkkairoFlagship
 * @version 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Setup theme supports and editor styles.
 */
function ekkairo_flagship_setup(): void {
	// Enable block styles and editor default styles.
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );

	// Enqueue editor stylesheet if compiled.
	if ( file_exists( get_theme_file_path( 'build/index.css' ) ) ) {
		add_editor_style( 'build/index.css' );
	}

	// Post thumbnails support.
	add_theme_support( 'post-thumbnails' );

	// Responsive embeds support.
	add_theme_support( 'responsive-embeds' );
}
add_action( 'after_setup_theme', 'ekkairo_flagship_setup' );

/**
 * Enqueue frontend and editor block scripts & styles.
 */
function ekkairo_flagship_assets(): void {
	$asset_file = get_theme_file_path( 'build/index.asset.php' );
	$asset      = file_exists( $asset_file )
		? require $asset_file
		: array(
			'dependencies' => array(),
			'version'      => '1.0.0',
		);

	if ( file_exists( get_theme_file_path( 'build/index.css' ) ) ) {
		wp_enqueue_style(
			'ekkairo-flagship-styles',
			get_theme_file_uri( 'build/index.css' ),
			array(),
			$asset['version']
		);
	}

	if ( file_exists( get_theme_file_path( 'build/index.js' ) ) ) {
		wp_enqueue_script(
			'ekkairo-flagship-scripts',
			get_theme_file_uri( 'build/index.js' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ekkairo_flagship_assets' );

/**
 * Register custom block styles.
 */
function ekkairo_flagship_register_block_styles(): void {
	register_block_style( 'core/query', array(
		'name'       => 'news-grid',
		'label'      => __( 'News Grid (7 Posts + CTA)', 'ekkairo-flagship' ),
		'is_default' => false,
	) );

	register_block_style( 'core/query', array(
		'name'       => 'news-carousel',
		'label'      => __( 'News Carousel Slider', 'ekkairo-flagship' ),
		'is_default' => false,
	) );

	register_block_style( 'core/group', array(
		'name'       => 'narrow-container',
		'label'      => __( 'Narrow Width (48em)', 'ekkairo-flagship' ),
		'is_default' => false,
	) );

	register_block_style( 'core/heading', array(
		'name'       => 'bordered-heading',
		'label'      => __( 'Left Border Accent', 'ekkairo-flagship' ),
		'is_default' => false,
	) );
}
add_action( 'init', 'ekkairo_flagship_register_block_styles' );

/**
 * Register custom block pattern categories.
 */
function ekkairo_flagship_pattern_categories(): void {
	register_block_pattern_category(
		'ekkairo',
		array( 'label' => __( 'EKK Flagship', 'ekkairo-flagship' ) )
	);
}
add_action( 'init', 'ekkairo_flagship_pattern_categories' );

/**
 * Register dynamic theme blocks for single posts.
 */
function ekkairo_flagship_register_custom_blocks(): void {
	register_block_type( 'ekkairo/social-share', array(
		'render_callback' => 'ekkairo_flagship_render_social_share',
		'attributes'      => array(),
	) );

	register_block_type( 'ekkairo/single-post-nav', array(
		'render_callback' => 'ekkairo_flagship_render_single_post_nav',
		'attributes'      => array(),
	) );
}
add_action( 'init', 'ekkairo_flagship_register_custom_blocks' );

/**
 * Render callback for the social sharing block.
 */
function ekkairo_flagship_render_social_share(): string {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$url     = rawurlencode( (string) get_permalink( $post_id ) );
	$title   = rawurlencode( (string) html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) );
	$raw_url = esc_url( (string) get_permalink( $post_id ) );

	$fb_url  = 'https://www.facebook.com/sharer/sharer.php?u=' . $url;
	$tw_url  = 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title;
	$wa_url  = 'https://api.whatsapp.com/send?text=' . $title . '%20' . $url;
	$li_url  = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url;

	ob_start();
	?>
	<div class="post-social-share-box">
		<div class="share-label">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92c0-1.61-1.31-2.92-2.92-2.92z"/></svg>
			<span>Κοινοποίηση άρθρου:</span>
		</div>
		<div class="share-buttons">
			<a href="<?php echo esc_url( $fb_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-fb" title="Facebook">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
				<span>Facebook</span>
			</a>
			<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-wa" title="WhatsApp">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.969.54 1.772.84 2.79.84 3.182 0 5.768-2.587 5.769-5.766.001-3.182-2.585-5.769-5.769-5.769zm10.165 5.766c-.001 5.626-4.577 10.201-10.201 10.201-1.795 0-3.498-.468-4.996-1.282l-5.59 1.465 1.492-5.451c-.896-1.556-1.368-3.328-1.367-5.133.001-5.625 4.577-10.2 10.202-10.2 5.625 0 10.2 4.575 10.2 10.2z"/></svg>
				<span>WhatsApp</span>
			</a>
			<a href="<?php echo esc_url( $tw_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-x" title="X (Twitter)">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
				<span>X</span>
			</a>
			<a href="<?php echo esc_url( $li_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-li" title="LinkedIn">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.64a1.66 1.66 0 1 0-.01 3.32 1.66 1.66 0 0 0 .01-3.32z"/></svg>
				<span>LinkedIn</span>
			</a>
			<button type="button" class="share-btn share-btn-copy" data-url="<?php echo $raw_url; ?>" title="Αντιγραφή συνδέσμου">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
				<span class="copy-text">Αντιγραφή</span>
			</button>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Render callback for single post previous/next visual navigation.
 */
function ekkairo_flagship_render_single_post_nav(): string {
	$prev_post = get_previous_post();
	$next_post = get_next_post();

	if ( ! ( $prev_post instanceof WP_Post ) && ! ( $next_post instanceof WP_Post ) ) {
		return '';
	}

	ob_start();
	?>
	<nav class="post-prev-next-nav" aria-label="Πλοήγηση άρθρων">
		<?php if ( $prev_post instanceof WP_Post ) :
			$prev_thumb = get_the_post_thumbnail_url( $prev_post->ID, 'medium' );
			$prev_title = get_the_title( $prev_post->ID );
			$prev_url   = get_permalink( $prev_post->ID );
		?>
			<a href="<?php echo esc_url( $prev_url ); ?>" class="nav-card nav-card-prev">
				<div class="nav-card-arrow">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
				</div>
				<?php if ( $prev_thumb ) : ?>
					<div class="nav-card-thumb">
						<img src="<?php echo esc_url( $prev_thumb ); ?>" alt="" loading="lazy" />
					</div>
				<?php endif; ?>
				<div class="nav-card-content">
					<span class="nav-card-label">← Προηγούμενο</span>
					<span class="nav-card-title"><?php echo esc_html( wp_trim_words( $prev_title, 7, '...' ) ); ?></span>
				</div>
			</a>
		<?php else : ?>
			<div class="nav-card-placeholder"></div>
		<?php endif; ?>

		<?php if ( $next_post instanceof WP_Post ) :
			$next_thumb = get_the_post_thumbnail_url( $next_post->ID, 'medium' );
			$next_title = get_the_title( $next_post->ID );
			$next_url   = get_permalink( $next_post->ID );
		?>
			<a href="<?php echo esc_url( $next_url ); ?>" class="nav-card nav-card-next">
				<div class="nav-card-content">
					<span class="nav-card-label">Επόμενο →</span>
					<span class="nav-card-title"><?php echo esc_html( wp_trim_words( $next_title, 7, '...' ) ); ?></span>
				</div>
				<?php if ( $next_thumb ) : ?>
					<div class="nav-card-thumb">
						<img src="<?php echo esc_url( $next_thumb ); ?>" alt="" loading="lazy" />
					</div>
				<?php endif; ?>
				<div class="nav-card-arrow">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
				</div>
			</a>
		<?php else : ?>
			<div class="nav-card-placeholder"></div>
		<?php endif; ?>
	</nav>
	<?php
	return (string) ob_get_clean();
}



