<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( version_compare( PHP_VERSION, "7.0.0", "<" ) ) {
	die( 'PHP version does not meet minimum requirements. Contact your system administrator.' );
}

define( 'AFKTRACK', true );

$time_now   = explode( ' ', microtime() );
$time_start = $time_now[1] + $time_now[0];

date_default_timezone_set( 'UTC' );

session_start();

// Override session cache control
header( 'Cache-Control: private, max-age=1800, pre-check=1800, must-revalidate' );

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

// A vicious hack to get the issue box on the main page to work.
if( isset( $_GET['issue_box'] ) ) {
	$link = '/index.php?a=issues&i=' . intval( $_GET['issue_box'] );

	header( 'Location: ' . $link );

	exit();
}

/*
 * Logic here:
 * If 'a' is not set, but some other query is, it's a bogus request for this software.
 * If 'a' is set, but the module doesn't exist, it's either a malformed URL or a bogus request.
 * Otherwise $missing remains false and no error is generated later.
 */
$missing = false;
$qstring = null;
$showprivacy = false;
if( !isset( $_GET['a'] ) ) {
	$module = 'issues';

	if( isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
		$qstring = $_SERVER['QUERY_STRING'];

		$missing = true;
	}

	if( isset( $_GET['s'] ) && $_GET['s'] == 'logout' ) {
		$missing = false;
	}
} elseif( $_GET['a'] == 'privacypolicy' ) {
	$module = 'issues';
	$showprivacy = true;
} elseif( !file_exists( 'modules/' . $_GET['a'] . '.php' ) ) {
	$module = 'issues';
	$missing = true;
	$qstring = $_SERVER['REQUEST_URI'];
} else {
	$module = $_GET['a'];
}

if( strstr( $module, '/' ) || strstr( $module, '\\' ) ) {
	setcookie( $this->settings['cookie_prefix'] . 'user', '', $this->time - 9000, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
	setcookie( $this->settings['cookie_prefix'] . 'pass', '', $this->time - 9000, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

	$_SESSION = array();

	session_destroy();

	header( 'Clear-Site-Data: "*"' );

	header( 'HTTP/1.0 403 Forbidden' );
	exit( 'You have been banned from this site.' );
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

require 'modules/'  . $module . '.php';

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
	$mod->clear_site_data();

	header( 'HTTP/1.0 403 Forbidden' );

	exit( 'You have been banned from this site.' );
}

if( isset( $mod->get['s'] ) && $mod->get['s'] == 'logout' ) {
	$mod->logout();
	exit;
}

$xtpl = new XTemplate( './skins/' . $mod->skin . '/index.xtpl' );
$mod->xtpl = $xtpl;

$xtpl->assign( 'site_root', $mod->settings['site_address'] );
$xtpl->assign( 'site_name', htmlspecialchars( $mod->settings['site_name'] ) );

$logo_image = "{$mod->banner_dir}{$mod->settings['header_logo']}";
$xtpl->assign( 'header_logo', $mod->settings['site_address'] . $logo_image );

$img_stats = getimagesize( $logo_image );
$img_height = $img_stats[1];

$xtpl->assign( 'img_height', $img_height );

$xtpl->assign( 'mobile_icons', $mod->settings['mobile_icons'] );

$mod->title = 'Site Title Not Set';
if( isset( $mod->settings['site_name'] ) && !empty( $mod->settings['site_name'] ) )
	$mod->title = $mod->settings['site_name'];

$site_keywords = null;
if( isset( $mod->settings['site_keywords'] ) )
	$site_keywords = "<meta name=\"keywords\" content=\"{$mod->settings['site_keywords']}\" />";
$xtpl->assign( 'site_keywords', $site_keywords );

// Set the defaults specified by the site owners, or leave out if not supplied.
$mod->meta_description( null );
if( isset( $mod->settings['site_meta'] ) && !empty( $mod->settings['site_meta'] ) )
	$mod->meta_description( $mod->settings['site_meta'] );

$style_link = "{$mod->settings['site_address']}skins/{$mod->skin}/styles.css";

$open = $mod->settings['site_open'];

if( $mod->login( 'index.php' ) == -1 ) {
	$mod->user['user_name'] = 'Anonymous';
	$mod->user['user_level'] = USER_GUEST;
	$mod->user['user_id'] = 1;
	$mod->user['user_issues_page'] = 0;
	$mod->user['user_comments_page'] = 0;
	$mod->user['user_timezone'] = $mod->settings['site_timezone'];
} elseif( $mod->login( 'index.php' ) == -2 ) {
	header( 'HTTP/1.0 403 Forbidden' );

	$xtpl->assign( 'register_link', "{$mod->settings['site_address']}index.php?a=register" );
	$xtpl->assign( 'lost_password', "{$mod->settings['site_address']}index.php?a=register&amp;s=forgotpassword" );
	$xtpl->assign( 'login_url', $mod->settings['site_address'] . 'index.php' );
	$xtpl->parse( 'Index.NavGuest' );

	$xtpl->assign( 'page_title', $mod->title );
	$xtpl->assign( 'meta_desc', $mod->meta_description );
	$xtpl->assign( 'style_link', $style_link );
	$xtpl->assign( 'fail_message', 'Username and password do not match, or this account does not exist. Please try again, or attempt a password recovery if you know the account should exist.' );

	$xtpl->parse( 'Index.BadLogin' );
	$xtpl->parse( 'Index' );
	$xtpl->out( 'Index' );

	$mod->db->close();
	exit();
}

$xtpl->assign( 'footer_text', $mod->settings['footer_text'] );
$xtpl->assign( 'privacypolicy', "{$mod->settings['site_address']}index.php?a=privacypolicy" );

if( !$open && $mod->user['user_level'] < USER_ADMIN ) {
	$xtpl->assign( 'page_title', $mod->title );
	$xtpl->assign( 'meta_desc', $mod->meta_description );
	$xtpl->assign( 'style_link', $style_link );
	$xtpl->assign( 'closed_message', $mod->settings['site_closedmessage'] );

	$xtpl->parse( 'Index.Closed' );
	$xtpl->parse( 'Index' );
	$xtpl->out( 'Index' );

	$mod->db->close();
	exit();
} elseif( $mod->user['user_level'] == USER_SPAM ) {
	$xtpl->assign( 'page_title', $mod->title );
	$xtpl->assign( 'meta_desc', $mod->meta_description );
	$xtpl->assign( 'style_link', $style_link );
	$xtpl->assign( 'spam_message', $mod->settings['site_spamregmessage'] );

	$xtpl->parse( 'Index.SpamReg' );
	$xtpl->parse( 'Index' );
	$xtpl->out( 'Index' );

	$mod->db->close();
	exit();
} elseif( $mod->user['user_level'] > USER_GUEST && ( $mod->user['user_perms'] & PERM_BANNED ) ) {
	$xtpl->assign( 'page_title', $mod->title );
	$xtpl->assign( 'meta_desc', $mod->meta_description );
	$xtpl->assign( 'style_link', $style_link );
	$xtpl->assign( 'spam_message', 'You have been banned from this site.' );

	$xtpl->parse( 'Index.SpamReg' );
	$xtpl->parse( 'Index' );
	$xtpl->out( 'Index' );

	$mod->db->close();
	exit();
} elseif( $showprivacy == true ) {
	$xtpl->assign( 'page_title', $mod->title );
	$xtpl->assign( 'meta_desc', $mod->meta_description );
	$xtpl->assign( 'style_link', $style_link );
	$xtpl->assign( 'global_announcement', $mod->format( $mod->settings['privacy_policy'] ) );

	$xtpl->parse( 'Index.GlobalAnnouncement' );
	$xtpl->parse( 'Index' );
	$xtpl->out( 'Index' );

	$mod->db->close();
	exit();
} else {
	$mod->projectid = 0;
	$mod->navselect = 0;

	$module_output = $mod->execute( $xtpl );

	if( $mod->nohtml ) {
		ob_start( 'ob_gzhandler' );

		echo $module_output;

		@ob_end_flush();
		@flush();
	} else {
		$xtpl->assign( 'meta_desc', $mod->meta_description );
		$xtpl->assign( 'page_title', $mod->title );
		$xtpl->assign( 'style_link', $style_link );

		if( $mod->user['user_level'] > USER_GUEST ) {
			$nav_member = "<li><a href=\"{$mod->settings['site_address']}index.php?a=profile\">My Profile</a></li>\n";
			if( $mod->user['user_level'] == USER_ADMIN )
				$nav_member .= "<li><a href=\"{$mod->settings['site_address']}admin.php\" target=\"_blank\">Admin CP</a></li>\n";
			$nav_member .= "<li><a href=\"{$mod->settings['site_address']}index.php?s=logout\">Log Off</a></li>\n";

			$icon = $mod->display_icon( $mod->user['user_icon'] );

			$nav_member .= "<li><img src=\"$icon\" alt=\"\" /></li>";

			$xtpl->assign( 'nav_member', $nav_member );
			$xtpl->parse( 'Index.NavMember' );
		}
		else {
			$xtpl->assign( 'register_link', "{$mod->settings['site_address']}index.php?a=register" );
			$xtpl->assign( 'lost_password', "{$mod->settings['site_address']}index.php?a=register&amp;s=forgotpassword" );
			$xtpl->assign( 'login_url', $mod->settings['site_address'] . 'index.php' );

			$xtpl->parse( 'Index.NavGuest' );
		}

		if( $mod->settings['rss_enabled'] ) {
			$rss_comments = null;

			if( $mod->projectid > 0 ) {
				$rss = 'index.php?a=rss&amp;proj=' . $mod->projectid;
			} else {
				$rss = 'index.php?a=rss';
				$rss_comments = 'index.php?a=rss&amp;type=comments';
			}

			$rss_feeds = "<link rel=\"alternate\" title=\"{$mod->settings['site_name']} Issues\" href=\"$rss\" type=\"application/rss+xml\" />
  <link rel=\"alternate\" title=\"{$mod->settings['site_name']} Comments\" href=\"$rss_comments\" type=\"application/rss+xml\" />";

			$xtpl->assign( 'rss_feeds', $rss_feeds );
			$xtpl->parse( 'Index.RSS' );
		}

		$all_projects_list = "<option value=\"{$mod->settings['site_address']}\">All Projects</option>";
		$projlist = $mod->db->dbquery( 'SELECT * FROM %pprojects ORDER BY project_name ASC' );
		while( $proj = $mod->db->assoc( $projlist ) )
		{
			if( $proj['project_id'] == $mod->projectid )
				$all_projects_list .= "<option value=\"{$mod->settings['site_address']}index.php?a=issues&amp;project={$proj['project_id']}\" selected=\"selected\">{$proj['project_name']}</option>";
			else
				$all_projects_list .= "<option value=\"{$mod->settings['site_address']}index.php?a=issues&amp;project={$proj['project_id']}\">{$proj['project_name']}</option>";
		}

		$xtpl->assign( 'all_projects_list', $all_projects_list );
		$xtpl->assign( 'issue_action', "{$mod->settings['site_address']}index.php" );

		$proj_navlinks = null;
		if( $mod->projectid == 0 )
			$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}\">Overview</a></li>";
		else
			$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;project={$mod->projectid}\">Overview</a></li>";

		if( $mod->user['user_level'] >= USER_MEMBER ) {
			if( $mod->navselect == 1 )
				$proj_navlinks .= "<li class=\"selected\"><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;project={$mod->projectid}\" title=\"List of issues for this project.\">Issue List</a></li>";
			else
				$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;project={$mod->projectid}\" title=\"List of issues for this project.\">Issue List</a></li>";

			if( $mod->projectid > 0 ) {
				if( $mod->navselect == 2 )
					$proj_navlinks .= "<li class=\"selected\"><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;s=create&amp;p={$mod->projectid}\" title=\"Open a new issue for this project.\">Open New Issue</a></li>";
				else
					$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;s=create&amp;p={$mod->projectid}\" title=\"Open a new issue for this project.\">Open New Issue</a></li>";
			}

			if( $mod->navselect == 4 )
				$proj_navlinks .= "<li class=\"selected\"><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;s=myissues\" title=\"List of issues I have created.\">My Issues</a></li>";
			else
				$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;s=myissues\" title=\"List of issues I have created.\">My Issues</a></li>";

			if( $mod->navselect == 5 )
				$proj_navlinks .= "<li class=\"selected\"><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;s=mywatchlist\" title=\"List of open issues I am watching.\">My Watchlist</a></li>";
			else
				$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;s=mywatchlist\" title=\"List of open issues I am watching.\">My Watchlist</a></li>";
		}

		if( $mod->user['user_level'] >= USER_DEVELOPER ) {
			if( $mod->navselect == 3 )
				$proj_navlinks .= "<li class=\"selected\"><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;s=assigned\" title=\"List of issues assigned to me.\">My Assignments</a></li>";
			else
				$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}index.php?a=issues&amp;s=assigned\" title=\"List of issues assigned to me.\">My Assignments</a></li>";

			$stmt = $mod->db->prepare( 'SELECT COUNT(reopen_id) count FROM %preopen' );

			$mod->db->execute_query( $stmt );

			$t_result = $stmt->get_result();
			$total = $t_result->fetch_assoc();

			$stmt->close();

			if( $total && $total['count'] > 0 ) {
				if( $mod->navselect == 6 )
					$proj_navlinks .= "<li class=\"selected\"><a href=\"{$mod->settings['site_address']}index.php?a=reopen\" title=\"List of issues requesting to be reopened.\">Reopen Requests</a></li>";
				else
					$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}index.php?a=reopen\" title=\"List of issues requesting to be reopened.\">Reopen Requests</a></li>";
			}
		}

		if( $mod->navselect == 7 )
			$proj_navlinks .= "<li class=\"selected\"><a href=\"{$mod->settings['site_address']}index.php?a=search\" title=\"Search\">Search</a></li>";
		else
			$proj_navlinks .= "<li><a href=\"{$mod->settings['site_address']}index.php?a=search\" title=\"Search\">Search</a></li>";

		$xtpl->assign( 'proj_navlinks', $proj_navlinks );
		$xtpl->parse( 'Index.AllProjects' );

		$xtpl->assign( 'module_output', $module_output );

		$google = null;
  		if( $mod->settings['site_analytics'] ) {
			$google = $mod->settings['site_analytics'];
                }

		$xtpl->assign( 'google', $google );

		if( !$open ) {
			$xtpl->assign( 'closed_message', htmlspecialchars( $mod->settings['site_closedmessage'] ) );
			$xtpl->parse( 'Index.Closed' );
		}

		if( $mod->user['user_level'] == USER_ADMIN ) {
			$spamstored = $mod->db->quick_query( 'SELECT COUNT(spam_id) count FROM %pspam' );
			if( $spamstored['count'] > 0 ) {
				$t = 'are';
				$s = 's';
				if( $spamstored['count'] == 1 ) {
					$t = 'is';
					$s = '';
				}
				$spam_message = 'There ' . $t . ' ' . $spamstored['count'] . ' item' . $s . ' currently flagged as spam.';
				$spam_link = $mod->settings['site_address'] . 'index.php?a=spam_control';

				$xtpl->assign( 'spam_link', $spam_link );
				$xtpl->assign( 'spam_message', $spam_message );
				$xtpl->parse( 'Index.Spam' );
			}
		}

		if( !empty( $mod->settings['global_announce'] ) ) {
			$announcement = $mod->format( $mod->settings['global_announce'], ISSUE_BBCODE );

			$xtpl->assign( 'global_announcement', $announcement );
			$xtpl->parse( 'Index.GlobalAnnouncement' );
		}

		// Update visit time for current user. Just not Anonymous though.
		if( $mod->user['user_level'] > USER_GUEST ) {
			$stmt = $mod->db->prepare( 'UPDATE %pusers SET user_last_visit=? WHERE user_id=?' );

			$stmt->bind_param( 'ii', $mod->time, $mod->user['user_id'] );
			$mod->db->execute_query( $stmt );
			$stmt->close();
		}

		// No need for members to see this.
		if( $mod->user['user_level'] > USER_MEMBER ) {
			$time_now  = explode( ' ', microtime() );
			$time_exec = round( $time_now[1] + $time_now[0] - $time_start, 4 );
			$queries = $mod->db->queries;
			$queries_exec = $mod->db->queries_exec;
			$xtpl->assign( 'page_generated', "Page generated in $time_exec seconds. $queries queries made in $queries_exec seconds." );
			$xtpl->parse( 'Index.PageStats' );
		}

		$xtpl->parse( 'Index' );

		ob_start( 'ob_gzhandler' );

		$xtpl->out( 'Index' );

		@ob_end_flush();
		@flush();
	}
	error_reporting( 0 ); // The active users info isn't important enough to care about errors with it.
	require_once( 'modules/active_users.php' );
	do_active( $mod, $module );
}
$mod->db->close();
?>