<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2020 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class profile extends module
{
	public function execute()
	{
		if( $this->user['user_level'] == USER_GUEST ) {
			return $this->error( 403, 'You must log in with a registered account in order to view your profile.' );
		}

		$this->title = $this->settings['site_name'] . ' :: User Profile';

		if( isset( $this->post['delete_profile'] ) ) {
			if( $this->user['user_level'] > USER_MEMBER ) {
				return $this->error( 403, 'Administrator or Developer accounts must first be demoted to normal members by an administrator before they can delete their own accounts.' );
			}

			if( !isset( $this->post['yes_delete_me'] ) ) {
				$xtpl = new XTemplate( './skins/' . $this->skin . '/profile.xtpl' );

				$action_link = "{$this->settings['site_address']}index.php?a=profile";

				$xtpl->assign( 'token', $this->generate_token() );
				$xtpl->assign( 'action_link', $action_link );
				$xtpl->assign( 'icon', $this->display_icon( $this->user ) );

				if( $this->user['user_issue_count'] > 0 || $this->user['user_comment_count'] > 0 ) {
					$xtpl->assign( 'issue_count', $this->user['user_issue_count'] );
					$xtpl->assign( 'comment_count', $this->user['user_comment_count'] );

					$xtpl->parse( 'DeleteConfirmation.ContentExists' );
				}

				$xtpl->parse( 'DeleteConfirmation' );

				return $xtpl->text( 'DeleteConfirmation' );
			}

			if( !$this->is_valid_token() ) {
				return $this->error( -1 );
			}

			$this->delete_user_account( $this->user );

			$options = array( 'expires' => $this->time - 9000, 'path' => $this->settings['cookie_path'], 'domain' => $this->settings['cookie_domain'], 'secure' => $this->settings['cookie_secure'], 'HttpOnly' => true, 'SameSite' => 'Lax' );

			setcookie( $this->settings['cookie_prefix'] . 'user', '', $options );
			setcookie( $this->settings['cookie_prefix'] . 'pass', '', $options );

			$_SESSION = array();

			session_destroy();

			$this->user['user_level'] = USER_GUEST;
			$this->user['user_id'] = 1;

			header( 'Clear-Site-Data: "*"' );
			return $this->message( 'Delete Your Profile', 'Your profile has been deleted.', 'Continue', "{$this->settings['site_address']}" );
		}

		$errors = array();

		$name = $this->user['user_name'];
		if( isset( $this->post['user_name'] ) ) {
			if( !$this->valid_user( $this->post['user_name'] ) )
				array_push( $errors, 'New user name entered is invalid.' );
			else
				$name = trim( $this->post['user_name'] );

			$stmt = $this->db->prepare( 'SELECT user_id FROM %pusers WHERE user_name=?' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$prev_user = $result->fetch_assoc();

			$stmt->close();

			if( $prev_user ) {
				if( $prev_user['user_id'] != $this->user['user_id'] ) {
					array_push( $errors, 'A user by that name has already registered here.' );
					$name = $this->user['user_name'];
				}
			}
		}

		$old_icon = $this->user['user_icon'];
		$old_type = $this->user['user_icon_type'];

		$gravatar = '';
		if( $old_type == ICON_GRAVATAR )
			$gravatar = $this->user['user_icon'];

		$icon_url = '';
		if( $old_type == ICON_URL )
			$icon_url = $this->user['user_icon'];

		$icon_type = $old_type;
		$icon = $old_icon;

		if( isset( $this->post['user_icon_type'] ) ) {
			if( !$this->is_valid_integer( $this->post['user_icon_type'] ) ) {
				array_push( $errors, 'An invalid avatar type was selected.' );
			} else {
				$icon_type = intval( $this->post['user_icon_type'] );

				if( $icon_type == ICON_GRAVATAR ) {
					if( isset( $this->post['user_gravatar'] ) ) {
						if( !$this->is_email( $this->post['user_gravatar'] ) ) {
							array_push( $errors, 'An invalid email address for Gravatar was entered.' );
							$gravatar = '';
							$icon_type = $old_type;
							$icon = $old_icon;
						} else {
							$gravatar = trim( $this->post['user_gravatar'] );

							$stmt = $this->db->prepare( 'SELECT user_id FROM %pusers WHERE user_icon=?' );

							$stmt->bind_param( 's', $gravatar );
							$this->db->execute_query( $stmt );

							$result = $stmt->get_result();
							$prev_email = $result->fetch_assoc();

							$stmt->close();

							if( $prev_email ) {
								if( $prev_email['user_id'] != $this->user['user_id'] ) {
									array_push( $errors, 'That Gravatar email address is already in use by someone else.' );
									$gravatar = '';
									$icon_type = $old_type;
									$icon = $old_icon;
								}
							} else {
								$gravatar = $this->post['user_gravatar'];
								$icon = $gravatar;
							}
						}
					}
				} elseif( $icon_type == ICON_URL ) {
					if( !$this->is_valid_url( $this->post['user_icon_url'] ) ) {
						array_push( $errors, 'The URL entered for the new avatar is not valid.' );
						$icon_type = $old_type;
						$icon = $old_icon;
					} else {
						$icon = $this->post['user_icon_url'];
						$icon_url = $icon;
					}
				}
			}
		}

		$email = $this->user['user_email'];
		if( isset( $this->post['user_email'] ) ) {
			if( !$this->is_email( $this->post['user_email'] ) )
				array_push( $errors, 'An invalid email address was entered.' );
			else {
				$email = $this->post['user_email'];

				$stmt = $this->db->prepare( 'SELECT user_id FROM %pusers WHERE user_email=?' );

				$stmt->bind_param( 's', $email );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$prev_email = $result->fetch_assoc();

				$stmt->close();

				if( $prev_email ) {
					if( $prev_email['user_id'] != $this->user['user_id'] ) {
						array_push( $errors, 'That email address is already in use by someone else.' );
						$email = $this->user['user_email'];
					}
				}
			}
		}

		$newtz = $this->user['user_timezone'];
		if( isset( $this->post['user_timezone'] ) )
			$newtz = $this->post['user_timezone'];

		if( isset( $this->post['user_password'] ) && isset( $this->post['user_pass_confirm'] ) ) {
			if( $this->post['user_password'] != $this->post['user_pass_confirm'] )
				array_push( $errors, 'Entered passwords do not match.' );
		}

		$issues = 0;
		if( isset( $this->post['user_issues_page'] ) ) {
			if( !$this->is_valid_integer( $this->post['user_issues_page'] ) ) {
				array_push( $errors, 'An invalid issues per page value was entered.' );
			} else {
				$issues = intval( $this->post['user_issues_page'] );
			}
		}

		$comments = 0;
		if( isset( $this->post['user_comments_page'] ) ) {
			if( !$this->is_valid_integer( $this->post['user_comments_page'] ) ) {
				array_push( $errors, 'An invalid comments per page value was entered.' );
			} else {
				$comments = intval( $this->post['user_comments_page'] );
			}
		}

		$password_changed = false;
		if( !empty( $this->post['user_password'] ) && !empty( $this->post['user_pass_confirm'] ) ) {
			if( $this->post['user_password'] != $this->post['user_pass_confirm'] )
				array_push( $errors, 'Password confirmation does not match.' );
			else
				$password_changed = true;
		}

		if( isset( $this->post['update_profile'] ) ) {
			if( !$this->is_valid_token() )
				array_push( $errors, 'The security validation token used to verify you are making this change is either invalid or expired. Please try again.' );
		}

		if( $icon_type == ICON_UPLOADED ) {
			if( isset( $this->files['user_icon'] ) && $this->files['user_icon']['error'] == UPLOAD_ERR_OK )	{
				$fname = $this->files['user_icon']['tmp_name'];
				$system = explode( '.', $this->files['user_icon']['name'] );
				$ext = strtolower( end( $system ) );

				if( !preg_match( '/jpg|jpeg|png|gif/', $ext ) ) {
					array_push( $errors, 'Invalid icon file type ' . $ext . '. Valid file types are jpg, png and gif.' );
					$icon = $old_icon;
					$icon_type = $old_type;
				} else {
					$icon = $this->user['user_id'] . '.' . $ext;
					$new_fname = $this->icon_dir . $this->user['user_id'] . '.' . $ext;

					if( !move_uploaded_file( $fname, $new_fname ) ) {
						array_push( $errors, 'Your new avatar failed to upload!' );
						$icon = $old_icon;
						$icon_type = $old_type;
					} else {
						$this->createthumb( $new_fname, $new_fname, $ext, $this->settings['site_icon_width'], $this->settings['site_icon_height'] );

						if( $old_icon != 'Anonymous.png' && $old_icon != $icon )
							@unlink( $this->icon_dir . $old_icon );
					}
				}
			}
		}

		$action_link = "{$this->settings['site_address']}index.php?a=profile";

		if( !isset( $this->post['update_profile'] ) || count( $errors ) != 0 ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/profile.xtpl' );

			if( count( $errors ) > 0 ) {
				$xtpl->assign( 'errors', implode( "<br>\n", $errors ) );
				$xtpl->parse( 'Profile.Errors' );
			}

			$issues = 0;
			if( isset($this->post['user_issues_page']) )
				$issues = intval( $this->post['user_issues_page'] );
			else
				$issues = $this->user['user_issues_page'];

			$comments = 0;
			if( isset( $this->post['user_comments_page'] ) )
				$comments = intval( $this->post['user_comments_page'] );
			else
				$comments = $this->user['user_comments_page'];

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', $action_link );
			$xtpl->assign( 'name', htmlspecialchars( $name ) );
			$xtpl->assign( 'email', htmlspecialchars( $email ) );
			$xtpl->assign( 'icon', $this->display_new_icon( $icon, $icon_type ) );
			$xtpl->assign( 'timezone', $this->select_timezones( $newtz, 'user_timezone' ) );
			$xtpl->assign( 'gravatar', htmlspecialchars( $gravatar ) );
			$xtpl->assign( 'icon_url', htmlspecialchars( $icon_url ) );
			$xtpl->assign( 'skin', $this->select_input( 'user_skin', $this->skin, $this->get_skins() ) );
			$xtpl->assign( 'issues', $issues );
			$xtpl->assign( 'comments', $comments );
			$xtpl->assign( 'site_issues_default', $this->settings['site_issuesperpage'] );
			$xtpl->assign( 'site_comments_default', $this->settings['site_commentsperpage'] );

			if( $icon_type == ICON_NONE ) {
				$xtpl->assign( 'av_val1', ' checked="checked"' );
				$xtpl->assign( 'av_val2', null );
				$xtpl->assign( 'av_val3', null );
				$xtpl->assign( 'av_val4', null );
			} elseif( $icon_type == ICON_UPLOADED ) {
				$xtpl->assign( 'av_val1', null );
				$xtpl->assign( 'av_val2', ' checked="checked"' );
				$xtpl->assign( 'av_val3', null );
				$xtpl->assign( 'av_val4', null );
			} elseif( $icon_type == ICON_GRAVATAR ) {
				$xtpl->assign( 'av_val1', null );
				$xtpl->assign( 'av_val2', null );
				$xtpl->assign( 'av_val3', ' checked="checked"' );
				$xtpl->assign( 'av_val4', null );
			} elseif( $icon_type == ICON_URL ) {
				$xtpl->assign( 'av_val1', null );
				$xtpl->assign( 'av_val2', null );
				$xtpl->assign( 'av_val3', null );
				$xtpl->assign( 'av_val4', ' checked="checked"' );
			}

			$xtpl->assign( 'date', $this->t_date( $this->user['user_joined'] ) );
			$level = $this->user['user_level'];

			$xtpl->assign( 'width', $this->settings['site_icon_width'] );
			$xtpl->assign( 'height', $this->settings['site_icon_height'] );
 
			$xtpl->parse( 'Profile' );
			return $xtpl->text( 'Profile' );
		}

		$skins = $this->get_skins();
		if( in_array( $this->post['user_skin'], $this->skins ) ) {
			$options = array( 'expires' => $this->time + $this->settings['cookie_logintime'], 'path' => $this->settings['cookie_path'], 'domain' => $this->settings['cookie_domain'], 'secure' => $this->settings['cookie_secure'], 'HttpOnly' => true, 'SameSite' => 'Lax' );

			setcookie( $this->settings['cookie_prefix'] . 'skin', $this->post['user_skin'], $options );
			$this->skin = $this->post['user_skin'];
		}

		if( $icon_type != ICON_UPLOADED && $old_type == ICON_UPLOADED )
			@unlink( $this->icon_dir . $old_icon );

		if( $icon_type == ICON_NONE )
			$icon = '';

		if( $password_changed == true ) {
			$newpass = $this->afktrack_password_hash( $this->post['user_password'] );

			$stmt = $this->db->prepare( 'UPDATE %pusers SET user_name=?, user_email=?, user_icon_type=?, user_icon=?, user_password=?, user_issues_page=?, user_comments_page=?, user_timezone=? WHERE user_id=?' );

			$stmt->bind_param( 'ssissiisi', $name, $email, $icon_type, $icon, $newpass, $issues, $comments, $newtz, $this->user['user_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();

			$action_link = '/';
		}
		else {
			$stmt = $this->db->prepare( 'UPDATE %pusers SET user_name=?, user_email=?, user_icon_type=?, user_icon=?, user_issues_page=?, user_comments_page=?, user_timezone=? WHERE user_id=?' );

			$stmt->bind_param( 'ssisiisi', $name, $email, $icon_type, $icon, $issues, $comments, $newtz, $this->user['user_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();
		}
		return $this->message( 'Edit Your Profile', 'Your profile has been updated.', 'Continue', $action_link );
	}

	private function display_new_icon( $icon, $type )
	{
		if( $type == ICON_NONE )
			$icon = 'Anonymous.png';

		$url = $this->settings['site_address'] . $this->icon_dir . $icon;

		if( $type == ICON_GRAVATAR ) {
			$url = 'https://secure.gravatar.com/avatar/';
			$url .= md5( strtolower( trim( $icon ) ) );
			$url .= "?s={$this->settings['site_icon_width']}&amp;r=pg";
		}

		if( $type == ICON_URL )
			$url = $icon;

		return $url;
	}
}
?>