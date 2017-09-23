<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2018 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

// Issue flags
define( 'ISSUE_BBCODE', 1 );
define( 'ISSUE_BREAKS', 2 );
define( 'ISSUE_EMOTICONS', 4 );
define( 'ISSUE_CLOSED', 8 );
define( 'ISSUE_RESTRICTED', 16 );
define( 'ISSUE_SPAM', 32 );

// Comment types
define( 'COMMENT_ISSUE', 0 );

// User levels
define( 'USER_GUEST', 1 );
define( 'USER_SPAM', 2 );
define( 'USER_VALIDATING', 3 );
define( 'USER_MEMBER', 4 );
define( 'USER_DEVELOPER', 5 );
define( 'USER_ADMIN', 6 );

define( 'PERM_ICON', 1 );

// Spam types
define( 'SPAM_REGISTRATION', 1 );
define( 'SPAM_ISSUE', 2 );
define( 'SPAM_COMMENT', 3 );

// Attachment errors
define( 'UPLOAD_TOO_LARGE', 1 );
define( 'UPLOAD_NOT_ALLOWED', 2 );
define( 'UPLOAD_FAILURE', 3 );
define( 'UPLOAD_SUCCESS', 4 );

define( 'AFKTRACK_QUERY_ERROR', 6 ); // For SQL errors to be reported properly by the error handler.

class module
{
	var $version		= 1.00;
	var $title		= null;
	var $meta_description	= null;
	var $skin		= 'Default';
	var $skins		= array();
	var $nohtml		= false;
	var $settings		= array();
	var $time		= 0;
	var $db			= null;
	var $server		= array();
	var $cookie		= array();
	var $post		= array();
	var $get		= array();
	var $files		= array();
	var $templates		= array();
	var $emoticons		= array();	  // Array of emoticons used for processing post formatting
	var $ip			= '127.0.0.1';
	var $agent		= 'Unknown';
	var $referrer		= 'Unknown';
	var $user		= array();
	var $xtpl		= null;
	var $icon_dir		= null;
	var $file_dir		= null;
	var $emote_dir		= null;
	var $banner_dir		= null;

	function module( $db = null )
	{
		$this->time	= time();
		$this->server	= $_SERVER;
		$this->cookie	= $_COOKIE;
		$this->post	= $_POST;
		$this->get	= $_GET;
		$this->files	= $_FILES;

		$this->db = $db;

		$this->ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

		$this->agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '-';
		$this->agent = substr($this->agent, 0, 254); // Cut off after 255 characters.

		$this->referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '-';
		$this->referrer = substr($this->agent, 0, 254); // Cut off after 255 characters.

		$this->file_dir = 'files/attachments/';
		$this->icon_dir = 'files/posticons/';
		$this->emote_dir = 'files/emoticons/';
		$this->banner_dir = 'files/banners/';

		if( version_compare( PHP_VERSION, "5.5.0", "<" ) ) {
			die( 'PHP version does not meet minimum requirements. Contact your system administrator.' );
		}
	}

	function title( $title )
	{
		$this->title .= ' &raquo; ' . htmlspecialchars($title);
	}

	function meta_description( $desc )
	{
		if( $desc != null ) {
			$desc = htmlspecialchars( $desc );
			$this->meta_description = "<meta name=\"description\" content=\"$desc\" />";
		}
		else
			$this->meta_description = null;
	}

	function set_skin( $skin = null )
	{
		$this->skins = $this->get_skins();
		if ( !$skin )
			$skin = $this->settings['site_defaultskin'];

		$skin = isset( $this->cookie['skin'] ) ? $this->cookie['skin'] : $skin;

		if ( !$skin || !in_array($skin,$this->skins) )
			return;
		$this->skin = $skin;

		setcookie($this->settings['cookie_prefix'] . 'skin', $skin, $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
	}

	function get_skins()
	{
		$skins = array();
		if ( $dh = opendir('./skins/') )
		{
			while( ( $item = readdir($dh) ) !== false )
				if ( $item[0] != '.' && is_dir('./skins/' . $item) )
					$skins[] = $item;
			closedir( $dh );
		}
		return $skins;
	}

	function load_emoticons()
	{
		$emotes = array();
		$dbemotes = $this->db->dbquery('SELECT * FROM %pemoticons');
		while( $e = $this->db->assoc($dbemotes) )
		{
			if( $e['emote_clickable'] == 1 )
				$emotes['click_replacement'][$e['emote_string']] = '<img src="' . $this->settings['site_address'] . 'files/emoticons/' . $e['emote_image'] . '" alt="' . $e['emote_string'] . '" />';
			else
				$emotes['replacement'][$e['emote_string']] = '<img src="' . $this->settings['site_address'] . 'files/emoticons/' . $e['emote_image'] . '" alt="' . $e['emote_string'] . '" />';
		}
		return $emotes;
	}

	function load_settings($settings)
	{
		// Converts old serialized array into a json encoded array due to potential exploits in the PHP serialize/unserialize functions.
		$settings_array = array();

		$sets = $this->db->quick_query( 'SELECT settings_version, settings_value FROM %psettings LIMIT 1' );

		if( !is_array( $sets ) )
			return $settings;

		$settings_array = array_merge( $settings, json_decode($sets['settings_value'], true) );

		return $settings_array;
	}

	function save_settings()
	{
		$default_settings = array( 'db_name', 'db_user', 'db_pass', 'db_host', 'db_pre', 'db_type', 'error_email' );

		$settings = array();

		foreach( $this->settings as $set => $val )
			if ( !in_array( $set, $default_settings ) )
				$settings[$set] = $val;

		$stmt = $this->db->prepare( 'UPDATE %psettings SET settings_value=?' );

		$encoded = json_encode($settings);
		$stmt->bind_param( 's', $encoded );
		$this->db->execute_query( $stmt );
		$stmt->close();
	}

	function logout()
	{
		setcookie($this->settings['cookie_prefix'] . 'user', '', $this->time - 9000, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
		setcookie($this->settings['cookie_prefix'] . 'pass', '', $this->time - 9000, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

		unset($_SESSION['user']);
		unset($_SESSION['pass']);

		$_SESSION = array();
		header( 'Location: index.php' );
	}

	function login( $page )
	{
		if( isset($this->post['login_name']) && isset($this->post['login_password']) ) {
			$username = $this->post['login_name'];
			$password = $this->post['login_password'];

			$stmt = $this->db->prepare( 'SELECT * FROM %pusers WHERE user_name=? LIMIT 1' );

			$stmt->bind_param( 's', $username );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$user = $result->fetch_assoc();

			$stmt->close();

			if( !$user )
				return false;

			if( !isset($user['user_id']) )
				return false;

			if( !password_verify( $password, $user['user_password'] ) )
				return false;

			$hashcheck = $this->check_hash_update( $password, $user['user_password'] );
			if( $hashcheck != $user['user_password'] ) {
				$user['user_password'] = $hashcheck;

				$stmt = $this->db->prepare( 'UPDATE %pusers SET user_password=? WHERE user_id=?'  );

				$stmt->bind_param( 'si', $user['user_password'], $user['user_id'] );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}

			setcookie($this->settings['cookie_prefix'] . 'user', $user['user_id'], $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
			setcookie($this->settings['cookie_prefix'] . 'pass', $user['user_password'], $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

			$this->user = $user;
			header( 'Location: ' . $page );
		} else if(isset($this->cookie[$this->settings['cookie_prefix'] . 'user']) && isset($this->cookie[$this->settings['cookie_prefix'] . 'pass'])) {
			$cookie_user = intval($this->cookie[$this->settings['cookie_prefix'] . 'user']);
			$cookie_pass = $this->cookie[$this->settings['cookie_prefix'] . 'pass'];

			$stmt = $this->db->prepare( 'SELECT * FROM %pusers WHERE user_id=? AND user_password=?' );

			$stmt->bind_param( 'ss', $cookie_user, $cookie_pass );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$user = $result->fetch_assoc();

			$stmt->close();

			if( !$user || !isset($user['user_id']) )
				return false;
		} else {
			return false;
		}

		$this->user = $user;
		return true;
	}

	function format( $in, $options = POST_BBCODE )
	{
		return $this->bbcode->format( $in, $options );
	}

	function closed_content( $content )
	{
		// All comments disabled
		if( $this->settings['global_comments'] == false )
			return true;

		// Manual close. Always return true regardless of other settings.
		if( ( $content['issue_flags'] & ISSUE_CLOSED ) )
			return true;

		// Didn't meet any other check, so the issue must still be open.
		return false;
	}

	function message( $title, $message, $link_name = null, $link = null, $delay = 4 )
	{
		if( $link && $delay > 0 )
			@header('Refresh: '.$delay.';url=' . $link);

		if( $link_name )
			$link_name = '<div style="text-align:center"><a href="'. $link . '">' . $link_name . '</a></div>';

		$this->xtpl->assign( 'title', $title );
		$this->xtpl->assign( 'message', $message );
		$this->xtpl->assign( 'link_name', $link_name );
		$this->xtpl->parse( 'Index.Message' );

		return '';
	}

	function make_links( $projid, $count, $min, $num )
	{
		if( $num < 1 ) $num = 1; // No more division by zero please.

		$current = ceil( $min / $num );
		$string  = null;
		$pages   = ceil( $count / $num );
		$end     = ($pages - 1) * $num;
		$link = '';

		if( $projid > 0 )
			$link = "{$this->settings['site_address']}index.php?a=issues&amp;project=$projid";
		elseif( $projid == -1 ) // Yeah I know, this is an ugly, ugly hack.
			$link = "{$this->settings['site_address']}admin.php?a=users";
		elseif( $projid == -2 ) // Oh god, another ugly hack!
			$link = "{$this->settings['site_address']}admin.php?a=attachments";
		else
			$link = "{$this->settings['site_address']}?a=issues";

		if( $this->navselect == 3 )
			$link = "{$this->settings['site_address']}?a=issues&amp;s=assigned";
		if( $this->navselect == 4 )
			$link = "{$this->settings['site_address']}?a=issues&amp;s=myissues";

		// check if there's previous articles
		if($min == 0) {
			$startlink = '&lt;&lt;';
			$previouslink = '';
		} else {
			$startlink = "<a href=\"$link&amp;min=0&amp;num=$num\">&lt;&lt;</a>";
			$prev = $min - $num;
			$previouslink = "<a href=\"$link&amp;min=$prev&amp;num=$num\">prev</a> ";
		}

		// check for next/end
		if(($min + $num) < $count) {
			$next = $min + $num;
  			$nextlink = "<a href=\"$link&amp;min=$next&amp;num=$num\">next</a>";
  			$endlink = "<a href=\"$link&amp;min=$end&amp;num=$num\">&gt;&gt;</a>";
		} else {
 			$nextlink = '';
  			$endlink = '&gt;&gt;';
		}

		// setup references
		$b = $current - 2;
		$e = $current + 2;

		// set end and beginning of loop
		if ($b < 0) {
  			$e = $e - $b;
  			$b = 0;
		}

		// check that end coheres to the issues
		if ($e > $pages - 1) {
  			$b = $b - ($e - $pages + 1);
  			$e = ($pages - 1 < $current) ? $pages : $pages - 1;
  			// b may need adjusting again
  			if ($b < 0) {
				$b = 0;
			}
		}

 		// ellipses
		if ($b != 0) {
			$badd = '...';
		} else {
			$badd = '';
		}

		if (($e != $pages - 1) && $count) {
			$eadd = '...';
		} else {
			$eadd = '';
		}

		// run loop for numbers to the page
		for ($i = $b; $i < $current; $i++)
		{
			$where = $num * $i;
			$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num\">" . ($i + 1) . '</a>';
		}

		// add in page
		$string .= ', <strong>' . ($current + 1) . '</strong>';

		// run to the end
		for ($i = $current + 1; $i <= $e; $i++)
		{
			$where = $num * $i;
			$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num\">" . ($i + 1) . '</a>';
		}

		// get rid of preliminary comma.
		if (substr($string, 0, 1) == ',') {
			$string = substr($string, 1);
		}

		if( $pages == 1 ) {
			$string = '';
			$startlink = '';
			$previouslink = '';
			$nextlink = '';
			$endlink = '';
		}

		$newmin = $min + 1;
		$newnum = $min + $num;

		if( $num > $count )
			$newnum = $count;

		if( $min + $num > $count )
			$newnum = $count;

		if( $projid > -1 )
			$showing = "Showing Issues $newmin - $newnum of $count ";
		elseif( $projid == -1 )
			$showing = "Showing Users $newmin - $newnum of $count ";
		else
			$showing = "Showing Attachments $newmin - $newnum of $count ";

		return "$showing $startlink $previouslink $badd $string $eadd $nextlink $endlink";
	}

	function get_file_icon( $type )
	{
		$file_icon = '/images/disk.png';

		switch( $type )
		{
			case 'jpg':
			case 'png':
			case 'bmp':
			case 'gif':
				$file_icon = '/images/photos.png';
				break;

			case 'txt':
			case 'log':
				$file_icon = '/images/page_white_text.png';
				break;

			case 'psc':
				$file_icon = '/images/script_code_red.png';
				break;

			case 'esp':
			case 'esm':
			case 'esl':
			case 'ess':
			case 'fos':
				$file_icon = '/images/ck.png';
				break;

			case '7z':
			case 'rar':
			case 'zip':
				$file_icon = '/images/zip.png';
				break;

			case 'mov':
				$file_icon = '/images/film.png';
				break;

			default: break;
		}

		return $file_icon;
	}

	/**
	 * Deliver an error message.
	 *
	 * @param string $message The error message to be delivered.
	 * @param bool $send404 Should this message result in a "Page not found" error?
	 * 
	 * @author Arthmoor
	 * @since 1.0
	 **/
	function error( $message, $errorcode = 0 )
	{
		$error_text = 'Unknown Error';

		switch( $errorcode )
		{
			case 403:
				$error_text = '403 Forbidden';
				header('HTTP/1.0 403 Forbidden');
				break;
			case 404:
				$error_text = '404 Not Found';
				header('HTTP/1.0 404 Not Found');
				$message .= '<br />If you followed a link from an external resource, you should notify the webmaster there that the link may be broken.';
				break;
			default: break;
		}
		return $this->message( 'Error: ' . $error_text, $message );
	}

	function createthumb( $name, $filename, $ext, $new_w, $new_h )
	{
		$system = explode( '.', $name );
		$src_img = null;

		if( preg_match( '/jpg|jpeg/', $ext ) )
			$src_img = imagecreatefromjpeg($name);
		else if ( preg_match( '/png/', $ext ) )
			$src_img = imagecreatefrompng($name);
		else if ( preg_match( '/gif/', $ext ) )
			$src_img = imagecreatefromgif($name);
		$old_x = imageSX( $src_img );
		$old_y = imageSY( $src_img );

		if ($old_x > $old_y)
		{
			$thumb_w = $new_w;
			$thumb_h = $old_y * ( $new_h / $old_x );
		}
		if ($old_x < $old_y)
		{
			$thumb_w = $old_x * ( $new_w / $old_y );
			$thumb_h = $new_h;
		}
		if ($old_x == $old_y)
		{
			$thumb_w = $new_w;
			$thumb_h = $new_h;
		}

		$dst_img = ImageCreateTrueColor( $thumb_w, $thumb_h );
		imagecopyresampled( $dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y );
		if (preg_match( '/png/', $ext ) )
			imagepng( $dst_img, $filename );
		else if ( preg_match( '/jpg|jpeg/', $ext ) )
			imagejpeg( $dst_img, $filename );
		else
			imagegif( $dst_img, $filename );
		imagedestroy( $dst_img );
		imagedestroy( $src_img );
		return array( 'width' => $old_x, 'height' => $old_y );
	}

	function valid_user( $name )
	{
		return !preg_match( '/[^a-zA-Z0-9_\\@]/', $name );
	}

	function is_email($addr)
	{
		$p = '/^[a-z0-9!#$%&*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
		$p.= '@([-a-z0-9]+\.)+([a-z]{2,3}';
		$p.= '|info|asia|arpa|aero|coop|name|jobs|mobi|museum|travel)$/ix';
		return preg_match($p, $addr);
	}

	function display_icon($icon)
	{
		$url = $this->settings['site_address'] . $this->icon_dir . $icon;

		if( $this->is_email($icon) ) {
			$url = 'https://secure.gravatar.com/avatar/';
			$url .= md5( strtolower( trim($icon) ) );
			$url .= "?s={$this->settings['site_icon_width']}&amp;r=pg";
		}

		return $url;
	}

	/**
	 * Hash a given string into a password suitable for database use
	 *
	 * @param string $pass The supplied password to hash
	 * @author Arthmoor
	 * @since 1.0
	 */
	function afktrack_password_hash($pass)
	{
		$options = [ 'cost' => 12, ];
		$newpass = password_hash( $pass, PASSWORD_DEFAULT, $options );

		return $newpass;
	}

	/**
	 * Check to see if a given password has needs to be updated to a new hash algorithm
	 *
	 * @param string $password The unencrypted password to rehash
	 * @param string $hash The hashed password to check
	 * @author Arthmoor
	 * @since 1.0
	 */
	function check_hash_update( $password, $hash )
	{
		$options = [ 'cost' => 12, ];

		if( password_needs_rehash( $hash, PASSWORD_DEFAULT, $options ) ) {
			$newhash = password_hash( $password, PASSWORD_DEFAULT, $options );

			$hash = $newhash;
		}
		return $hash;
	}

	/**
	 * Generates a random pronounceable password
	 *
	 * @param int $length Length of password
	 * @author http://www.zend.com/codex.php?id=215&single=1
	 * @since 1.0
	 */
	function generate_pass($length)
	{
		$vowels = array('a', 'e', 'i', 'o', 'u');
		$cons = array('b', 'c', 'd', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'u', 'v', 'w', 'tr',
		'cr', 'br', 'fr', 'th', 'dr', 'ch', 'ph', 'wr', 'st', 'sp', 'sw', 'pr', 'sl', 'cl');

		$num_vowels = count($vowels);
		$num_cons = count($cons);

		$password = '';

		for ($i = 0; $i < $length; $i++)
		{
			$password .= $cons[rand(0, $num_cons - 1)] . $vowels[rand(0, $num_vowels - 1)];
		}

		return substr($password, 0, $length);
	}

	function cidrmatch( $cidr )
	{
		$ip = decbin( ip2long($this->ip) );
		list( $cidr1, $cidr2, $cidr3, $cidr4, $bits ) = sscanf( $cidr, '%d.%d.%d.%d/%d' );
		$cidr = decbin( ip2long( "$cidr1.$cidr2.$cidr3.$cidr4" ) );
		for( $i = strlen($ip); $i < 32; $i++ )
			$ip = "0$ip";
		for( $i = strlen($cidr); $i < 32; $i++ )
			$cidr = "0$cidr";
		return !strcmp( substr($ip, 0, $bits), substr($cidr, 0, $bits) );
	}

	function is_ipv6( $ip )
	{
		return( substr_count( $ip, ":" ) > 0 && substr_count( $ip, "." ) == 0 );
	}

	function ip_banned( )
	{
		if ( isset($this->settings['banned_ips']) )
		{
			foreach ($this->settings['banned_ips'] as $ip)
			{
				if( $this->is_ipv6( $this->ip ) ) {
					if( !strcasecmp( $ip, $this->ip ) )
						return true;
				}

				if ( ( strstr($ip, '/') && $this->cidrmatch($ip) ) || !strcasecmp( $ip, $this->ip ) )
					return true;
			}
		}
		return false;
	}

	function ReverseIPOctets($inputip)
	{
		$ipoc = explode( ".", $inputip );
		return $ipoc[3] . "." . $ipoc[2] . "." . $ipoc[1] . "." . $ipoc[0];
	}

	function IsTorExitPoint( $ip )
	{
		if( gethostbyname( $this->ReverseIPOctets($ip) . "." . $_SERVER['SERVER_PORT'] . "." . $this->ReverseIPOctets($_SERVER['SERVER_ADDR']) . ".ip-port.exitlist.torproject.org" ) == "127.0.0.2" )
		{
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Generates a random security token for forms.
	 *
	 * @author Arthmoor
	 * @return string Generated security token.
	 * @since 1.0
	 */
	function generate_token()
	{
		$token = md5(uniqid(mt_rand(), true));
		$_SESSION['token'] = $token;
		$_SESSION['token_time'] = $this->time + 7200; // Token is valid for 2 hours.

		return $token;
	}

	/**
	 * Checks to be sure a submitted security token matches the one the form is expecting.
	 *
	 * @author Arthmoor
	 * @return false if invalid, true if valid
	 * @since 1.0
	 */
	function is_valid_token()
	{
		if( !isset($_SESSION['token']) || !isset($_SESSION['token_time']) || !isset($this->post['token']) ) {
			return false;
		}

		if( $_SESSION['token'] != $this->post['token'] ) {
			return false;
		}

		$age = $this->time - $_SESSION['token_time'];

		if( $age > 7200 ) // Token is valid for 2 hours.
			return false;

		return true;
	}
}

function get_backtrace()
{
	$backtrace = debug_backtrace();
	$out = "Backtrace:\n\n";

	foreach( $backtrace as $trace => $frame )
	{
		// 2 is the file that actually died. We don't need to list the error handlers in the trace.
		if( $trace < 2 ) {
			continue;
		}
		$args = array();

		if( $trace > 2 ) { // The call in the error handler is irrelevent anyway, so don't bother with the arg list
			if ( isset( $frame['args'] ) )
			{
				foreach( $frame['args'] as $arg )
				{
					if ( is_array( $arg ) && array_key_exists( 0, $arg ) && is_string( $arg[0] ) ) {
						$argument = htmlspecialchars( $arg[0] );
					} elseif( is_string( $arg ) ) {
						$argument = htmlspecialchars( $arg );
					} else {
						$argument = NULL;
					}
					$args[] = "'{$argument}'";
				}
			}
		}

		$frame['class'] = (isset($frame['class'])) ? $frame['class'] : '';
		$frame['type'] = (isset($frame['type'])) ? $frame['type'] : '';
		$frame['file'] = (isset($frame['file'])) ? $frame['file'] : '';
		$frame['line'] = (isset($frame['line'])) ? $frame['line'] : '';

		$func = '';
		$arg_list = implode(", ", $args);
		if( $trace == 2 ) {
			$func = 'See above for details.';
		} else {
			$func = htmlspecialchars($frame['class'] . $frame['type'] . $frame['function']) . "(" . $arg_list . ")";
		}

		$out .= 'File: ' . $frame['file'] . "\n";
		$out .= 'Line: ' . $frame['line'] . "\n";
		$out .= 'Call: ' . $func . "\n\n";
	}
	return $out;
}

function error($type, $message, $file, $line = 0)
{
	global $settings;

	if( !(error_reporting() & $type) )
		return;

	switch($type)
	{
	case E_USER_ERROR:
		$type_str = 'Error';
		break;

	case E_WARNING:
	case E_USER_WARNING:
		$type_str = 'Warning';
		break;

	case E_NOTICE:
	case E_USER_NOTICE:
		$type_str = 'Notice';
		break;

	case E_STRICT:
		$type_str = 'Strict Standards';
		break;

	case AFKTRACK_QUERY_ERROR:
		$type_str = 'Query Error';
		break;

	default:
		$type = -1;
		$type_str = 'Unknown Error';
	}

	$details = null;

	$backtrace = get_backtrace();

	if ($type != AFKTRACK_QUERY_ERROR) {
		if (strpos($message, 'mysql_fetch_array(): supplied argument') === false) {
			$lines = null;
			$details2 = null;

			if (file_exists($file)) {
				$lines = file($file);
			}

			if ($lines) {
				$details2 = "Code:\n" . error_getlines($lines, $line);
			}
		} else {
			$details2 = "MySQL Said:\n" . mysql_error() . "\n";
		}

		$details .= "$type_str [$type]:\n
		The error was reported on line $line of $file\n\n
		$details2";
	} else {
		$details .= "$type_str [$line]:\n
		This type of error is reported by MySQL.\n\n
		Query:\n$file\n";
	}

	// IIS does not use $_SERVER['QUERY_STRING'] in the same way as Apache and might not set it
	if (isset($_SERVER['QUERY_STRING'])) {
		$querystring = str_replace( '&', '&amp;', $_SERVER['QUERY_STRING'] );
	} else {
		$querystring = '';
	}

	// DO NOT allow this information into the error reports!!!
	$details = str_replace( $settings['db_name'], '****', $details );
	$details = str_replace( $settings['db_pass'], '****', $details );
	$details = str_replace( $settings['db_user'], '****', $details );
	$details = str_replace( $settings['db_host'], '****', $details );
	$backtrace = str_replace( $settings['db_name'], '****', $backtrace );
	$backtrace = str_replace( $settings['db_pass'], '****', $backtrace );
	$backtrace = str_replace( $settings['db_user'], '****', $backtrace );
	$backtrace = str_replace( $settings['db_host'], '****', $backtrace );

	// Don't send it if this isn't available. Spamming mail servers is a bad bad thing.
	// This will also email the user agent string, in case errors are being generated by evil bots.
	if( isset($settings['error_email']) ) {
		$headers = "From: Your AFKTrack Site <{$settings['error_email']}>\r\n" . "X-Mailer: PHP/" . phpversion();

		$agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

		$error_report = "AFKTrack has exited with an error!\n";
		$error_report .= "The error details are as follows:\n\nURL: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . "?" . $querystring . "\n";
		$error_report .= "Querying user agent: " . $agent . "\n";
		$error_report .= "Querying IP: " . $ip . "\n\n";
		$error_report .= $message . "\n\n" . $details . "\n\n" . $backtrace;
		$error_report = str_replace( "&nbsp;", " ", html_entity_decode($error_report) );

		@mail( $settings['error_email'], "[AFKTrack] Fatal Error Report", $error_report, $headers );
	}

	header('HTTP/1.0 500 Internal Server Error');
	exit( "
<!DOCTYPE html>
<html lang=\"en-US\">
 <head>
  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
  <meta name=\"robots\" content=\"noodp\" />
  <meta name=\"generator\" content=\"AFKTrack\" />
  <title>Fatal Error</title>
  <link rel=\"stylesheet\" type=\"text/css\" href=\"./skins/Default/styles.css\" />
 </head>
 <body>
 <div id=\"container\">
  <div id=\"header\">
   <div id=\"company\">
    <div class=\"logo\"><img src=\"./files/afktracklogo.png\" alt=\"\" /></div>
    <div class=\"title\">
     <h1>AFKTrack: Fatal Error</h1>
    </div>
   </div>
   <ul id=\"navigation\">
    <li><a href=\"/\">Home</a></li>
   </ul>
  </div>

  <div id=\"fullscreen\">
   <div class=\"article\">
    <div class=\"title\" style=\"color:yellow\">Fatal Error</div>
    The AFKTrack software has experienced a fatal error and is unable to process your request at this time. Unfortunately any data you may have sent has been lost, and we apologize for the inconvenience.<br /><br />
    A detailed report on exactly what went wrong has been sent to the site owner and will be investigated and resolved as quickly as possible.
   </div>
  </div>

  <div id=\"bottom\">&nbsp;</div>
 </div>
 <div id=\"footer\">Powered by AFKTrack &copy; 2017-2018 Roger Libiez [<a href=\"https://github.com/Arthmoor/AFKTrack\">GitHub</a>]</div>
</body>
</html>" );
}

function error_getlines($lines, $line)
{
	$code    = null;
	$padding = ' ';
	$previ   = $line-3;
	$total_lines = count($lines);

	for ($i = $line - 3; $i <= $line + 3; $i++)
	{
		if ((strlen($previ) < strlen($i)) && ($padding == ' ')) {
			$padding = null;
		}

		if (($i < 1) || ($i > $total_lines)) {
			continue;
		}

		$codeline = rtrim(htmlentities($lines[$i-1]));
		$codeline = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $codeline);
		$codeline = str_replace(' ', '&nbsp;', $codeline);

		$code .= $i . $padding . $codeline . "\n";

		$previ = $i;
	}
	return $code;
}
?>