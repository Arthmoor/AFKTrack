<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2025 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class rss extends module
{
	public function execute()
	{
		$this->nohtml = true;

		header( 'Content-type: text/xml', 1 );

		if( isset( $this->get['type'] ) ) {
			switch( $this->get['type'] )
			{
				case 'comments':		return $this->comment_rss();
				case 'issues':			return $this->issue_rss();
			}
		} else { // Default to issues
			return $this->issue_rss();
		}
	}

	private function remove_breaks( $in )
	{
		return preg_replace( "/(<br\s*\/?>\s*)+/", ' ', $in );
	}

	private function comment_rss()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/rss.xtpl' );

		if( $this->user['user_level'] < USER_DEVELOPER ) {
  			$stmt = $this->db->prepare_query( 'SELECT c.comment_id, c.comment_date, comment_message, i.issue_id, i.issue_summary, u.user_name
				FROM %pcomments c
				LEFT JOIN %pissues i ON i.issue_id=c.comment_issue
				LEFT JOIN %pusers u ON u.user_id=c.comment_user
				WHERE !(i.issue_flags & ?) AND !(i.issue_flags & ?)
				ORDER BY c.comment_date DESC LIMIT ?' );

			$f1 = ISSUE_RESTRICTED;
			$f2 = ISSUE_SPAM;
			$stmt->bind_param( 'iii', $f1, $f2, $this->settings['rss_items'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$stmt->close();
		} else {
	  		$stmt = $this->db->prepare_query( 'SELECT c.comment_id, c.comment_date, comment_message, i.issue_id, i.issue_summary, u.user_name
				FROM %pcomments c
				LEFT JOIN %pissues i ON i.issue_id=c.comment_issue
				LEFT JOIN %pusers u ON u.user_id=c.comment_user
				ORDER BY c.comment_date DESC LIMIT ?' );

			$stmt->bind_param( 'i', $this->settings['rss_items'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$stmt->close();
		}

		while( $entry = $this->db->assoc( $result ) )
		{
			$item_title = '';
			$link = '';

			if( isset( $entry['issue_summary'] ) ) {
				$link = "index.php?a=issues&amp;i={$entry['issue_id']}&amp;c={$entry['comment_id']}#comment-{$entry['comment_id']}";

				$item_title = $entry['issue_summary'];
			}

			$xtpl->assign( 'item_title', htmlspecialchars($item_title) );
			$xtpl->assign( 'item_link', htmlspecialchars($this->settings['site_address']) . $link );

			$text = '';
			if( strlen( $entry['comment_message'] ) < 200 )
				$text = $entry['comment_message'];
			else
				$text = substr( $entry['comment_message'], 0, 197 ) . '...';
			$xtpl->assign( 'item_desc', htmlspecialchars( $text ) );

			// ISO822 format is standard for XML feeds
			$xtpl->assign( 'atom_url', $this->settings['site_address'] . 'index.php?a=rss' );
			$xtpl->assign( 'item_date', $this->t_date( $entry['comment_date'], true ) );
			$xtpl->assign( 'item_category', 'Comments' );
			$xtpl->assign( 'item_author', htmlspecialchars('nobody@example.com (' . $entry['user_name'] . ')') );

			$xtpl->parse( 'RSSFeed.Item' );
		}

		$xtpl->assign( 'rss_title', htmlspecialchars( $this->settings['site_name'] . ' :: Comments' ) );
		$xtpl->assign( 'rss_link', htmlspecialchars( $this->settings['site_address'] ) );
		$xtpl->assign( 'rss_desc', htmlspecialchars( $this->settings['rss_description'] ) );
		$xtpl->assign( 'rss_refresh', intval( $this->settings['rss_refresh'] ) );

		if( isset( $this->settings['header_logo'] ) && !empty( $this->settings['header_logo'] ) ) {
			$image_url = "{$this->settings['site_address']}{$this->banner_dir}{$this->settings['header_logo']}";
			$xtpl->assign( 'rss_image_url', htmlspecialchars( $image_url ) );
			$xtpl->parse( 'RSSFeed.Image' );
		}

		$xtpl->parse( 'RSSFeed' );
		return $xtpl->text( 'RSSFeed' );
	}

	private function issue_rss()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/rss.xtpl' );

  		$proj = 0;
  		if( isset( $this->get['proj'] ) )
			$proj = intval( $this->get['proj'] );

		$stmt = $this->db->prepare_query( 'SELECT project_name FROM %pprojects WHERE project_id=?' );

		$stmt->bind_param( 'i', $proj );
		$this->db->execute_query( $stmt );

		$p_result = $stmt->get_result();
		$project = $p_result->fetch_assoc();

		$stmt->close();

		if( !$project )
			$proj = 0;

		if( $proj ) {
			if( $this->user['user_level'] < USER_DEVELOPER ) {
				$stmt = $this->db->prepare_query( 'SELECT i.issue_id, i.issue_summary, i.issue_text, i.issue_date, p.project_name, u.user_name FROM %pissues i
					   LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					   LEFT JOIN %pusers u ON u.user_id=i.issue_user
					   WHERE p.project_id=? AND !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?)
					   ORDER BY i.issue_date DESC LIMIT ?' );

				$f1 = ISSUE_RESTRICTED;
				$f2 = ISSUE_SPAM;
				$f3 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiiii', $proj, $f1, $f2, $f3, $this->settings['rss_items'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$stmt->close();
			} else {
				$stmt = $this->db->prepare_query( 'SELECT i.issue_id, i.issue_summary, i.issue_text, i.issue_date, p.project_name, u.user_name FROM %pissues i
					   LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					   LEFT JOIN %pusers u ON u.user_id=i.issue_user
					   WHERE p.project_id=? AND !(issue_flags & ?)
					   ORDER BY i.issue_date DESC LIMIT ?' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'iii', $proj, $f1, $this->settings['rss_items'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$stmt->close();
			}
		} else {
			if( $this->user['user_level'] < USER_DEVELOPER ) {
				$stmt = $this->db->prepare_query( 'SELECT i.issue_id, i.issue_summary, i.issue_text, i.issue_date, p.project_name, u.user_name FROM %pissues i
					   LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					   LEFT JOIN %pusers u ON u.user_id=i.issue_user
					   WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?)
					   ORDER BY i.issue_date DESC LIMIT ?' );

				$f1 = ISSUE_RESTRICTED;
				$f2 = ISSUE_SPAM;
				$f3 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiii', $f1, $f2, $f3, $this->settings['rss_items'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$stmt->close();
			} else {
				$stmt = $this->db->prepare_query( 'SELECT i.issue_id, i.issue_summary, i.issue_text, i.issue_date, p.project_name, u.user_name FROM %pissues i
					   LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					   LEFT JOIN %pusers u ON u.user_id=i.issue_user
					   WHERE !(issue_flags & ?)
					   ORDER BY i.issue_date DESC LIMIT ?' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'ii', $f1, $this->settings['rss_items'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$stmt->close();
			}
		}

		while( $entry = $this->db->assoc( $result ) )
		{
			$xtpl->assign( 'item_title', htmlspecialchars( $entry['issue_summary'] ) );

			$link = "index.php?a=issues&amp;i={$entry['issue_id']}";
			$xtpl->assign( 'item_link', htmlspecialchars( $this->settings['site_address'] ) . $link );

			$text = '';
			if( strlen( $entry['issue_text'] ) < 200 )
				$text = $entry['issue_text'];
			else
				$text = substr( $entry['issue_text'], 0, 197 ) . '...';
			$xtpl->assign( 'item_desc', htmlspecialchars( $text ) );

			// ISO822 format is standard for XML feeds
			$xtpl->assign( 'atom_url', $this->settings['site_address'] . 'index.php?a=rss' );
			$xtpl->assign( 'item_date', $this->t_date( $entry['issue_date'], true ) );
			$xtpl->assign( 'item_category', 'Issues' );
			$xtpl->assign( 'item_project', htmlspecialchars( $entry['project_name'] ) );
			$xtpl->assign( 'item_author', htmlspecialchars( 'nobody@example.com (' . $entry['user_name'] . ')' ) );

			$xtpl->parse( 'RSSFeed.Item' );
  		}

		if( $proj )
			$xtpl->assign( 'rss_title', htmlspecialchars( $this->settings['site_name'] . ' :: ' . $project['project_name'] . ' :: Issues ') );
		else
			$xtpl->assign( 'rss_title', htmlspecialchars( $this->settings['site_name'] . ' :: Issues' ) );
		$xtpl->assign( 'rss_link', htmlspecialchars( $this->settings['site_address'] ) );
		$xtpl->assign( 'rss_desc', htmlspecialchars( $this->settings['rss_description'] ) );
		$xtpl->assign( 'rss_refresh', intval( $this->settings['rss_refresh'] ) );

		if( isset( $this->settings['header_logo'] ) && !empty( $this->settings['header_logo'] ) ) {
			$image_url = "{$this->settings['site_address']}{$this->banner_dir}{$this->settings['header_logo']}";
			$xtpl->assign( 'rss_image_url', htmlspecialchars( $image_url ) );
			$xtpl->parse( 'RSSFeed.Image' );
		}

		$xtpl->parse( 'RSSFeed' );
		return $xtpl->text( 'RSSFeed' );
	}
}
?>