<?php
/**
 * Plugin Name:     Core Web Vitals Booster
 * Plugin URI:      https://speedien.com
 * Description:     Plugin to help with core web vitals metrics and page speed.
 * Author:          Speedien Team
 * Text Domain:     speedien
 * Domain Path:     /
 * Version:         1.1.8
 */

// Your code starts here.
// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}
define('SPEEDIEN_API_URL', 'https://my.speedien.com');

require_once 'speedien_config.php';
require_once 'speedien_ui.php';
require_once 'speedien_cache.php';

register_activation_hook( __FILE__, 'speedien_cache_init' );

/**
 * Deactivation hook.
 */
function speedien_deactivate() {
	unlink(WP_CONTENT_DIR . '/advanced-cache.php');
	$wp_config = file_get_contents($wp_config_file);
    $wp_cache = '/define\([\'\"]WP_CACHE[\'\"].*/';
    $wp_config = preg_replace($wp_cache, '', $wp_config);
    file_put_contents($wp_config_file, $wp_config);
}
register_deactivation_hook( __FILE__, 'speedien_deactivate' );
