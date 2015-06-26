<?php
$link = add_query_arg(
	array(
		'utm_campaign' => 'in-app',
		'utm_medium'   => 'plugin-tec',
		'utm_source'   => 'notice'
	), self::$tecUrl . 'license-keys/'
);

$link = esc_url( $link );
$tec = Tribe__Events__Main::instance();

$licenses_tab = array(
	'info-start' => array(
		'type' => 'html',
		'html' => '<div id="modern-tribe-info">'
	),
	'info-box-title' => array(
		'type' => 'html',
		'html' => '<h2>' . __( 'Licenses', 'tribe-events-calendar' ) . '</h2>',
	),
	'info-box-description' => array(
		'type' => 'html',
		'html' => sprintf(
			__( '<p>The license key you received when completing your purchase from %s will grant you access to support and updates until it expires. You do not need to enter the key below for the plugins to work, but you will need to enter it to get automatic updates. <strong>Find your license keys at <a href="%s" target="_blank">%s</a></strong>.</p> <p>Each paid add-on has its own unique license key. Simply paste the key into its appropriate field on below, and give it a moment to validate. You know you\'re set when a green expiration date appears alongside a "valid" message.</p> <p>If you\'re seeing a red message telling you that your key isn\'t valid or is out of installs, visit <a href="%s" target="_blank">%s</a> to manage your installs or renew / upgrade your license.</p><p>Not seeing an update but expecting one? In WordPress, go to <a href="%s">Dashboard > Updates</a> and click "Check Again".</p>', 'tribe-events-calendar' ),
			self::$tecUrl,
			$link,
			self::$tecUrl . 'license-keys/',
			$link,
			self::$tecUrl . 'license-keys/',
			admin_url( '/update-core.php' )
		),
	),
	'info-end' => array(
		'type' => 'html',
		'html' => '</div>'
	),
	'tribe-form-content-start' => array(
		'type' => 'html',
		'html' => '<div class="tribe-settings-form-wrap">'
	),
	// TODO: Figure out how properly close this wrapper after the license content
	'tribe-form-content-end'   => array(
		'type' => 'html',
		'html' => '</div>'
	)
);