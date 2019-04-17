<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK_INSTALLER' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class upgrade extends module
{
	public function upgrade_site( $step )
	{
		switch( $step )
		{
			default:
			echo "<form action='{$this->self}?mode=upgrade&amp;step=2' method='post'>
			 <div class='article'>
			  <div class='title'>Upgrade AFKTrack</div>
			  <div class='subtitle'>Directory Permissions</div>";

			check_writeable_files();

			$dbt = 'db_' . $this->settings['db_type'];
			$db = new $dbt( $this->settings['db_name'], $this->settings['db_user'], $this->settings['db_pass'], $this->settings['db_host'], $this->settings['db_pre'] );

			if( !$db->db )
			{
				echo '<br /><br />A connection to the database could not be established. Please check your settings.php file to be sure it has the correct information.';
				break;
			}
			$this->db = $db;

			// Need to do this before anything else.
			$coms = $this->db->quick_query( "SELECT COUNT(*) count FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '{$this->settings['db_name']}' AND table_name = '%psettings'" );

			if( $coms['count'] < 3 ) {
				$this->db->dbquery( "ALTER TABLE %psettings ADD settings_version smallint(2) NOT NULL default '1' AFTER settings_id" );
			}

			$this->settings = $this->load_settings( $this->settings );

			$v_message = 'To determine what version you are running, check the bottom of your AdminCP page. Or check the CHANGELOG file and look for the latest revision mentioned there.';
			if( isset( $this->settings['app_version'] ) )
				$v_message = 'The upgrade script has determined you are currently using ' . $this->settings['app_version'];

			echo "<br /><br /><strong>{$v_message}</strong>";

			if( isset( $this->settings['app_version'] ) && $this->settings['app_version'] == $this->version ) {
				echo "<br /><br /><strong>The detected version of AFKTrack is the same as the version you are trying to upgrade to. The upgrade cannot be processed.</strong>";
			} else {
				echo "	<div class='title' style='text-align:center'>Upgrade from what version?</div>

					<span class='field'><input type='radio' name='from' value='1.0' id='100' /></span>
					<span class='form'><label for='100'>AFKTrack 1.0</label></span>
					<p class='line'></p>

					<span class='field'><input type='radio' name='from' value='1.01' id='101' /></span>
					<span class='form'><label for='101'>AFKTrack 1.0.1</label></span>
					<p class='line'></p>

					<div style='text-align:center'>
					 <input type='submit' value='Continue' />
					 <input type='hidden' name='mode' value='upgrade' />
					 <input type='hidden' name='step' value='2' />
					</div>";
			}
			echo "    </div>
			    </form>\n";
			break;

			case 2:
			 	echo "<div class='article'>
			 		<div class='title'>Upgrade AFKTrack</div>";
				$dbt = 'db_' . $this->settings['db_type'];
				$db = new $dbt( $this->settings['db_name'], $this->settings['db_user'], $this->settings['db_pass'], $this->settings['db_host'], $this->settings['db_pre'] );

				if( !$db->db )
				{
					echo '<br />A connection to the database could not be established. Please check your settings.php file to be sure it has the correct information.';
					break;
				}
				$this->db = $db;
				$this->settings = $this->load_settings( $this->settings );

				// Missing breaks are deliberate. Upgrades from older versions need to step through all of this.
				switch( $this->post['from'] )
				{
					case '1.0': // 1.0 to 1.1:
					case '1.01':
						$this->settings['site_timezone'] = 'Europe/London';
						$this->settings['privacy_policy'] = 'The administration has not yet defined a privacy policy.';
						$this->settings['prune_watchlist'] = false;

						$queries[] = "ALTER TABLE %pusers ADD user_timezone varchar(255) NOT NULL DEFAULT 'Europe/London' AFTER user_url";
						$wueries[] = "ALTER TABLE %pissues ADD issue_ruling mediumtext DEFAULT NULL AFTER issue_text";
						$queries[] = "CREATE TABLE %preopen (
							  reopen_id int(10) unsigned NOT NULL AUTO_INCREMENT,
							  reopen_issue int(10) unsigned NOT NULL DEFAULT '0',
							  reopen_project int(10) unsigned NOT NULL DEFAULT '0',
							  reopen_user int(10) unsigned NOT NULL DEFAULT '0',
							  reopen_date int(10) unsigned NOT NULL DEFAULT '0',
							  reopen_reason mediumtext NOT NULL,
							  PRIMARY KEY (reopen_id),
							  KEY reopen_issue (reopen_issue)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8";

					default:
						break;
				}

				execute_queries( $queries, $this->db );

				$this->settings['app_version'] = $this->version;
				$this->save_settings();

				echo "<div class='title'>Upgrade Successful</div>
					You can <a href=\"../index.php\">return to your site</a> now.<br /><br />
				        <span style='color:red'>Please DELETE THE INSTALL DIRECTORY NOW for security purposes!!</span>
				</div>";
				break;
		}
	}
}
?>