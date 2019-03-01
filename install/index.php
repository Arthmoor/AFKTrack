<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

define( 'AFKTRACK', true );
define( 'AFKTRACK_INSTALLER', true );

error_reporting(E_ALL);

require_once( '../settings.php' );

$mode = null;
if( isset($_GET['mode']) ) {
	$mode = $_GET['mode'];
}

if ( isset( $_POST['db_type'] ) )
	$settings['db_type'] = $_POST['db_type'];
elseif( $mode != 'upgrade' )
	$settings['db_type'] = 'database';

$settings['include_path'] = '..';
require $settings['include_path'] . '/lib/' . $settings['db_type'] . '.php';
require $settings['include_path'] . '/global.php';

function execute_queries($queries, $db)
{
	foreach ($queries as $query)
	{
		$db->dbquery($query);
	}
}

function check_writeable_files()
{
	// Need to check to see if the necessary directories are writeable.
	$writeable = true;
	$fixme = '';

	if(!is_writeable('../settings.php')) {
		$fixme .= "settings.php<br />";
		$writeable = false;
	}
	if(!is_writeable('../files')) {
		$fixme .= "../files/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/attachments')) {
		$fixme .= "../files/attachments/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/banners')) {
		$fixme .= "../files/banners/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/banners/afktracklogo.png')) {
		$fixme .= "../files/banners/afktracklogo.png<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/emoticons')) {
		$fixme .= "../files/emoticons/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/posticons')) {
		$fixme .= "../files/posticons/<br />";
		$writeable = false;
	}

	if( !$writeable ) {
		echo "The following files and directories are missing or not writeable. Some functions will be impaired unless these are changed to 0777 permission.<br /><br />";
                echo "<span style='color:red'>" . $fixme . "</span>";
	} else {
		echo "<span style='color:green'>Directory and file permissions are all OK.</span>";
	}
}

function get_sql_version()
{
	$output = shell_exec( 'mysql -V' );

	preg_match( '@[0-9]+\.[0-9]+\.[0-9]+@', $output, $version );

	return $version[0];
}

if (!isset($_GET['step'])) {
	$step = 1;
} else {
	$step = $_GET['step'];
}

if ($mode) {
	require './' . $mode . '.php';
	$afktrack = new $mode;
} else {
	$afktrack = new module;
}
	$afktrack->settings = $settings;
	$afktrack->self = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'index.php';
	$failed = false;

	$php_version = PHP_VERSION;
	$os = defined('PHP_OS') ? PHP_OS : 'unknown';
	$register_globals = get_cfg_var('register_globals') ? 'on' : 'off';
	$server = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown';

	if( version_compare( $php_version, "5.5.0", "<" ) ) {
		echo 'Your PHP version is ' . $php_version . '.<br />PHP 5.5.0 and higher is required.';
		$failed = true;
	}

	$db_fail = 0;
	$mysqli = false;

	if( !extension_loaded( 'mysqli' ) ) {
		$db_fail++;
	} else {
		$mysqli = true;
	}

	if( $db_fail > 0 )
	{
		if( $failed ) { // If we have already shown a message, show the next one two lines down
			echo '<br /><br />';
		}

		echo 'Your PHP installation does not support MySQLi.';
		$failed = true;
	}

	$sql_version = 'Unknown';
	if( $mysqli ) {
		$sql_version = get_sql_version();

		if( version_compare( $sql_version, "5.6.0", "<" ) ) {
			if( $failed ) { // If we have already shown a message, show the next one two lines down
				echo '<br /><br />';
			}
			echo 'Your MySQL version is not supported.<br /> Your version: ' . $sql_version . '.<br /> Required: 5.6.0 or higher.';
			$failed = true;
		}
	}

	if( $failed ) {
		echo "<br /><br /><strong>To run AFKTrack and other advanced PHP software, the above error(s) must be fixed by you or your web host.</strong>";
		exit;
	}

	if ($mysqli) {
		$mysqli_client = '<li>MySQL Version: ' . $sql_version . '</li><hr />';
	} else {
		$mysqli_client = '';
	}

	echo "<!DOCTYPE html>
<html lang=\"en-US\">
<head>
 <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
 <title>AFKTrack Installer</title>
 <link rel=\"stylesheet\" type=\"text/css\" href=\"../skins/Default/admincp.css\" />
</head>

<body>
 <div id='container'>
  <div id='header' style='height:152px;'>
   <div id='company'>
    <div class='logo'><img src='/files/banners/afktracklogo.png' alt='' /></div>
    <div class='title'><h1>AFKTrack Installer {$afktrack->version}</h1></div>
   </div>
  </div>

  <div id='blocks'>
   <div class='block'>
    <div class='title'><img src='/skins/Default/images/wrench.png' alt='' /> System Information</div>
    <ul>
     <li>PHP Version: $php_version</li><hr />
     <li>Operating System: $os</li><hr />
     <li>Register globals: $register_globals</li><hr />
     <li>Server Software: $server</li><hr />
     $mysqli_client
    </ul>
   </div>
  </div>

  <div id='main'>";

	switch( $mode )
	{
		default:
			include "choose_install.php";
			break;
		case 'new_install':
			$afktrack->install( $step, $mysqli );
			break;
		case 'upgrade':
			$afktrack->upgrade_site( $step );
			break;
	}

	echo "   <div id='bottom'>&nbsp;</div>
  </div>
  <div id='footer'>
   <a href='https://github.com/Arthmoor/AFKTrack'>AFKTrack</a> {$afktrack->version} &copy; 2017-2018 Roger Libiez [<a href='https://www.iguanadons.net'>Arthmoor</a>]
  </div>
 </div>
</body>
</html>";
?>