<?php

/**
 * Plugin Name: MemberSuite GeoSearch
 * Description: Syncs MemberSuite members with lat/long for geolocation search.
 * Version: 1.2.0
 * Author: ASMBS
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use MemberSuiteGeoSearch\Plugin;
use MemberSuiteGeoSearch\Settings;

// Boot the plugin
add_action('plugins_loaded', function () {
	new Plugin();
	new Settings();
});
