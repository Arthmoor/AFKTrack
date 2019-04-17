<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

// Issue flags
define( 'ISSUE_BBCODE', 1 );
define( 'ISSUE_BREAKS', 2 );
define( 'ISSUE_EMOTICONS', 4 );
define( 'ISSUE_CLOSED', 8 );
define( 'ISSUE_RESTRICTED', 16 );
define( 'ISSUE_SPAM', 32 );
define( 'ISSUE_REOPEN_REQUEST', 64 );
define( 'ISSUE_REOPEN_RESOLVED', 128 );

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
define( 'PERM_BANNED', 2 );

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
	public $version	         = 1.10;
	public $title            = null;
	public $meta_description = null;
	public $skin             = 'Default';
	public $skins            = array();
	public $nohtml           = false;
	public $settings         = array();
	public $time             = 0;
	public $db               = null;
	public $server           = array();
	public $cookie           = array();
	public $post             = array();
	public $get              = array();
	public $files            = array();
	public $templates        = array();
	public $emoticons        = array(); // Array of emoticons used for processing post formatting
	public $ip               = '127.0.0.1';
	public $agent            = 'Unknown';
	public $referrer         = 'Unknown';
	public $user             = array();
	public $xtpl             = null;
	public $icon_dir         = null;
	public $file_dir         = null;
	public $emote_dir        = null;
	public $banner_dir       = null;

	public function __construct( $db = null )
	{
		$this->time	= time();
		$this->server	= $_SERVER;
		$this->cookie	= $_COOKIE;
		$this->post	= $_POST;
		$this->get	= $_GET;
		$this->files	= $_FILES;

		$this->db = $db;

		$this->ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		$this->agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '-';
		$this->agent = substr( $this->agent, 0, 254 ); // Cut off after 255 characters.
		$this->referrer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '-';
		$this->referrer = substr( $this->agent, 0, 254 ); // Cut off after 255 characters.

		$this->file_dir = 'files/attachments/';
		$this->icon_dir = 'files/posticons/';
		$this->emote_dir = 'files/emoticons/';
		$this->banner_dir = 'files/banners/';
	}

	public function title( $title )
	{
		$this->title .= ' &raquo; ' . htmlspecialchars( $title );
	}

	public function meta_description( $desc )
	{
		if( $desc != null ) {
			$desc = htmlspecialchars( $desc );
			$this->meta_description = "<meta name=\"description\" content=\"$desc\" />";
		}
		else
			$this->meta_description = null;
	}

	public function set_skin( $skin = null )
	{
		$this->skins = $this->get_skins();
		if( !$skin )
			$skin = $this->settings['site_defaultskin'];

		$skin = isset( $this->cookie['skin'] ) ? $this->cookie['skin'] : $skin;

		if( !$skin || !in_array( $skin, $this->skins ) )
			return;
		$this->skin = $skin;

		setcookie( $this->settings['cookie_prefix'] . 'skin', $skin, $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
	}

	public function get_skins()
	{
		$skins = array();

		if( $dh = opendir( './skins/' ) )
		{
			while( ( $item = readdir( $dh ) ) !== false )
				if ( $item[0] != '.' && is_dir( './skins/' . $item ) )
					$skins[] = $item;
			closedir( $dh );
		}
		return $skins;
	}

	public function load_emoticons()
	{
		$emotes = array();
		$dbemotes = $this->db->dbquery( 'SELECT * FROM %pemoticons' );
		while( $e = $this->db->assoc( $dbemotes ) )
		{
			if( $e['emote_clickable'] == 1 )
				$emotes['click_replacement'][$e['emote_string']] = '<img src="' . $this->settings['site_address'] . 'files/emoticons/' . $e['emote_image'] . '" alt="' . $e['emote_string'] . '" />';
			else
				$emotes['replacement'][$e['emote_string']] = '<img src="' . $this->settings['site_address'] . 'files/emoticons/' . $e['emote_image'] . '" alt="' . $e['emote_string'] . '" />';
		}
		return $emotes;
	}

	public function load_settings( $settings )
	{
		// Converts old serialized array into a json encoded array due to potential exploits in the PHP serialize/unserialize functions.
		$settings_array = array();

		$sets = $this->db->quick_query( 'SELECT settings_version, settings_value FROM %psettings LIMIT 1' );

		if( !is_array( $sets ) )
			return $settings;

		$settings_array = array_merge( $settings, json_decode( $sets['settings_value'], true ) );

		return $settings_array;
	}

	public function save_settings()
	{
		$default_settings = array( 'db_name', 'db_user', 'db_pass', 'db_host', 'db_pre', 'db_type', 'error_email' );

		$settings = array();

		foreach( $this->settings as $set => $val )
			if( !in_array( $set, $default_settings ) )
				$settings[$set] = $val;

		$stmt = $this->db->prepare( 'UPDATE %psettings SET settings_value=?' );

		$encoded = json_encode($settings);
		$stmt->bind_param( 's', $encoded );
		$this->db->execute_query( $stmt );
		$stmt->close();
	}

	public function logout()
	{
		setcookie( $this->settings['cookie_prefix'] . 'user', '', $this->time - 9000, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
		setcookie( $this->settings['cookie_prefix'] . 'pass', '', $this->time - 9000, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

		unset( $_SESSION['user'] );
		unset( $_SESSION['pass'] );

		$_SESSION = array();
		header( 'Location: index.php' );
	}

	public function login( $page )
	{
		if( isset( $this->post['login_name'] ) && isset( $this->post['login_password'] ) ) {
			$username = $this->post['login_name'];
			$password = $this->post['login_password'];

			$stmt = $this->db->prepare( 'SELECT * FROM %pusers WHERE user_name=? LIMIT 1' );

			$stmt->bind_param( 's', $username );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$user = $result->fetch_assoc();

			$stmt->close();

			if( !$user )
				return -2;

			if( !isset($user['user_id']) )
				return -2;

			if( !password_verify( $password, $user['user_password'] ) )
				return -2;

			$hashcheck = $this->check_hash_update( $password, $user['user_password'] );
			if( $hashcheck != $user['user_password'] ) {
				$user['user_password'] = $hashcheck;

				$stmt = $this->db->prepare( 'UPDATE %pusers SET user_password=? WHERE user_id=?' );

				$stmt->bind_param( 'si', $user['user_password'], $user['user_id'] );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}

			setcookie( $this->settings['cookie_prefix'] . 'user', $user['user_id'], $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
			setcookie( $this->settings['cookie_prefix'] . 'pass', $user['user_password'], $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

			$this->user = $user;
			header( 'Location: ' . $page );
		} else if( isset( $this->cookie[$this->settings['cookie_prefix'] . 'user'] ) && isset( $this->cookie[$this->settings['cookie_prefix'] . 'pass'] ) ) {
			$cookie_user = intval( $this->cookie[$this->settings['cookie_prefix'] . 'user'] );
			$cookie_pass = $this->cookie[$this->settings['cookie_prefix'] . 'pass'];

			$stmt = $this->db->prepare( 'SELECT * FROM %pusers WHERE user_id=? AND user_password=?' );

			$stmt->bind_param( 'ss', $cookie_user, $cookie_pass );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$user = $result->fetch_assoc();

			$stmt->close();

			if( !$user || !isset($user['user_id']) )
				return -1;
		} else {
			return -1;
		}

		$this->user = $user;
		return 1;
	}

	public function t_date( $time = 0, $rssfeed = false, $server_time = false )
	{
		if (!$time) {
			$time = $this->time;
		}

		$timezone = $this->user['user_timezone'];

		if( $this->user['user_level'] < USER_VALIDATING )
			$timezone = $this->settings['site_timezone'];

		if( $server_time == true )
			$timezone = $this->settings['site_timezone'];

		$dt = new DateTime();
		$dt->setTimezone( new DateTimeZone( $timezone ) );
		$dt->setTimestamp( $time );

		if( $rssfeed == false )
			return $dt->format( $this->settings['site_dateformat'] );

		// ISO822 format is standard for XML feeds
		return $dt->format( 'D, j M Y H:i:s T' );
	}

	public function select_input( $name, $value, $values = array() )
	{
		$out = null;

		foreach( $values as $key )
			$out .= '<option' . ($key == $value ? ' selected="selected"' : '') . ">$key</option>";

		return "<select name=\"$name\">$out</select>";
	}

	public function select_timezones( $zone, $variable_name )
	{
		$out = null;

		$zones = array(
			'-12'			=> 'GMT-12    - Baker Island/International Dateline West',
			'Pacific/Pago_Pago'	=> 'GMT-11    - Pacific: Midway Islands',
			'America/Adak'		=> 'GMT-10    - USA: Alaska (Aleutian Islands)',
			'Pacific/Honolulu'	=> 'GMT-10    - USA: Hawaii Time Zone',
			'America/Anchorage'	=> 'GMT-9     - USA: Alaska Time Zone',
			'America/Los_Angeles'	=> 'GMT-8     - US/Canada: Pacific Time Zone',
			'America/Denver'	=> 'GMT-7     - US/Canada: Mountain Time Zone',
			'America/Phoenix'	=> 'GMT-7     - USA: Mountain Time Zone (Arizona)',
			'America/Chicago'	=> 'GMT-6     - US/Canada: Central Time Zone',
			'America/New_York'	=> 'GMT-5     - US/Canada: Eastern Time Zone',
			'America/Halifax'	=> 'GMT-4     - US/Canada: Atlantic Time Zone',
			'America/St_Johns'	=> 'GMT-3.5   - Canada: Newfoundland',
			'America/Argentina/Buenos_Aires'	=> 'GMT-3     - Argentina',
			'America/Sao_Paulo'	=> 'GMT-3     - Brazil: Sao Paulo',
			'America/Noronha'	=> 'GMT-2     - Brazil: Atlantic islands/Noronha',
			'Atlantic/Azores'	=> 'GMT-1     - Europe: Portugal/Azores',
			'Europe/London'		=> 'GMT       - Europe: Greenwich Mean Time (UK/Ireland)',
			'Atlantic/Reykjavik'	=> 'GMT       - Europe: Greenwich Mean Time (Iceland)',
			'Europe/Berlin'		=> 'GMT+1     - Europe: France/Germany/Spain',
			'Europe/Athens'		=> 'GMT+2     - Europe: Greece (Athens)',
			'Europe/Moscow'		=> 'GMT+3     - Europe: Russia (Moscow)',
			'Asia/Tehran'		=> 'GMT+3.5   - Asia: Iran',
			'Asia/Dubai'		=> 'GMT+4     - Asia: Oman/United Arab Emerites',
			'Asia/Kabul'		=> 'GMT+4.5   - Asia: Afghanistan',
			'Asia/Karachi'		=> 'GMT+5     - Asia: Pakistan',
			'Asia/Kolkata'		=> 'GMT+5.5   - Asia: India',
			'Asia/Almaty'		=> 'GMT+6     - Asia: Kazakhstan',
			'Asia/Yangon'		=> 'GMT+6.5   - Asia: Myanmar',
			'Asia/Bangkok'  	=> 'GMT+7     - Asia: Thailand/Cambodia/Laos',
			'Asia/Shanghai'		=> 'GMT+8     - Asia: China/Mongolia/Phillipines',
			'Australia/Perth'	=> 'GMT+8     - Australia: Western (Perth)',
			'Australia/Eucla'	=> 'GMT+8.75  - Australia: Western (Eucla)',
			'Asia/Tokyo'		=> 'GMT+9     - Asia: Japan/Korea/New Guinea',
			'Australia/Broken_Hill'	=> 'GMT+9.5   - Australia: New South Wales (Yancowinna)',
			'Australia/Darwin'	=> 'GMT+9.5   - Australia: Northern Territory (Darwin)',
			'Australia/Brisbane'    => 'GMT+10    - Australia: Queensland',
			'Australia/Hobart'	=> 'GMT+10    - Australia: Tasmania',
			'Australia/Melbourne'	=> 'GMT+10    - Australia: Victoria/New South Wales',
			'Australia/Lord_Howe'	=> 'GMT+10.5  - Australia: Lord Howe Island',
			'Pacific/Bougainville'	=> 'GMT+11    - Pacific: Solomon Islands/Vanuatu/New Caledonia',
			'Asia/Kamchatka'	=> 'GMT+12    - Asia: Kamchatka',
			'Pacific/Auckland'	=> 'GMT+12    - Pacific: New Zealand/Fiji',
			'Pacific/Funafuti'	=> 'GMT+12    - Pacific: Tuvalu/Marshall Islands',
			'Pacific/Chatham'	=> 'GMT+12.75 - Pacific: Chatham Islands',
			'Pacific/Tongatapu'	=> 'GMT+13    - Pacific: Tonga/Phoenix Islands',
			'Pacific/Kiritimati'	=> 'GMT+14    - Pacific: Line Islands'
		);

		foreach( $zones as $offset => $zone_name )
		{
			$out .= "<option value='$offset'" . ( ( $offset == $zone ) ? ' selected=\'selected\'' : null ) . ">$zone_name</option>\n";
		}

		return "<select name=\"$variable_name\">$out</select>";
	}

	public function format( $in, $options = ISSUE_BBCODE )
	{
		return $this->bbcode->format( $in, $options );
	}

	public function closed_content( $content )
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

	public function message( $title, $message, $link_name = null, $link = null, $delay = 4 )
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

	public function make_links( $projid, $count, $min, $num, $sortkey )
	{
		if( $num < 1 ) $num = 1; // No more division by zero please.

		$current = ceil( $min / $num );
		$string  = null;
		$pages   = ceil( $count / $num );
		$end     = ( $pages - 1 ) * $num;
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
		if( $this->navselect == 5 )
			$link = "{$this->settings['site_address']}?a=issues&amp;s=mywatchlist";
		if( $this->navselect == 6 )
			$link = "{$this->settings['site_address']}?a=reopen";

		// check if there's previous articles
		if( $min == 0 ) {
			$startlink = '&lt;&lt;';
			$previouslink = '';
		} else {
			$prev = $min - $num;

			if( $sortkey != null ) {
				$startlink = "<a href=\"$link&amp;min=0&amp;num=$num&amp;sortby=$sortkey\">&lt;&lt;</a>";
				$previouslink = "<a href=\"$link&amp;min=$prev&amp;num=$num&amp;sortby=$sortkey\">prev</a> ";
			} else {
				$startlink = "<a href=\"$link&amp;min=0&amp;num=$num\">&lt;&lt;</a>";
				$previouslink = "<a href=\"$link&amp;min=$prev&amp;num=$num\">prev</a> ";
			}
		}

		// check for next/end
		if( ( $min + $num ) < $count ) {
			$next = $min + $num;

			if( $sortkey != null ) {
	  			$nextlink = "<a href=\"$link&amp;min=$next&amp;num=$num&amp;sortby=$sortkey\">next</a>";
	  			$endlink = "<a href=\"$link&amp;min=$end&amp;num=$num&amp;sortby=$sortkey\">&gt;&gt;</a>";
			} else {
	  			$nextlink = "<a href=\"$link&amp;min=$next&amp;num=$num\">next</a>";
	  			$endlink = "<a href=\"$link&amp;min=$end&amp;num=$num\">&gt;&gt;</a>";
			}
		} else {
 			$nextlink = '';
  			$endlink = '&gt;&gt;';
		}

		// setup references
		$b = $current - 2;
		$e = $current + 2;

		// set end and beginning of loop
		if( $b < 0 ) {
  			$e = $e - $b;
  			$b = 0;
		}

		// check that end coheres to the issues
		if( $e > $pages - 1 ) {
  			$b = $b - ( $e - $pages + 1 );
  			$e = ( $pages - 1 < $current ) ? $pages : $pages - 1;
  			// b may need adjusting again
  			if( $b < 0 ) {
				$b = 0;
			}
		}

 		// ellipses
		if( $b != 0 ) {
			$badd = '...';
		} else {
			$badd = '';
		}

		if( ( $e != $pages - 1 ) && $count ) {
			$eadd = '...';
		} else {
			$eadd = '';
		}

		// run loop for numbers to the page
		for( $i = $b; $i < $current; $i++ )
		{
			$where = $num * $i;

			if( $sortkey != null )
				$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num&amp;sortby=$sortkey\">" . ($i + 1) . '</a>';
			else
				$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num\">" . ($i + 1) . '</a>';
		}

		// add in page
		$string .= ', <strong>' . ($current + 1) . '</strong>';

		// run to the end
		for( $i = $current + 1; $i <= $e; $i++ )
		{
			$where = $num * $i;

			if( $sortkey != null )
				$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num&amp;sortby=$sortkey\">" . ( $i + 1 ) . '</a>';
			else
				$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num\">" . ( $i + 1 ) . '</a>';
		}

		// get rid of preliminary comma.
		if( substr( $string, 0, 1 ) == ',' ) {
			$string = substr( $string, 1 );
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

	public function get_file_icon( $type )
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
	public function error( $message, $errorcode = 0 )
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

	public function valid_user( $name )
	{
		return !preg_match( '/[^a-zA-Z0-9_\\@]/', $name );
	}

	public function is_email( $addr )
	{
		return filter_var( $addr, FILTER_VALIDATE_EMAIL );
	}

	public function display_icon( $icon )
	{
		$url = $this->settings['site_address'] . $this->icon_dir . $icon;

		if( $this->is_email( $icon ) ) {
			$url = 'https://secure.gravatar.com/avatar/';
			$url .= md5( strtolower( trim( $icon ) ) );
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
	public function afktrack_password_hash( $pass )
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
	private function check_hash_update( $password, $hash )
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
	 * @author https://www.zend.com/codex.php?id=215&single=1
	 * @since 1.0
	 */
	public function generate_pass( $length )
	{
		$vowels = array( 'a', 'e', 'i', 'o', 'u' );
		$cons = array( 'b', 'c', 'd', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'u', 'v', 'w', 'tr',
		'cr', 'br', 'fr', 'th', 'dr', 'ch', 'ph', 'wr', 'st', 'sp', 'sw', 'pr', 'sl', 'cl' );

		$num_vowels = count( $vowels );
		$num_cons = count( $cons );

		$password = '';

		for( $i = 0; $i < $length; $i++ )
		{
			$password .= $cons[rand( 0, $num_cons - 1 )] . $vowels[rand( 0, $num_vowels - 1 )];
		}

		return substr( $password, 0, $length );
	}

	private function cidrmatch( $cidr )
	{
		$ip = decbin( ip2long( $this->ip ) );

		list( $cidr1, $cidr2, $cidr3, $cidr4, $bits ) = sscanf( $cidr, '%d.%d.%d.%d/%d' );

		$cidr = decbin( ip2long( "$cidr1.$cidr2.$cidr3.$cidr4" ) );

		for( $i = strlen( $ip ); $i < 32; $i++ )
			$ip = "0$ip";

		for( $i = strlen( $cidr ); $i < 32; $i++ )
			$cidr = "0$cidr";

		return !strcmp( substr( $ip, 0, $bits ), substr( $cidr, 0, $bits ) );
	}

	private function is_ipv6( $ip )
	{
		return( substr_count( $ip, ":" ) > 0 && substr_count( $ip, "." ) == 0 );
	}

	public function ip_banned( )
	{
		if( isset( $this->settings['banned_ips'] ) )
		{
			foreach( $this->settings['banned_ips'] as $ip )
			{
				if( $this->is_ipv6( $this->ip ) ) {
					if( !strcasecmp( $ip, $this->ip ) )
						return true;
				}

				if( ( strstr( $ip, '/' ) && $this->cidrmatch( $ip ) ) || !strcasecmp( $ip, $this->ip ) )
					return true;
			}
		}
		return false;
	}

	private function ReverseIPOctets( $inputip )
	{
		$ipoc = explode( ".", $inputip );
		return $ipoc[3] . "." . $ipoc[2] . "." . $ipoc[1] . "." . $ipoc[0];
	}

	private function IsTorExitPoint( $ip )
	{
		if( gethostbyname( $this->ReverseIPOctets( $ip ) . "." . $_SERVER['SERVER_PORT'] . "." . $this->ReverseIPOctets( $_SERVER['SERVER_ADDR'] ) . ".ip-port.exitlist.torproject.org" ) == "127.0.0.2" )
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
	public function generate_token()
	{
		$token = md5( uniqid( mt_rand(), true ) );
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
	public function is_valid_token()
	{
		if( !isset( $_SESSION['token'] ) || !isset( $_SESSION['token_time'] ) || !isset( $this->post['token'] ) ) {
			return false;
		}

		if( $_SESSION['token'] != $this->post['token'] ) {
			return false;
		}

		$age = $this->time - $_SESSION['token_time'];

		if( $age > 3600 ) // Token is valid for 1 hour.
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
			if( isset( $frame['args'] ) )
			{
				foreach( $frame['args'] as $arg )
				{
					if( is_array( $arg ) && array_key_exists( 0, $arg ) && is_string( $arg[0] ) ) {
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

		$frame['class'] = ( isset( $frame['class'] ) ) ? $frame['class'] : '';
		$frame['type'] = ( isset( $frame['type'] ) ) ? $frame['type'] : '';
		$frame['file'] = ( isset( $frame['file'] ) ) ? $frame['file'] : '';
		$frame['line'] = ( isset( $frame['line'] ) ) ? $frame['line'] : '';

		$func = '';
		$arg_list = implode( ", ", $args );
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

function error( $type, $message, $file, $line = 0 )
{
	global $settings;

	if( !(error_reporting() & $type) )
		return;

	switch( $type )
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

	if( $type != AFKTRACK_QUERY_ERROR ) {
		if( strpos( $message, 'mysql_fetch_array(): supplied argument' ) === false ) {
			$lines = null;
			$details2 = null;

			if( file_exists( $file ) ) {
				$lines = file( $file );
			}

			if( $lines ) {
				$details2 = "Code:\n" . error_getlines( $lines, $line );
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
	if( isset( $_SERVER['QUERY_STRING'] ) ) {
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
		$error_report .= "The error details are as follows:\n\nURL: https://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . "?" . $querystring . "\n";
		$error_report .= "Querying user agent: " . $agent . "\n";
		$error_report .= "Querying IP: " . $ip . "\n\n";
		$error_report .= $message . "\n\n" . $details . "\n\n" . $backtrace;
		$error_report = str_replace( "&nbsp;", " ", html_entity_decode($error_report) );

		@mail( $settings['error_email'], "[AFKTrack] Fatal Error Report", $error_report, $headers );
	}

	header( 'HTTP/1.0 500 Internal Server Error' );
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
 <div id=\"footer\">Powered by AFKTrack &copy; 2017-2019 Roger Libiez [<a href=\"https://github.com/Arthmoor/AFKTrack\">GitHub</a>]</div>
</body>
</html>" );
}

function error_getlines( $lines, $line )
{
	$code    = null;
	$padding = ' ';
	$previ   = $line-3;
	$total_lines = count( $lines );

	for( $i = $line - 3; $i <= $line + 3; $i++ )
	{
		if( ( strlen( $previ ) < strlen( $i ) ) && ( $padding == ' ' ) ) {
			$padding = null;
		}

		if( ( $i < 1 ) || ( $i > $total_lines ) ) {
			continue;
		}

		$codeline = rtrim( htmlentities( $lines[$i-1] ) );
		$codeline = str_replace( "\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $codeline );
		$codeline = str_replace( ' ', '&nbsp;', $codeline );

		$code .= $i . $padding . $codeline . "\n";

		$previ = $i;
	}
	return $code;
}
?>