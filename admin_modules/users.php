<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class users extends module
{
	private $user_groups = array( USER_GUEST => 'Anonymous', USER_SPAM => 'Spammer', USER_VALIDATING => 'Validating', USER_MEMBER => 'Member', USER_DEVELOPER => 'Developer', USER_ADMIN => 'Administrator' );

	public function execute()
	{
		if( isset( $this->get['s'] ) )
			switch( $this->get['s'] )
			{
				case 'create':	return $this->create_user();
				case 'edit':	return $this->edit_user();
				case 'delete':	return $this->delete_user();
			}

		return $this->list_users();
	}

	private function list_users()
	{
		$find = null;
		if( isset( $this->post['find_user'] ) )
			$find = $this->post['find_user'];

		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) )
			$num = intval( $this->get['num'] );

		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		$total = null;
		$list_total = 0;

		if( $find ) {
			$stmt = $this->db->prepare( 'SELECT user_id, user_name, user_icon, user_email, user_level, user_joined, user_issue_count, user_comment_count, user_last_visit
				FROM %pusers WHERE user_name LIKE ? ORDER BY user_joined DESC' );

			$find = "%$find%";
			$stmt->bind_param( 's', $find );
			$this->db->execute_query( $stmt );

			$users = $stmt->get_result();
			$stmt->close();

			$stmt = $this->db->prepare( 'SELECT COUNT(user_id) count FROM %pusers WHERE user_name LIKE ?' );

			$stmt->bind_param( 's', $find );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$total = $result->fetch_assoc();

			$stmt->close();
		} else {
			$stmt = $this->db->prepare( 'SELECT user_id, user_name, user_icon, user_email, user_level, user_joined, user_issue_count, user_comment_count, user_last_visit
			   FROM %pusers ORDER BY user_joined DESC LIMIT ?, ?' );

			$stmt->bind_param( 'ii', $min, $num );
			$this->db->execute_query( $stmt );

			$users = $stmt->get_result();
			$stmt->close();

			$total = $this->db->quick_query( 'SELECT COUNT(user_id) count FROM %pusers' );
		}

		if( $total )
			$list_total = $total['count'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/users.xtpl' );

		$xtpl->assign(' action_link', 'admin.php?a=users&amp;s=find' );
		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'header', 'User List' );

 		while( $user = $this->db->assoc( $users ) )
		{
			$icon_file = $user['user_icon'];
			if( empty($icon_file) )
				$icon_file = 'Anonymous.png';
			$xtpl->assign( 'user_icon', $this->display_icon( $icon_file ) );

			$xtpl->assign( 'user_id', $user['user_id'] );
			$xtpl->assign( 'user_name', htmlspecialchars($user['user_name']) );
			$xtpl->assign( 'user_email', htmlspecialchars($user['user_email']) );
			$xtpl->assign( 'user_group', $this->user_groups[$user['user_level']] );
			$xtpl->assign( 'join_date', $this->t_date( $user['user_joined'] ) );
			$xtpl->assign( 'visit_date', $this->t_date( $user['user_last_visit'] ) );
			$xtpl->assign( 'issue_count', $user['user_issue_count'] );
			$xtpl->assign( 'comment_count', $user['user_comment_count'] );

			$xtpl->parse( 'Users.Member' );
		}

		if( !$find ) {
			$pagelinks = $this->make_links( -1, $list_total, $min, $num, null );

			$xtpl->assign( 'pagelinks', $pagelinks );
			$xtpl->parse( 'Users.PageLinks' );
		}

		$xtpl->parse( 'Users' );
		return $xtpl->text( 'Users' );
	}

	private function user_form( $header, $link, $label, $id = -1, $user = array( 'user_perms' => 1, 'user_name' => null, 'user_email' => null, 'user_icon' => 'Anonymous.png', 'user_level' => USER_SPAM ) )
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/user_form.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'link', $link );
		$xtpl->assign( 'header', $header );
		$xtpl->assign( 'user_name', htmlspecialchars( $user['user_name'] ) );
		$xtpl->assign( 'email', htmlspecialchars( $user['user_email'] ) );

		if( $label == 'Edit' ) {
			$xtpl->assign( 'icon_file', $this->display_icon( $user['user_icon']) );
			if( $this->is_email( $user['user_icon'] ) )
				$xtpl->assign( 'gravatar', $user['user_icon'] );
			else
				$xtpl->assign( 'gravatar', null );

			$xtpl->parse( 'UserForm.Edit' );
		}

		$options = null;
		for( $x = USER_GUEST; $x <= USER_ADMIN; $x++ )
		{
			if( $x == $user['user_level'] )
				$options .= "<option value=\"$x\" selected=\"selected\">{$this->user_groups[$x]}</option>";
			else
				$options .= "<option value=\"$x\">{$this->user_groups[$x]}</option>";
		}
		$xtpl->assign( 'group_options', $options );

		$xtpl->assign( 'perm_banned', PERM_BANNED );
		$xtpl->assign( 'perm_icon', PERM_ICON );

		$xtpl->assign( 'iconbox', $user['user_perms'] & PERM_ICON ? ' checked="checked"' : null );
		$xtpl->assign( 'banned_box', $user['user_perms'] & PERM_BANNED ? ' checked="checked"' : null );

		$xtpl->parse( 'UserForm' );
		return $xtpl->text( 'UserForm' );
	}

	private function create_user()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if( isset( $this->post['submit'] ) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			if( empty( $this->post['user_name'] ) || empty( $this->post['user_email'] ) )
				return $this->message( 'Create User', 'You must fill in all fields.' );

			if( !$this->valid_user( $this->post['user_name'] ) )
				return $this->message( 'Create User', 'User name contains illegal characters.' );

			if( !$this->is_email( $this->post['user_email'] ) )
				return $this->message( 'Create User', 'User email contains illegal charcters.' );

			$name = $this->post['user_name'];
			$exists = $this->db->quick_query( "SELECT user_id, user_name FROM %pusers WHERE user_name='%s'", $name );
			if( $exists )
				return $this->message( 'Create User', 'User already exists. Do you want to edit them?', 'Edit', "admin.php?a=users&amp;s=edit&amp;user={$exists['user_id']}", 0 );

			$email = $this->post['user_email'];
			$pass = $this->generate_pass( 16 );
			$dbpass = $this->afktrack_password_hash( $pass );
			$level = intval( $this->post['user_level'] );
			if( $level < USER_VALIDATING || $level > USER_ADMIN )
				$level = USER_VALIDATING;

			$perms = 0;
			if( isset( $this->post['user_perms'] ) ) {
				foreach( $this->post['user_perms'] as $flag )
					$perms |= intval( $flag );
			}

			$this->db->dbquery( "INSERT INTO %pusers (user_name, user_password, user_email, user_level, user_perms, user_icon, user_joined)
					   VALUES( '%s', '%s', '%s', %d, %d, 'Anonymous.png', %d )", $name, $dbpass, $email, $level, $perms, $this->time );

			$this->settings['user_count']++;
			$this->save_settings();

			$headers = "From: {$this->settings['site_name']} <{$this->settings['email_adm']}>\r\n" . "X-Mailer: PHP/" . phpversion();
			$subject = "New account creation";
			$message = "A new account has been registered for you at {$this->settings['site_name']}: {$this->settings['site_address']}\n\n";
			$message .= "Your user name is: {$this->post['user_name']}\n";
			$message .= "Your temporary password is: $pass\n\n";
			$message .= 'Please write this information down as you will need it in order to log on to the site. You should change this password at your earliest convenience to something you will more easily remember. ';
			$message .= 'You will be able to make any changes to your user profile once you log on the first time.';

			mail( $this->post['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
			return $this->message( 'Create User', 'User created. Their password has been mailed to them.', 'Continue', 'admin.php?a=users' );
		}
		return $this->user_form( 'Create User', 'admin.php?a=users&amp;s=create', 'Create' );
	}

	private function edit_user()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( isset($this->get['user']) )
		{
			$id = intval( $this->get['user'] );

			$user = $this->db->quick_query( 'SELECT user_name, user_email, user_icon, user_level, user_perms FROM %pusers WHERE user_id=%d', $id );
			if( !$user )
				return $this->message( 'Edit User', 'No such user exists.' );

			if ( isset($this->post['submit']) )
			{
				if( !$this->is_valid_token() ) {
					return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
				}

				if ( !$this->is_email( $this->post['user_email'] ) )
					return $this->message( 'Edit User', 'Email contains illegal characters.' );

				$name = $this->post['user_name'];
				$email = $this->post['user_email'];

				$icon = null;
				if( isset($this->post['user_icon']) )
					$icon = "user_icon='Anonymous.png',";

				$level = intval($this->post['user_level']);
				if( $level < USER_VALIDATING || $level > USER_ADMIN )
					$level = USER_VALIDATING;

				$perms = 0;
				if( isset( $this->post['user_perms'] ) ) {
					foreach( $this->post['user_perms'] as $flag )
						$perms |= intval($flag);
				}

				$passgen = null;
				if( isset($this->post['user_pass']) ) {
					$pass = $this->generate_pass(8);
					$dbpass = $this->afktrack_password_hash( $pass );
					$passgen = '<br />New password generated and emailed.';

					$headers = "From: {$this->settings['site_name']} <{$this->settings['email_adm']}>\r\n" . "X-Mailer: PHP/" . phpversion();
					$subject = "Administrative Password Reset";
					$message = "Your password at {$this->settings['site_name']} has been reset by an administrator.\n\n";
					$message .= "Your temporary password is: $pass\n\n";
					$message .= 'Please write this information down as you will need it in order to log on to the site. You should change this password at your earliest convenience to something you will more easily remember.';
					$message .= 'You can change your password via the user profile management screen after logging in.\n';
					$message .= "Site URL: {$this->settings['site_address']}";

					mail( $this->post['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );

					$this->db->dbquery( "UPDATE %pusers SET user_password='%s', $icon user_name='%s', user_email='%s', user_level=%d, user_perms=%d WHERE user_id=%d",
						$dbpass, $name, $email, $level, $perms, $id );
				}
				else {
					$this->db->dbquery( "UPDATE %pusers SET $icon user_name='%s', user_email='%s', user_level=%d, user_perms=%d WHERE user_id=%d",
						$name, $email, $level, $perms, $id );
				}

				return $this->message( 'Edit User', "User edited.$passgen", 'Continue', 'admin.php?a=users' );
			}
			return $this->user_form( 'Edit User', "admin.php?a=users&amp;s=edit&amp;user=$id", 'Edit', $id, $user );
		}
		return $this->list_users();
	}

	private function delete_user()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if( $this->settings['user_count'] <= 1 )
			return $this->message( 'Delete User', 'You cannot delete the only user left.' );

		if( isset( $this->get['user'] ) )
		{
			$id = intval( $this->get['user'] );

			$user = $this->db->quick_query( 'SELECT user_name, user_icon FROM %pusers WHERE user_id=%d', $id );
			if( !$user )
				return $this->message( 'Delete User', 'No such user exists.' );

			if( $this->user['user_id'] == $id )
				return $this->message( 'Delete User', 'You cannot delete yourself.' );

			if( $id == 1 )
				return $this->message( 'Delete User', 'You cannot delete the Anonymous user.' );

			if( !isset( $this->post['submit'] ) ) {
				$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/user_form.xtpl' );

				$xtpl->assign( 'token', $this->generate_token() );
				$xtpl->assign( 'action_link', 'admin.php?a=users&amp;s=delete&amp;user=' . $id );
				$xtpl->assign( 'user_name', $user['user_name'] );

				$xtpl->parse( 'UserDelete' );
				return $xtpl->text( 'UserDelete' );
			}

			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$this->db->dbquery( 'DELETE FROM %pusers WHERE user_id=%d', $id );
			$this->settings['user_count']--;
			$this->save_settings();

			// Deleting a user is a big deal, but content should be preserved and disposed of at the administration's discretion.
			$this->db->dbquery( 'UPDATE %pspam SET spam_user=1 WHERE spam_user=%d', $id );
			$this->db->dbquery( 'UPDATE %pcomments SET comment_user=1 WHERE comment_user=%d', $id );
			$this->db->dbquery( 'UPDATE %pissues SET issue_user=1 WHERE issue_user=%d', $id );

			if( $user['user_icon'] != 'Anonymous.png' )
				@unlink( $this->icon_dir . $user['user_icon'] );
			return $this->message( 'Delete User', 'User deleted.', 'Continue', 'admin.php?a=users' );
		}
		return $this->list_users();
	}
}
?>