<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2018 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

require_once './lib/comments.php';

class issues extends module
{
	function execute( $index_template )
	{
		$this->comments = new comments($this);

		if ( isset($this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'create':		return $this->create_issue( $index_template );
				case 'edit':		return $this->edit_issue( $index_template );
				case 'del':		return $this->delete_issue( $index_template );
				case 'edit_comment':	return $this->comments->edit_comment();
				case 'del_comment':	return $this->comments->delete_comment();
				case 'assigned':	return $this->list_assignments();
				case 'myissues':	return $this->list_my_issues();
			}
			return $this->error( 'Invalid option passed.' );
		}

		if( isset($this->get['i']) )
			return $this->view_issue(intval($this->get['i']), $index_template);

		$projid = 0;
		if( isset($this->get['project']) )
			$projid = intval( $this->get['project'] );

		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) )
			$num = intval( $this->get['num'] );

		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		$stmt = null;
		$total = null;
		$list_total = 0;

		if( $this->user['user_level'] < USER_DEVELOPER ) {
			if( $projid == 0 ) {
				$stmt = $this->db->prepare( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
					LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					LEFT JOIN %pcategories c ON c.category_id=i.issue_category
					LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
					LEFT JOIN %pstatus t ON t.status_id=i.issue_status
					LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
					LEFT JOIN %ptypes x ON x.type_id=i.issue_type
					LEFT JOIN %pusers u ON u.user_id=i.issue_user
					WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?)
					ORDER BY issue_date DESC LIMIT ?, ?' );

				$f1 = ISSUE_RESTRICTED;
				$f2 = ISSUE_SPAM;
				$f3 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiiii', $f1, $f2, $f3, $min, $num );

				$this->db->execute_query( $stmt );
				$result = $stmt->get_result();
				$stmt->close();

				$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?)' );

				$f1 = ISSUE_RESTRICTED;
				$f2 = ISSUE_SPAM;
				$f3 = ISSUE_CLOSED;
				$stmt->bind_param( 'iii', $f1, $f2, $f3 );
				$this->db->execute_query( $stmt );

				$t_result = $stmt->get_result();
				$total = $t_result->fetch_assoc();

				$stmt->close();
			}
			else {
				$stmt = $this->db->prepare( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
					LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					LEFT JOIN %pcategories c ON c.category_id=i.issue_category
					LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
					LEFT JOIN %pstatus t ON t.status_id=i.issue_status
					LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
					LEFT JOIN %ptypes x ON x.type_id=i.issue_type
					LEFT JOIN %pusers u ON u.user_id=i.issue_user
					WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?) AND issue_project=?
					ORDER BY issue_date DESC LIMIT ?, ?' );

				$f1 = ISSUE_RESTRICTED;
				$f2 = ISSUE_SPAM;
				$f3 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiiiii', $f1, $f2, $f3, $projid, $min, $num );

				$this->db->execute_query( $stmt );
				$result = $stmt->get_result();
				$stmt->close();

				$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?) AND issue_project=?' );

				$f1 = ISSUE_RESTRICTED;
				$f2 = ISSUE_SPAM;
				$f3 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiii', $f1, $f2, $f3, $projid );
				$this->db->execute_query( $stmt );

				$t_result = $stmt->get_result();
				$total = $t_result->fetch_assoc();

				$stmt->close();
			}

		}
		elseif( $this->user['user_level'] >= USER_DEVELOPER ) {
			if( $projid == 0 ) {
				$stmt = $this->db->prepare( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
					LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					LEFT JOIN %pcategories c ON c.category_id=i.issue_category
					LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
					LEFT JOIN %pstatus t ON t.status_id=i.issue_status
					LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
					LEFT JOIN %ptypes x ON x.type_id=i.issue_type
					LEFT JOIN %pusers u ON u.user_id=i.issue_user
					WHERE !(issue_flags & ?) ORDER BY issue_date DESC LIMIT ?, ?' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'iii', $f1, $min, $num );

				$this->db->execute_query( $stmt );
				$result = $stmt->get_result();
				$stmt->close();

				$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?)' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'i', $f1 );
				$this->db->execute_query( $stmt );

				$t_result = $stmt->get_result();
				$total = $t_result->fetch_assoc();

				$stmt->close();
			}
			else {
				$stmt = $this->db->prepare( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
					LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					LEFT JOIN %pcategories c ON c.category_id=i.issue_category
					LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
					LEFT JOIN %pstatus t ON t.status_id=i.issue_status
					LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
					LEFT JOIN %ptypes x ON x.type_id=i.issue_type
					LEFT JOIN %pusers u ON u.user_id=i.issue_user
					WHERE !(issue_flags & ?) AND issue_project=?
					ORDER BY issue_date DESC LIMIT ?, ?' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiii', $f1, $projid, $min, $num );

				$this->db->execute_query( $stmt );
				$result = $stmt->get_result();
				$stmt->close();

				$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?) AND issue_project=?' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'ii', $f1, $projid );
				$this->db->execute_query( $stmt );

				$t_result = $stmt->get_result();
				$total = $t_result->fetch_assoc();

				$stmt->close();
			}
		}

		$list_total = $total['count'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/issues.xtpl' );

		$site_name = null;
		$this->navselect = 1;

		if( $projid == 0 ) {
			$site_name = 'All Projects';
			$this->title = $this->settings['site_name'] . ' :: All Projects';
		}

		$projlist = $this->db->dbquery( 'SELECT * FROM %pprojects ORDER BY project_name ASC' );
		while( $proj = $this->db->assoc($projlist) )
		{
			if( $proj['project_id'] == $projid ) {
				$site_name = $proj['project_name'];
				$this->projectid = $proj['project_id'];
				$this->title = $this->settings['site_name'] . ' :: ' . $proj['project_name'];
			}
		}

		$index_template->assign( 'site_name', $site_name );

		while( $row = $this->db->assoc($result) )
		{
			$xtpl->assign( 'icon', $this->display_icon( $row['user_icon'] ) );

			$issue_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$row['issue_id']}";

			$colorclass = 'article';

			if( $row['issue_flags'] & ISSUE_SPAM )
				$colorclass = 'articlealert';

			if( $row['issue_flags'] & ISSUE_RESTRICTED )
				$colorclass = 'articleredalert';

			$xtpl->assign( 'colorclass', $colorclass );

			$xtpl->assign( 'issue_id', $row['issue_id'] );
			$xtpl->assign( 'issue_type', $row['type_name'] );

			if( $row['issue_flags'] & ISSUE_CLOSED )
				$xtpl->assign( 'issue_status', 'Closed' );
			else
				$xtpl->assign( 'issue_status', $row['status_name'] );

			$xtpl->assign( 'issue_opened', date( $this->settings['site_dateformat'], $row['issue_date'] ) );
			$xtpl->assign( 'issue_opened_by', $row['user_name'] );
			$xtpl->assign( 'issue_project', $row['project_name'] );
			$xtpl->assign( 'issue_category', $row['category_name'] );
			$xtpl->assign( 'issue_summary', $row['issue_summary'] );
			$xtpl->assign( 'issue_platform', $row['platform_name'] );
			$xtpl->assign( 'issue_severity', $row['severity_name'] );
			$xtpl->assign( 'issue_link', $issue_link );

			$xtpl->parse( 'Issue.Post' );
		}

		$pagelinks = $this->make_links( $projid, $list_total, $min, $num );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Issue.PageLinks' );

		$xtpl->parse( 'Issue' );
		return $xtpl->text( 'Issue' );
	}

	function list_assignments()
	{
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 'The page you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

		$this->title = $this->settings['site_name'] . ' :: My Assignments';

		$list_total = 0;
		$total = null;

		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) )
			$num = intval( $this->get['num'] );

		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		$stmt = $this->db->prepare( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
			LEFT JOIN %pprojects p ON p.project_id=i.issue_project
			LEFT JOIN %pcategories c ON c.category_id=i.issue_category
			LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
			LEFT JOIN %pstatus t ON t.status_id=i.issue_status
			LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
			LEFT JOIN %ptypes x ON x.type_id=i.issue_type
			LEFT JOIN %pusers u ON u.user_id=i.issue_user
			WHERE issue_user_assigned=? AND !( issue_flags & ?)
			ORDER BY issue_date DESC LIMIT ?, ?', $this->user['user_id'], $min, $num );

		$f1 = ISSUE_CLOSED;
		$stmt->bind_param( 'iiii', $this->user['user_id'], $f1, $min, $num );

		$this->db->execute_query( $stmt );
		$result = $stmt->get_result();
		$stmt->close();

		$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE issue_user_assigned=? AND !(issue_flags & ?)' );

		$f1 = ISSUE_CLOSED;
		$stmt->bind_param( 'ii', $this->user['user_id'], $f1 );
		$this->db->execute_query( $stmt );

		$t_result = $stmt->get_result();
		$total = $t_result->fetch_assoc();

		$stmt->close();

		if( $total )
			$list_total = $total['count'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/issues_assigned.xtpl' );

		$this->navselect = 3;

		while ( $row = $this->db->assoc($result) )
		{
			$xtpl->assign( 'icon', $this->display_icon( $row['user_icon'] ) );

			$issue_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$row['issue_id']}";

			$colorclass = 'article';

			if( $row['issue_flags'] & ISSUE_SPAM )
				$colorclass = 'articlealert';

			if( $row['issue_flags'] & ISSUE_RESTRICTED )
				$colorclass = 'articleredalert';

			$xtpl->assign( 'colorclass', $colorclass );

			$xtpl->assign( 'issue_id', $row['issue_id'] );
			$xtpl->assign( 'issue_type', $row['type_name'] );

			if( $row['issue_flags'] & ISSUE_CLOSED )
				$xtpl->assign( 'issue_status', 'Closed' );
			else
				$xtpl->assign( 'issue_status', $row['status_name'] );

			$xtpl->assign( 'issue_opened', date( $this->settings['site_dateformat'], $row['issue_date'] ) );
			$xtpl->assign( 'issue_opened_by', $row['user_name'] );
			$xtpl->assign( 'issue_project', $row['project_name'] );
			$xtpl->assign( 'issue_category', $row['category_name'] );
			$xtpl->assign( 'issue_summary', $row['issue_summary'] );
			$xtpl->assign( 'issue_platform', $row['platform_name'] );
			$xtpl->assign( 'issue_severity', $row['severity_name'] );
			$xtpl->assign( 'issue_link', $issue_link );

			$xtpl->parse( 'Issue.Post' );
		}

		$pagelinks = $this->make_links( 0, $list_total, $min, $num );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Issue.PageLinks' );

		$xtpl->parse( 'Issue' );
		return $xtpl->text( 'Issue' );
	}

	function list_my_issues()
	{
		$this->title = $this->settings['site_name'] . ' :: Issues I Created';

		$list_total = 0;
		$total = null;

		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) )
			$num = intval( $this->get['num'] );

		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		if( $this->user['user_level'] < USER_DEVELOPER ) {
			$stmt = $this->db->prepare( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
				LEFT JOIN %pprojects p ON p.project_id=i.issue_project
				LEFT JOIN %pcategories c ON c.category_id=i.issue_category
				LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
				LEFT JOIN %pstatus t ON t.status_id=i.issue_status
				LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
				LEFT JOIN %ptypes x ON x.type_id=i.issue_type
				LEFT JOIN %pusers u ON u.user_id=i.issue_user
				WHERE issue_user=? AND !(issue_flags & ?) AND !(issue_flags & ?)
				ORDER BY issue_date DESC LIMIT ?, ?' );

			$f1 = ISSUE_RESTRICTED;
			$f2 = ISSUE_SPAM;
			$stmt->bind_param( 'iiiii', $this->user['user_id'], $f1, $f2, $min, $num );

			$this->db->execute_query( $stmt );
			$result = $stmt->get_result();
			$stmt->close();

			$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE issue_user=? AND !(issue_flags & ?) AND !(issue_flags & ?)' );

			$f1 = ISSUE_RESTRICTED;
			$f2 = ISSUE_SPAM;
			$stmt->bind_param( 'iii', $this->user['user_id'], $f1, $f2 );
			$this->db->execute_query( $stmt );

			$t_result = $stmt->get_result();
			$total = $t_result->fetch_assoc();

			$stmt->close();
		}
		elseif( $this->user['user_level'] >= USER_DEVELOPER ) {
			$stmt = $this->db->prepare( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
				LEFT JOIN %pprojects p ON p.project_id=i.issue_project
				LEFT JOIN %pcategories c ON c.category_id=i.issue_category
				LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
				LEFT JOIN %pstatus t ON t.status_id=i.issue_status
				LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
				LEFT JOIN %ptypes x ON x.type_id=i.issue_type
				LEFT JOIN %pusers u ON u.user_id=i.issue_user
				WHERE issue_user=?
				ORDER BY issue_date DESC LIMIT ?, ?' );

			$stmt->bind_param( 'iii', $this->user['user_id'], $min, $num );

			$this->db->execute_query( $stmt );
			$result = $stmt->get_result();
			$stmt->close();

			$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE issue_user=?' );

			$stmt->bind_param( 'i', $this->user['user_id'] );
			$this->db->execute_query( $stmt );

			$t_result = $stmt->get_result();
			$total = $t_result->fetch_assoc();

			$stmt->close();
		}

		if( $total )
			$list_total = $total['count'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/issues_mine.xtpl' );

		$this->navselect = 4;

		while ( $row = $this->db->assoc($result) )
		{
			$xtpl->assign( 'icon', $this->display_icon( $row['user_icon'] ) );

			$issue_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$row['issue_id']}";

			$colorclass = 'article';

			if( $row['issue_flags'] & ISSUE_SPAM )
				$colorclass = 'articlealert';

			if( $row['issue_flags'] & ISSUE_RESTRICTED )
				$colorclass = 'articleredalert';

			$xtpl->assign( 'colorclass', $colorclass );

			$xtpl->assign( 'issue_id', $row['issue_id'] );
			$xtpl->assign( 'issue_type', $row['type_name'] );

			if( $row['issue_flags'] & ISSUE_CLOSED )
				$xtpl->assign( 'issue_status', 'Closed' );
			else
				$xtpl->assign( 'issue_status', $row['status_name'] );

			$xtpl->assign( 'issue_opened', date( $this->settings['site_dateformat'], $row['issue_date'] ) );
			$xtpl->assign( 'issue_opened_by', $row['user_name'] );
			$xtpl->assign( 'issue_project', $row['project_name'] );
			$xtpl->assign( 'issue_category', $row['category_name'] );
			$xtpl->assign( 'issue_summary', $row['issue_summary'] );
			$xtpl->assign( 'issue_platform', $row['platform_name'] );
			$xtpl->assign( 'issue_severity', $row['severity_name'] );
			$xtpl->assign( 'issue_link', $issue_link );

			$xtpl->parse( 'Issue.Post' );
		}

		$pagelinks = $this->make_links( 0, $list_total, $min, $num );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Issue.PageLinks' );

		$xtpl->parse( 'Issue' );
		return $xtpl->text( 'Issue' );
	}

	function view_issue( $i, $index_template )
	{
		$stmt = $this->db->prepare( 'SELECT i.*, c.category_name, p.project_id, p.project_name, b.component_name, s.platform_name, t.status_name, r.severity_name, v.resolution_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
			LEFT JOIN %pprojects p ON p.project_id=i.issue_project
			LEFT JOIN %pcomponents b ON b.component_id=i.issue_component
			LEFT JOIN %pcategories c ON c.category_id=i.issue_category
			LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
			LEFT JOIN %pstatus t ON t.status_id=i.issue_status
			LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
			LEFT JOIN %presolutions v ON v.resolution_id=i.issue_resolution
			LEFT JOIN %pusers u ON u.user_id=i.issue_user
			LEFT JOIN %ptypes x ON x.type_id=i.issue_type
			WHERE issue_id=?' );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$issue = $result->fetch_assoc();

		$stmt->close();

		if ( !$issue || ( (($issue['issue_flags'] & ISSUE_RESTRICTED) || ($issue['issue_flags'] & ISSUE_SPAM) ) && $this->user['user_level'] < USER_DEVELOPER ) )
			return $this->error( 'The issue you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

		$this->title( '#' . $issue['issue_id'] . ' '. $issue['issue_summary'] );
		$this->meta_description( '#' . $issue['issue_id'] . ' '. $issue['issue_summary'] );

		// If these conditions are true, a comment is being posted.
		if( isset( $this->post['submit'] ) || isset( $this->post['preview'] ) || isset( $this->post['attach'] ) || isset( $this->post['detach'] ) )
		{
			if( $this->user['user_level'] < USER_MEMBER )
				return $this->error( 'You must have a validated account in order to post comments.', 403 );

			if( $this->closed_content( $issue ) )
				return $this->error( 'Sorry, this issue is closed.', 403 );

			$result = $this->comments->post_comment( $issue );

			if( is_string($result) )
				return $result;

			if( isset($this->post['request_uri']) )
				header( 'Location: ' . $this->post['request_uri'] );

			$link = "{$this->settings['site_address']}index.php?a=issues&i=$i&c=$result#comment-$result";
			header( 'Location: ' . $link );
		}

		if( isset( $this->get['w'] ) && $this->user['user_level'] >= USER_MEMBER ) {
			if( $this->get['w'] == 'startwatch' ) {
				$stmt = $this->db->prepare( 'SELECT * FROM %pwatching WHERE watch_issue=? AND watch_user=?' );
				
				$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$watching = $result->fetch_assoc();

				$stmt->close();

				if( !$watching ) {
					$stmt = $this->db->prepare( 'INSERT INTO %pwatching (watch_issue, watch_user) VALUES ( ?, ? )' );

					$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
					$this->db->execute_query( $stmt );

					$stmt->close();
				}
			}

			if( $this->get['w'] == 'stopwatch' ) {
				$stmt = $this->db->prepare( 'SELECT * FROM %pwatching WHERE watch_issue=? AND watch_user=?' );

				$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$watching = $result->fetch_assoc();

				if( $watching ) {
					$stmt = $this->db->prepare( 'DELETE FROM %pwatching WHERE watch_issue=? AND watch_user=?' );

					$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
					$this->db->execute_query( $stmt );

					$stmt->close();
				}
			}

			if( $this->get['w'] == 'vote' ) {
				$stmt = $this->db->prepare( 'SELECT * FROM %pvotes WHERE vote_issue=? AND vote_user=?' );

				$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$voted = $result->fetch_assoc();

				if( !$voted ) {
					$stmt = $this->db->prepare( 'INSERT INTO %pvotes (vote_issue, vote_user) VALUES ( ?, ? )' );

					$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
					$this->db->execute_query( $stmt );

					$stmt->close();
				}
			}
		}

		$num = $this->settings['site_commentsperpage'];
		if( $this->user['user_comments_page'] > 0 )
			$num = $this->user['user_comments_page'];

		if( isset( $this->get['num'] ) )
			$num = intval( $this->get['num'] );

		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		if( isset($this->get['c']) ) {
			$cmt = intval($this->get['c']);

			// We need to find what page the requested comment is on
			$stmt = $this->db->prepare( 'SELECT COUNT(comment_id) count FROM %pcomments WHERE comment_issue=? AND comment_id < ?' );

			$stmt->bind_param( 'ii', $i, $cmt );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$coms = $result->fetch_assoc();

			$stmt->close();

			if ($coms)
				$count = $coms['count'] + 1;
			else $count = 0;

			$min = 0; // Start at the first page regardless
			while ($count > ($min + $num)) {
				$min += $num;
			}
		}

		$index_template->assign( 'site_name', $issue['project_name'] );
		$this->projectid = $issue['project_id'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/issue_viewissue.xtpl' );

		$older = null;
		$newer = null;
		$next_issue = null;
		$prev_issue = null;

		if( $this->user['user_level'] >= USER_DEVELOPER ) {
			$stmt = $this->db->prepare( 'SELECT issue_id, issue_summary FROM %pissues WHERE issue_date > ? AND issue_project=? ORDER BY issue_date ASC LIMIT 1' );

			$stmt->bind_param( 'ii', $issue['issue_date'], $issue['project_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$next_issue = $result->fetch_assoc();

			$stmt->close();
		} elseif( $this->user['user_level'] > USER_GUEST ) {
			$stmt = $this->db->prepare( 'SELECT issue_id, issue_summary FROM %pissues
				WHERE issue_date > ? AND issue_project=? AND !(issue_flags & ?) AND !(issue_flags & ?)
				ORDER BY issue_date ASC LIMIT 1' );

			$f1 = ISSUE_RESTRICTED;
			$f2 = ISSUE_SPAM;
			$stmt->bind_param( 'iiii', $issue['issue_date'], $issue['project_id'], $f1, $f2 );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$next_issue = $result->fetch_assoc();

			$stmt->close();
		}
		if( $next_issue ) {
			$new_sub_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$next_issue['issue_id']}";
			$new_sub = htmlspecialchars($next_issue['issue_summary']);
			$newer = "<a href=\"$new_sub_link\">$new_sub</a> &raquo;";
		}

		if( $this->user['user_level'] >= USER_DEVELOPER ) {
			$stmt = $this->db->prepare( 'SELECT issue_id, issue_summary FROM %pissues WHERE issue_date < ? AND issue_project=? ORDER BY issue_date DESC LIMIT 1' );

			$stmt->bind_param( 'ii', $issue['issue_date'], $issue['project_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$prev_issue = $result->fetch_assoc();

			$stmt->close();
		} elseif( $this->user['user_level'] > USER_GUEST ) {
			$stmt = $this->db->prepare( 'SELECT issue_id, issue_summary FROM %pissues
				WHERE issue_date < ? AND issue_project=? AND !(issue_flags & ?) AND !(issue_flags & ?)
				ORDER BY issue_date DESC LIMIT 1' );

			$f1 = ISSUE_RESTRICTED;
			$f2 = ISSUE_SPAM;
			$stmt->bind_param( 'iiii', $issue['issue_date'], $issue['project_id'], $f1, $f2 );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$prev_issue = $result->fetch_assoc();

			$stmt->close();
		}
		if( $prev_issue ) {
			$new_sub_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$prev_issue['issue_id']}";
			$new_sub = htmlspecialchars($prev_issue['issue_summary']);
			$older = "&laquo; <a href=\"$new_sub_link\">$new_sub</a>";
		}

		if( $older || $newer ) {
			$xtpl->assign( 'older', $older );
			$xtpl->assign( 'newer', $newer );

			$xtpl->parse( 'IssuePost.NavLinks' );
		}

		$xtpl->assign( 'id', $issue['issue_id'] );

		$summary = htmlspecialchars($issue['issue_summary']);
		$xtpl->assign( 'summary', $summary );
		$xtpl->assign( 'restricted', ($issue['issue_flags'] & ISSUE_RESTRICTED) ? ' <span style="color:yellow"> [RESTRICTED ENTRY]</span>' : null );

		$xtpl->assign( 'icon', $this->display_icon( $issue['user_icon'] ) );

		$text = $this->format( $issue['issue_text'], $issue['issue_flags'] );

		$xtpl->assign( 'text', $text );
		$xtpl->assign( 'count', $issue['issue_comment_count'] );

		$closed = $this->closed_content( $issue );
		$xtpl->assign( 'closed', $closed ? ' [Closed]' : null );

		$issue_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$issue['issue_id']}";

		if( $issue['issue_comment_count'] > 0 ) {
			$xtpl->assign( 'comments', $this->comments->list_comments( $i, $issue['issue_summary'], $issue['user_name'], $issue['issue_comment_count'], $min, $num, $issue_link ) );

			$xtpl->parse( 'IssuePost.Comments' );
		}

		$author = htmlspecialchars($this->user['user_name']);

		$action_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$issue['issue_id']}#newcomment";

		if( $this->user['user_level'] >= USER_MEMBER )
			$xtpl->assign( 'comment_form', $this->comments->generate_comment_form( $author, $summary, $action_link, $closed ) );

		$related = null;
		$stmt = $this->db->prepare( 'SELECT * FROM %prelated WHERE related_this=?' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$stmt->close();

		while( $row = $this->db->assoc($result) )
		{
			$stmt = $this->db->prepare( 'SELECT issue_summary, issue_flags FROM %pissues WHERE issue_id=?' );

			$stmt->bind_param( 'i', $row['related_other'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$other = $result->fetch_assoc();

			$stmt->close();

			if( $other['issue_flags'] & ISSUE_CLOSED )
				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']} [closed]\" style=\"text-decoration:line-through;\">{$row['related_other']}</a>&nbsp;&nbsp;";
			else
				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']}\">{$row['related_other']}</a>&nbsp;&nbsp;";
		}

		if( $related ) {
			$xtpl->assign( 'related', $related );
			$xtpl->parse( 'IssuePost.Related' );
		}

		$mod_controls = null;
		if( $this->user['user_level'] >= USER_DEVELOPER ) {
			$mod_controls = '<div class="mod_controls">[ <a href="index.php?a=issues&amp;s=edit&amp;i=' . $issue['issue_id'] . '">Edit</a> ] | [ <a href="index.php?a=issues&amp;s=del&amp;i=' . $issue['issue_id'] . '">Delete</a> ]</div>';
		}
		$xtpl->assign( 'mod_controls', $mod_controls );

		$has_files = false;
		$file_list = null;

		$stmt = $this->db->prepare( 'SELECT * FROM %pattachments WHERE attachment_issue=? AND attachment_comment=0' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$attachments = $stmt->get_result();
		$stmt->close();

		while( $attachment = $this->db->assoc($attachments) )
		{
			$has_files = true;

			$file_icon = $this->get_file_icon( $attachment['attachment_type'] );

			$file_list .= "<img src=\"{$this->settings['site_address']}skins/{$this->skin}$file_icon\" alt=\"\" /> <a href=\"{$this->settings['site_address']}index.php?a=attachments&amp;f={$attachment['attachment_id']}\" rel=\"nofollow\">{$attachment['attachment_name']}</a><br />\n";
		}
		if( $has_files ) {
			$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->skin}" );
			$xtpl->assign( 'attached_files', $file_list );
			$xtpl->parse( 'IssuePost.Attachments' );
		}

		$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->skin}" );

		if( $issue['issue_flags'] & ISSUE_CLOSED )
			$status_name = 'Closed';
		else
			$status_name = $issue['status_name'];

		$xtpl->assign( 'issue_status', $status_name );
		$xtpl->assign( 'issue_type', $issue['type_name'] );
		$xtpl->assign( 'issue_component', $issue['component_name'] );
		$xtpl->assign( 'issue_project', $issue['project_name'] );
		$xtpl->assign( 'issue_category', $issue['category_name'] );

		if( $issue['issue_user_assigned'] > 1 ) {
			$stmt = $this->db->prepare( 'SELECT user_name FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $issue['issue_user_assigned'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$assigned_to = $result->fetch_assoc();

			$stmt->close();

			$xtpl->assign( 'issue_assigned', $assigned_to['user_name'] );
		} else {
			$xtpl->assign( 'issue_assigned', 'Nobody' );
		}

		$xtpl->assign( 'issue_platform', $issue['platform_name'] );
		$xtpl->assign( 'issue_severity', $issue['severity_name'] );

		$vote_count = 0;
		$stmt = $this->db->prepare( 'SELECT COUNT(vote_id) count FROM %pvotes WHERE vote_issue=?' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$votes = $result->fetch_assoc();

		$stmt->close();

		if( $votes )
			$vote_count = $votes['count'];

		$xtpl->assign( 'issue_votes', $vote_count );

		if( !($issue['issue_flags'] & ISSUE_CLOSED) && $this->user['user_level'] >= USER_MEMBER ) {
			$vote_link = null;
			$stmt = $this->db->prepare( 'SELECT vote_id FROM %pvotes WHERE vote_issue=? AND vote_user=?' );

			$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$user_vote = $result->fetch_assoc();

			$stmt->close();

			if( !$user_vote )
				$vote_link = " <a href=\"{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}&amp;w=vote\">+1</a>";
			$xtpl->assign( 'vote_link', $vote_link );

			$stmt = $this->db->prepare( 'SELECT watch_issue FROM %pwatching WHERE watch_issue=? AND watch_user=?' );

			$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$watching = $result->fetch_assoc();

			$stmt->close();

			if( $watching ) {
				$xtpl->assign( 'issue_watch', "<a href=\"{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}&amp;w=stopwatch\">Stop Watching</a>" );
			} else {
				$xtpl->assign( 'issue_watch', "<a href=\"{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}&amp;w=startwatch\">Start Watching</a>" );
			}
		} else {
			$xtpl->assign( 'issue_watch', 'N/A' );
		}

		$xtpl->assign( 'issue_user', $issue['user_name'] );
		$xtpl->assign( 'issue_date', date( $this->settings['site_dateformat'], $issue['issue_date'] ) );

		if( $issue['issue_user_edited'] > 1 ) {
			$stmt = $this->db->prepare( 'SELECT user_name FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $issue['issue_user_edited'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$edited_by = $result->fetch_assoc();

			$stmt->close();

			$xtpl->assign( 'issue_edited_by', $edited_by['user_name'] );
			$xtpl->assign( 'edit_date', date( $this->settings['site_dateformat'], $issue['issue_edited_date'] ) );

			$xtpl->parse( 'IssuePost.EditedBy' );
		}

		if( $issue['issue_flags'] & ISSUE_CLOSED ) {
			$stmt = $this->db->prepare( 'SELECT user_name FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $issue['issue_user_closed'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$closed_by = $result->fetch_assoc();

			$stmt->close();

			$xtpl->assign( 'issue_closed_by', $closed_by['user_name'] );
			$xtpl->assign( 'closed_date', date( $this->settings['site_dateformat'], $issue['issue_closed_date'] ) );
			$xtpl->assign( 'issue_resolution', $issue['resolution_name'] );

			$xtpl->parse( 'IssuePost.Closed' );

			if( !empty( $issue['issue_closed_comment'] ) ) {
				$xtpl->assign( 'issue_closed_comment', $issue['issue_closed_comment'] );
				$xtpl->parse( 'IssuePost.ClosedComment' );
			}
		}

		$xtpl->parse( 'IssuePost' );
		return $xtpl->text( 'IssuePost' );
	}

	function create_issue( $index_template )
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_MEMBER )
			return $this->error( 'A validated user account is required to open new issues.' );

		$errors = array();

		if( isset($this->get['p']) )
			$p = intval($this->get['p']);
		else
			return $this->error( 'An invalid project was specified for creating an issue.', 404 );

		$stmt = $this->db->prepare( 'SELECT * FROM %pprojects WHERE project_id=?' );

		$stmt->bind_param( 'i', $p );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$project = $result->fetch_assoc();

		$stmt->close();

		if( !$project )
			return $this->error( 'An invalid project was specified for creating an issue.', 404 );

		if( $project['project_retired'] == true )
			return $this->error( $project['project_name'] . ' has been retired. No further issues are being accepted for it.', 403 );

		$summary = '';
		$text = '';
		$related = '';

		$flags = 0;
		if( isset( $this->post['issue_flags'] ) ) {
			foreach( $this->post['issue_flags'] as $flag )
				$flags |= intval($flag);
		}

		$status = 1;
		$assigned = 0;
		if( $this->user['user_level'] >= USER_DEVELOPER ) {
			if( isset($this->post['issue_status']) )
				$status = intval($this->post['issue_status']);
			if( isset($this->post['issue_assigned']) )
				$assigned = intval($this->post['issue_assigned']);
		}

		$type = 1;
		if( isset( $this->post['issue_type'] ) )
			$type = intval( $this->post['issue_type'] );

		$category = 1;
		if( isset( $this->post['issue_category'] ) )
			$category = intval( $this->post['issue_category'] );

		$component = 1;
		if( isset( $this->post['issue_component'] ) )
			$component = intval( $this->post['issue_component'] );

		$platform = 1;
		if( isset( $this->post['issue_platform'] ) )
			$platform = intval( $this->post['issue_platform'] );

		$severity = 1;
		if( isset( $this->post['issue_severity'] ) )
			$severity = intval( $this->post['issue_severity'] );

		if( isset( $this->post['issue_summary'] ) )
			$summary = $this->post['issue_summary'];
		if( isset( $this->post['issue_text'] ) )
			$text = $this->post['issue_text'];
		if( isset( $this->post['new_related'] ) )
			$related = $this->post['new_related'];

		if( isset($this->post['submit']) )
		{
			if ( !isset( $this->post['issue_summary'] ) || empty($this->post['issue_summary']) )
				array_push( $errors, 'You did not enter an issue summary.' );
			if ( !isset( $this->post['issue_text'] ) || empty($this->post['issue_text']))
				array_push( $errors, 'You did not enter any text in the body.' );
			if( !$this->is_valid_token() && ! isset( $this->post['preview'] ) )
				array_push( $errors, 'The security validation token used to verify you are posting this entry is either invalid or expired. Please try again.' );
		}

		if( !isset( $this->post['submit'] ) || count($errors) != 0 || isset( $this->post['preview'] ) || isset( $this->post['attach'] ) || isset( $this->post['detach'] ) )
		{
			$this->navselect = 2;

			$xtpl = new XTemplate( './skins/' . $this->skin . '/issue_newissue.xtpl' );

			$index_template->assign( 'site_name', $project['project_name'] );
			$this->projectid = $project['project_id'];

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'summary', htmlspecialchars( $summary ) );
			$xtpl->assign( 'text', htmlspecialchars( $text ) );
			$xtpl->assign( 'new_related', htmlspecialchars( $related ) );

			$xtpl->assign( 'bb', ISSUE_BBCODE );
			$xtpl->assign( 'em', ISSUE_EMOTICONS );
			$xtpl->assign( 'bbbox', $flags & ISSUE_BBCODE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'embox', $flags & ISSUE_EMOTICONS ? " checked=\"checked\"" : null );

			if( $this->user['user_level'] >= USER_DEVELOPER ) {
				$xtpl->assign( 'cls', ISSUE_CLOSED );
				$xtpl->assign( 'res', ISSUE_RESTRICTED );

				if( isset($this->post['issue_flags']) ) {
					$xtpl->assign( 'clsbox', $flags & ISSUE_CLOSED ? " checked=\"checked\"" : null );
					$xtpl->assign( 'resbox', $flags & ISSUE_RESTRICTED ? " checked=\"checked\"" : null );
				}

				$xtpl->parse( 'IssueNewPost.DevBlock' );
			}

			if( isset($this->post['issue_flags']) ) {
				$xtpl->assign( 'bbbox', $flags & ISSUE_BBCODE ? " checked=\"checked\"" : null );
				$xtpl->assign( 'embox', $flags & ISSUE_EMOTICONS ? " checked=\"checked\"" : null );
			} else {
				$xtpl->assign( 'clsbox', null );
				$xtpl->assign( 'resbox', null );
				$xtpl->assign( 'bbbox', ' checked="checked"' );
				$xtpl->assign( 'embox', ' checked="checked"' );
			}

			$xtpl->assign( 'icon', $this->display_icon( $this->user['user_icon'] ) );
			$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=issues&amp;s=create&p={$p}" );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'emoticons', $this->bbcode->generate_emote_links() );

			$xtpl->assign( 'project_name', $project['project_name'] );

			if( $this->user['user_level'] < USER_DEVELOPER )
				$xtpl->assign( 'issue_status', 'New' );
			else
				$xtpl->assign( 'issue_status', $this->select_input( 'issue_status', $status, $this->get_status_names() ) );

			$xtpl->assign( 'issue_type', $this->select_input( 'issue_type', $type, $this->get_type_names() ) );
			$xtpl->assign( 'issue_component', $this->select_input( 'issue_component', $component, $this->get_component_names( $project['project_id'] ) ) );
			$xtpl->assign( 'issue_category', $this->select_input( 'issue_category', $category, $this->get_category_names( $project['project_id'] ) ) );

			if( $this->user['user_level'] >= USER_DEVELOPER ) {
				$xtpl->assign( 'issue_assigned', $this->select_input( 'issue_user_assigned', $assigned, $this->get_developer_names() ) );

				$xtpl->parse( 'IssueNewPost.Assigned' );
			}

			$xtpl->assign( 'issue_platform', $this->select_input( 'issue_platform', $platform, $this->get_platform_names() ) );
			$xtpl->assign( 'issue_severity', $this->select_input( 'issue_severity', $severity, $this->get_severity_names() ) );

			if( count($errors) > 0 ) {
				$xtpl->assign( 'errors', implode( $errors, "<br />\n" ) );

				$xtpl->parse( 'IssueNewPost.Errors' );
			}

			if( isset( $this->post['preview'] ) ) {
				$xtpl->assign( 'preview_summary', htmlspecialchars($summary) );
				$xtpl->assign( 'preview_text', $this->format( $text, $flags ) );
				$xtpl->parse( 'IssueNewPost.Preview' );
			}

			// Time to deal with icky file attachments.
			$attached = null;
			$attached_data = null;
			$upload_status = null;

			if( !isset( $this->post['attached_data'] ) ) {
				$this->post['attached_data'] = array();
			}

			if( isset( $this->post['attach'] ) ) {
				$upload_status = $this->attach_file( $this->files['attach_upload'], $this->post['attached_data'] );
			}

			if( isset( $this->post['detach'] ) ) {
				$this->delete_attachment( $this->post['attached'], $this->post['attached_data'] );
			}

			$this->make_attached_options( $attached, $attached_data, $this->post['attached_data'] );

			if( $attached != null ) {
				$xtpl->assign( 'attached_files', $attached );
				$xtpl->assign( 'attached_data', $attached_data );

				$xtpl->parse( 'IssueNewPost.AttachedFiles' );
			}

			$xtpl->assign( 'upload_status', $upload_status );

			$xtpl->parse( 'IssueNewPost' );
			return $xtpl->text( 'IssueNewPost' );
		}

		$status = 1;
		if( $this->user['user_level'] >= USER_DEVELOPER )
			$status = intval($this->post['issue_status']);
		$type = intval($this->post['issue_type']);
		$category = intval($this->post['issue_category']);

		$assigned_to = 0;
		if( $this->user['user_level'] >= USER_DEVELOPER )
			$assigned_to = intval($this->post['issue_user_assigned']);

		$platform = intval($this->post['issue_platform']);
		$severity = intval($this->post['issue_severity']);
		$component = intval($this->post['issue_component']);

		$flags = 0;
		foreach( $this->post['issue_flags'] as $flag)
			$flags |= intval($flag);

		$stmt = $this->db->prepare( 'INSERT INTO %pissues (issue_status, issue_type, issue_category, issue_user_assigned, issue_platform, issue_severity, issue_user, issue_date, issue_project, issue_component, issue_flags, issue_summary, issue_text )
			     VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )' );

		$stmt->bind_param( 'iiiiiiiiiiiss', $status, $type, $category, $assigned_to, $platform, $severity, $this->user['user_id'], $this->time, $project['project_id'], $component, $flags, $summary, $text );
		$this->db->execute_query( $stmt );

		$id = $this->db->insert_id();
		$stmt->close();

		$stmt = $this->db->prepare( 'UPDATE %pusers SET user_issue_count=user_issue_count+1 WHERE user_id=?' );

		$stmt->bind_param( 'i', $this->user['user_id'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		// Users opening an issue automatically start watching it.
		$stmt = $this->db->prepare( 'INSERT INTO %pwatching (watch_issue, watch_user) VALUES ( ?, ? )' );

		$stmt->bind_param( 'ii', $id, $this->user['user_id'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		// Notify assignee if one was set and it's not this person.
		if( $assigned_to > 1 && $assigned_to != $this->user['user_id'] ) {
			$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
			$subject = " :: [{$project['project_name']}] Issue Assignment Update: Issue #$id - $summary";
			$message = "An issue at {$this->settings['site_name']} has been assigned to you.\n\n";
			$message .= "{$this->settings['site_address']}index.php?a=issues&i=$id\n\n";
			$message .= "You will now receive notifications when this issue is updated.\n\n";

			$stmt = $this->db->prepare( 'SELECT user_email FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $assigned_to );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$notify = $result->fetch_assoc();

			$stmt->close();

			mail( $notify['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
		}

		if( isset( $this->post['new_related'] ) ) {
			$related = explode( ',', $this->post['new_related'] );

			foreach( $related as $value )
			{
				$other = intval($value);

				if( $other == $id )
					continue;

				$stmt = $this->db->prepare( 'SELECT issue_id FROM %pissues WHERE issue_id=?' );

				$stmt->bind_param( 'i', $other );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$other_issue = $result->fetch_assoc();

				$stmt->close();

				if( !$other_issue )
					continue;

				$stmt = $this->db->prepare( 'INSERT INTO %prelated (related_this, related_other) VALUES (?, ?)' );
				$stmt->bind_param( 'ii', $id, $other );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$stmt = $this->db->prepare( 'INSERT INTO %prelated (related_this, related_other) VALUES (?, ?)' );
				$stmt->bind_param( 'ii', $other, $id );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}
		}

		if( isset( $this->post['attached_data'] ) ) {
			$this->attach_files_db( $id, $this->post['attached_data'] );
		}

		if( !empty( $this->settings['wordpress_api_key'] ) ) {
			require_once( 'lib/akismet.php' );
			$akismet = null;
			$spam_checked = false;

			if( $this->user['user_level'] < USER_DEVELOPER ) {
				try {
					$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->version);

					$akismet->setCommentAuthor($this->user['user_name']);
					$akismet->setCommentAuthorEmail($this->user['user_email']);
					if( isset($this->post['url']) && !empty($this->post['url']) )
						$akismet->setCommentAuthorURL($this->post['url']);
					else
						$akismet->setCommentAuthorURL( '' );
					$akismet->setCommentContent($text);
					$akismet->setCommentType('bug-report');

					$plink = $this->settings['site_address'] . "index.php?a=issues&i=$id";
					$akismet->setPermalink($plink);

					$spam_checked = true;
				}
				// Try and deal with it rather than say something.
				catch(Exception $e) { $this->error($e->getMessage()); }
			} else {
				$spam_checked = true;
			}

			if( $spam_checked && $akismet != null && $akismet->isCommentSpam() )
			{
				// Store the contents of the entire $_SERVER array.
				$svars = json_encode($_SERVER);

				$stmt = $this->db->prepare( 'INSERT INTO %pspam (spam_issue, spam_user, spam_type, spam_date, spam_ip, spam_server) VALUES (?, ?, ?, ?, ?, ?)' );

				$f1 = SPAM_ISSUE;
				$stmt->bind_param( 'iiiiss', $id, $this->user['user_id'], $f1, $this->time, $type, $this->ip, $svars );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$this->settings['spam_count']++;
				$this->save_settings();
				$this->purge_old_spam();

				$flags |= ISSUE_SPAM;
				$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_flags=? WHERE issue_id=?' );

				$stmt->bind_param( 'ii', $flags, $id );
				$this->db->execute_query( $stmt );
				$stmt->close();

				return $this->message( 'Akismet Warning', 'This issue has been flagged as possible spam and will need to be evaluated by the administration before being visible.' );
			}
		}

		$this->settings['total_issues']++;
		$this->save_settings();

		$link = 'index.php?a=issues&i=' . $id;
		header( 'Location: ' . $link );
	}

	function edit_issue( $index_template )
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( !isset($this->get['i']) )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		$i = intval($this->get['i']);

		$errors = array();

		$summary = '';
		$text = '';

		$flags = 0;
		if( isset( $this->post['issue_flags'] ) ) {
			foreach( $this->post['issue_flags'] as $flag )
				$flags |= intval($flag);
		}

		if( isset( $this->post['issue_summary'] ) )
			$summary = $this->post['issue_summary'];
		if( isset( $this->post['issue_text'] ) )
			$text = $this->post['issue_text'];

		if ( isset($this->post['submit']) )
		{
			if ( !isset( $this->post['issue_summary'] ) || empty($this->post['issue_summary']) )
				array_push( $errors, 'You did not enter an issue summary.' );
			if ( !isset( $this->post['issue_text'] ) || empty($this->post['issue_text']))
				array_push( $errors, 'You did not enter any text in the body.' );
			if( !$this->is_valid_token() && !isset( $this->post['preview'] ) )
				array_push( $errors, 'The security validation token used to verify you are editing this entry is either invalid or expired. Please try again.' );
		}

		$stmt = $this->db->prepare( 'SELECT i.*, c.category_name, b.component_name, p.project_id, p.project_name, p.project_retired, s.platform_name, t.status_name, r.severity_name, v.resolution_name, x.type_name, u.user_name, u.user_icon FROM %pissues i
			LEFT JOIN %pprojects p ON p.project_id=i.issue_project
			LEFT JOIN %pcomponents b ON b.component_id=i.issue_component
			LEFT JOIN %pcategories c ON c.category_id=i.issue_category
			LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
			LEFT JOIN %pstatus t ON t.status_id=i.issue_status
			LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
			LEFT JOIN %presolutions v ON v.resolution_id=i.issue_resolution
			LEFT JOIN %pusers u ON u.user_id=i.issue_user
			LEFT JOIN %ptypes x ON x.type_id=i.issue_type
			WHERE issue_id=?', $i );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$issue = $result->fetch_assoc();

		$stmt->close();

		if( !$issue )
			return $this->message( 'Edit Issue', 'No such issue.' );

		if( $issue['project_retired'] )
			return $this->error( 'Edit Issue', "Issue# {$issue['issue_id']} ( {$issue['issue_summary']} ) is part of a retired project and cannot be edited.", 403 );

		if( !isset( $this->post['submit'] ) || count($errors) != 0 || isset( $this->post['preview'] ) || isset( $this->post['attach'] ) || isset( $this->post['detach'] ) )
		{
			$xtpl = new XTemplate( './skins/' . $this->skin . '/issue_editissue.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );

			$index_template->assign( 'site_name', $issue['project_name'] );
			$this->projectid = $issue['project_id'];

			$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->skin}" );
			$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=issues&amp;s=edit&amp;i={$issue['issue_id']}" );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'emoticons', $this->bbcode->generate_emote_links() );
			$xtpl->assign( 'submitted_by', htmlspecialchars($issue['user_name']) );
			$xtpl->assign( 'icon', $this->display_icon( $issue['user_icon'] ) );

			if ( !isset( $this->post['issue_summary'] ) || empty($this->post['issue_summary']) )
				$summary = $issue['issue_summary'];
			$xtpl->assign( 'summary', htmlspecialchars($summary) );

			if ( !isset( $this->post['issue_text'] ) || empty($this->post['issue_text']))
				$text = $issue['issue_text'];
			$xtpl->assign( 'text', htmlspecialchars($text) );

			$xtpl->assign( 'issue_id', $issue['issue_id'] );
			$xtpl->assign( 'issue_status', $this->select_input( 'issue_status', $issue['issue_status'], $this->get_status_names() ) );
			$xtpl->assign( 'issue_type', $this->select_input( 'issue_type', $issue['issue_type'], $this->get_type_names() ) );
			$xtpl->assign( 'issue_project', $this->select_input( 'issue_project', $issue['issue_project'], $this->get_project_names() ) );
			$xtpl->assign( 'issue_component', $this->select_input( 'issue_component', $issue['issue_component'], $this->get_component_names( $issue['issue_project'] ) ) );
			$xtpl->assign( 'issue_category', $this->select_input( 'issue_category', $issue['issue_category'], $this->get_category_names( $issue['issue_project'] ) ) );
			$xtpl->assign( 'issue_assigned', $this->select_input( 'issue_assigned', $issue['issue_user_assigned'], $this->get_developer_names() ) );
			$xtpl->assign( 'issue_platform', $this->select_input( 'issue_platform', $issue['issue_platform'], $this->get_platform_names() ) );
			$xtpl->assign( 'issue_severity', $this->select_input( 'issue_severity', $issue['issue_severity'], $this->get_severity_names() ) );
			$xtpl->assign( 'issue_resolution', $this->select_input( 'issue_resolution', $issue['issue_resolution'], $this->get_resolution_names() ) );

			$xtpl->assign( 'bb', ISSUE_BBCODE );
			$xtpl->assign( 'em', ISSUE_EMOTICONS );
			$xtpl->assign( 'bbbox', $issue['issue_flags'] & ISSUE_BBCODE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'embox', $issue['issue_flags'] & ISSUE_EMOTICONS ? " checked=\"checked\"" : null );

			if( $this->user['user_level'] >= USER_DEVELOPER ) {
				$xtpl->assign( 'cls', ISSUE_CLOSED );
				$xtpl->assign( 'res', ISSUE_RESTRICTED );

				$xtpl->assign( 'clsbox', $issue['issue_flags'] & ISSUE_CLOSED ? " checked=\"checked\"" : null );
				$xtpl->assign( 'pubbox', $issue['issue_flags'] & ISSUE_RESTRICTED ? " checked=\"checked\"" : null );

				$closed_comment = null;
				if( !empty( $issue['issue_closed_comment'] ) )
					$closed_comment = $issue['issue_closed_comment'];

				$xtpl->assign( 'closed_comment', $issue['issue_closed_comment'] );

				$xtpl->parse( 'IssueEditPost.DevBlock.ClosedComment' );
				$xtpl->parse( 'IssueEditPost.DevBlock' );
			}

			if( isset( $this->post['preview'] ) ) {
				$xtpl->assign( 'preview_summary', htmlspecialchars($summary) );
				$xtpl->assign( 'preview_text', $this->format( $text, $issue['issue_flags'] ) );

				$xtpl->parse( 'IssueEditPost.Preview' );
			}

			if( count($errors) > 0 ) {
				$xtpl->assign( 'errors', implode( $errors, "<br />\n" ) );
				$xtpl->parse( 'IssueEditPost.Errors' );
			}

			$related = null;
			$stmt = $this->db->prepare( 'SELECT * FROM %prelated WHERE related_this=?' );

			$stmt->bind_param( 'i', $issue['issue_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$stmt->close();

			while( $row = $this->db->assoc($result) )
			{
				$stmt = $this->db->prepare( 'SELECT issue_summary FROM %pissues WHERE issue_id=?' );

				$stmt->bind_param( 'i', $row['related_other'] );
				$this->db->execute_query( $stmt );

				$new_result = $stmt->get_result();
				$other = $new_result->fetch_assoc();

				$stmt->close();

				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']}\">{$row['related_other']}</a>&nbsp;&nbsp;";
			}

			if( $related ) {
				$xtpl->assign( 'related', $related );
				$xtpl->parse( 'IssueEditPost.Related' );
			}

			// Time to deal with icky file attachments.
			$attached = null;
			$attached_data = null;
			$upload_status = null;

			if( !isset( $this->post['attached_data'] ) ) {
				$this->post['attached_data'] = array();
			}

			if( isset( $this->post['attach'] ) ) {
				$upload_status = $this->attach_file( $this->files['attach_upload'], $this->post['attached_data'] );
			}

			if( isset( $this->post['detach'] ) ) {
				$this->delete_attachment( $this->post['attached'], $this->post['attached_data'] );
			}

			$this->make_attached_options( $attached, $attached_data, $this->post['attached_data'] );

			if( $attached != null ) {
				$xtpl->assign( 'attached_files', $attached );
				$xtpl->assign( 'attached_data', $attached_data );

				$xtpl->parse( 'IssueEditPost.AttachedFiles' );
			}

			$existing_files = null;
			$stmt = $this->db->prepare( 'SELECT attachment_id, attachment_name, attachment_filename FROM %pattachments WHERE attachment_issue=? AND attachment_comment=0' );

			$stmt->bind_param( 'i', $i );
			$this->db->execute_query( $stmt );

			$attachments = $stmt->get_result();
			$stmt->close();

			while( $row = $this->db->assoc($attachments) )
			{
				$existing_files .= "<input type=\"checkbox\" name=\"file_array[]\" value=\"{$row['attachment_id']}\" /> Delete Attachment - {$row['attachment_name']}<br />\n";
			}
			$xtpl->assign( 'existing_attachments', $existing_files );
			$xtpl->assign( 'upload_status', $upload_status );

			$xtpl->parse( 'IssueEditPost' );
			return $xtpl->text( 'IssueEditPost' );
		}

		$status = intval($this->post['issue_status']);
		$type = intval($this->post['issue_type']);
		$project = intval($this->post['issue_project']);
		$component = intval($this->post['issue_component']);
		$category = intval($this->post['issue_category']);
		$assigned_to = intval($this->post['issue_assigned']);
		$platform = intval($this->post['issue_platform']);
		$severity = intval($this->post['issue_severity']);

		$flags = 0;
		if( isset( $this->post['issue_flags'] ) ) {
			foreach( $this->post['issue_flags'] as $flag)
				$flags |= intval($flag);
		}

		$notify_users = false;
		$notify_new_assignee = false;
		$new_assignee = 0;
		$notify_message = null;

		// Oh yeah. This is gonna be one hell of a list :|
		if( $status != $issue['issue_status'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare( 'SELECT status_name FROM %pstatus WHERE status_id=?' );

			$stmt->bind_param( 'i', $status );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_status = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nStatus Changed: {$issue['status_name']} ---> {$new_status['status_name']}";
		}

		if( $type != $issue['issue_type'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare( 'SELECT type_name FROM %ptypes WHERE type_id=?' );

			$stmt->bind_param( 'i', $type );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_type = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nType Changed: {$issue['type_name']} ---> {$new_type['type_name']}";
		}

		if( $project != $issue['issue_project'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare( 'SELECT project_name FROM %pprojects WHERE project_id=?' );

			$stmt->bind_param( 'i', $project );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_project = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nProject Changed: {$issue['project_name']} ---> {$new_project['project_name']}";
		}

		if( $component != $issue['issue_component'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare( 'SELECT component_name FROM %pcomponents WHERE component_id=?' );

			$stmt->bind_param( 'i', $component );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_component = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nComponent Changed: {$issue['component_name']} ---> {$new_component['component_name']}";
		}

		if( $category != $issue['issue_category'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare( 'SELECT category_name FROM %pcategories WHERE category_id=?' );

			$stmt->bind_param( 'i', $category );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_category = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nCategory Changed: {$issue['category_name']} ---> {$new_category['category_name']}";
		}

		if( $assigned_to != $issue['issue_user_assigned'] ) {
			$notify_users = true;
			$old_user = null;

			if( $issue['issue_user_assigned'] == 0 ) {
				$old_user = 'Nobody';
			} else {
				$stmt = $this->db->prepare( 'SELECT user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $issue['issue_user_assigned'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$assigned_user = $result->fetch_assoc();

				$stmt->close();

				$old_user = $assigned_user['user_name'];
			}

			$stmt = $this->db->prepare( 'SELECT user_id, user_name FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $assigned_to );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_user = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nAssignment Changed: $old_user ---> {$new_user['user_name']}";

			if( $assigned_to > 1 ) {
				$stmt = $this->db->prepare( 'SELECT watch_user FROM %pwatching WHERE watch_user=? AND watch_issue=?' );

				$stmt->bind_param( 'ii', $new_user['user_id'], $issue['issue_id'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$watching = $result->fetch_assoc();

				$stmt->close();

				if( !$watching ) {
					$notify_new_assignee = true;
					$new_assignee = $new_user['user_id'];
				}
			}
		}

		if( $platform != $issue['issue_platform'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare( 'SELECT platform_name FROM %pplatforms WHERE platform_id=?' );

			$stmt->bind_param( 'i', $platform );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_platform = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nPlatform Changed: {$issue['platform_name']} ---> {$new_platform['platform_name']}";
		}

		if( $severity != $issue['issue_severity'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare( 'SELECT severity_name FROM %pseverities WHERE severity_id=?' );

			$stmt->bind_param( 'i', $severity );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_severity = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nSeverity Changed: {$issue['severity_name']} ---> {$new_severity['severity_name']}";
		}

		$edit_date = $this->time;
		$edit_by = $this->user['user_id'];

		$closed_by = 0;
		$closed_date = 0;
		$resolution = 0;
		$closed_comment = '';
		if( $flags & ISSUE_CLOSED ) {
			if( $issue['issue_closed_date'] == 0 ) {
				$closed_by = $edit_by;
				$closed_date = $edit_date;
			} else {
				$closed_by = $issue['issue_user_closed'];
				$closed_date = $issue['issue_closed_date'];
			}

			$resolution = intval($this->post['issue_resolution']);

			if( isset( $this->post['closed_comment'] ) )
				$closed_comment = $this->post['closed_comment'];

		}

		if( ($flags & ISSUE_CLOSED) && !($issue['issue_flags'] & ISSUE_CLOSED) ) {
			$notify_users = true;

			$date = date( $this->settings['site_dateformat'], $this->time );
			$notify_message .= "\nIssue has been closed by {$this->user['user_name']} on $date";

			$stmt = $this->db->prepare( 'SELECT resolution_name FROM %presolutions WHERE resolution_id=?' );

			$stmt->bind_param( 'i', $resolution );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$resolved = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nThe resolution for this issue was: {$resolved['resolution_name']}";

			if( $closed_comment != '' )
				$notify_message .= "\nAdditional comments: $closed_comment";
		}

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_status=?, issue_resolution=?, issue_type=?, issue_project=?, issue_category=?, issue_user_assigned=?, issue_platform=?, issue_component=?,
			   issue_severity=?, issue_summary=?, issue_text=?, issue_flags=?, issue_edited_date=?, issue_user_edited=?, issue_closed_date=?, issue_user_closed=?, issue_closed_comment=?
			WHERE issue_id=?' );

		$stmt->bind_param( 'iiiiiiiiissiiiiisi', $status, $resolution, $type, $project, $category, $assigned_to, $platform, $component, $severity, $summary, $text, $flags, $edit_date, $edit_by, $closed_date, $closed_by, $closed_comment, $i );
		$this->db->execute_query( $stmt );

		if( isset( $this->post['new_related'] ) ) {
			$related = explode( ',', $this->post['new_related'] );

			foreach( $related as $value )
			{
				$other = intval($value);

				if( $other == $i )
					continue;

				$stmt = $this->db->prepare( 'SELECT issue_id FROM %pissues WHERE issue_id=?' );

				$stmt->bind_param( 'i', $other );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$other_issue = $result->fetch_assoc();

				$stmt->close();

				if( !$other_issue )
					continue;

				$stmt = $this->db->prepare( 'SELECT related_other FROM %prelated WHERE related_this=? AND related_other=?' );

				$stmt->bind_param( 'ii', $i, $other );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$duplicate = $result->fetch_assoc();

				$stmt->close();

				if( $duplicate )
					continue;

				$stmt = $this->db->prepare( 'INSERT INTO %prelated (related_this, related_other) VALUES (?, ?)' );
				$stmt->bind_param( 'ii', $i, $other );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$stmt = $this->db->prepare( 'INSERT INTO %prelated (related_this, related_other) VALUES (?, ?)' );
				$stmt->bind_param( 'ii', $other, $i );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}
		}

		if( isset( $this->post['attached_data'] ) ) {
			$this->attach_files_db( $i, $this->post['attached_data'] );
		}

		// Delete attachments selected for removal
		if( isset( $this->post['file_array'] ) ) {
			foreach( $this->post['file_array'] as $fileval )
			{
				$file = intval($fileval);

				$stmt = $this->db->prepare( 'SELECT attachment_filename FROM %pattachments WHERE attachment_id=?' );

				$stmt->bind_param( 'i', $file );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$attachment = $result->fetch_assoc();

				$stmt->close();

				@unlink( $this->file_dir . $attachment['attachment_filename'] );

				$stmt = $this->db->prepare( 'DELETE FROM %pattachments WHERE attachment_id=?' );
				$stmt->bind_param( 'i', $file );
				$stmt->close();
			}
		}

		if( $notify_users ) {
			$stmt = $this->db->prepare( 'SELECT w.*, u.user_id, u.user_name, u.user_email FROM %pwatching w
				LEFT JOIN %pusers u ON u.user_id=w.watch_user WHERE watch_issue=?' );

			$stmt->bind_param( 'i', $issue['issue_id']  );
			$this->db->execute_query( $stmt );

			$notify_list = $stmt->get_result();
			$stmt->close();

			if( $notify_list ) {
				while( $notify = $this->db->assoc($notify_list) )
				{
					// No need to email the person making the changes. They obviously know.
					if( $notify['user_id'] == $this->user['user_id'] )
						continue;

					$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
					$subject = ":: [{$issue['project_name']}] Issue Tracking Update: Issue #{$issue['issue_id']} - {$issue['issue_summary']}";
					$message = "An issue you are watching at {$this->settings['site_name']} has been updated.\n\n";
					$message .= "{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}\n";
					$message .= "$notify_message\n\n";

					mail( $notify['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
				}
			}
		}

		if( $notify_new_assignee && $new_assignee > 1 ) {
			$stmt = $this->db->prepare( 'INSERT INTO %pwatching (watch_issue, watch_user) VALUES ( ?, ? )' );
			$stmt->bind_param( 'ii', $issue['issue_id'], $new_assignee );
			$this->db->execute_query( $stmt );
			$stmt->close();

			// If the new assigneee was the one doing the edit, then they don't need to be told they have a new assignment.
			if( $new_assignee != $this->user['user_id'] ) {
				$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
				$subject = ":: [{$issue['project_name']}] Issue Assignment Update: Issue #{$issue['issue_id']} - {$issue['issue_summary']}";
				$message = "An issue at {$this->settings['site_name']} has been assigned to you.\n\n";
				$message .= "{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}\n\n";
				$message .= "You will now receive notifications when this issue is updated.\n\n";

				$stmt = $this->db->prepare( 'SELECT user_email FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $new_assignee );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$notify = $result->fetch_assoc();

				$stmt->close();

				mail( $notify['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
			}
		}

		$link = 'index.php?a=issues&i=' . $i;
		header( 'Location: ' . $link );
	}

	function delete_issue( $index_template )
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( !isset($this->get['i']) )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		$i = intval($this->get['i']);

		if( !isset($this->post['confirm'])) {
			$stmt = $this->db->prepare( 'SELECT i.*, c.category_name, p.project_id, p.project_name, b.component_name, s.platform_name, t.status_name, r.severity_name, x.type_name, y.resolution_name, u.user_name, u.user_icon FROM %pissues i
				LEFT JOIN %pprojects p ON p.project_id=i.issue_project
				LEFT JOIN %pcomponents b ON b.component_id=i.issue_component
				LEFT JOIN %pcategories c ON c.category_id=i.issue_category
				LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
				LEFT JOIN %pstatus t ON t.status_id=i.issue_status
				LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
				LEFT JOIN %pusers u ON u.user_id=i.issue_user
				LEFT JOIN %ptypes x ON x.type_id=i.issue_type
				LEFT JOIN %presolutions y ON y.resolution_id=i.issue_resolution
				WHERE issue_id=?' );

			$stmt->bind_param( 'i', $i );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$issue = $result->fetch_assoc();

			$stmt->close();

			if( !$issue )
				return $this->message( 'Delete Issue', 'No such issue.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/issue_deleteissue.xtpl' );

			$index_template->assign( 'site_name', $issue['project_name'] );
			$this->projectid = $issue['project_id'];

			$xtpl->assign( 'token', $this->generate_token() );

			$xtpl->assign( 'action_link', $this->settings['site_address'] . 'index.php?a=issues&amp;s=del&amp;i=' . $issue['issue_id'] . '&amp;confirm=1' );
			$xtpl->assign( 'issue_id', $issue['issue_id'] );
			$xtpl->assign( 'summary', htmlspecialchars($issue['issue_summary']) );
			$xtpl->assign( 'text', $this->format( $issue['issue_text'], $issue['issue_flags'] ) );
			$xtpl->assign( 'icon', $this->display_icon( $issue['user_icon'] ) );

			$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->skin}" );

			if( $issue['issue_flags'] & ISSUE_CLOSED )
				$status_name = 'Closed';
			else
				$status_name = $issue['status_name'];

			$xtpl->assign( 'issue_status', $status_name );

			$xtpl->assign( 'issue_type', $issue['type_name'] );
			$xtpl->assign( 'issue_component', $issue['component_name'] );
			$xtpl->assign( 'issue_project', $issue['project_name'] );
			$xtpl->assign( 'issue_category', $issue['category_name'] );

			if( $issue['issue_user_assigned'] > 1 ) {
				$stmt = $this->db->prepare( 'SELECT user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $issue['issue_user_assigned'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$assigned_to = $result->fetch_assoc();

				$stmt->close();

				$xtpl->assign( 'issue_assigned', $assigned_to['user_name'] );
			} else {
				$xtpl->assign( 'issue_assigned', 'Nobody' );
			}

			$xtpl->assign( 'issue_platform', $issue['platform_name'] );
			$xtpl->assign( 'issue_severity', $issue['severity_name'] );

			$vote_count = 0;
			$stmt = $this->db->prepare( 'SELECT COUNT(vote_id) count FROM %pvotes WHERE vote_issue=?' );

			$stmt->bind_param( 'i', $issue['issue_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$votes = $result->fetch_assoc();

			$stmt->close();

			if( $votes )
				$vote_count = $votes['count'];

			$xtpl->assign( 'issue_votes', $vote_count );
			$xtpl->assign( 'issue_user', htmlspecialchars($issue['user_name']) );
			$xtpl->assign( 'issue_date', date( $this->settings['site_dateformat'], $issue['issue_date'] ) );

			if( $issue['issue_user_edited'] > 1 ) {
				$stmt = $this->db->prepare( 'SELECT user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $issue['issue_user_edited'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$edited_by = $result->fetch_assoc();

				$stmt->close();

				$xtpl->assign( 'issue_edited_by', htmlspecialchars($edited_by['user_name']) );
				$xtpl->assign( 'edit_date', date( $this->settings['site_dateformat'], $issue['issue_edited_date'] ) );

				$xtpl->parse( 'IssuePostDelete.EditedBy' );
			}

			if( $issue['issue_flags'] & ISSUE_CLOSED ) {
				$stmt = $this->db->prepare( 'SELECT user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $issue['issue_user_closed'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$closed_by = $result->fetch_assoc();

				$stmt->close();

				$xtpl->assign( 'issue_closed_by', htmlspecialchars($closed_by['user_name']) );
				$xtpl->assign( 'closed_date', date( $this->settings['site_dateformat'], $issue['issue_closed_date'] ) );
				$xtpl->assign( 'issue_resolution', $issue['resolution_name'] );

				$xtpl->parse( 'IssuePostDelete.Closed' );

				if( !empty( $issue['issue_closed_comment'] ) ) {
					$xtpl->assign( 'issue_closed_comment', htmlspecialchars($issue['issue_closed_comment']) );
					$xtpl->parse( 'IssuePostDelete.ClosedComment' );
				}
			}

			$count = $issue['issue_comment_count'];
			$xtpl->assign( 'count', $count );
			$confirm_message = "Are you sure you wish to delete this issue";
			if( $count <= 0 )
				$confirm_message .= '?';
			else if( $count == 1 )
				$confirm_message .= ' and 1 attached comment?';
			else
				$confirm_message .= " and ALL $count attached comments?";
			$xtpl->assign( 'confirm_message', $confirm_message );

			$xtpl->parse( 'IssuePostDelete' );
			return $xtpl->text( 'IssuePostDelete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'The security validation token used to verify you are deleting this entry is either invalid or expired. Please go back and try again.' );
		}

		$stmt = $this->db->prepare( 'SELECT issue_id, issue_project, issue_user FROM %pissues WHERE issue_id=?' );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$check = $result->fetch_assoc();

		$stmt->close();

		if( !$check )
			return $this->error( 'The issue you are trying to delete does not exist.', 404 );

		$stmt = $this->db->prepare( 'SELECT attachment_filename FROM %pattachments WHERE attachment_issue=?' );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$attachments = $stmt->get_result();
		$stmt->close();

		while( $attachment = $this->db->assoc($attachments) )
		{
			@unlink( $this->file_dir . $attachment['attachment_filename'] );
		}

		$stmt = $this->db->prepare( 'SELECT comment_id FROM %pcomments WHERE comment_issue=?', $i );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$comments = $stmt->get_result();
		$stmt->close();

		while( $comment = $this->db->assoc($comments) )
		{
			$stmt = $this->db->prepare( 'SELECT attachment_filename FROM %pattachments WHERE attachment_comment=?' );

			$stmt->bind_param( 'i', $comment['comment_id'] );
			$this->db->execute_query( $stmt );

			$c_attachments = $stmt->get_result();
			$stmt->close();

			while( $c_attachment = $this->db->assoc($c_attachments) )
			{
				@unlink( $this->file_dir . $c_attachment['attachment_filename'] );
			}
		}

		$stmt = $this->db->prepare( 'UPDATE %pusers SET user_issue_count=user_issue_count-1 WHERE user_id=?' );
		$stmt->bind_param( 'i', $check['issue_user'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pissues WHERE issue_id=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pcomments WHERE comment_issue=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pattachments WHERE attachment_issue=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %prelated WHERE related_this=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %prelated WHERE related_other=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pwatching WHERE watch_issue=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$this->settings['total_issues']--;
		$this->save_settings();

		return $this->message( 'Delete Issue', 'Issue and all comments and attachments have been deleted.', 'Continue', "index.php?a=issues&project={$check['issue_project']}" );
	}

	function select_input( $name, $value, $data = array() )
	{
		$out = null;
		for( $count = 0; $count < count($data); $count++ )
		{
			$selected = '';
			if( $data[$count]['id'] == $value )
				$selected = ' selected="selected"';
			$out .= "<option value=\"{$data[$count]['id']}\"{$selected}>{$data[$count]['name']}</option>";
		}

		return "<select name=\"$name\">$out</select>";
	}

	function get_status_names()
	{
		$names = array();

		$status = $this->db->dbquery( 'SELECT * FROM %pstatus WHERE status_shows=1 ORDER BY status_position ASC' );
		while ( $row = $this->db->assoc($status) )
		{
			$names[] = array( 'id' => $row['status_id'], 'name' => $row['status_name'] );
		}

		return $names;
	}

	function get_project_names()
	{
		$names = array();

		$projects = $this->db->dbquery( 'SELECT * FROM %pprojects ORDER BY project_position ASC' );
		while ( $row = $this->db->assoc($projects) )
		{
			$names[] = array( 'id' => $row['project_id'], 'name' => $row['project_name'] );
		}

		return $names;
	}

	function get_component_names( $projid )
	{
		$names = array();

		$stmt = $this->db->prepare( 'SELECT * FROM %pcomponents WHERE component_project=? ORDER BY component_position ASC' );

		$stmt->bind_param( 'i', $projid );
		$this->db->execute_query( $stmt );

		$components = $stmt->get_result();
		$stmt->close();

		while ( $row = $this->db->assoc($components) )
		{
			$names[] = array( 'id' => $row['component_id'], 'name' => $row['component_name'] );
		}

		return $names;
	}

	function get_category_names( $projid )
	{
		$names = array();

		$stmt = $this->db->prepare( 'SELECT * FROM %pcategories WHERE category_project=? ORDER BY category_position ASC' );

		$stmt->bind_param( 'i', $projid );
		$this->db->execute_query( $stmt );

		$categories = $stmt->get_result();
		$stmt->close();

		while ( $row = $this->db->assoc($categories) )
		{
			$names[] = array( 'id' => $row['category_id'], 'name' => $row['category_name'] );
		}

		return $names;
	}

	function get_developer_names()
	{
		$names = array();

		$names[] = array( 'id' => 0, 'name' => 'Nobody' );

		$stmt = $this->db->prepare( 'SELECT * FROM %pusers WHERE user_level>=? ORDER BY user_id ASC' );

		$level = USER_DEVELOPER;
		$stmt->bind_param( 'i', $level );
		$this->db->execute_query( $stmt );

		$developers = $stmt->get_result();
		$stmt->close();

		while ( $row = $this->db->assoc($developers) )
		{
			$names[] = array( 'id' => $row['user_id'], 'name' => $row['user_name'] );
		}

		return $names;
	}

	function get_platform_names()
	{
		$names = array();

		$platforms = $this->db->dbquery( 'SELECT * FROM %pplatforms ORDER BY platform_position ASC' );
		while ( $row = $this->db->assoc($platforms) )
		{
			$names[] = array( 'id' => $row['platform_id'], 'name' => $row['platform_name'] );
		}

		return $names;
	}

	function get_severity_names()
	{
		$names = array();

		$severities = $this->db->dbquery( 'SELECT * FROM %pseverities ORDER BY severity_position ASC' );
		while ( $row = $this->db->assoc($severities) )
		{
			$names[] = array( 'id' => $row['severity_id'], 'name' => $row['severity_name'] );
		}

		return $names;
	}

	function get_resolution_names()
	{
		$names = array();

		$resolutions = $this->db->dbquery( 'SELECT * FROM %presolutions ORDER BY resolution_position ASC' );
		while ( $row = $this->db->assoc($resolutions) )
		{
			$names[] = array( 'id' => $row['resolution_id'], 'name' => $row['resolution_name'] );
		}

		return $names;
	}

	function get_type_names()
	{
		$names = array();

		$types = $this->db->dbquery( 'SELECT * FROM %ptypes ORDER BY type_position ASC' );
		while ( $row = $this->db->assoc($types) )
		{
			$names[] = array( 'id' => $row['type_id'], 'name' => $row['type_name'] );
		}

		return $names;
	}

	// Stuff for attaching files to issues.
	function attach_file( &$file, &$attached_data )
	{
		$upload_error = null; // Null is no error

		if( !isset($file) ) {
			$upload_error = 'The attachment upload failed. The file you specified may not exist.';
		} else {
			$md5 = md5( $file['name'] . microtime() );

			$ret = $this->upload( $file, $this->file_dir . $md5, $this->settings['attachment_size_limit_mb'], $this->settings['attachment_types_allowed'] );

			switch($ret)
			{
				case UPLOAD_TOO_LARGE:
					$upload_error = sprintf( 'The specified file is too large. The maximum size is %d MB.', $this->settings['attachment_size_limit_mb'] );
				break;

				case UPLOAD_NOT_ALLOWED:
					$upload_error = 'You cannot attach files of that type.';
				break;

				case UPLOAD_SUCCESS:
					$attached_data[$md5] = $file['name'];
				break;

				default:
					$upload_error = 'The attachment upload failed. The file you specified may not exist.';
			}
		}
		return $upload_error;
	}

	function delete_attachment( $filename, &$attached_data )
	{
		unset( $attached_data[$filename] );
		@unlink( $this->file_dir . $filename );
	}

	function make_attached_options( &$options, &$hiddennames, $attached_data )
	{
		foreach( $attached_data as $md5 => $file )
		{
			$file = htmlspecialchars( $file );

			$options .= "<option value='$md5'>$file</option>\n";
			$hiddennames .= "<input type='hidden' name='attached_data[$md5]' value='$file' />\n";
		}
	}

	function upload( $file, $destination, $max_size, $allowed_types )
	{
		if( $file['size'] > ( $max_size * 1024 * 1024 ) ) {
			return UPLOAD_TOO_LARGE;
		}

		$temp = explode( '.', $file['name'] );
		$ext = strtolower( end( $temp ) );

		if( !in_array( $ext, $allowed_types ) ) {
			return UPLOAD_NOT_ALLOWED;
		}

		if( is_uploaded_file( $file['tmp_name'] ) ) {
			$result = @move_uploaded_file( $file['tmp_name'], str_replace( '\\', '/', $destination ) );
			if( $result ) {
				return UPLOAD_SUCCESS;
			}
		}
		return UPLOAD_FAILURE;
	}

	function attach_files_db( $id, $attached_data )
	{
		foreach( $attached_data as $md5 => $filename )
		{
			$renamed = $id . '_' . $md5;
			rename( $this->file_dir . $md5, $this->file_dir . $renamed );

			$temp = explode( '.', $filename );
			$ext = strtolower( end( $temp ) );

			$stmt = $this->db->prepare( 'INSERT INTO %pattachments( attachment_issue, attachment_name, attachment_filename, attachment_type, attachment_size, attachment_user, attachment_date ) VALUES ( ?, ?, ?, ?, ?, ?, ? )' );

			$size = filesize( $this->file_dir . $renamed );
			$stmt->bind_param( 'isssiii', $id, $filename, $renamed, $ext, $size, $this->user['user_id'], $this->time );
			$this->db->execute_query( $stmt );
			$stmt->close();
		}
	}
}
?>