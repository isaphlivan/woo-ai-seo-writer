<?php
/** * Plugin Name: Woo AI Destekli SEO+GEO
 * Plugin URI: https://isapehlivan.com/ 
 * Description: SEO ve GEO Uyumlu AI İçerik Üretici - Rank Math & Yoast 100/100 Garantili, Schema Markup, E-E-A-T Sinyalleri
 * Version: 5.0
 * Author: İsa Pehlivan 
 * Text Domain: woo-ai-seo 
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WASW_VERSION', '5.0');
define('WASW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WASW_PLUGIN_URL', plugin_dir_url(__FILE__));

// Core classes
require_once WASW_PLUGIN_DIR . 'includes/class-wasw-api.php';
require_once WASW_PLUGIN_DIR . 'includes/class-wasw-seo-handler.php';
require_once WASW_PLUGIN_DIR . 'includes/class-wasw-schema.php';
require_once WASW_PLUGIN_DIR . 'includes/class-wasw-license.php';
require_once WASW_PLUGIN_DIR . 'includes/class-wasw-ajax.php';
require_once WASW_PLUGIN_DIR . 'includes/class-wasw-admin.php';

function wasw_init()
{
    new WASW_License();
    new WASW_SEO_Handler();
    new WASW_Schema();
    new WASW_Ajax();
    new WASW_Admin();
}

add_action('plugins_loaded', 'wasw_init');