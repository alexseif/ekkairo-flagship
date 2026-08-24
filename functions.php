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

