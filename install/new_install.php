<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK_INSTALLER') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class new_install extends module
{
	private function save_settings_file($settings)
	{
		$file = "<?php
	if ( !defined('AFKTRACK') ) {
		header('HTTP/1.0 403 Forbidden');
		die;
	}
\$settings = array(
	'db_name'	=> '{$settings['db_name']}',
	'db_user'	=> '{$settings['db_user']}',
	'db_pass'	=> '{$settings['db_pass']}',
	'db_host'	=> '{$settings['db_host']}',
	'db_pre'	=> '{$settings['db_pre']}',
	'db_type'	=> '{$settings['db_type']}',
	'error_email'	=> '{$settings['error_email']}'
	);
?>";

		$fp = @fopen('../settings.php', 'w');

		if (!$fp) {
			return false;
		}

		if (!@fwrite($fp, $file)) {
			return false;
		}

		fclose($fp);
		return true;
	}

	private function server_url()
	{ 
		$proto = "http" . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") ? "s" : "") . "://";
		$server = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
		return $proto . $server;
	}

	public function install( $step, $mysqli )
	{
		switch( $step )
		{
			default:
			$url = preg_replace('/install\/?$/i', '', $this->server_url() . dirname($_SERVER['PHP_SELF']));

			echo "<form action='{$url}?mode=new_install&amp;step=2' method='post'>
			 <div class='article'>
			  <div class='title'>New AFKTrack Installation</div>
			  <div class='subtitle'>Directory Permissions</div>";

			  check_writeable_files();

			echo "    <p></p>
 			 <div class='subtitle'>Database Configuration</div>

  <span class='field'>Host Server:</span>
  <span class='form'><input class='input' type='text' name='db_host' value='{$this->settings['db_host']}' /></span>
  <p class='line'></p>

  <span class='field'>Database Type:</span>
  <span class='form'>
   <select name='db_type'>";

  if( $mysqli )
    echo "<option value='mysqli'>MySQLi</option>";

  echo "</select>
  </span>
  <p class='line'></p>

  <span class='field'>Database Name:</span>
  <span class='form'><input class='input' type='text' name='db_name' value='{$this->settings['db_name']}' /></span>
  <p class='line'></p>

  <span class='field'>Database Username:</span>
  <span class='form'><input class='input' type='text' name='db_user' value='{$this->settings['db_user']}' /></span>
  <p class='line'></p>

  <span class='field'>Database Password:</span>
  <span class='form'><input class='input' type='password' name='db_pass' value='' /></span>
  <p class='line'></p>

  <span class='field'>Table Prefix:</span>
  <span class='form'>
   <input class='input' type='text' name='db_pre' value='{$this->settings['db_pre']}' /><br />
   This should only be changed if you need to install multiple AFKTrack sites in the same database.
  </span>
  <p></p>

  <div class='subtitle'>New Site Settings</div>

  <span class='field'>Site Name:</span>
  <span class='form'><input class='input' type='text' name='site_name' value='AFKTrack' size='75' /></span>
  <p class='line'></p>

  <span class='field'>Site URL:</span>
  <span class='form'><input class='input' type='text' name='site_url' value='{$url}' size='75' /></span>
  <p></p>

  <div class='subtitle'>Administrator Account Settings</div>

  <span class='field'>User Name:</span>
  <span class='form'><input class='input' type='text' name='admin_name' size='30' maxlength='30' /></span>
  <p class='line'></p>

  <span class='field'>User Password:</span>
  <span class='form'><input class='input' type='password' name='admin_pass' size='30' /></span>
  <p class='line'></p>

  <span class='field'>Password (confirmation):</span>
  <span class='form'><input class='input' type='password' name='admin_pass2' size='30' /></span>
  <p class='line'></p>

  <span class='field'>Contact Email:</span>
  <span class='form'>
   <input class='input' type='text' name='contact_email' size='50' maxlength='100' />
   This is where contact form emails and error messages are sent.
  </span>
  <p class='line'></p>

  <span class='field'>System Email:</span>
  <span class='form'>
   <input class='input' type='text' name='admin_email' size='50' maxlength='100' />
   Address used by the system to send mail. Can be fake if desired.
  </span>
  <p class='line'></p>

  <div style='text-align:center'>
   <input type='submit' name='submit' value='Continue' />
  </div>
 </div>
</form>";
break;

	case 2:
		  echo "<div class='article'>
		  <div class='title'>New AFKTrack Installation</div>";

			$dbt = 'db_' . $this->post['db_type'];
			$db = new $dbt($this->post['db_name'], $this->post['db_user'], $this->post['db_pass'], $this->post['db_host'], $this->post['db_pre']);

			if (!$db->db) {
				echo "Couldn't connect to a database using the specified information.";
				break;
			}
			$this->db = &$db;

			$this->settings['db_host'] = $this->post['db_host'];
			$this->settings['db_user'] = $this->post['db_user'];
			$this->settings['db_pass'] = $this->post['db_pass'];
			$this->settings['db_name'] = $this->post['db_name'];
			$this->settings['db_type'] = $this->post['db_type'];
			$this->settings['db_pre']  = trim(preg_replace('/[^a-zA-Z0-9_]/', '', $this->post['db_pre']));
			$this->settings['error_email'] = $this->post['contact_email'];

			if(!is_writeable('../settings.php')) {
				echo 'Cannot write to settings.php file. Please change the permissions to at least 0666, then go back and try again.';
				break;
			}
			$this->save_settings_file($this->settings);

			if (!is_readable( './' . $this->settings['db_type'] . '_queries.php' )) {
				echo "Unable to read queries file: ./{$this->settings['db_type']}_queries.php";
				break;
			}

			if((trim($this->post['admin_name']) == '')
				|| (trim($this->post['admin_pass']) == '')
				|| (trim($this->post['contact_email']) == '')) {
				echo 'You have not specified an admistrator account. Please go back and correct this error.';
				break;
			}

			if ($this->post['admin_pass'] != $this->post['admin_pass2']) {
				echo 'Your administrator passwords do not match. Please go back and correct this error.';
				break;
			}

			$this->settings['site_name'] = $this->post['site_name'];

			if( !empty($this->post['site_url']) && $this->post['site_url'][strlen($this->post['site_url'])-1] != '/' )
				$this->post['site_url'] = $this->post['site_url'] . '/';
			$this->settings['site_address'] = $this->post['site_url'];

			$this->settings['site_meta'] = 'AFKTrack: Bug tracking made easy.';
			$this->settings['site_keywords'] = 'afktrack,bug,tracker';
			$this->settings['mobile_icons'] = '';
			$this->settings['email_adm'] = $this->post['contact_email'];
			$this->settings['email_sys'] = $this->post['admin_email'];
			$this->settings['site_open'] = true;
			$this->settings['site_closedmessage'] = 'This site is currently down for maintenance.';
			$this->settings['site_spamregmessage'] = 'Information provided during registration has been flagged as a possible spam source. Your account has been placed into the moderation queue and will require manual approval from the site administrators.';
			$this->settings['site_defaultskin'] = 'Default';
			$this->settings['site_analytics'] = '';
			$this->settings['wordpress_api_key'] = '';
			$this->settings['footer_text'] = '';
			$this->settings['site_issuesperpage'] = 25;
			$this->settings['site_commentsperpage'] = 25;
			$this->settings['site_dateformat'] = 'M j, Y g:i a';
			$this->settings['site_timezone'] = 'Europe/London';
			$this->settings['site_icon_width'] = 50;
			$this->settings['site_icon_height'] = 50;
			$this->settings['global_comments'] = true;
			$this->settings['validate_users'] = true;
			$this->settings['total_issues'] = 1;
			$this->settings['attachment_size_limit_mb'] = 25;
			$this->settings['attachment_types_allowed'] = array( 'jpg', 'gif', 'png', 'zip', 'rar', '7z', 'ess', 'esp', 'esm', 'esl' );
			$this->settings['header_logo'] = 'afktracklogo.png';
			$this->settings['search_flood_time'] = 30;
			$this->settings['registration_terms'] = '';
			$this->settings['privacy_policy'] = 'The administration has not yet defined a privacy policy.';

			$server = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
			$this->settings['cookie_domain'] = $server;

			$path = dirname($_SERVER['PHP_SELF']);
			$path = str_replace( 'install', '', $path );
			$this->settings['cookie_path'] = $path;

			$this->settings['cookie_secure'] = false;
			$this->settings['cookie_prefix'] = 'afktrack_';
			$this->settings['cookie_logintime'] = 31536000;

			$this->settings['html_email'] = false;
			$this->settings['spam_count'] = 0;
			$this->settings['email_spam_count'] = 0;
			$this->settings['ham_count'] = 0;
			$this->settings['spam_uncaught'] = 0;
			$this->settings['banned_ips'] = array();
			$this->settings['user_count'] = 1;
			$this->settings['register_spam_count'] = 0;
			$this->settings['global_announce'] = '';
			$this->settings['rss_enabled'] = true;
			$this->settings['rss_name'] = $this->post['site_name'];
			$this->settings['rss_description'] = '';
			$this->settings['rss_items'] = 10;
			$this->settings['rss_refresh'] = 60;

			$queries = array();
			$pre = $this->settings['db_pre'];

			// Create tables
			include './' . $this->settings['db_type'] . '_queries.php';
			execute_queries($queries, $db);
			$queries = null;

			$newsets = array();
			$this->db->dbquery( "INSERT INTO %psettings (settings_value) VALUES( '%s' )", json_encode($newsets) );
			$this->db->dbquery( "INSERT INTO %pprojects (project_position, project_name, project_description) VALUES( 1, 'Default Project', 'The default project. This can be edited to suit, but cannot be deleted.' )" );
			$this->db->dbquery( "INSERT INTO %pcomponents (component_name, component_project) VALUES ( 'Default', 1 )" );
			$this->db->dbquery( "INSERT INTO %pcategories (category_name, category_project) VALUES ( 'Default', 1 )" );
			$this->db->dbquery( "INSERT INTO %pissues (issue_category, issue_type, issue_status, issue_severity, issue_project, issue_platform, issue_user, issue_summary, issue_text, issue_date, issue_flags) VALUES(1, 1, 1, 1, 1, 1, 1, 'Welcome to your AFKTrack Website!', 'We hope you enjoy it. The GitHub repository can be found at: https://github.com/Arthmoor/AFKTrack Please post bug reports, feature requests and ideas there.', " . time() . ", 7 )" );
			$this->db->dbquery( "INSERT INTO %pplatforms (platform_name, platform_position) VALUES ( 'All', 1 )" );
			$this->db->dbquery( "INSERT INTO %pstatus (status_name, status_position) VALUES ( 'New', 1 )" );
			$this->db->dbquery( "INSERT INTO %pseverities (severity_name, severity_position) VALUES ( 'None', 1 )" );
			$this->db->dbquery( "INSERT INTO %presolutions (resolution_name, resolution_position) VALUES ( 'Invalid', 1 )" );
			$this->db->dbquery( "INSERT INTO %ptypes (type_name, type_position) VALUES ( 'Bug', 1 )" );

			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':alien:', 'alien.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':biggrin:', 'biggrin.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':blues:', 'blues.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':cool:', 'cool.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':cry:', 'cry.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':cyclops:', 'cyclops.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':devil:', 'devil.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':evil:', 'evil.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':ghostface:', 'ghostface.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':grinning:', 'grinning.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':lol:', 'lol.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':mad:', 'angry.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':redface:', 'redface.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':robot:', 'robot.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':rolleyes:', 'rolleyes.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':sad:', 'sad.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':smile:', 'smile.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':stare:', 'stare.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':surprised:', 'surprised.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':thinking:', 'thinking.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':tongue:', 'tongue.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':unclesam:', 'unclesam.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':wink:', 'wink.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':huh:', 'huh.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':blink:', 'blink.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':facepalm:', 'facepalm.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':whistle:', 'whistle.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':sick:', 'sick.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':headbang:', 'headbang.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':innocent:', 'innocent.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':crazy:', 'crazy.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':rofl:', 'rofl.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':lmao:', 'lmao.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':shrug:', 'shrug.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':ninja:', 'ninja.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':nuke:', 'nuke.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':wub:', 'wub.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':imp:', 'imp.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':banana:', 'dancingbanana.gif', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':cricket:', 'cricket.png', 1 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':(', 'sad.png', 0 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':P', 'tongue.png', 0 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (';)', 'wink.png', 0 )" );
			$this->db->dbquery( "INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (':)', 'smile.gif', 0 )" );

			$this->settings['app_version'] = $this->version;
			$this->save_settings();

			// Generate the Anonymous user.
			$this->db->dbquery( "INSERT INTO %pusers (user_name, user_level, user_perms, user_icon)	VALUES( 'Anonymous', 1, 0, 'Anonymous.png' )" );

			// Add the administrator next.
			$pass = $this->afktrack_password_hash( $this->post['admin_pass'] );
			$current_time = time();
			$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

			$this->db->dbquery( "INSERT INTO %pusers (user_name, user_password, user_email, user_level, user_perms, user_joined, user_icon, user_ip)
				VALUES( '%s', '%s', '%s', 6, 1, %d, 'Anonymous.png', '%s' )", $this->post['admin_name'], $pass, $this->post['contact_email'], $current_time, $ip );
			$id = $this->db->insert_id();

			setcookie($this->settings['cookie_prefix'] . 'user', $id, $current_time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
			setcookie($this->settings['cookie_prefix'] . 'pass', $pass, $current_time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

			echo "
			<div class='article'>
			 <div class='title'>Installation Successful!</div>
			 Your AFKTrack site is now installed. <a href='{$this->settings['site_address']}'>Click here</a> to go there now.<br /><br />
			 Or <a href=\"{$this->settings['site_address']}admin.php\">Click here</a> to go directly to the AdminCP.<br /><br />
			 <span style='color:red'>Please DELETE THE INSTALL DIRECTORY NOW for security purposes!!</span><br /><br />
			<span style='color:red'>Please make sure your settings.php file is marked with 0444 permissions for security purposes!!</span>
			</div>";
		}
	}
}
?>