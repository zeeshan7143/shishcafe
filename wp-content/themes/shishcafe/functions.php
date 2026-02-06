<?php

/**
 * shishcafe functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package shishcafe
 */

if (! defined('_S_VERSION')) {
	// Replace the version number of the theme on each release.
	define('_S_VERSION', '1.0.0');
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function shishcafe_setup()
{
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on shishcafe, use a find and replace
		* to change 'shishcafe' to the name of your theme in all the template files.
		*/
	load_theme_textdomain('shishcafe', get_template_directory() . '/languages');

	// Add default posts and comments RSS feed links to head.
	add_theme_support('automatic-feed-links');

	/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
	add_theme_support('title-tag');

	/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
	add_theme_support('post-thumbnails');

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'menu-1' => esc_html__('Primary', 'shishcafe'),
		)
	);

	/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'shishcafe_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support('customize-selective-refresh-widgets');

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
}
add_action('after_setup_theme', 'shishcafe_setup');

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function shishcafe_content_width()
{
	$GLOBALS['content_width'] = apply_filters('shishcafe_content_width', 640);
}
add_action('after_setup_theme', 'shishcafe_content_width', 0);

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function shishcafe_widgets_init()
{
	register_sidebar(
		array(
			'name'          => esc_html__('Sidebar', 'shishcafe'),
			'id'            => 'sidebar-1',
			'description'   => esc_html__('Add widgets here.', 'shishcafe'),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action('widgets_init', 'shishcafe_widgets_init');

/**
 * Enqueue scripts and styles.
 */
function shishcafe_scripts()
{
	wp_enqueue_style('fonts', get_template_directory_uri() . '/assets/fonts/fonts.css');
	wp_enqueue_style('custom-style', get_template_directory_uri() . '/assets/styles/customstyle.css');
	wp_enqueue_style('shishcafe-style', get_stylesheet_uri(), array(), _S_VERSION);
	wp_style_add_data('shishcafe-style', 'rtl', 'replace');

	wp_enqueue_script('shishcafe-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true);
	wp_enqueue_script('sticky-header', get_template_directory_uri() . '/js/stickyheader.js', array(), _S_VERSION, true);
	wp_enqueue_script('custom-script', get_template_directory_uri() . '/js/custom.js', array(), _S_VERSION, true);
	// wp_enqueue_script('custom-shop', get_template_directory_uri() . '/js/custom-shop.js', array(), _S_VERSION, true);
	// wp_enqueue_script('get-location', get_template_directory_uri() . '/js/getLocation.js', array(), _S_VERSION, true);

	// Localize script to pass AJAX URL
	// wp_localize_script('custom-shop', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));

	if (is_singular() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}
}
add_action('wp_enqueue_scripts', 'shishcafe_scripts');
// Custom script 

// Custom Function Files 
// Shop Page 
require_once get_template_directory() . '/custom-shop.php';
// Price Range
require_once get_template_directory() . '/custompricerange.php';
// Custom Product Query
require_once get_template_directory() . '/custom-product-query.php';
// Add Custom Column (Location) in Order Table at admin Side.
require_once get_template_directory() . '/custom-column-order.php';
// Add Product Description in Order Table at admin Side.
require_once get_template_directory() . '/custom-description-order.php';
// for locatio check if already exist clear cart  
require get_stylesheet_directory() . '/location-check.php';
// Custom Delivery System and charges 
require_once get_template_directory() . '/custom-delivery.php';
// add_action('wp', function () {
//     // if ( is_admin() ) {
//     //     return;
//     // }

//     if ( function_exists('is_checkout') && is_checkout() ) {
//         require_once get_template_directory() . '/custom-delivery.php';
//     }
// });

// Cusotm Features
require_once get_template_directory() . '/custom-features.php';
/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if (defined('JETPACK__VERSION')) {
	require get_template_directory() . '/inc/jetpack.php';
}

// Increase Varriations Limit in products 
define('WC_MAX_LINKED_VARIATIONS', 500);

// Remove update notifications Spped Accelerator
function remove_update_notifications4($value)
{
	if (isset($value) && is_object($value)) {
		unset($value->response['seraphinite-accelerator-ext/main.php']);
	}
	return $value;
}
add_filter('site_transient_update_plugins', 'remove_update_notifications4');

// All tags and id to li 
add_filter('woocommerce_post_class', 'add_data_tags_to_product_li', 10, 2);
function add_data_tags_to_product_li($classes, $product)
{
	if (is_a($product, 'WC_Product')) {
		$post_id = $product->get_id();

		// Get product tags
		$tags = wp_get_post_terms($post_id, 'product_tag', array('fields' => 'slugs'));

		if (!empty($tags)) {
			foreach ($tags as $tag) {
				$classes[] = 'product_tag-' . sanitize_title($tag); // Add product tag classes
			}
		}

		// Add post ID class
		$classes[] = 'post-' . $post_id;
	}
	return $classes;
}

// always english language 
add_filter('locale', function ($locale) {
	return 'en_US'; // Force site language to English (United States)
});


// Api Keys 
define('GOOGLE_API_KEY', 'AIzaSyBVqwnoESxdkv0RJVKKSdBkCP8QG5xweyo');
// define('GOOGLE_API_KEY', 'AIz');
// for test 
define('STORE_LONGITUDE', '71.653028');
define('STORE_LATITUDE', '29.40263');
define('STORE_ADDRESS', 'Railway Station, Bahawalpur, Pakistan');

// Rochdale 
// define('STORE_LONGITUDE', '-2.158473');
// define('STORE_LATITUDE', '53.609749');
// define('STORE_ADDRESS', '31-33 tweedale street Rochdale Ol11 1hh');


define('PRINT_API_KEY', 'f188d88d-94f6-4d5a-8e9c-46e145du8G89');
define('SHINSH_CAFE_CONSUMER_KEY', 'ck_50af38c3b255147d4c81c88fb2eab729954afc2c');
define('SHINSH_CAFE_CONSUMER_SECRET', 'cs_d169c61c6581393d29430b2cfcbe4c047a73b044');
