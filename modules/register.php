<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2018 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class register extends module
{
	function execute()
	{
		$this->title = $this->settings['site_name'] . ' :: User Registration';

		if (!isset($this->get['s'])) {
			$this->get['s'] = null;
		}

		switch( $this->get['s'] )
		{
			case 'validateaccount':
				return $this->validate_user();
				break;

			case 'forgotpassword':
				return $this->forgot_password();
				break;

			case 'resetpassword':
				return $this->reset_password();
				break;
		}

		if (!isset($this->post['submit'])) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/register.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );

			if( !empty($this->settings['wordpress_api_key']) ) {
				$xtpl->parse( 'Registration.Akismet' );
			}

			$type = mt_rand(1,3);
			$num1 = mt_rand();
			$num2 = mt_rand();
			$answer = 0;

			switch( $type )
			{
				case 1: $answer = $num1 + $num2; $op = '+'; break;
				case 2: $answer = $num1 - $num2; $op = '-'; break;
				case 3: $answer = $num1 * $num2; $op = '*'; break;
			}
			$_SESSION['answer'] = $answer;

			$xtpl->assign( 'prompt', "What is $num1 $op $num2 ?" );

			if( isset( $this->settings['registration_terms'] ) && !empty( $this->settings['registration_terms'] ) ) {
				$flags = ISSUE_BBCODE | ISSUE_EMOTICONS;
				$text = $this->format( $this->settings['registration_terms'], $flags );

				$xtpl->assign( 'registration_terms', $text );
				$xtpl->parse( 'Registration.Terms' );
			}

			$xtpl->parse( 'Registration' );
			return $xtpl->text( 'Registration' );
		}

		if( !$this->is_valid_token() ) {
			return $this->message( 'New User Registration', 'Cookies are not being accepted by your browser. Please adjust your privacy settings, then go back and try again.' );
		}

		if( isset( $this->settings['registration_terms'] ) && !empty( $this->settings['registration_terms'] ) ) {
			if( !isset( $this->post['terms_agreed'] ) )
				return $this->message( 'New User Registration', 'You have declined to agree to the terms of use.' );
		}

		if ( !isset( $this->post['user_name'] ) || !$this->valid_user( $this->post['user_name'] ) )
			return $this->message( 'New User Registration', 'User name contains illegal characters.' );

		if ( !isset( $this->post['user_email'] ) || !$this->is_email( $this->post['user_email'] ) )
			return $this->message( 'New User Registration', 'User email contains illegal characters.' );

		if ( !isset( $this->post['user_math'] ) )
			return $this->message( 'New User Registration', 'You failed to correctly answer the math question. Please try again.' );

		$name = $this->post['user_name'];
		$email = $this->post['user_email'];
		$url = $this->post['user_url'];
		$math = $this->post['user_math'];

		if( $math != $_SESSION['answer'] )
			return $this->message( 'New User Registration', 'You failed to correctly answer the math question. Please try again.' );

		$stmt = $this->db->prepare( 'SELECT user_id FROM %pusers WHERE user_name=?' );

		$stmt->bind_param( 's', $name );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$prev_user = $result->fetch_assoc();

		$stmt->close();

		if( $prev_user )
			return $this->message( 'New User Registration', 'A user by that name has already registered here.' );

		$stmt = $this->db->prepare( 'SELECT user_id FROM %pusers WHERE user_email=?' );

		$stmt->bind_param( 's', $email );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$prev_email = $result->fetch_assoc();

		$stmt->close();

		if( $prev_email )
			return $this->message( 'New User Registration', 'A user with that email address has already registered here.' );

		if( !empty( $this->settings['wordpress_api_key'] ) ) {
			require_once( 'lib/akismet.php' );
			$spam_checked = false;

			try {
				$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->version);

				$akismet->setCommentAuthor($this->post['user_name']);
				$akismet->setCommentAuthorEmail($this->post['user_email']);
				$akismet->setCommentAuthorURL($this->post['user_url']);
				$akismet->setCommentContent($this->post['user_regcomment']);
				$akismet->setCommentType('signup');

				$spam_checked = true;
			}
			// Try and deal with it rather than say something.
			catch(Exception $e) {}

			if( $spam_checked && $akismet->isCommentSpam() ) {
				$this->settings['register_spam_count']++;
				$this->save_settings();

				$stmt = $this->db->prepare( 'INSERT INTO %pusers (user_name, user_password, user_email, user_level, user_perms, user_joined, user_url, user_ip) VALUES( ?, ?, ?, ?, ?, ?, ?, ? )' );

				$f1 = USER_SPAM;
				$stmt->bind_param( 'sssiiiss', $name, $dbpass, $email, $f1, $perms, $jointime, $url, $this->ip );
				$this->db->execute_query( $stmt );

				$id = $this->db->insert_id();
				$stmt->close();

				$stmt = $this->db->prepare( 'INSERT INTO %pspam (spam_user, spam_type, spam_date, spam_url, spam_ip, spam_server) VALUES( ?, ?, ?, ?, ? )' );

				$svars = json_encode($_SERVER);
				$f1 = SPAM_REGISTRATION;
				$stmt->bind_param( 'iiiss', $id, $f1, $this->time, $url, $this->ip, $svars );

				$this->db->execute_query( $stmt );
				$stmt->close();

				return $this->message( 'New User Registration', $this->settings['site_spamregmessage'] );
			}
		}

		if( $this->post['user_pass'] != $this->post['user_passconfirm'] )
			return $this->message( 'Registration Failure', 'Your password does not match the confirmation field. Please go back and try again.' );

		$dbpass = $this->afktrack_password_hash( $this->post['user_pass'] );

		$this->settings['user_count']++;
		$this->save_settings();

		if( isset( $this->settings['validate_users'] ) && $this->settings['validate_users'] == 1 ) {
			$level = USER_VALIDATING;
		} else {
			$level = USER_MEMBER;
		}

		$perms = PERM_ICON;

		$stmt = $this->db->prepare( 'INSERT INTO %pusers (user_name, user_password, user_email, user_level, user_perms, user_joined, user_ip) VALUES( ?, ?, ?, ?, ?, ?, ? )' );

		$stmt->bind_param( 'sssiiis', $name, $dbpass, $email, $level, $perms, $this->time, $this->ip );
		$this->db->execute_query( $stmt );

		$id = $this->db->insert_id();
		$stmt->close();

		setcookie($this->settings['cookie_prefix'] . 'user', $id, $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
		setcookie($this->settings['cookie_prefix'] . 'pass', $dbpass, $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

		if( isset( $this->settings['validate_users'] ) && $this->settings['validate_users'] == true ) {
			$this->send_user_validation_email( $email, $name, $dbpass, $this->time, true );

			return $this->message( 'New User Registration', 'Your account has been created. Email validation is required. A link has been sent to your email address to validate your account.', 'Continue', '/' );
		}
		return $this->message( 'New User Registration', 'Your account has been created.', 'Continue', '/' );
	}

	function send_user_validation_email( $email, $name, $dbpass, $jointime, $newaccount )
	{
		$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
		$subject = 'User Account Validation';
		$message = "An email validation has been initiated for your user account at {$this->settings['site_name']}: {$this->settings['site_address']}\n\n";
		$message .= "Your user name is: {$this->post['user_name']}\n";
		$message .= "Click on the following link to validate your account: {$this->settings['site_address']}index.php?a=register&s=validateaccount&e=" . md5($email . $name . $dbpass . $jointime) . "\n\n";

		mail( $this->post['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );

		if( $newaccount && $this->settings['admin_notify_accounts'] ) {
			$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
			$subject = 'New user signup';
			$message = "A new user has signed up at {$this->settings['site_name']} named {$this->post['user_name']}\n";

			mail( $this->settings['email_adm'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
		}
	}

	function validate_user()
	{
		if (isset($this->get['e'])) {
			$stmt = $this->db->prepare( 'SELECT user_id, user_level FROM %pusers WHERE MD5(CONCAT(user_email, user_name, user_password, user_joined))=? LIMIT 1' );

			$stmt->bind_param( 's', $this->get['e'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$user = $result->fetch_assoc();

			$stmt->close();

			if( $user && $user['user_id'] != USER_GUEST && $user['user_level'] == USER_VALIDATING ) {
				$stmt = $this->db->prepare( 'UPDATE %pusers SET user_level=? WHERE user_id=?' );

				$f1 = USER_MEMBER;
				$stmt->bind_param( 'ii', $f1, $user['user_id'] );
				$this->db->execute_query( $stmt );

				$stmt->close();

				return $this->message( 'User Account Validation', 'Your account has been validated.', 'Continue', '/' );
			}
		}

		return $this->message( 'User Account Validation', 'There was an error during validation. Please make sure you have used the correct validation link that was sent to you.', 'Continue', '/' );
	}

	function forgot_password()
	{
		if (!isset($this->post['submit'])) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/register.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );

			$xtpl->assign( 'action_url', "{$this->settings['site_address']}index.php?a=register&amp;s=forgotpassword" );

			$xtpl->parse( 'LostPassword' );
			return $xtpl->text( 'LostPassword' );
		} else {
			if( !$this->is_valid_token() ) {
				return $this->message( 'Lost Password Recovery', 'Session security token has expired. Please return to the homepage and try again.' );
			}

			$stmt = $this->db->prepare( 'SELECT user_id, user_name, user_password, user_joined, user_email FROM %pusers WHERE user_name=? AND user_id != ? LIMIT 1' );

			$f1 = USER_GUEST;
			$stmt->bind_param( 'si', $this->post['user_name'], $f1 );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$target = $result->fetch_assoc();

			$stmt->close();

			if( !$target || !isset($target['user_id']) ) {
				return $this->message( 'Lost Password Recovery', 'No such user exists at this site.' );
			}

			$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
			$subject = 'Lost Password Recovery';

			$message  = "{$target['user_name']}:\n\n";
			$message .= "Someone has requested a password recovery for your account at {$this->settings['site_name']}.\n";
			$message .= "If you do not want to recover a lost password, please ignore or delete this email.\n\n";
			$message .= "Go to the below URL to continue with the password recovery:\n";
			$message .= "{$this->settings['site_address']}index.php?a=register&s=resetpassword&e=" . md5($target['user_email'] . $target['user_name'] . $target['user_password'] . $target['user_joined']) . "\n\n";
			$message .= "Requested from IP: {$this->ip}";

			mail( $target['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );

			return $this->message( 'Lost Password Recovery', "Lost password recovery request for user {$this->post['user_name']} has been emailed to the registered address with instructions." );
		}
	}

	function reset_password()
	{
		if (!isset($this->get['e'])) {
			$this->get['e'] = null;
		}

		$stmt = $this->db->prepare( 'SELECT user_id, user_name, user_email FROM %pusers WHERE MD5(CONCAT(user_email, user_name, user_password, user_joined))=? AND user_id != ? LIMIT 1' );

		$f1 = USER_GUEST;
		$e = preg_replace('/[^a-z0-9]/', '', $this->get['e']);
		$stmt->bind_param( 'si', $e, $f1 );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$target = $result->fetch_assoc();

		$stmt->close();

		if (!isset($target['user_id'])) {
			return $this->message( 'Lost Password Recovery', 'No such user exists at this site.' );
		}

		$newpass = $this->generate_pass(8);
		$dbpass = $this->afktrack_password_hash( $newpass );

		$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
		$subject = 'Lost Password Recovery - New Password';

		$message  = "{$target['user_name']}:\n\n";
		$message .= "You have completed a password recovery for your account at {$this->settings['site_name']}.\n";
		$message .= "Your new password is: {$newpass}\n\n";
		$message .= "It is strongly advised to log on and change this to something more secure via your profile management screen.\n\n";
		$message .= "If you are receiving this message but did NOT request a password recovery, please contact the site administrator immediately to report a security issue.";

		mail( $target['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );

		$stmt = $this->db->prepare( 'UPDATE %pusers SET user_password=? WHERE user_id=?' );

		$stmt->bind_param( 'si', $dbpass, $target['user_id'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Lost Password Recovery' , 'Password recovery complete. A new password has been sent to your email address.' );
	}
}