<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2025 Roger Libiez aka Arthmoor
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

			check_writeable_files( 'upgrade' );

			$dbt = 'db_' . $this->settings['db_type'];
			$db = new $dbt( $this->settings['db_name'], $this->settings['db_user'], $this->settings['db_pass'], $this->settings['db_host'], $this->settings['db_pre'] );

			if( !$db->db )
			{
				echo '<br><br>A connection to the database could not be established. Please check your settings.php file to be sure it has the correct information.';
				break;
			}
			$this->db = $db;

			// Need to do this before anything else.
			$coms = $this->db->quick_query( "SELECT COUNT(*) count FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '{$this->settings['db_name']}' AND table_name = '%psettings'" );

			if( $coms['count'] < 3 ) {
				$this->db->dbquery( 'ALTER TABLE %psettings ADD settings_version smallint(2) NOT NULL default 1 AFTER settings_id' );
			}

			$this->settings = $this->load_settings( $this->settings );

			if( isset( $this->settings['app_version'] ) && $this->settings['app_version'] == $this->version ) {
				echo "<br><br><strong>The detected version of AFKTrack is the same as the version you are trying to upgrade to. The upgrade cannot be processed.</strong>";
			} elseif( isset( $this->settings['app_version'] ) && $this->settings['app_version'] > $this->version ) {
            echo "<br><br><strong>The detected version of AFKTrack is newer than the version you are trying to upgrade to. The upgrade cannot be processed.</strong>";
         } else {
				echo "	<br><br><strong>Current detected version: " . $this->settings['app_version'] . "</strong>
               <br><br><strong>Upgrading to version: " . $this->version . "</strong>
 
					<div style='text-align:center'>
					 <input type='submit' value='Proceed With Upgrade'>
					 <input type='hidden' name='mode' value='upgrade'>
					 <input type='hidden' name='step' value='2'>
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
					echo '<br>A connection to the database could not be established. Please check your settings.php file to be sure it has the correct information.';
					break;
				}
				$this->db = $db;
				$this->settings = $this->load_settings( $this->settings );

				// Missing breaks are deliberate. Upgrades from older versions need to step through all of this.
				switch( $this->settings['app_version'] )
				{
					case 1: // 1.0 to 1.1:
					case 1.01:
						unset( $this->settings['html_email'] );

						$this->settings['site_timezone'] = 'Europe/London';
						$this->settings['privacy_policy'] = 'The administration has not yet defined a privacy policy.';
						$this->settings['prune_watchlist'] = false;
						$this->settings['htts_enabled'] = 0;
						$this->settings['htts_max_age'] = 0;
						$this->settings['xfo_enabled'] = 0;
						$this->settings['xfo_policy'] = 1;
						$this->settings['xfo_allowed_origin'] = '';
						$this->settings['xcto_enabled'] = 0;
						$this->settings['csp_enabled'] = 0;
						$this->settings['csp_details'] = '';
						$this->settings['fp_enabled'] = 0;
						$this->settings['fp_details'] = '';

						$this->db->dbquery( 'ALTER TABLE %pusers ADD user_icon_type smallint(2) unsigned NOT NULL DEFAULT 1 AFTER user_issues_page' );
                  $this->db->dbquery( "ALTER TABLE %preopen ADD reopen_ip varchar(40) NOT NULL DEFAULT '127.0.0.1' AFTER reopen_date" );

						$users = $this->db->dbquery( 'SELECT * FROM %pusers' );

                  $icon_query = $this->db->prepare_query( 'UPDATE %pusers SET user_icon=?, user_icon_type=? WHERE user_id=?' );
                  $icon_query->bind_param( 'sii', $icon, $icon_type, $user_id );

						while( $user = $this->db->assoc( $users ) )
						{
							$icon_type = ICON_NONE;
							$icon = '';

							if( $this->is_email( $user['user_icon'] ) ) {
								$icon = $user['user_icon'];
								$icon_type = ICON_GRAVATAR;
							} elseif( $user['user_icon'] != 'Anonymous.png' ) {
								$icon_type = ICON_UPLOADED;

								$ext = strstr( $user['user_icon'], '.' );

								@rename( $this->icon_dir . $user['user_icon'], $this->icon_dir . $user['user_id'] . $ext );

								$icon = $user['user_id'] . $ext;
							}

							$user_id = $user['user_id'];
							$this->db->execute_query( $icon_query );
						}
						$icon_query->close();

						$queries[] = 'ALTER TABLE %pusers CHANGE user_icon user_icon varchar(50) DEFAULT NULL';
						$queries[] = "ALTER TABLE %pusers ADD user_timezone varchar(255) NOT NULL DEFAULT 'Europe/London' AFTER user_url";
						$queries[] = 'ALTER TABLE %pissues ADD issue_ruling mediumtext DEFAULT NULL AFTER issue_text';
						$queries[] = 'ALTER TABLE %pcomments DROP COLUMN comment_url';
						$queries[] = "CREATE TABLE %preopen (
							  reopen_id int(10) unsigned NOT NULL AUTO_INCREMENT,
							  reopen_issue int(10) unsigned NOT NULL DEFAULT '0',
							  reopen_project int(10) unsigned NOT NULL DEFAULT '0',
							  reopen_user int(10) unsigned NOT NULL DEFAULT '0',
							  reopen_date int(10) unsigned NOT NULL DEFAULT '0',
							  reopen_reason mediumtext NOT NULL,
							  PRIMARY KEY (reopen_id),
							  KEY reopen_issue (reopen_issue)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

						$queries[] = "CREATE TABLE %pvalidation (
							  validate_id int(10) unsigned NOT NULL,
							  validate_hash varchar(255) NOT NULL,
							  validate_time int(10) unsigned NOT NULL,
							  validate_ip varchar(40) NOT NULL DEFAULT '127.0.0.1',
							  validate_user_agent varchar(255) NOT NULL,
							  PRIMARY KEY (validate_id)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

						$queries[] = 'ALTER TABLE %pemoticons CHANGE emote_id emoji_id int(10) unsigned NOT NULL auto_increment';
						$queries[] = "ALTER TABLE %pemoticons CHANGE emote_string emoji_string varchar(15) NOT NULL default ''";
						$queries[] = "ALTER TABLE %pemoticons CHANGE emote_image emoji_image varchar(255) NOT NULL default ''";
						$queries[] = 'ALTER TABLE %pemoticons CHANGE emote_clickable emoji_clickable tinyint(1) unsigned NOT NULL default 1';
						$queries[] = 'ALTER TABLE %pemoticons RENAME %pemojis';

               case 1.1: // 1.1.0 to 1.2.0
                  $queries[] = 'ALTER TABLE %pactive CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pprojects CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pcategories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pcomponents CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pissues CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pcomments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pplatforms CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pstatus CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pseverities CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %presolutions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %ptypes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %prelated CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pvotes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pwatching CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pattachments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pemojis CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %psettings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pspam CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pusers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %preopen CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                  $queries[] = 'ALTER TABLE %pvalidation CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

                  $queries[] = "ALTER TABLE %pprojects ADD project_groups varchar(255) NOT NULL default '' AFTER project_position";

                  $queries[] = 'ALTER TABLE %pactive ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pprojects ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pcategories ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pcomponents ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pissues ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pcomments ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pplatforms ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pseverities ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %presolutions ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %ptypes ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %prelated ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pvotes ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pwatching ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pattachments ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pemojis ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %psettings ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pspam ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pusers ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %preopen ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pvalidation ENGINE=InnoDB';
                  $queries[] = 'ALTER TABLE %pstatus ENGINE=InnoDB';

               case 1.3: // 1.2.0 to 1.3.0

					default:
						break;
				}

				execute_queries( $queries, $this->db );

				$this->settings['app_version'] = $this->version;
				$this->save_settings();

				echo "<div class='title'>Upgrade Successful</div>
					You can <a href=\"../index.php\">return to your site</a> now.<br><br>
				        <span style='color:red'>Please DELETE THE INSTALL DIRECTORY NOW for security purposes!!</span>
				</div>";
				break;
		}
	}
}
?>