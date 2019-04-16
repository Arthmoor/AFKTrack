<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class profile extends module
{
	public function execute()
	{
		if( $this->user['user_level'] == USER_GUEST ) {
			return $this->error( 'You must log in with a registered account in order to view your profile.', 403 );
		}

		$this->title = $this->settings['site_name'] . ' :: User Profile';
		$errors = array();

		$email = $this->user['user_email'];
		$gravatar = null;
		$newtz = $this->user['user_timezone'];

		if( $this->is_email( $this->user['user_icon'] ) )
			$gravatar = $this->user['user_icon'];

		if( isset( $this->post['user_name'] ) )
			$name = $this->post['user_name'];

		if( isset( $this->post['user_email'] ) )
			$email = $this->post['user_email'];

		if( isset( $this->post['user_timezone'] ) )
			$newtz = $this->post['user_timezone'];

		if( isset( $this->post['submit'] ) ) {
			if( !isset( $this->post['user_name'] ) || empty( $this->post['user_name'] ) ) {
				array_push( $errors, 'You cannot enter a blank user name.' );
			}
			if( !$this->valid_user( $this->post['user_name'] ) ) {
				array_push( $errors, 'User name contains illegal characters.' );
			}

			$stmt = $this->db->prepare( 'SELECT user_id FROM %pusers WHERE user_name=?' );

			$stmt->bind_param( 's', $this->post['user_name'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$prev_user = $result->fetch_assoc();

			$stmt->close();

			if( $prev_user ) {
				if( $prev_user['user_id'] != $this->user['user_id'] )
					array_push( $errors, 'A user by that name has already registered here.' );
			}

			if( !isset( $this->post['user_email'] ) || empty( $this->post['user_email'] ) ) {
				array_push( $errors, 'You cannot enter a blank email address.' );
			}
			if( !$this->is_email( $this->post['user_email'] ) )
				array_push( $errors, 'You did not enter a valid email address.' );

			$stmt = $this->db->prepare( 'SELECT user_id FROM %pusers WHERE user_email=?' );

			$stmt->bind_param( 's', $this->post['user_email'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$prev_email = $result->fetch_assoc();

			$stmt->close();

			if( $prev_email ) {
				if( $prev_email['user_id'] != $this->user['user_id'] )
					array_push( $errors, 'That email address is already in use by someone else.' );
			}

			if( isset( $this->post['user_gravatar'] ) && !empty( $this->post['user_gravatar'] ) ) {
				if( !$this->is_email( $this->post['user_gravatar'] ) )
					array_push( $errors, 'You did not specify a valid Gravatar email address.' );

				$stmt = $this->db->prepare( 'SELECT user_id FROM %pusers WHERE user_icon=?' );

				$stmt->bind_param( 's', $this->post['user_gravatar'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$prev_email = $result->fetch_assoc();

				$stmt->close();

				if( $prev_email ) {
					if( $prev_email['user_id'] != $this->user['user_id'] )
						array_push( $errors, 'That Gravatar email address is already in use by someone else.' );
				}
			}

			if( isset( $this->post['user_password'] ) && isset( $this->post['user_pass_confirm'] ) ) {
				if( $this->post['user_password'] != $this->post['user_pass_confirm'] )
					array_push( $errors, 'Entered passwords do not match.' );
			}
			if( !$this->is_valid_token() )
				array_push( $errors, 'The security validation token used to verify you are making this change is either invalid or expired. Please try again.' );
		}

		$icon = null;
		$old_icon = $this->user['user_icon'];
		if( !isset( $this->post['user_gravatar'] ) || empty($this->post['user_gravatar']) ) {
			if( isset( $this->files['user_icon'] ) && $this->files['user_icon']['error'] == UPLOAD_ERR_OK )	{
				$fname = $this->files['user_icon']['tmp_name'];
				$system = explode( '.', $this->files['user_icon']['name'] );
				$ext = strtolower( end( $system ) );

				if( !preg_match( '/jpg|jpeg|png|gif/', $ext ) ) {
					array_push( $errors, 'Invalid icon file type ' . $ext . '. Valid file types are jpg, png and gif.' );
				} else {
					$icon = $this->user['user_name'] . '.' . $ext;
					$new_fname = $this->icon_dir . $this->user['user_name'] . '.' . $ext;

					if ( !move_uploaded_file( $fname, $new_fname ) ) {
						array_push( $errors, 'Post icon failed to upload!' );
					} else {
						$this->createthumb( $new_fname, $new_fname, $ext, $this->settings['site_icon_width'], $this->settings['site_icon_height'] );

						if( $old_icon != 'Anonymous.png' )
							@unlink( $this->icon_dir . $old_icon );
					}
				}
			} else {
				$icon = $old_icon;
			}
		} else {
			if( $this->is_email( $this->post['user_gravatar'] ) ) {
				$icon = $this->post['user_gravatar'];

				if( $old_icon != 'Anonymous.png' )
					@unlink( $this->icon_dir . $old_icon );
			} else {
				$icon = $old_icon;
			}
		}

		$action_link = "{$this->settings['site_address']}index.php?a=profile";

		if( !isset( $this->post['submit'] ) || count( $errors ) != 0 ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/profile.xtpl' );

			if( count( $errors ) > 0 ) {
				$xtpl->assign( 'errors', implode( $errors,"<br />\n" ) );
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
			$xtpl->assign( 'name', htmlspecialchars( $this->user['user_name'] ) );
			$xtpl->assign( 'email', htmlspecialchars( $email ) );
			$xtpl->assign( 'icon', $this->display_icon( $icon ) );
			$xtpl->assign( 'timezone', $this->select_timezones( $this->user['user_timezone'], 'user_timezone' ) );
			$xtpl->assign( 'gravatar', htmlspecialchars( $gravatar ) );
			$xtpl->assign( 'skin', $this->select_input( 'user_skin', $this->skin, $this->get_skins() ) );
			$xtpl->assign( 'issues', $issues );
			$xtpl->assign( 'comments', $comments );
			$xtpl->assign( 'site_issues_default', $this->settings['site_issuesperpage'] );
			$xtpl->assign( 'site_comments_default', $this->settings['site_commentsperpage'] );

			$xtpl->assign( 'date', $this->t_date( $this->user['user_joined'] ) );
			$level = $this->user['user_level'];

			$xtpl->assign( 'width', $this->settings['site_icon_width'] );
			$xtpl->assign( 'height', $this->settings['site_icon_height'] );
 
			$xtpl->parse( 'Profile' );
			return $xtpl->text( 'Profile' );
		}

		$issues = 0;
		if( isset( $this->post['user_issues_page'] ) )
			$issues = intval( $this->post['user_issues_page'] );

		$comments = 0;
		if( isset( $this->post['user_comments_page'] ) )
			$comments = intval($this->post['user_comments_page']);

		$skins = $this->get_skins();
		if( in_array( $this->post['user_skin'], $this->skins ) ) {
			setcookie( $this->settings['cookie_prefix'] . 'skin', $this->post['user_skin'], $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
			$this->skin = $this->post['user_skin'];
		}

		if( !empty( $this->post['user_password'] ) && !empty( $this->post['user_pass_confirm'] ) ) {
			$newpass = $this->afktrack_password_hash( $this->post['user_password'] );

			$stmt = $this->db->prepare( 'UPDATE %pusers SET user_name=?, user_email=?, user_icon=?, user_password=?, user_issues_page=?, user_comments_page=?, user_timezone=? WHERE user_id=?' );

			$stmt->bind_param( 'ssssiisi', $name, $email, $icon, $newpass, $issues, $comments, $newtz, $this->user['user_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();

			$action_link = '/';
		}
		else {
			$stmt = $this->db->prepare( 'UPDATE %pusers SET user_name=?, user_email=?, user_icon=?, user_issues_page=?, user_comments_page=?, user_timezone=? WHERE user_id=?' );

			$stmt->bind_param( 'sssiisi', $name, $email, $icon, $issues, $comments, $newtz, $this->user['user_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();
		}
		return $this->message( 'Edit Your Profile', 'Your profile has been updated.', 'Continue', $action_link );
	}

	private function select_input( $name, $value, $values = array() )
	{
		$out = null;

		foreach( $values as $key )
			$out .= '<option' . ( $key == $value ? ' selected="selected"' : '' ) . ">$key</option>";

		return "<select name=\"$name\">$out</select>";
	}

	private function createthumb( $name, $filename, $ext, $new_w, $new_h )
	{
		$system = explode( '.', $name );
		$src_img = null;

		if( preg_match( '/jpg|jpeg/', $ext ) )
			$src_img = imagecreatefromjpeg( $name );
		else if ( preg_match( '/png/', $ext ) )
			$src_img = imagecreatefrompng( $name );
		else if ( preg_match( '/gif/', $ext ) )
			$src_img = imagecreatefromgif( $name );

		$old_x = imageSX( $src_img );
		$old_y = imageSY( $src_img );

		if( $old_x > $old_y )
		{
			$thumb_w = $new_w;
			$thumb_h = $old_y * ( $new_h / $old_x );
		}

		if( $old_x < $old_y )
		{
			$thumb_w = $old_x * ( $new_w / $old_y );
			$thumb_h = $new_h;
		}

		if( $old_x == $old_y )
		{
			$thumb_w = $new_w;
			$thumb_h = $new_h;
		}

		$dst_img = ImageCreateTrueColor( $thumb_w, $thumb_h );
		imagecopyresampled( $dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y );

		if( preg_match( '/png/', $ext ) )
			imagepng( $dst_img, $filename );
		else if( preg_match( '/jpg|jpeg/', $ext ) )
			imagejpeg( $dst_img, $filename );
		else
			imagegif( $dst_img, $filename );

		imagedestroy( $dst_img );
		imagedestroy( $src_img );
		return array( 'width' => $old_x, 'height' => $old_y );
	}
}
?>