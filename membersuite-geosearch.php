<?php

/**
 * Plugin Name: MemberSuite GeoSearch
 * Description: Syncs MemberSuite members with lat/long for geolocation search.
 * Version: 1.3.5
 * Author: ASMBS
 */

if (!defined('ABSPATH')) {
	exit;
}

// Dependencies are loaded by the root composer autoloader
// Only load our own autoloader if running standalone
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
}

use MemberSuiteGeoSearch\Plugin;
use MemberSuiteGeoSearch\Settings;

add_action('plugins_loaded', function () {
	new Plugin();
	new Settings();
});
