<?php

$inmap_plugin_slug = 'inreach-mapshare';
$inmap_colour_primary = '#dd9933';

$config = [
	'plugin_slug' => $inmap_plugin_slug,
	'plugin_text_domain' => $inmap_plugin_slug,
	'plugin_name' => 'inReach MapShare',
	'plugin_version' => '1.0',
	'settings_id' => 'inreach_mapshare',
	'site_url' => 'https://wordpress.org/support/plugin/' . $inmap_plugin_slug . '/',
	'directory_url' => 'https://wordpress.org/support/plugin/' . $inmap_plugin_slug . '/',
	'shortcode' => $inmap_plugin_slug,
	'plugin_about' => '<img alt="Joe\'s mug" src="https://www.morehawes.co.uk/assets/images/Joe1BW.jpg" />',
	
	'map' => [
		'styles' => [
			'tracking_colour' => $inmap_colour_primary
		]
	],
	'misc' => [
		'advanced' => [
			'debug_mode' => true
		]
	]
];

Joe_Config::init($config);