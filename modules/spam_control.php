<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2020 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class spam_control extends module
{
	public function execute()
	{
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 403, 'Access Denied: You do not have permission to perform that action.' );

		$svars = array();
		$this->title( 'Spam Control' );

		if( !isset( $this->get['c'] ) ) {
			return $this->display_spam();
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		if( !$this->is_valid_integer( $this->get['c'] ) ) {
			return $this->error( 404 );
		}

		$c = intval( $this->get['c'] );

		if( $c == 0 ) {
			if( $this->user['user_level'] < USER_ADMIN )
				return $this->error( 403, 'Access Denied: You do not have permission to perform that action.' );

			$this->db->dbquery( 'TRUNCATE TABLE %pspam' );
			return $this->message( 'Spam Control', 'All comment spam has been deleted.', 'Continue', '/index.php' );
		}

		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'delete_spam':	return $this->delete_spam( $c );
				case 'report_ham':	return $this->report_ham( $c );
			}
		}
		return $this->error( 404 );
	}

	private function delete_spam( $c )
	{
		$stmt = $this->db->prepare( 'SELECT * FROM %pspam WHERE spam_id=?' );

		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$spam = $result->fetch_assoc();

		$stmt->close();

		if( !$spam )
			return $this->message( 'Spam Control', 'There is no such spam entry.', 'Continue', '/index.php?a=spam_control' );

		$stmt = $this->db->prepare( 'DELETE FROM %pspam WHERE spam_id=?', $c );

		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Spam Control', 'Spam Deleted.', 'Continue', $this->settings['site_address'] . 'index.php?a=spam_control' );
	}

	private function report_ham( $c )
	{
		$stmt = $this->db->prepare( 'SELECT s.*, i.issue_id, i.issue_text, u.user_id, u.user_name, u.user_email FROM %pspam s
			LEFT JOIN %pusers u ON u.user_id=s.spam_user
			LEFT JOIN %pissues i ON i.issue_id=s.spam_issue
			LEFT JOIN %pcomments c ON c.comment_id=s.spam_issue
			WHERE spam_id=?' );

		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$spam = $result->fetch_assoc();

		$stmt->close();

		if( !$spam )
			return $this->message( 'Spam Control', 'There is no such spam entry.', 'Continue', '/index.php?a=spam_control' );

		$svars = json_decode( $spam['spam_server'], true );

		// Setup and deliver the information to flag this comment as legit with Akismet.
		require_once( 'lib/akismet.php' );
		$akismet = new Akismet( $this );
		$akismet->set_comment_author( $spam['user_name'] );
		$akismet->set_comment_author_url( $spam['spam_url'] );
		$akismet->set_comment_ip( $spam['spam_ip'] );
		$akismet->set_comment_referrer( $svars['HTTP_REFERER'] );
		$akismet->set_comment_useragent( $svars['HTTP_USER_AGENT'] );
		$akismet->set_comment_time( $spam['spam_date'] );

		switch( $spam['spam_type'] )
		{
			case SPAM_REGISTRATION:
				$akismet->set_comment_author_email( $spam['user_email'] );
				$akismet->set_comment_type( 'signup' );
				break;

			case SPAM_ISSUE:
				$akismet->set_comment_content( $spam['issue_text'] );
				$akismet->set_comment_type( 'bug-report' );
				break;

			case SPAM_COMMENT:
				$akismet->set_comment_content( $spam['spam_comment'] );
				$akismet->set_comment_type( 'comment' );
				break;
		}
		$akismet->submit_ham();

		$this->settings['spam_count']--;
		$this->settings['ham_count']++;

		switch( $spam['spam_type'] )
		{
			case SPAM_REGISTRATION:
				$stmt = $this->db->prepare( 'UPDATE %pusers SET user_level=? WHERE user_id=?' );

				$f1 = USER_MEMBER;
				$stmt->bind_param( 'ii', $f1, $spam['user_id'] );
				$this->db->execute_query( $stmt );
				$stmt->close();

				break;

			case SPAM_ISSUE:
				$stmt = $this->db->prepare( 'SELECT issue_flags FROM %pissues WHERE issue_id=?' );

				$stmt->bind_param( 'i', $spam['issue_id'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$issueflags = $result->fetch_assoc();

				$stmt->close();

				// Split into 2 steps here as it doesn't seem to like doing it in one.
				$flags = $issueflags['issue_flags'];
				$flags &= ~ISSUE_SPAM;

				$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_flags=? WHERE issue_id=?' );

				$stmt->bind_param( 'ii', $flags, $spam['issue_id'] );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$this->settings['total_issues']++;

				break;

			case SPAM_COMMENT:
				$stmt = $this->db->prepare( 'INSERT INTO %pcomments (comment_issue, comment_user, comment_message, comment_date, comment_ip)
				   VALUES ( ?, ?, ?, ?, ?)' );

				$stmt->bind_param( 'iisis', $spam['issue_id'], $spam['spam_user'], $spam['spam_comment'], $spam['spam_date'], $spam['spam_ip'] );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_comment_count=issue_comment_count+1 WHERE issue_id=?' );

				$stmt->bind_param( 'i', $spam['issue_id'] );
				$this->db->execute_query( $stmt );
				$stmt->close();

				break;
		}

		$this->save_settings();
		$stmt = $this->db->prepare( 'DELETE FROM %pspam WHERE spam_id=?', $c );

		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Spam Control', 'Spam entry has been resolved and Akismet notified of a false positive.', 'Continue', $this->settings['site_address'] . 'index.php?a=spam_control' );
	}

	private function display_spam()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/spam_control.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );

		$result = $this->db->dbquery( 'SELECT s.*, u.user_name, i.issue_id, i.issue_summary FROM %pspam s
			LEFT JOIN %pusers u ON u.user_id=s.spam_user
			LEFT JOIN %pissues i ON i.issue_id=s.spam_issue
			LEFT JOIN %pcomments c ON c.comment_id=s.spam_issue' );

		while( $spam = $this->db->assoc( $result ) )
		{
			$xtpl->assign( 'spam_id', $spam['spam_id'] );
			$xtpl->assign( 'spam_user', htmlspecialchars( $spam['user_name'] ) );
			$xtpl->assign( 'spam_ip', $spam['spam_ip'] );
			$xtpl->assign( 'ham_link', $this->settings['site_address'] . 'index.php?a=spam_control&amp;s=report_ham&amp;c=' . $spam['spam_id'] );
			$xtpl->assign( 'delete_link', $this->settings['site_address'] . 'index.php?a=spam_control&amp;s=delete_spam&amp;c=' . $spam['spam_id'] );

			$xtpl->assign( 'spam_date', $this->t_date( $spam['spam_date'] ) );

			$type = 'Unknown';
			$content = 'Unknown';

			switch( $spam['spam_type'] )
			{
				case SPAM_REGISTRATION:
					$type = 'User Registration';
					$content = 'Username: ' . $spam['user_name'];
					break;

				case SPAM_ISSUE:
					$type = 'Issue';
					$content = "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$spam['issue_id']}\">Issue# {$spam['issue_id']}: {$spam['issue_summary']}</a>";
					break;

				case SPAM_COMMENT:
					$type = 'Comment';
					$content = htmlspecialchars($spam['spam_comment']);
					break;

				default:              break;
			}

			$xtpl->assign( 'spam_type', $type );
			$xtpl->assign( 'spam_content', $content );

			$xtpl->parse( 'SpamControl.Entry' );
		}

		if( $this->user['user_level'] == USER_ADMIN ) {
			$xtpl->assign( 'clear_all_link', $this->settings['site_address'] . 'index.php?a=spam_control&amp;c=0' );

			$xtpl->parse( 'SpamControl.ClearAll' );
		}

		$xtpl->parse( 'SpamControl' );
		return $xtpl->text( 'SpamControl' );
	}
}
?>