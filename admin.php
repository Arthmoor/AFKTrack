<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( version_compare( PHP_VERSION, "7.0.0", "<" ) ) {
	die( 'PHP version does not meet minimum requirements. Contact your system administrator.' );
}

define( 'AFKTRACK', true );
define( 'AFKTRACK_ADM', true );

$time_now   = explode( ' ', microtime() );
$time_start = $time_now[1] + $time_now[0];

date_default_timezone_set( 'UTC' );

session_start();

$_REQUEST = array();

require './settings.php';
$settings['include_path'] = '.';
require_once $settings['include_path'] . '/lib/' . $settings['db_type'] . '.php';
require_once $settings['include_path'] . '/global.php';
require_once $settings['include_path'] . '/lib/bbcode.php';
require_once $settings['include_path'] . '/lib/file_tools.php';
require_once $settings['include_path'] . '/lib/zTemplate.php';

set_error_handler( 'error' );
error_reporting( E_ALL );

$dbt = 'db_' . $settings['db_type'];
$db = new $dbt( $settings['db_name'], $settings['db_user'], $settings['db_pass'], $settings['db_host'], $settings['db_pre'] );
if( !$db->db ) {
	error( E_USER_ERROR, 'A connection to the database could not be established and/or the specified database could not be found.', __FILE__, __LINE__ );
}

/*
 * Logic here:
 * If 'a' is not set, but some other query is, it's a bogus request for this software.
 * If 'a' is set, but the module doesn't exist, it's either a malformed URL or a bogus request.
 * Otherwise $missing remains false and no error is generated later.
 */
$missing = false;
$qstring = null;
$module = null;

if( !isset( $_GET['a'] ) ) {
	$module = 'home';

	if( isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
		$qstring = $_SERVER['QUERY_STRING'];

		$missing = true;
	}
} elseif( !empty( $_GET['a'] ) ) {
	$a = trim( $_GET['a'] );

	// Should restrict us to only valid alphabetic characters, which are all that's valid for this software.
	if( !preg_match( '/^[a-zA-Z]*$/', $a ) ) {
		if( isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
			$qstring = $_SERVER['QUERY_STRING'];
		}

		$missing = true;

		$_SESSION = array();

		session_destroy();

		header( 'Clear-Site-Data: "*"' );
	} elseif( !file_exists( 'admin_modules/' . $a . '.php' ) ) {
		$missing = true;
		$qstring = $_SERVER['REQUEST_URI'];
	} else {
		$module = $a;
	}
} else {
	if( isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
		$qstring = $_SERVER['QUERY_STRING'];

		$missing = true;
	}
}

// I know this looks corny and all but it mimics the output from a real 404 page.
if( $missing ) {
	header( 'HTTP/1.0 404 Not Found' );

	echo( "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
	<html><head>
	<title>404 Not Found</title>
	</head><body>
	<h1>Not Found</h1>
	<p>The requested URL $qstring was not found on this server.</p>
	<hr>
	{$_SERVER['SERVER_SIGNATURE']}	</body></html>" );

	exit( );
}

require 'admin_modules/' . $module . '.php';

$mod = new $module( $db, $settings );

// Security header options
if( $mod->settings['htts_enabled'] && $mod->settings['htts_max_age'] > -1 ) {
	header( "Strict-Transport-Security: max-age={$mod->settings['htts_max_age']}" );
}

if( $mod->settings['xss_enabled'] ) {
	if( $mod->settings['xss_policy'] == 0 ) {
		header( 'X-XSS-Protection: 0' );
	}

	if( $mod->settings['xss_policy'] == 1 ) {
		header( 'X-XSS-Protection: 1' );
	}

	if( $mod->settings['xss_policy'] == 2 ) {
		header( 'X-XSS-Protection: 1; mode=block' );
	}
}

if( $mod->settings['xfo_enabled'] ) {
	if( $mod->settings['xfo_policy'] == 0 ) {
		header( 'X-Frame-Options: deny' );
	}

	if( $mod->settings['xfo_policy'] == 1 ) {
		header( 'X-Frame-Options: sameorigin' );
	}

	if( $mod->settings['xfo_policy'] == 2 ) {
		header( "X-Frame-Options: allow-from {$mod->settings['xfo_allowed_origin']}" );
	}
}

if( $mod->settings['xcto_enabled'] ) {
	header( 'X-Content-Type-Options: nosniff' );
}

if( $mod->settings['ect_enabled'] ) {
	header( "Expect-CT: max-age={$mod->settings['ect_max_age']}" );
}

if( $mod->settings['csp_enabled'] ) {
	header( "Content-Security-Policy: {$mod->settings['csp_details']}" );
}

if( $mod->ip_banned( $mod->ip ) )
{
	header( 'HTTP/1.0 403 Forbidden' );
	exit( 'You have been banned from this site.' );
}

$xtpl = new XTemplate( 'skins/' . $mod->skin . '/AdminCP/index.xtpl' );
$mod->xtpl = $xtpl;

$mod->title = 'AFKTrack: Administration Control Panel';

$logo_image = "{$mod->banner_dir}{$mod->settings['header_logo']}";
$xtpl->assign( 'header_logo', $mod->settings['site_address'] . $logo_image );

$img_stats = getimagesize( $logo_image );
$img_height = $img_stats[1];

$xtpl->assign( 'img_height', $img_height );

if( !$mod->login( 'admin.php' ) ) {
	header( 'Clear-Site-Data: "*"' );
	header( 'HTTP/1.0 403 Forbidden' );

	setcookie( $mod->settings['cookie_prefix'] . 'user', '', $mod->time - 9000, $mod->settings['cookie_path'], $mod->settings['cookie_domain'], $mod->settings['cookie_secure'], true );
	setcookie( $mod->settings['cookie_prefix'] . 'pass', '', $mod->time - 9000, $mod->settings['cookie_path'], $mod->settings['cookie_domain'], $mod->settings['cookie_secure'], true );

	$_SESSION = array();

	session_destroy();

	$mod->db->close();
	exit( '<h1>Access Denied. Proper authentication was not provided.</h1>' );
} elseif( $mod->user['user_level'] < USER_ADMIN ) {
	header( 'Clear-Site-Data: "*"' );
	header( 'HTTP/1.0 403 Forbidden' );

	setcookie( $mod->settings['cookie_prefix'] . 'user', '', $mod->time - 9000, $mod->settings['cookie_path'], $mod->settings['cookie_domain'], $mod->settings['cookie_secure'], true );
	setcookie( $mod->settings['cookie_prefix'] . 'pass', '', $mod->time - 9000, $mod->settings['cookie_path'], $mod->settings['cookie_domain'], $mod->settings['cookie_secure'], true );

	$_SESSION = array();

	session_destroy();

	$mod->db->close();
	exit( '<h1>Access Denied. Proper authentication was not provided.</h1>' );
} else {
	$mod->projectid = 0;
	$mod->navselect = 0;

	$module_output = $mod->execute();

	if( $mod->nohtml ) {
		ob_start( 'ob_gzhandler' );

		echo $module_output;

		@ob_end_flush();
		@flush();
	} else {
		$xtpl->assign( 'page_title', $mod->title );
		$xtpl->assign( 'style_link', "{$mod->settings['site_address']}skins/{$mod->skin}/AdminCP/admincp.css" );
		$xtpl->assign( 'site_name', htmlspecialchars( $mod->settings['site_name'] ) );
		$xtpl->assign( 'site_link', $mod->settings['site_address'] );
		$xtpl->assign( 'imgsrc', "{$mod->settings['site_address']}skins/{$mod->skin}" );

		$open = $mod->settings['site_open'];
		if( !$open ) {
			$xtpl->assign( 'closed_message', $mod->settings['site_closedmessage'] );
			$xtpl->parse( 'Index.Closed' );
		}

		$spamstored = $mod->db->quick_query( 'SELECT COUNT(spam_id) count FROM %pspam' );
		if( $spamstored['count'] > 0 ) {
			$t = 'are';
			$s = 's';
			if( $spamstored['count'] == 1 ) {
				$t = 'is';
				$s = '';
			}
			$spam_message = 'There ' . $t . ' ' . $spamstored['count'] . ' comment' . $s . ' currently flagged as spam.';

			$xtpl->assign( 'spam_message', $spam_message );
			$xtpl->parse( 'Index.Spam' );
		}

		$xtpl->assign( 'module_output', $module_output );

		$xtpl->assign( 'version', $mod->version );

		$time_now  = explode( ' ', microtime() );
		$time_exec = round($time_now[1] + $time_now[0] - $time_start, 4);
		$queries = $mod->db->queries;
		$queries_exec = $mod->db->queries_exec;
		$xtpl->assign( 'page_generated', "Page generated in $time_exec seconds. $queries queries made in $queries_exec seconds." );

		$xtpl->parse( 'Index' );

		ob_start( 'ob_gzhandler' );

		$xtpl->out( 'Index' );

		@ob_end_flush();
		@flush();
	}
}
$mod->db->close();
?>