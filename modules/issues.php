<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2025 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

require_once './lib/comments.php';

class issues extends module
{
   private $comments;

	public function execute( $index_template )
	{
		$this->comments = new comments( $this );

		$sorting = 'issue_date DESC';
		$sortkey = null;

		if( isset( $this->get['sortby'] ) ) {
			$sortkey = $this->get['sortby'];

			if( $sortkey == 'category' )
				$sorting = 'issue_category ASC, issue_date DESC';
			if( $sortkey == 'status' )
				$sorting = 'issue_status ASC, issue_date DESC';
		}

		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'create':		return $this->create_issue( $index_template );
				case 'edit':		return $this->edit_issue( $index_template );
				case 'del':		return $this->delete_issue( $index_template );
				case 'edit_comment':	return $this->comments->edit_comment();
				case 'del_comment':	return $this->comments->delete_comment();
				case 'assigned':	return $this->list_assignments( $sorting, $sortkey );
				case 'myissues':	return $this->list_my_issues( $sorting, $sortkey );
				case 'mywatchlist':
				{
					if( $this->user['user_level'] < USER_MEMBER )
						return $this->error( 404 );

					return $this->show_my_watchlist( $sorting, $sortkey );
				}
			}
			return $this->error( 404 );
		}

		if( isset( $this->get['i'] ) ) {
			if( !$this->is_valid_integer( $this->get['i'] ) ) {
				return $this->error( 404 );
			}

			$i = intval( $this->get['i'] );

			return $this->view_issue( $i, $index_template );
		}

		$projid = 0;
		if( isset ( $this->get['project'] ) ) {
			if( !$this->is_valid_integer( $this->get['project'] ) ) {
				return $this->error( 404 );
			}

			$projid = intval( $this->get['project'] );
		}

		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) ) {
			if( $this->is_valid_integer( $this->get['num'] ) ) {
				$num = intval( $this->get['num'] );
			}
		}

		$min = 0;
		if( isset( $this->get['min'] ) ) {
			if( $this->is_valid_integer( $this->get['min'] ) ) {
				$min = intval( $this->get['min'] );
			}
		}

		$stmt = null;
		$total = null;
		$list_total = 0;

		if( $this->user['user_level'] < USER_DEVELOPER ) {
			if( $projid == 0 ) {
				$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, y.component_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
					LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					LEFT JOIN %pcategories c ON c.category_id=i.issue_category
					LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
					LEFT JOIN %pstatus t ON t.status_id=i.issue_status
					LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
					LEFT JOIN %ptypes x ON x.type_id=i.issue_type
               LEFT JOIN %pcomponents y ON y.component_id=i.issue_component
					LEFT JOIN %pusers u ON u.user_id=i.issue_user
					WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?)
					ORDER BY ' . $sorting . ' LIMIT ?, ?' );

				$f1 = ISSUE_RESTRICTED;
				$f2 = ISSUE_SPAM;
				$f3 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiiii', $f1, $f2, $f3, $min, $num );

				$this->db->execute_query( $stmt );
				$result = $stmt->get_result();
				$stmt->close();

				$stmt = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?)' );

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
				$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, y.component_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
					LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					LEFT JOIN %pcategories c ON c.category_id=i.issue_category
					LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
					LEFT JOIN %pstatus t ON t.status_id=i.issue_status
					LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
					LEFT JOIN %ptypes x ON x.type_id=i.issue_type
               LEFT JOIN %pcomponents y ON y.component_id=i.issue_component
					LEFT JOIN %pusers u ON u.user_id=i.issue_user
					WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?) AND issue_project=?
					ORDER BY ' . $sorting . ' LIMIT ?, ?' );

				$f1 = ISSUE_RESTRICTED;
				$f2 = ISSUE_SPAM;
				$f3 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiiiii', $f1, $f2, $f3, $projid, $min, $num );

				$this->db->execute_query( $stmt );
				$result = $stmt->get_result();
				$stmt->close();

				$stmt = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?) AND !(issue_flags & ?) AND !(issue_flags & ?) AND issue_project=?' );

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
				$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, y.component_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
					LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					LEFT JOIN %pcategories c ON c.category_id=i.issue_category
					LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
					LEFT JOIN %pstatus t ON t.status_id=i.issue_status
					LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
					LEFT JOIN %ptypes x ON x.type_id=i.issue_type
               LEFT JOIN %pcomponents y ON y.component_id=i.issue_component
					LEFT JOIN %pusers u ON u.user_id=i.issue_user
					WHERE !(issue_flags & ?) ORDER BY ' . $sorting . ' LIMIT ?, ?' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'iii', $f1, $min, $num );

				$this->db->execute_query( $stmt );
				$result = $stmt->get_result();
				$stmt->close();

				$stmt = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?)' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'i', $f1 );
				$this->db->execute_query( $stmt );

				$t_result = $stmt->get_result();
				$total = $t_result->fetch_assoc();

				$stmt->close();
			}
			else {
				$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, y.component_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
					LEFT JOIN %pprojects p ON p.project_id=i.issue_project
					LEFT JOIN %pcategories c ON c.category_id=i.issue_category
					LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
					LEFT JOIN %pstatus t ON t.status_id=i.issue_status
					LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
					LEFT JOIN %ptypes x ON x.type_id=i.issue_type
					LEFT JOIN %pcomponents y ON y.component_id=i.issue_component
					LEFT JOIN %pusers u ON u.user_id=i.issue_user
					WHERE !(issue_flags & ?) AND issue_project=?
					ORDER BY ' . $sorting . ' LIMIT ?, ?' );

				$f1 = ISSUE_CLOSED;
				$stmt->bind_param( 'iiii', $f1, $projid, $min, $num );

				$this->db->execute_query( $stmt );
				$result = $stmt->get_result();
				$stmt->close();

				$stmt = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?) AND issue_project=?' );

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
		while( $proj = $this->db->assoc( $projlist ) )
		{
			if( $proj['project_id'] == $projid ) {
				$site_name = $proj['project_name'];
				$this->projectid = $proj['project_id'];
				$this->title = $this->settings['site_name'] . ' :: ' . $proj['project_name'];
			}
		}

		$index_template->assign( 'site_name', $site_name );

		$cat_sort = "{$this->settings['site_address']}index.php?a=issues&amp;project=$projid&amp;sortby=category";
		$xtpl->assign( 'cat_sort', $cat_sort );

		$status_sort = "{$this->settings['site_address']}index.php?a=issues&amp;project=$projid&amp;sortby=status";
		$xtpl->assign( 'status_sort', $status_sort );

		while( $row = $this->db->assoc( $result ) )
		{
			$xtpl->assign( 'icon', $this->display_icon( $row ) );

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

			$xtpl->assign( 'issue_opened', $this->t_date( $row['issue_date'] ) );
			$xtpl->assign( 'issue_opened_by', $row['user_name'] );
			$xtpl->assign( 'issue_project', $row['project_name'] );
			$xtpl->assign( 'issue_category', $row['category_name'] );
         $xtpl->assign( 'issue_component', $row['component_name'] );
			$xtpl->assign( 'issue_summary', $row['issue_summary'] );
			$xtpl->assign( 'issue_platform', $row['platform_name'] );
			$xtpl->assign( 'issue_severity', $row['severity_name'] );
			$xtpl->assign( 'issue_link', $issue_link );

			$xtpl->parse( 'Issue.Post' );
		}

		$pagelinks = $this->make_links( $projid, $list_total, $min, $num, $sortkey );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Issue.PageLinks' );

		$xtpl->parse( 'Issue' );
		return $xtpl->text( 'Issue' );
	}

	private function list_assignments( $sorting, $sortkey )
	{
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 404 );

		$this->title = $this->settings['site_name'] . ' :: My Assignments';

		$list_total = 0;
		$total = null;

		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) ) {
			if( $this->is_valid_integer( $this->get['num'] ) ) {
				$num = intval( $this->get['num'] );
			}
		}

		$min = 0;
		if( isset( $this->get['min'] ) ) {
			if( $this->is_valid_integer( $this->get['min'] ) ) {
				$min = intval( $this->get['min'] );
			}
		}

		$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
			LEFT JOIN %pprojects p ON p.project_id=i.issue_project
			LEFT JOIN %pcategories c ON c.category_id=i.issue_category
			LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
			LEFT JOIN %pstatus t ON t.status_id=i.issue_status
			LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
			LEFT JOIN %ptypes x ON x.type_id=i.issue_type
			LEFT JOIN %pusers u ON u.user_id=i.issue_user
			WHERE issue_user_assigned=? AND !( issue_flags & ?)
			ORDER BY ' . $sorting . ' LIMIT ?, ?' );

		$f1 = ISSUE_CLOSED;
		$stmt->bind_param( 'iiii', $this->user['user_id'], $f1, $min, $num );

		$this->db->execute_query( $stmt );
		$result = $stmt->get_result();
		$stmt->close();

		$stmt = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE issue_user_assigned=? AND !(issue_flags & ?)' );

		$f1 = ISSUE_CLOSED;
		$stmt->bind_param( 'ii', $this->user['user_id'], $f1 );
		$this->db->execute_query( $stmt );

		$t_result = $stmt->get_result();
		$total = $t_result->fetch_assoc();

		$stmt->close();

		if( $total )
			$list_total = $total['count'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/issues.xtpl' );

		$this->navselect = 3;

		$cat_sort = "{$this->settings['site_address']}index.php?a=issues&amp;s=assigned&amp;sortby=category";
		$xtpl->assign( 'cat_sort', $cat_sort );

		$status_sort = "{$this->settings['site_address']}index.php?a=issues&amp;s=assigned&amp;sortby=status";
		$xtpl->assign( 'status_sort', $status_sort );

		while( $row = $this->db->assoc( $result ) )
		{
			$xtpl->assign( 'icon', $this->display_icon( $row ) );

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

			$xtpl->assign( 'issue_opened', $this->t_date( $row['issue_date'] ) );
			$xtpl->assign( 'issue_opened_by', $row['user_name'] );
			$xtpl->assign( 'issue_project', $row['project_name'] );
			$xtpl->assign( 'issue_category', $row['category_name'] );
			$xtpl->assign( 'issue_summary', $row['issue_summary'] );
			$xtpl->assign( 'issue_platform', $row['platform_name'] );
			$xtpl->assign( 'issue_severity', $row['severity_name'] );
			$xtpl->assign( 'issue_link', $issue_link );

			$xtpl->parse( 'Issue.Post' );
		}

		$pagelinks = $this->make_links( 0, $list_total, $min, $num, $sortkey );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Issue.PageLinks' );

		$xtpl->parse( 'Issue' );
		return $xtpl->text( 'Issue' );
	}

	private function list_my_issues( $sorting, $sortkey )
	{
		$this->title = $this->settings['site_name'] . ' :: Issues I Created';

		$list_total = 0;
		$total = null;

		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) ) {
			if( $this->is_valid_integer( $this->get['num'] ) ) {
				$num = intval( $this->get['num'] );
			}
		}

		$min = 0;
		if( isset( $this->get['min'] ) ) {
			if( $this->is_valid_integer( $this->get['min'] ) ) {
				$min = intval( $this->get['min'] );
			}
		}

		if( $this->user['user_level'] < USER_DEVELOPER ) {
			$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
				LEFT JOIN %pprojects p ON p.project_id=i.issue_project
				LEFT JOIN %pcategories c ON c.category_id=i.issue_category
				LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
				LEFT JOIN %pstatus t ON t.status_id=i.issue_status
				LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
				LEFT JOIN %ptypes x ON x.type_id=i.issue_type
				LEFT JOIN %pusers u ON u.user_id=i.issue_user
				WHERE issue_user=? AND !(issue_flags & ?) AND !(issue_flags & ?)
				ORDER BY ' . $sorting . ' LIMIT ?, ?' );

			$f1 = ISSUE_RESTRICTED;
			$f2 = ISSUE_SPAM;
			$stmt->bind_param( 'iiiii', $this->user['user_id'], $f1, $f2, $min, $num );

			$this->db->execute_query( $stmt );
			$result = $stmt->get_result();
			$stmt->close();

			$stmt = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE issue_user=? AND !(issue_flags & ?) AND !(issue_flags & ?)' );

			$f1 = ISSUE_RESTRICTED;
			$f2 = ISSUE_SPAM;
			$stmt->bind_param( 'iii', $this->user['user_id'], $f1, $f2 );
			$this->db->execute_query( $stmt );

			$t_result = $stmt->get_result();
			$total = $t_result->fetch_assoc();

			$stmt->close();
		}
		elseif( $this->user['user_level'] >= USER_DEVELOPER ) {
			$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
				LEFT JOIN %pprojects p ON p.project_id=i.issue_project
				LEFT JOIN %pcategories c ON c.category_id=i.issue_category
				LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
				LEFT JOIN %pstatus t ON t.status_id=i.issue_status
				LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
				LEFT JOIN %ptypes x ON x.type_id=i.issue_type
				LEFT JOIN %pusers u ON u.user_id=i.issue_user
				WHERE issue_user=?
				ORDER BY ' . $sorting . ' LIMIT ?, ?' );

			$stmt->bind_param( 'iii', $this->user['user_id'], $min, $num );

			$this->db->execute_query( $stmt );
			$result = $stmt->get_result();
			$stmt->close();

			$stmt = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE issue_user=?' );

			$stmt->bind_param( 'i', $this->user['user_id'] );
			$this->db->execute_query( $stmt );

			$t_result = $stmt->get_result();
			$total = $t_result->fetch_assoc();

			$stmt->close();
		}

		if( $total )
			$list_total = $total['count'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/issues.xtpl' );

		$this->navselect = 4;

		$cat_sort = "{$this->settings['site_address']}index.php?a=issues&amp;s=myissues&amp;sortby=category";
		$xtpl->assign( 'cat_sort', $cat_sort );

		$status_sort = "{$this->settings['site_address']}index.php?a=issues&amp;s=myissues&amp;sortby=status";
		$xtpl->assign( 'status_sort', $status_sort );

		while( $row = $this->db->assoc( $result ) )
		{
			$xtpl->assign( 'icon', $this->display_icon( $row ) );

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

			$xtpl->assign( 'issue_opened', $this->t_date( $row['issue_date'] ) );
			$xtpl->assign( 'issue_opened_by', $row['user_name'] );
			$xtpl->assign( 'issue_project', $row['project_name'] );
			$xtpl->assign( 'issue_category', $row['category_name'] );
			$xtpl->assign( 'issue_summary', $row['issue_summary'] );
			$xtpl->assign( 'issue_platform', $row['platform_name'] );
			$xtpl->assign( 'issue_severity', $row['severity_name'] );
			$xtpl->assign( 'issue_link', $issue_link );

			$xtpl->parse( 'Issue.Post' );
		}

		$pagelinks = $this->make_links( 0, $list_total, $min, $num, $sortkey );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Issue.PageLinks' );

		$xtpl->parse( 'Issue' );
		return $xtpl->text( 'Issue' );
	}

	private function show_my_watchlist( $sorting, $sortkey )
	{
		if( $this->user['user_level'] < USER_MEMBER )
			return $this->error( 403, 'You must have a validated account in order to view a watchlist.' );

		$this->title = $this->settings['site_name'] . ' :: Open Issues I Am Watching';

		$list_total = 0;

		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) ) {
			if( $this->is_valid_integer( $this->get['num'] ) ) {
				$num = intval( $this->get['num'] );
			}
		}

		$min = 0;
		if( isset( $this->get['min'] ) ) {
			if( $this->is_valid_integer( $this->get['min'] ) ) {
				$min = intval( $this->get['min'] );
			}
		}

		$stmt = null;
		if( $this->user['user_level'] < USER_DEVELOPER ) {
			$stmt = $this->db->prepare_query( 'SELECT w.watch_issue, i.issue_flags FROM %pwatching w
				LEFT JOIN %pissues i ON i.issue_id=w.watch_issue
				WHERE w.watch_user=? AND !(i.issue_flags & ?) AND !(i.issue_flags & ?) AND !(i.issue_flags & ?)' );

			$f1 = ISSUE_RESTRICTED;
			$f2 = ISSUE_SPAM;
			$f3 = ISSUE_CLOSED;
			$stmt->bind_param( 'iiii', $this->user['user_id'], $f1, $f2, $f3 );
		} else {
			$stmt = $this->db->prepare_query( 'SELECT w.*, i.issue_flags FROM %pwatching w
				LEFT JOIN %pissues i ON i.issue_id=w.watch_issue
				WHERE w.watch_user=? AND !(i.issue_flags & ?)' );

			$f1 = ISSUE_CLOSED;
			$stmt->bind_param( 'ii', $this->user['user_id'], $f1 );
		}

		$this->db->execute_query( $stmt );
		$watch_list = $stmt->get_result();
		$stmt->close();

		$issue_ids = array();

		$list_total = 0;
		while( $row = $this->db->assoc( $watch_list ) )
		{
			$issue_ids[] = $row['watch_issue'];
			$list_total++;
		}

		$in = '0';
		if( $list_total > 0 ) {
			$in .= ', ';
			$in .= implode( ', ', $issue_ids );
		}

		$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, t.status_name, r.severity_name, x.type_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
			LEFT JOIN %pprojects p ON p.project_id=i.issue_project
			LEFT JOIN %pcategories c ON c.category_id=i.issue_category
			LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
			LEFT JOIN %pstatus t ON t.status_id=i.issue_status
			LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
			LEFT JOIN %ptypes x ON x.type_id=i.issue_type
			LEFT JOIN %pusers u ON u.user_id=i.issue_user
			WHERE i.issue_id IN (' . $in . ')
			ORDER BY ' . $sorting . ' LIMIT ?, ?' );

		$stmt->bind_param( 'ii', $min, $num );

		$this->db->execute_query( $stmt );
		$result = $stmt->get_result();
		$stmt->close();

		$xtpl = new XTemplate( './skins/' . $this->skin . '/issues.xtpl' );

		$this->navselect = 5;

		$cat_sort = "{$this->settings['site_address']}index.php?a=issues&amp;s=mywatchlist&amp;sortby=category";
		$xtpl->assign( 'cat_sort', $cat_sort );

		$status_sort = "{$this->settings['site_address']}index.php?a=issues&amp;s=mywatchlist&amp;sortby=status";
		$xtpl->assign( 'status_sort', $status_sort );

		while( $row = $this->db->assoc( $result ) )
		{
			$xtpl->assign( 'icon', $this->display_icon( $row ) );

			$issue_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$row['issue_id']}";

			$colorclass = 'article';

			if( $row['issue_flags'] & ISSUE_SPAM )
				$colorclass = 'articlealert';

			if( $row['issue_flags'] & ISSUE_RESTRICTED )
				$colorclass = 'articleredalert';

			$xtpl->assign( 'colorclass', $colorclass );

			$xtpl->assign( 'issue_id', $row['issue_id'] );
			$xtpl->assign( 'issue_type', $row['type_name'] );
			$xtpl->assign( 'issue_status', $row['status_name'] );
			$xtpl->assign( 'issue_opened', $this->t_date( $row['issue_date'] ) );
			$xtpl->assign( 'issue_opened_by', $row['user_name'] );
			$xtpl->assign( 'issue_project', $row['project_name'] );
			$xtpl->assign( 'issue_category', $row['category_name'] );
			$xtpl->assign( 'issue_summary', $row['issue_summary'] );
			$xtpl->assign( 'issue_platform', $row['platform_name'] );
			$xtpl->assign( 'issue_severity', $row['severity_name'] );
			$xtpl->assign( 'issue_link', $issue_link );

			$xtpl->parse( 'Issue.Post' );
		}

		$pagelinks = $this->make_links( 0, $list_total, $min, $num, $sortkey );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Issue.PageLinks' );

		$xtpl->parse( 'Issue' );
		return $xtpl->text( 'Issue' );
	}

	private function view_issue( $i, $index_template )
	{
		$stmt = $this->db->prepare_query( 'SELECT i.*, c.category_name, p.project_id, p.project_name, p.project_retired, b.component_name, s.platform_name, t.status_name, r.severity_name, v.resolution_name, x.type_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
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

		if( !$issue || ( ( ( $issue['issue_flags'] & ISSUE_RESTRICTED ) || ( $issue['issue_flags'] & ISSUE_SPAM ) ) && $this->user['user_level'] < USER_DEVELOPER ) )
			return $this->error( 404 );

		$this->title( '#' . $issue['issue_id'] . ' '. $issue['issue_summary'] );
		$this->meta_description( '#' . $issue['issue_id'] . ' '. $issue['issue_summary'] );

		// If these conditions are true, a comment is being posted.
		if( isset( $this->post['submit'] ) || isset( $this->post['preview'] ) || isset( $this->post['attach'] ) || isset( $this->post['detach'] ) )
		{
			if( $this->user['user_level'] < USER_MEMBER )
				return $this->error( 403, 'You must have a validated account in order to post comments.' );

			if( $this->closed_content( $issue ) )
				return $this->error( 403, 'Sorry, this issue is closed.' );

			$result = $this->comments->post_comment( $issue );

			if( is_string( $result ) )
				return $result;

			if( isset( $this->post['request_uri'] ) )
				header( 'Location: ' . $this->post['request_uri'] );

			$link = "{$this->settings['site_address']}index.php?a=issues&i=$i&c=$result#comment-$result";
			header( 'Location: ' . $link );
		}

		// If this condition is true, a quick close request by a developer was sent.
		if( isset( $this->post['quick_close'] ) && $this->user['user_level'] >= USER_DEVELOPER ) {
			if( $issue['issue_flags'] & ISSUE_CLOSED )
				return $this->error( 0, 'Quick Close: This issue is already closed.' );

			$resolution = 0;
			$closed_comment = '';

			$issue['issue_flags'] |= ISSUE_CLOSED;

			$date = $this->t_date( $this->time, false, true );
			$notify_message = "\nIssue has been closed by {$this->user['user_name']} on $date";

			if( !$this->is_valid_integer( $this->post['issue_resolution'] ) ) {
				return $this->error( -2 );
			}

			$resolution = intval( $this->post['issue_resolution'] );

			$stmt = $this->db->prepare_query( 'SELECT resolution_name FROM %presolutions WHERE resolution_id=?' );

			$stmt->bind_param( 'i', $resolution );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$resolved = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nThe resolution for this issue was: {$resolved['resolution_name']}";

			if( isset( $this->post['closed_comment'] ) ) {
				if( !is_string( $this->post['closed_comment'] ) )
					return $this->error( -2 );

				$closed_comment = $this->post['closed_comment'];
				$notify_message .= "\nAdditional comments: $closed_comment";
			}

			$stmt = $this->db->prepare_query( 'UPDATE %pissues SET issue_flags=?, issue_resolution=?, issue_closed_date=?, issue_user_closed=?, issue_closed_comment=? WHERE issue_id=?' );

			$stmt->bind_param( 'iiiisi', $issue['issue_flags'], $resolution, $this->time, $this->user['user_id'], $closed_comment, $issue['issue_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();

			$stmt = $this->db->prepare_query( 'SELECT w.*, u.user_id, u.user_name, u.user_email FROM %pwatching w
				LEFT JOIN %pusers u ON u.user_id=w.watch_user WHERE watch_issue=?' );

			$stmt->bind_param( 'i', $issue['issue_id'] );
			$this->db->execute_query( $stmt );

			$notify_list = $stmt->get_result();
			$stmt->close();

			if( $notify_list ) {
				while( $notify = $this->db->assoc( $notify_list ) )
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

			// Now clean up the watch data. Leaving behind so many closed tickets there is bloating the table.
			if( $this->settings['prune_watchlist'] ) {
				$stmt = $this->db->prepare_query( 'DELETE FROM %pwatching WHERE watch_issue=?' );

				$stmt->bind_param( 'i', $issue['issue_id'] );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}

			$link = "{$this->settings['site_address']}index.php?a=issues&i=$i";
			header( 'Location: ' . $link );
		}

		if( isset( $this->get['w'] ) && $this->user['user_level'] >= USER_MEMBER ) {
			if( $this->get['w'] == 'startwatch' ) {
				$stmt = $this->db->prepare_query( 'SELECT * FROM %pwatching WHERE watch_issue=? AND watch_user=?' );
				
				$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$watching = $result->fetch_assoc();

				$stmt->close();

				if( !$watching ) {
					$stmt = $this->db->prepare_query( 'INSERT INTO %pwatching (watch_issue, watch_user) VALUES ( ?, ? )' );

					$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
					$this->db->execute_query( $stmt );

					$stmt->close();
				}
			}

			if( $this->get['w'] == 'stopwatch' ) {
				$stmt = $this->db->prepare_query( 'SELECT * FROM %pwatching WHERE watch_issue=? AND watch_user=?' );

				$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$watching = $result->fetch_assoc();

				if( $watching ) {
					$stmt = $this->db->prepare_query( 'DELETE FROM %pwatching WHERE watch_issue=? AND watch_user=?' );

					$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
					$this->db->execute_query( $stmt );

					$stmt->close();
				}
			}

			if( $this->get['w'] == 'vote' ) {
				$stmt = $this->db->prepare_query( 'SELECT * FROM %pvotes WHERE vote_issue=? AND vote_user=?' );

				$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$voted = $result->fetch_assoc();

				if( !$voted ) {
					$stmt = $this->db->prepare_query( 'INSERT INTO %pvotes (vote_issue, vote_user) VALUES ( ?, ? )' );

					$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
					$this->db->execute_query( $stmt );

					$stmt->close();
				}
			}
		}

		$num = $this->settings['site_commentsperpage'];
		if( $this->user['user_comments_page'] > 0 )
			$num = $this->user['user_comments_page'];

		if( isset( $this->get['num'] ) ) {
			if( $this->is_valid_integer( $this->get['num'] ) ) {
				$num = intval( $this->get['num'] );
			}
		}

		$min = 0;
		if( isset( $this->get['min'] ) ) {
			if( $this->is_valid_integer( $this->get['min'] ) ) {
				$min = intval( $this->get['min'] );
			}
		}

		if( isset( $this->get['c'] ) ) {
			if( !$this->is_valid_integer( $this->get['c'] ) ) {
				return $this->error( 404 );
			}

			$cmt = intval( $this->get['c'] );

			// We need to find what page the requested comment is on
			$stmt = $this->db->prepare_query( 'SELECT COUNT(comment_id) count FROM %pcomments WHERE comment_issue=? AND comment_id < ?' );

			$stmt->bind_param( 'ii', $i, $cmt );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$coms = $result->fetch_assoc();

			$stmt->close();

			if( $coms )
				$count = $coms['count'] + 1;
			else $count = 0;

			$min = 0; // Start at the first page regardless
			while( $count > ( $min + $num ) ) {
				$min += $num;
			}
		}

		$index_template->assign( 'site_name', $issue['project_name'] );
		$this->projectid = $issue['project_id'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/issue_viewissue.xtpl' );

		// URL stripped back to only the necessary parts cause I'm tired of people linking to URLs with long lists of parameters
		if( !isset( $this->get['c'] ) ) {
			$xtpl->assign( 'core_url', "{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}" );
		} else {
			$xtpl->assign( 'core_url', $this->server['REQUEST_URI'] );
		}

		$older = null;
		$newer = null;
		$next_issue = null;
		$prev_issue = null;

		if( $this->user['user_level'] >= USER_DEVELOPER ) {
			$stmt = $this->db->prepare_query( 'SELECT issue_id, issue_summary FROM %pissues WHERE issue_date > ? AND issue_project=? ORDER BY issue_date ASC LIMIT 1' );

			$stmt->bind_param( 'ii', $issue['issue_date'], $issue['project_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$next_issue = $result->fetch_assoc();

			$stmt->close();
		} elseif( $this->user['user_level'] > USER_GUEST ) {
			$stmt = $this->db->prepare_query( 'SELECT issue_id, issue_summary FROM %pissues
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
			$stmt = $this->db->prepare_query( 'SELECT issue_id, issue_summary FROM %pissues WHERE issue_date < ? AND issue_project=? ORDER BY issue_date DESC LIMIT 1' );

			$stmt->bind_param( 'ii', $issue['issue_date'], $issue['project_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$prev_issue = $result->fetch_assoc();

			$stmt->close();
		} elseif( $this->user['user_level'] > USER_GUEST ) {
			$stmt = $this->db->prepare_query( 'SELECT issue_id, issue_summary FROM %pissues
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

		$summary = htmlspecialchars( $issue['issue_summary'] );
		$xtpl->assign( 'summary', $summary );
		$xtpl->assign( 'restricted', ( $issue['issue_flags'] & ISSUE_RESTRICTED ) ? ' <span style="color:yellow"> [RESTRICTED ENTRY]</span>' : null );

		$xtpl->assign( 'icon', $this->display_icon( $issue ) );

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

		$author = htmlspecialchars( $this->user['user_name'] );

		$action_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$issue['issue_id']}#newcomment";

		if( $this->user['user_level'] >= USER_MEMBER )
			$xtpl->assign( 'comment_form', $this->comments->generate_comment_form( $author, $summary, $action_link, $closed ) );

		$related = null;
		$stmt = $this->db->prepare_query( 'SELECT * FROM %prelated WHERE related_this=? ORDER BY related_other ASC' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$stmt->close();

      $summary_query = $this->db->prepare_query( 'SELECT issue_summary, issue_flags FROM %pissues WHERE issue_id=?' );
      $summary_query->bind_param( 'i', $related_other );

		while( $row = $this->db->assoc( $result ) )
		{
			$related_other = $row['related_other'];
			$this->db->execute_query( $summary_query );

			$o_result = $summary_query->get_result();
			$other = $o_result->fetch_assoc();

			if( $other['issue_flags'] & ISSUE_CLOSED )
				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']} [closed]\" style=\"text-decoration:line-through;\">{$row['related_other']}</a>&nbsp;&nbsp;";
			else
				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']}\">{$row['related_other']}</a>&nbsp;&nbsp;";
		}
		$summary_query->close();

		if( $related ) {
			$xtpl->assign( 'related', $related );
			$xtpl->parse( 'IssuePost.Related' );
		}

		$mod_controls = null;

		if( $this->user['user_level'] >= USER_DEVELOPER ) {
			if( !( $issue['issue_flags'] & ISSUE_CLOSED ) || ( $issue['issue_flags'] & ISSUE_REOPEN_REQUEST ) || ( $issue['issue_flags'] & ISSUE_REOPEN_RESOLVED ) ) {
				$mod_controls = '<div class="mod_controls">[ <a href="index.php?a=issues&amp;s=edit&amp;i=' . $issue['issue_id'] . '">Edit</a> ] | [ <a href="index.php?a=issues&amp;s=del&amp;i=' . $issue['issue_id'] . '">Delete</a> ]</div>';
			} else {
				$mod_controls = '<div class="mod_controls">[ <a href="index.php?a=reopen&amp;s=request&amp;i=' . $issue['issue_id'] . '">Request Reopen</a> ] | [ <a href="index.php?a=issues&amp;s=edit&amp;i=' . $issue['issue_id'] . '">Edit</a> ] | [ <a href="index.php?a=issues&amp;s=del&amp;i=' . $issue['issue_id'] . '">Delete</a> ]</div>';
			}

			if( !( $issue['issue_flags'] & ISSUE_CLOSED ) ) {
				$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=issues&amp;i={$issue['issue_id']}" );
				$xtpl->assign( 'issue_resolution', $this->select_input( 'issue_resolution', 1, $this->get_resolution_names() ) );
				$xtpl->parse( 'IssuePost.DevCloseBox' );
			}
		} elseif( $this->user['user_level'] == USER_MEMBER ) {
			if( ( $issue['issue_flags'] & ISSUE_CLOSED ) && !( $issue['issue_flags'] & ISSUE_REOPEN_REQUEST ) && !( $issue['issue_flags'] & ISSUE_REOPEN_RESOLVED ) && $issue['project_retired'] == false ) {
				$mod_controls = '<div class="mod_controls">[ <a href="index.php?a=reopen&amp;s=request&amp;i=' . $issue['issue_id'] . '">Request Reopen</a> ]</div>';
			}
		}
		$xtpl->assign( 'mod_controls', $mod_controls );

		$has_files = false;
		$file_list = null;

		$stmt = $this->db->prepare_query( 'SELECT * FROM %pattachments WHERE attachment_issue=? AND attachment_comment=0' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$attachments = $stmt->get_result();
		$stmt->close();

		$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->skin}" );

		while( $attachment = $this->db->assoc( $attachments ) )
		{
			$has_files = true;

			$file_icon = $this->file_tools->get_file_icon( $attachment['attachment_type'] );

			$file_list .= "<img src=\"{$this->settings['site_address']}skins/{$this->skin}$file_icon\" alt=\"\"> <a href=\"{$this->settings['site_address']}index.php?a=attachments&amp;f={$attachment['attachment_id']}\" rel=\"nofollow\">{$attachment['attachment_name']}</a><br>\n";
		}
		if( $has_files ) {
			$xtpl->assign( 'attached_files', $file_list );
			$xtpl->parse( 'IssuePost.Attachments' );
		}

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
			$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

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
		$stmt = $this->db->prepare_query( 'SELECT COUNT(vote_id) count FROM %pvotes WHERE vote_issue=?' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$votes = $result->fetch_assoc();

		$stmt->close();

		if( $votes )
			$vote_count = $votes['count'];

		$xtpl->assign( 'issue_votes', $vote_count );

		if( !( $issue['issue_flags'] & ISSUE_CLOSED ) && $this->user['user_level'] >= USER_MEMBER ) {
			$vote_link = null;
			$stmt = $this->db->prepare_query( 'SELECT vote_id FROM %pvotes WHERE vote_issue=? AND vote_user=?' );

			$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$user_vote = $result->fetch_assoc();

			$stmt->close();

			if( !$user_vote )
				$vote_link = " <a href=\"{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}&amp;w=vote\">+1</a>";
			$xtpl->assign( 'vote_link', $vote_link );

			$stmt = $this->db->prepare_query( 'SELECT watch_issue FROM %pwatching WHERE watch_issue=? AND watch_user=?' );

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
		$xtpl->assign( 'issue_date', $this->t_date( $issue['issue_date'] ) );

		if( $issue['issue_user_edited'] > 1 ) {
			$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $issue['issue_user_edited'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$edited_by = $result->fetch_assoc();

			$stmt->close();

			$xtpl->assign( 'issue_edited_by', $edited_by['user_name'] );
			$xtpl->assign( 'edit_date', $this->t_date( $issue['issue_edited_date'] ) );

			$xtpl->parse( 'IssuePost.EditedBy' );
		}

		if( $issue['issue_flags'] & ISSUE_CLOSED ) {
			$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $issue['issue_user_closed'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$closed_by = $result->fetch_assoc();

			$stmt->close();

			if( isset($closed_by) ) {
				$xtpl->assign( 'issue_closed_by', $closed_by['user_name'] );
			}
			$xtpl->assign( 'closed_date', $this->t_date( $issue['issue_closed_date'] ) );
			$xtpl->assign( 'issue_resolution', $issue['resolution_name'] );

			$xtpl->parse( 'IssuePost.Closed' );

			if( !empty( $issue['issue_closed_comment'] ) ) {
				$xtpl->assign( 'issue_closed_comment', $issue['issue_closed_comment'] );
				$xtpl->parse( 'IssuePost.ClosedComment' );
			}
		}

		if( $issue['issue_flags'] & ISSUE_REOPEN_RESOLVED ) {
			$ruling = $this->format( $issue['issue_ruling'], $issue['issue_flags'] );

			$xtpl->assign( 'issue_ruling', $ruling );

			$xtpl->parse( 'IssuePost.ReopenRuling' );
		}

		$xtpl->parse( 'IssuePost' );
		return $xtpl->text( 'IssuePost' );
	}

	private function create_issue( $index_template )
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_MEMBER )
			return $this->error( 403, 'A validated user account is required to open new issues.' );

		$errors = array();

		$p = -1;
		if( isset( $this->get['p'] ) ) {
			if( !$this->is_valid_integer( $this->get['p'] ) ) {
				return $this->error( 404, 'An invalid project was specified for creating an issue.' );
			}

			$p = intval( $this->get['p'] );
		}
		else {
			return $this->error( 404, 'An invalid project was specified for creating an issue.' );
		}

		$stmt = $this->db->prepare_query( 'SELECT * FROM %pprojects WHERE project_id=?' );

		$stmt->bind_param( 'i', $p );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$project = $result->fetch_assoc();

		$stmt->close();

		if( !$project )
			return $this->error( 404, 'An invalid project was specified for creating an issue.' );

		if( $project['project_retired'] == true && $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 403, $project['project_name'] . ' has been retired. No further issues are being accepted for it.' );

		$flags = 0;
		if( isset( $this->post['issue_flags'] ) ) {
			foreach( $this->post['issue_flags'] as $flag ) {
				if( $this->is_valid_integer( $flag ) ) {
					$flags |= intval( $flag );
				}
			}

			if( $flags >= ISSUE_FLAG_MAX ) {
				array_push( $errors, 'An out of bounds value for the issues flags was detected.' );
				$flags = 0;
			}
		}

		$status = 1;
		$assigned_to = 0;
		if( $this->user['user_level'] >= USER_DEVELOPER ) {
			if( isset( $this->post['issue_status'] ) ) {
				if( !$this->is_valid_integer( $this->post['issue_status'] ) ) {
					array_push( $errors, 'An invalid status was selected.' );
				} else {
					$status = intval( $this->post['issue_status'] );
				}
			}

			if( isset( $this->post['issue_user_assigned'] ) ) {
				if( !$this->is_valid_integer( $this->post['issue_user_assigned'] ) ) {
					array_push( $errors, 'An invalid assigned user was selected.' );
				} else {
					$assigned_to = intval( $this->post['issue_user_assigned'] );
				}
			}
		}

		$type = 1;
		if( isset( $this->post['issue_type'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_type'] ) ) {
				array_push( $errors, 'An invalid issue type was selected.' );
			} else {
				$type = intval( $this->post['issue_type'] );
			}
		}

		$category = 1;
		if( isset( $this->post['issue_category'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_category'] ) ) {
				array_push( $errors, 'An invalid category was selected.' );
			} else {
				$category = intval( $this->post['issue_category'] );
			}
		}

		$component = 1;
		if( isset( $this->post['issue_component'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_component'] ) ) {
				array_push( $errors, 'An invalid component was selected.' );
			} else {
				$component = intval( $this->post['issue_component'] );
			}
		}

		$platform = 1;
		if( isset( $this->post['issue_platform'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_platform'] ) ) {
				array_push( $errors, 'An invalid platform was selected.' );
			} else {
				$platform = intval( $this->post['issue_platform'] );
			}
		}

		$severity = 1;
		if( isset( $this->post['issue_severity'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_severity'] ) ) {
				array_push( $errors, 'An invalid severity was selected.' );
			} else {
				$severity = intval( $this->post['issue_severity'] );
			}
		}

		$summary = '';
		if( isset( $this->post['issue_summary'] ) ) {
			if( !is_string( $this->post['issue_summary'] ) )
				array_push( $errors, 'An invalid value was submitted for the issue summary.' );
			else
				$summary = trim( $this->post['issue_summary'] );
		}

		$text = '';
		if( isset( $this->post['issue_text'] ) ) {
			if( !is_string( $this->post['issue_text'] ) )
				array_push( $errors, 'An invalid value was submitted for the issue details.' );
			else
				$text = trim( $this->post['issue_text'] );
		}

		$new_related = '';
		if( isset( $this->post['new_related'] ) ) {
			if( is_string( $this->post['new_related'] ) )
				$new_related = trim( $this->post['new_related'] );
		}

		if( isset( $this->post['submit'] ) )
		{
			if( empty( $summary ) )
				array_push( $errors, 'You did not enter an issue summary.' );
			if( empty( $text ) )
				array_push( $errors, 'You did not enter any text in the body.' );
			if( !$this->is_valid_token() && ! isset( $this->post['preview'] ) )
				array_push( $errors, 'The security validation token used to verify you are posting this entry is either invalid or expired. Please try again.' );
		}

		if( !isset( $this->post['submit'] ) || count( $errors ) != 0 || isset( $this->post['preview'] ) || isset( $this->post['attach'] ) || isset( $this->post['detach'] ) )
		{
			$this->navselect = 2;

			$xtpl = new XTemplate( './skins/' . $this->skin . '/issue_newissue.xtpl' );

			$index_template->assign( 'site_name', $project['project_name'] );
			$this->projectid = $project['project_id'];

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'summary', htmlspecialchars( $summary ) );
			$xtpl->assign( 'text', htmlspecialchars( $text ) );
			$xtpl->assign( 'new_related', htmlspecialchars( $new_related ) );

			$xtpl->assign( 'bb', ISSUE_BBCODE );
			$xtpl->assign( 'em', ISSUE_EMOJIS );
			$xtpl->assign( 'bbbox', $flags & ISSUE_BBCODE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'embox', $flags & ISSUE_EMOJIS ? " checked=\"checked\"" : null );

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
				$xtpl->assign( 'embox', $flags & ISSUE_EMOJIS ? " checked=\"checked\"" : null );
			} else {
				$xtpl->assign( 'clsbox', null );
				$xtpl->assign( 'resbox', null );
				$xtpl->assign( 'bbbox', ' checked="checked"' );
				$xtpl->assign( 'embox', ' checked="checked"' );
			}

			$xtpl->assign( 'icon', $this->display_icon( $this->user ) );
			$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=issues&amp;s=create&p={$p}" );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'emojis', $this->bbcode->generate_emoji_links() );

			$xtpl->assign( 'project_name', $project['project_name'] );

			if( $this->user['user_level'] < USER_DEVELOPER )
				$xtpl->assign( 'issue_status', 'New' );
			else
				$xtpl->assign( 'issue_status', $this->select_input( 'issue_status', $status, $this->get_status_names() ) );

			$xtpl->assign( 'issue_type', $this->select_input( 'issue_type', $type, $this->get_type_names() ) );
			$xtpl->assign( 'issue_component', $this->select_input( 'issue_component', $component, $this->get_component_names( $project['project_id'] ) ) );
			$xtpl->assign( 'issue_category', $this->select_input( 'issue_category', $category, $this->get_category_names( $project['project_id'] ) ) );

			if( $this->user['user_level'] >= USER_DEVELOPER ) {
				$xtpl->assign( 'issue_assigned', $this->select_input( 'issue_user_assigned', $assigned_to, $this->get_developer_names() ) );

				$xtpl->parse( 'IssueNewPost.Assigned' );
			}

			$xtpl->assign( 'issue_platform', $this->select_input( 'issue_platform', $platform, $this->get_platform_names() ) );
			$xtpl->assign( 'issue_severity', $this->select_input( 'issue_severity', $severity, $this->get_severity_names() ) );

			if( count($errors) > 0 ) {
				$xtpl->assign( 'errors', implode( "<br>\n", $errors ) );

				$xtpl->parse( 'IssueNewPost.Errors' );
			}

			if( isset( $this->post['preview'] ) ) {
				$xtpl->assign( 'preview_summary', htmlspecialchars( $summary ) );
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
				$upload_status = $this->file_tools->attach_file( $this->files['attach_upload'], $this->post['attached_data'] );
			}

			if( isset( $this->post['detach'] ) ) {
				$this->file_tools->delete_attachment( $this->post['attached'], $this->post['attached_data'] );
			}

			$this->file_tools->make_attached_options( $attached, $attached_data, $this->post['attached_data'] );

			if( $attached != null ) {
				$xtpl->assign( 'attached_files', $attached );
				$xtpl->assign( 'attached_data', $attached_data );

				$xtpl->parse( 'IssueNewPost.AttachedFiles' );
			}

			$xtpl->assign( 'upload_status', $upload_status );

			$xtpl->parse( 'IssueNewPost' );
			return $xtpl->text( 'IssueNewPost' );
		}

		$stmt = $this->db->prepare_query( 'INSERT INTO %pissues (issue_status, issue_type, issue_category, issue_user_assigned, issue_platform, issue_severity, issue_user, issue_date, issue_project, issue_component, issue_flags, issue_summary, issue_text )
			     VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )' );

		$stmt->bind_param( 'iiiiiiiiiiiss', $status, $type, $category, $assigned_to, $platform, $severity, $this->user['user_id'], $this->time, $project['project_id'], $component, $flags, $summary, $text );
		$this->db->execute_query( $stmt );

		$id = $this->db->insert_id();
		$stmt->close();

		$stmt = $this->db->prepare_query( 'UPDATE %pusers SET user_issue_count=user_issue_count+1 WHERE user_id=?' );

		$stmt->bind_param( 'i', $this->user['user_id'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		// Users opening an issue automatically starts watching it.
		$stmt = $this->db->prepare_query( 'INSERT INTO %pwatching (watch_issue, watch_user) VALUES ( ?, ? )' );

		$stmt->bind_param( 'ii', $id, $this->user['user_id'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		// Notify assignee if one was set and it's not this person.
		if( $assigned_to > 1 && $assigned_to != $this->user['user_id'] ) {
			$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
			$subject = ":: [{$project['project_name']}] Issue Assignment Update: Issue #$id - $summary";
			$message = "An issue at {$this->settings['site_name']} has been assigned to you.\n\n";
			$message .= "{$this->settings['site_address']}index.php?a=issues&i=$id\n\n";
			$message .= "You will now receive notifications when this issue is updated.\n\n";

			$stmt = $this->db->prepare_query( 'SELECT user_email FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $assigned_to );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$notify = $result->fetch_assoc();

			$stmt->close();

			mail( $notify['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
		}

		if( !empty( $new_related ) ) {
			$related = explode( ',', $new_related );

			foreach( $related as $value )
			{
				if( !$this->is_valid_integer( $value ) )
					continue;

				$other = intval( $value );

				if( $other == $id )
					continue;

				$stmt = $this->db->prepare_query( 'SELECT issue_id FROM %pissues WHERE issue_id=?' );

				$stmt->bind_param( 'i', $other );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$other_issue = $result->fetch_assoc();

				$stmt->close();

				if( !$other_issue )
					continue;

				$stmt = $this->db->prepare_query( 'INSERT INTO %prelated (related_this, related_other) VALUES (?, ?)' );
				$stmt->bind_param( 'ii', $id, $other );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$stmt = $this->db->prepare_query( 'INSERT INTO %prelated (related_this, related_other) VALUES (?, ?)' );
				$stmt->bind_param( 'ii', $other, $id );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}
		}

		if( isset( $this->post['attached_data'] ) ) {
			$this->file_tools->attach_files_db( $id, 0, $this->post['attached_data'] );
		}

		if( !empty( $this->settings['wordpress_api_key'] ) ) {
			require_once( 'lib/akismet.php' );
			$akismet = null;
			$spam_checked = false;

			if( $this->user['user_level'] < USER_DEVELOPER ) {
				try {
					$akismet = new Akismet( $this );

					$akismet->set_comment_author( $this->user['user_name'] );
					$akismet->set_comment_author_email( $this->user['user_email'] );

					if( isset( $this->post['url'] ) && !empty( $this->post['url'] ) )
						$akismet->set_comment_author_url( $this->post['url'] );
					else
						$akismet->set_comment_author_url( $this->user['user_url'] );

					$akismet->set_comment_content( $text );
					$akismet->set_comment_type( 'bug-report' );

					$plink = $this->settings['site_address'] . "index.php?a=issues&i=$id";
					$akismet->set_permalink( $plink );

					$spam_checked = true;
				}
				// Try and deal with it rather than say something.
				catch( Exception $e ) { $this->error( 0, $e->getMessage() ); }
			} else {
				$spam_checked = true;
			}

			if( $spam_checked && $akismet != null )
			{
            $response = $akismet->is_this_spam();

            if( isset( $response[1] ) && $response[1] == 'true' ) {
               // Only store this if Akismet has not issues the x-akismet-pro-tip header
               if( !isset( $response[0]['x-akismet-pro-tip'] ) || $response[0]['x-akismet-pro-tip'] != 'discard' ) {
                  // Store the contents of the entire $_SERVER array.
                  $svars = json_encode( $_SERVER );

                  $stmt = $this->db->prepare_query( 'INSERT INTO %pspam (spam_issue, spam_user, spam_type, spam_date, spam_ip, spam_server, spam_comment) VALUES (?, ?, ?, ?, ?, ?, ?)' );

                  $f1 = SPAM_ISSUE;
                  $s1 = '';
                  $stmt->bind_param( 'iiiisss', $id, $this->user['user_id'], $f1, $this->time, $this->ip, $svars, $s1 );
                  $this->db->execute_query( $stmt );
                  $stmt->close();

                  $this->settings['spam_count']++;
                  $this->save_settings();
                  $this->comments->purge_old_spam();

                  $flags |= ISSUE_SPAM;
                  $stmt = $this->db->prepare_query( 'UPDATE %pissues SET issue_flags=? WHERE issue_id=?' );

                  $stmt->bind_param( 'ii', $flags, $id );
                  $this->db->execute_query( $stmt );
                  $stmt->close();

                  return $this->message( 'Akismet Warning', 'This issue has been flagged as possible spam and will need to be evaluated by the administration before being visible.' );
               }
               else {
                  $this->settings['spam_count']++;
                  $this->save_settings();
                  $this->comments->purge_old_spam();

                  return $this->message( 'Akismet Warning', 'This issue has been flagged as known spam and will not be submitted.' );
               }
            }
			}
		}

		$this->settings['total_issues']++;
		$this->save_settings();

		$link = 'index.php?a=issues&i=' . $id;
		header( 'Location: ' . $link );
	}

	private function edit_issue( $index_template )
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 403, 'Access Denied: You do not have permission to perform that action.' );

		if( !isset( $this->get['i'] ) )
			return $this->error( 403, 'Access Denied: You do not have permission to perform that action.' );

		if( !$this->is_valid_integer( $this->get['i'] ) )
			return $this->error( 403, 'Access Denied: You do not have permission to perform that action.' );

		$i = intval( $this->get['i'] );

		$stmt = $this->db->prepare_query( 'SELECT i.*, c.category_name, b.component_name, p.project_id, p.project_name, p.project_retired, s.platform_name, t.status_name, r.severity_name, v.resolution_name, x.type_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
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

		$errors = array();

		$flags = $issue['issue_flags'];
		if( isset( $this->post['issue_flags'] ) ) {
			$flags = 0;

			foreach( $this->post['issue_flags'] as $flag ) {
				if( $this->is_valid_integer( $flag ) ) {
					$flags |= intval( $flag );
				}
			}

			if( $flags >= ISSUE_FLAG_MAX ) {
				array_push( $errors, 'An out of bounds value for the issues flags was detected.' );
				$flags = $issue['issue_flags'];
			}
		}

		$summary = $issue['issue_summary'];
		if( isset( $this->post['issue_summary'] ) ) {
			if( !is_string( $this->post['issue_summary'] ) )
				array_push( $errors, 'An invalid value was submitted for the issue summary.' );
			else
				$summary = trim( $this->post['issue_summary'] );
		}

		$text = $issue['issue_text'];
		if( isset( $this->post['issue_text'] ) ) {
			if( !is_string( $this->post['issue_text'] ) )
				array_push( $errors, 'An invalid value was submitted for the issue details.' );
			else
				$text = trim( $this->post['issue_text'] );
		}

		$new_related = '';
		if( isset( $this->post['new_related'] ) ) {
			if( is_string( $this->post['new_related'] ) )
				$new_related = trim( $this->post['new_related'] );
		}

		$status = $issue['issue_status'];
		$assigned_to = $issue['issue_user_assigned'];
		if( $this->user['user_level'] >= USER_DEVELOPER ) {
			if( isset( $this->post['issue_status'] ) ) {
				if( !$this->is_valid_integer( $this->post['issue_status'] ) ) {
					array_push( $errors, 'An invalid status was selected.' );
				} else {
					$status = intval( $this->post['issue_status'] );
				}
			}

			if( isset( $this->post['issue_user_assigned'] ) ) {
				if( !$this->is_valid_integer( $this->post['issue_user_assigned'] ) ) {
					array_push( $errors, 'An invalid assigned user was selected.' );
				} else {
					$assigned_to = intval( $this->post['issue_user_assigned'] );
				}
			}
		}

		$type = $issue['issue_type'];
		if( isset( $this->post['issue_type'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_type'] ) ) {
				array_push( $errors, 'An invalid issue type was selected.' );
			} else {
				$type = intval( $this->post['issue_type'] );
			}
		}

		$category = $issue['issue_category'];
		if( isset( $this->post['issue_category'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_category'] ) ) {
				array_push( $errors, 'An invalid category was selected.' );
			} else {
				$category = intval( $this->post['issue_category'] );
			}
		}

		$component = $issue['issue_component'];
		if( isset( $this->post['issue_component'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_component'] ) ) {
				array_push( $errors, 'An invalid component was selected.' );
			} else {
				$component = intval( $this->post['issue_component'] );
			}
		}

		$platform = $issue['issue_platform'];
		if( isset( $this->post['issue_platform'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_platform'] ) ) {
				array_push( $errors, 'An invalid platform was selected.' );
			} else {
				$platform = intval( $this->post['issue_platform'] );
			}
		}

		$severity = $issue['issue_severity'];
		if( isset( $this->post['issue_severity'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_severity'] ) ) {
				array_push( $errors, 'An invalid severity was selected.' );
			} else {
				$severity = intval( $this->post['issue_severity'] );
			}
		}

		$project = $issue['issue_project'];
		if( isset( $this->post['issue_project'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_project'] ) ) {
				array_push( $errors, 'An invalid project was selected.' );
			} else {
				$project = intval( $this->post['issue_project'] );
			}
		}

		$resolution = $issue['issue_resolution'];
		if( isset( $this->post['issue_resolution'] ) ) {
			if( !$this->is_valid_integer( $this->post['issue_resolution'] ) ) {
				array_push( $errors, 'An invalid resolution was selected.' );
			} else {
				$resolution = intval( $this->post['issue_resolution'] );
			}
		}

		if( isset( $this->post['submit'] ) )
		{
			if( empty( $summary ) )
				array_push( $errors, 'You did not enter an issue summary.' );
			if( empty( $text ) )
				array_push( $errors, 'You did not enter any text in the body.' );
			if( !$this->is_valid_token() && !isset( $this->post['preview'] ) )
				array_push( $errors, 'The security validation token used to verify you are editing this entry is either invalid or expired. Please try again.' );
		}

		if( !isset( $this->post['submit'] ) || count( $errors ) != 0 || isset( $this->post['preview'] ) || isset( $this->post['attach'] ) || isset( $this->post['detach'] ) )
		{
			$xtpl = new XTemplate( './skins/' . $this->skin . '/issue_editissue.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );

			$index_template->assign( 'site_name', $issue['project_name'] );
			$this->projectid = $issue['project_id'];

			$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->skin}" );
			$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=issues&amp;s=edit&amp;i={$issue['issue_id']}" );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'emojis', $this->bbcode->generate_emoji_links() );
			$xtpl->assign( 'submitted_by', htmlspecialchars( $issue['user_name'] ) );
			$xtpl->assign( 'icon', $this->display_icon( $issue ) );

			if( empty( $summary ) )
				$summary = $issue['issue_summary'];
			$xtpl->assign( 'summary', htmlspecialchars( $summary ) );

			if( empty( $text ) )
				$text = $issue['issue_text'];
			$xtpl->assign( 'text', htmlspecialchars( $text ) );

			$xtpl->assign( 'issue_id', $issue['issue_id'] );
			$xtpl->assign( 'issue_status', $this->select_input( 'issue_status', $status, $this->get_status_names() ) );
			$xtpl->assign( 'issue_type', $this->select_input( 'issue_type', $type, $this->get_type_names() ) );
			$xtpl->assign( 'issue_project', $this->select_input( 'issue_project', $project, $this->get_project_names() ) );
			$xtpl->assign( 'issue_component', $this->select_input( 'issue_component', $component, $this->get_component_names( $issue['issue_project'] ) ) );
			$xtpl->assign( 'issue_category', $this->select_input( 'issue_category', $category, $this->get_category_names( $issue['issue_project'] ) ) );
			$xtpl->assign( 'issue_assigned', $this->select_input( 'issue_user_assigned', $assigned_to, $this->get_developer_names() ) );
			$xtpl->assign( 'issue_platform', $this->select_input( 'issue_platform', $platform, $this->get_platform_names() ) );
			$xtpl->assign( 'issue_severity', $this->select_input( 'issue_severity', $severity, $this->get_severity_names() ) );
			$xtpl->assign( 'issue_resolution', $this->select_input( 'issue_resolution', $resolution, $this->get_resolution_names() ) );

			$xtpl->assign( 'bb', ISSUE_BBCODE );
			$xtpl->assign( 'em', ISSUE_EMOJIS );
			$xtpl->assign( 'bbbox', $flags & ISSUE_BBCODE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'embox', $flags & ISSUE_EMOJIS ? " checked=\"checked\"" : null );

			if( $this->user['user_level'] >= USER_DEVELOPER ) {
				$xtpl->assign( 'cls', ISSUE_CLOSED );
				$xtpl->assign( 'res', ISSUE_RESTRICTED );

				$xtpl->assign( 'clsbox', $flags & ISSUE_CLOSED ? " checked=\"checked\"" : null );
				$xtpl->assign( 'resbox', $flags & ISSUE_RESTRICTED ? " checked=\"checked\"" : null );

				$closed_comment = null;
				if( !empty( $issue['issue_closed_comment'] ) )
					$closed_comment = $issue['issue_closed_comment'];

				$xtpl->assign( 'closed_comment', $issue['issue_closed_comment'] );

				$xtpl->parse( 'IssueEditPost.DevBlock.ClosedComment' );
				$xtpl->parse( 'IssueEditPost.DevBlock' );
			}

			if( isset( $this->post['preview'] ) ) {
				$xtpl->assign( 'preview_summary', htmlspecialchars( $summary ) );
				$xtpl->assign( 'preview_text', $this->format( $text, $flags ) );

				$xtpl->parse( 'IssueEditPost.Preview' );
			}

			if( count( $errors ) > 0 ) {
				$xtpl->assign( 'errors', implode( "<br>\n", $errors ) );
				$xtpl->parse( 'IssueEditPost.Errors' );
			}

			$related = null;
			$stmt = $this->db->prepare_query( 'SELECT * FROM %prelated WHERE related_this=?' );

			$stmt->bind_param( 'i', $issue['issue_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$stmt->close();

         $summary_query = $this->db->prepare_query( 'SELECT issue_summary FROM %pissues WHERE issue_id=?' );
         $summary_query->bind_param( 'i', $related_other );

			while( $row = $this->db->assoc( $result ) )
			{
				$related_other = $row['related_other'];
				$this->db->execute_query( $summary_query );

				$new_result = $summary_query->get_result();
				$other = $new_result->fetch_assoc();

				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']}\">{$row['related_other']}</a>&nbsp;&nbsp;";
			}
			$summary_query->close();

			if( $related ) {
				$xtpl->assign( 'related', $related );
				$xtpl->parse( 'IssueEditPost.Related' );
			}

			if( !empty( $new_related ) )
				$xtpl->assign( 'new_related', $new_related );

			// Time to deal with icky file attachments.
			$attached = null;
			$attached_data = null;
			$upload_status = null;

			if( !isset( $this->post['attached_data'] ) ) {
				$this->post['attached_data'] = array();
			}

			if( isset( $this->post['attach'] ) ) {
				$upload_status = $this->file_tools->attach_file( $this->files['attach_upload'], $this->post['attached_data'] );
			}

			if( isset( $this->post['detach'] ) ) {
				$this->file_tools->delete_attachment( $this->post['attached'], $this->post['attached_data'] );
			}

			$this->file_tools->make_attached_options( $attached, $attached_data, $this->post['attached_data'] );

			if( $attached != null ) {
				$xtpl->assign( 'attached_files', $attached );
				$xtpl->assign( 'attached_data', $attached_data );

				$xtpl->parse( 'IssueEditPost.AttachedFiles' );
			}

			$existing_files = null;
			$stmt = $this->db->prepare_query( 'SELECT attachment_id, attachment_name, attachment_filename FROM %pattachments WHERE attachment_issue=? AND attachment_comment=0' );

			$stmt->bind_param( 'i', $i );
			$this->db->execute_query( $stmt );

			$attachments = $stmt->get_result();
			$stmt->close();

			while( $row = $this->db->assoc( $attachments ) )
			{
				$existing_files .= "<input type=\"checkbox\" name=\"file_array[]\" value=\"{$row['attachment_id']}\"> Delete Attachment - {$row['attachment_name']}<br>\n";
			}
			$xtpl->assign( 'existing_attachments', $existing_files );
			$xtpl->assign( 'upload_status', $upload_status );

			$xtpl->parse( 'IssueEditPost' );
			return $xtpl->text( 'IssueEditPost' );
		}

		$notify_users = false;
		$notify_new_assignee = false;
		$new_assignee = 0;
		$notify_message = null;

		// Oh yeah. This is gonna be one hell of a list :|
		if( $status != $issue['issue_status'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare_query( 'SELECT status_name FROM %pstatus WHERE status_id=?' );

			$stmt->bind_param( 'i', $status );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_status = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nStatus Changed: {$issue['status_name']} ---> {$new_status['status_name']}";
		}

		if( $type != $issue['issue_type'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare_query( 'SELECT type_name FROM %ptypes WHERE type_id=?' );

			$stmt->bind_param( 'i', $type );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_type = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nType Changed: {$issue['type_name']} ---> {$new_type['type_name']}";
		}

		if( $project != $issue['issue_project'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare_query( 'SELECT project_name FROM %pprojects WHERE project_id=?' );

			$stmt->bind_param( 'i', $project );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_project = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nProject Changed: {$issue['project_name']} ---> {$new_project['project_name']}";
		}

		if( $component != $issue['issue_component'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare_query( 'SELECT component_name FROM %pcomponents WHERE component_id=?' );

			$stmt->bind_param( 'i', $component );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_component = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nComponent Changed: {$issue['component_name']} ---> {$new_component['component_name']}";
		}

		if( $category != $issue['issue_category'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare_query( 'SELECT category_name FROM %pcategories WHERE category_id=?' );

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
				$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $issue['issue_user_assigned'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$assigned_user = $result->fetch_assoc();

				$stmt->close();

				$old_user = $assigned_user['user_name'];
			}

			$user_name = 'Nobody';
			$new_user = null;

			if( $assigned_to > 1 ) {
				$stmt = $this->db->prepare_query( 'SELECT user_id, user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $assigned_to );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$new_user = $result->fetch_assoc();

				$stmt->close();
				$user_name = $new_user['user_name'];
			}

			$notify_message .= "\nAssignment Changed: $old_user ---> {$user_name}";

			if( $assigned_to > 1 ) {
				$stmt = $this->db->prepare_query( 'SELECT watch_user FROM %pwatching WHERE watch_user=? AND watch_issue=?' );

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

			$stmt = $this->db->prepare_query( 'SELECT platform_name FROM %pplatforms WHERE platform_id=?' );

			$stmt->bind_param( 'i', $platform );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$new_platform = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nPlatform Changed: {$issue['platform_name']} ---> {$new_platform['platform_name']}";
		}

		if( $severity != $issue['issue_severity'] ) {
			$notify_users = true;

			$stmt = $this->db->prepare_query( 'SELECT severity_name FROM %pseverities WHERE severity_id=?' );

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

			$resolution = intval( $this->post['issue_resolution'] );

			if( isset( $this->post['closed_comment'] ) )
				$closed_comment = $this->post['closed_comment'];

		}

		if( ( $flags & ISSUE_CLOSED ) && !( $issue['issue_flags'] & ISSUE_CLOSED ) ) {
			$notify_users = true;

			$date = $this->t_date( $this->time, false, true );
			$notify_message .= "\nIssue has been closed by {$this->user['user_name']} on $date";

			$stmt = $this->db->prepare_query( 'SELECT resolution_name FROM %presolutions WHERE resolution_id=?' );

			$stmt->bind_param( 'i', $resolution );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$resolved = $result->fetch_assoc();

			$stmt->close();

			$notify_message .= "\nThe resolution for this issue was: {$resolved['resolution_name']}";

			if( $closed_comment != '' )
				$notify_message .= "\nAdditional comments: $closed_comment";
		}

		$stmt = $this->db->prepare_query( 'UPDATE %pissues SET issue_status=?, issue_resolution=?, issue_type=?, issue_project=?, issue_category=?, issue_user_assigned=?, issue_platform=?, issue_component=?,
			   issue_severity=?, issue_summary=?, issue_text=?, issue_flags=?, issue_edited_date=?, issue_user_edited=?, issue_closed_date=?, issue_user_closed=?, issue_closed_comment=?
			WHERE issue_id=?' );

		$stmt->bind_param( 'iiiiiiiiissiiiiisi', $status, $resolution, $type, $project, $category, $assigned_to, $platform, $component, $severity, $summary, $text, $flags, $edit_date, $edit_by, $closed_date, $closed_by, $closed_comment, $i );
		$this->db->execute_query( $stmt );

		if( !empty( $new_related ) ) {
			$related = explode( ',', $new_related );

			foreach( $related as $value )
			{
				if( !$this->is_valid_integer( $value ) )
					continue;

				$other = intval( $value );

				if( $other == $i )
					continue;

				$stmt = $this->db->prepare_query( 'SELECT issue_id FROM %pissues WHERE issue_id=?' );

				$stmt->bind_param( 'i', $other );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$other_issue = $result->fetch_assoc();

				$stmt->close();

				if( !$other_issue )
					continue;

				$stmt = $this->db->prepare_query( 'SELECT related_other FROM %prelated WHERE related_this=? AND related_other=?' );

				$stmt->bind_param( 'ii', $i, $other );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$duplicate = $result->fetch_assoc();

				$stmt->close();

				if( $duplicate )
					continue;

				$stmt = $this->db->prepare_query( 'INSERT INTO %prelated (related_this, related_other) VALUES (?, ?)' );
				$stmt->bind_param( 'ii', $i, $other );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$stmt = $this->db->prepare_query( 'INSERT INTO %prelated (related_this, related_other) VALUES (?, ?)' );
				$stmt->bind_param( 'ii', $other, $i );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}
		}

		if( isset( $this->post['attached_data'] ) ) {
			$this->file_tools->attach_files_db( $i, 0, $this->post['attached_data'] );
		}

		// Delete attachments selected for removal
		if( isset( $this->post['file_array'] ) ) {
			foreach( $this->post['file_array'] as $fileval )
			{
				$file = intval( $fileval );

				$stmt = $this->db->prepare_query( 'SELECT attachment_filename FROM %pattachments WHERE attachment_id=?' );

				$stmt->bind_param( 'i', $file );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$attachment = $result->fetch_assoc();

				$stmt->close();

				@unlink( $this->file_dir . $attachment['attachment_filename'] );

				$stmt = $this->db->prepare_query( 'DELETE FROM %pattachments WHERE attachment_id=?' );
				$stmt->bind_param( 'i', $file );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}
		}

		if( $notify_users ) {
			$stmt = $this->db->prepare_query( 'SELECT w.*, u.user_id, u.user_name, u.user_email FROM %pwatching w
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
			$stmt = $this->db->prepare_query( 'INSERT INTO %pwatching (watch_issue, watch_user) VALUES ( ?, ? )' );
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

				$stmt = $this->db->prepare_query( 'SELECT user_email FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $new_assignee );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$notify = $result->fetch_assoc();

				$stmt->close();

				mail( $notify['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
			}
		}

		// Now clean up the watch data. Leaving behind so many closed tickets there is bloating the table.
		if( $this->settings['prune_watchlist'] && $closed_by > 0 ) {
			$stmt = $this->db->prepare_query( 'DELETE FROM %pwatching WHERE watch_issue=?' );

			$stmt->bind_param( 'i', $issue['issue_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();
		}

		$link = 'index.php?a=issues&i=' . $i;
		header( 'Location: ' . $link );
	}

	private function delete_issue( $index_template )
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 403, 'Access Denied: You do not have permission to perform that action.' );

		if( !isset( $this->get['i'] ) )
			return $this->error( 403, 'Access Denied: You do not have permission to perform that action.' );

		if( !$this->is_valid_integer( $this->get['i'] ) )
			return $this->error( 403, 'Access Denied: You do not have permission to perform that action.' );

		$i = intval( $this->get['i'] );

		if( !isset( $this->post['confirm'] ) ) {
			$stmt = $this->db->prepare_query( 'SELECT i.*, c.category_name, p.project_id, p.project_name, b.component_name, s.platform_name, t.status_name, r.severity_name, x.type_name, y.resolution_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
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
			$xtpl->assign( 'summary', htmlspecialchars( $issue['issue_summary'] ) );
			$xtpl->assign( 'text', $this->format( $issue['issue_text'], $issue['issue_flags'] ) );
			$xtpl->assign( 'icon', $this->display_icon( $issue ) );

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
				$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

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
			$stmt = $this->db->prepare_query( 'SELECT COUNT(vote_id) count FROM %pvotes WHERE vote_issue=?' );

			$stmt->bind_param( 'i', $issue['issue_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$votes = $result->fetch_assoc();

			$stmt->close();

			if( $votes )
				$vote_count = $votes['count'];

			$xtpl->assign( 'issue_votes', $vote_count );
			$xtpl->assign( 'issue_user', htmlspecialchars( $issue['user_name'] ) );
			$xtpl->assign( 'issue_date', $this->t_date( $issue['issue_date'] ) );

			if( $issue['issue_user_edited'] > 1 ) {
				$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $issue['issue_user_edited'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$edited_by = $result->fetch_assoc();

				$stmt->close();

				$xtpl->assign( 'issue_edited_by', htmlspecialchars( $edited_by['user_name'] ) );
				$xtpl->assign( 'edit_date', $this->t_date( $issue['issue_edited_date'] ) );

				$xtpl->parse( 'IssuePostDelete.EditedBy' );
			}

			if( $issue['issue_flags'] & ISSUE_CLOSED ) {
				$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $issue['issue_user_closed'] );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$closed_by = $result->fetch_assoc();

				$stmt->close();

				$xtpl->assign( 'issue_closed_by', htmlspecialchars( $closed_by['user_name'] ) );
				$xtpl->assign( 'closed_date', $this->t_date( $issue['issue_closed_date'] ) );
				$xtpl->assign( 'issue_resolution', $issue['resolution_name'] );

				$xtpl->parse( 'IssuePostDelete.Closed' );

				if( !empty( $issue['issue_closed_comment'] ) ) {
					$xtpl->assign( 'issue_closed_comment', htmlspecialchars( $issue['issue_closed_comment'] ) );
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
			return $this->error( -1 );
		}

		$stmt = $this->db->prepare_query( 'SELECT issue_id, issue_project, issue_user FROM %pissues WHERE issue_id=?' );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$check = $result->fetch_assoc();

		$stmt->close();

		if( !$check )
			return $this->error( 404 );

		$stmt = $this->db->prepare_query( 'SELECT attachment_filename FROM %pattachments WHERE attachment_issue=?' );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$attachments = $stmt->get_result();
		$stmt->close();

		while( $attachment = $this->db->assoc( $attachments ) )
		{
			@unlink( $this->file_dir . $attachment['attachment_filename'] );
		}

		$stmt = $this->db->prepare_query( 'SELECT comment_id FROM %pcomments WHERE comment_issue=?', $i );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$comments = $stmt->get_result();
		$stmt->close();

      $attachment_query = $this->db->prepare_query( 'SELECT attachment_filename FROM %pattachments WHERE attachment_comment=?' );
      $attachment_query->bind_param( 'i', $comment_id );

		while( $comment = $this->db->assoc( $comments ) )
		{
			$comment_id = $comment['comment_id'];
			$this->db->execute_query( $attachment_query );

			$c_attachments = $attachment_query->get_result();

			while( $c_attachment = $this->db->assoc( $c_attachments ) )
			{
				@unlink( $this->file_dir . $c_attachment['attachment_filename'] );
			}
		}
		$attachment_query->close();

		$stmt = $this->db->prepare_query( 'UPDATE %pusers SET user_issue_count=user_issue_count-1 WHERE user_id=?' );
		$stmt->bind_param( 'i', $check['issue_user'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare_query( 'DELETE FROM %pissues WHERE issue_id=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare_query( 'DELETE FROM %pcomments WHERE comment_issue=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare_query( 'DELETE FROM %pattachments WHERE attachment_issue=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare_query( 'DELETE FROM %prelated WHERE related_this=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare_query( 'DELETE FROM %prelated WHERE related_other=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare_query( 'DELETE FROM %pwatching WHERE watch_issue=?' );
		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$this->settings['total_issues']--;
		$this->save_settings();

		return $this->message( 'Delete Issue', 'Issue and all comments and attachments have been deleted.', 'Continue', "index.php?a=issues&project={$check['issue_project']}" );
	}

	public function select_input( $name, $value, $data = array() )
	{
		$out = null;

		for( $count = 0; $count < count( $data ); $count++ )
		{
			$selected = '';

			if( $data[$count]['id'] == $value )
				$selected = ' selected="selected"';

			$out .= "<option value=\"{$data[$count]['id']}\"{$selected}>{$data[$count]['name']}</option>";
		}

		return "<select name=\"$name\">$out</select>";
	}

	private function get_status_names()
	{
		$names = array();

		$status = $this->db->dbquery( 'SELECT * FROM %pstatus WHERE status_shows=1 ORDER BY status_position ASC' );
		while( $row = $this->db->assoc( $status ) )
		{
			$names[] = array( 'id' => $row['status_id'], 'name' => $row['status_name'] );
		}

		return $names;
	}

	private function get_project_names()
	{
		$names = array();

		$projects = $this->db->dbquery( 'SELECT * FROM %pprojects ORDER BY project_position ASC' );

		while( $row = $this->db->assoc( $projects ) )
		{
			$names[] = array( 'id' => $row['project_id'], 'name' => $row['project_name'] );
		}

		return $names;
	}

	private function get_component_names( $projid )
	{
		$names = array();

		$stmt = $this->db->prepare_query( 'SELECT * FROM %pcomponents WHERE component_project=? ORDER BY component_position ASC' );

		$stmt->bind_param( 'i', $projid );
		$this->db->execute_query( $stmt );

		$components = $stmt->get_result();
		$stmt->close();

		while( $row = $this->db->assoc( $components ) )
		{
			$names[] = array( 'id' => $row['component_id'], 'name' => $row['component_name'] );
		}

		return $names;
	}

	private function get_category_names( $projid )
	{
		$names = array();

		$stmt = $this->db->prepare_query( 'SELECT * FROM %pcategories WHERE category_project=? ORDER BY category_position ASC' );

		$stmt->bind_param( 'i', $projid );
		$this->db->execute_query( $stmt );

		$categories = $stmt->get_result();
		$stmt->close();

		while( $row = $this->db->assoc( $categories ) )
		{
			$names[] = array( 'id' => $row['category_id'], 'name' => $row['category_name'] );
		}

		return $names;
	}

	private function get_developer_names()
	{
		$names = array();

		$names[] = array( 'id' => 0, 'name' => 'Nobody' );

		$stmt = $this->db->prepare_query( 'SELECT * FROM %pusers WHERE user_level>=? ORDER BY user_name ASC' );

		$level = USER_DEVELOPER;
		$stmt->bind_param( 'i', $level );
		$this->db->execute_query( $stmt );

		$developers = $stmt->get_result();
		$stmt->close();

		while( $row = $this->db->assoc( $developers ) )
		{
			$names[] = array( 'id' => $row['user_id'], 'name' => $row['user_name'] );
		}

		return $names;
	}

	private function get_platform_names()
	{
		$names = array();

		$platforms = $this->db->dbquery( 'SELECT * FROM %pplatforms ORDER BY platform_position ASC' );

		while( $row = $this->db->assoc( $platforms ) )
		{
			$names[] = array( 'id' => $row['platform_id'], 'name' => $row['platform_name'] );
		}

		return $names;
	}

	private function get_severity_names()
	{
		$names = array();

		$severities = $this->db->dbquery( 'SELECT * FROM %pseverities ORDER BY severity_position ASC' );

		while( $row = $this->db->assoc( $severities ) )
		{
			$names[] = array( 'id' => $row['severity_id'], 'name' => $row['severity_name'] );
		}

		return $names;
	}

	private function get_resolution_names()
	{
		$names = array();

		$resolutions = $this->db->dbquery( 'SELECT * FROM %presolutions ORDER BY resolution_position ASC' );

		while( $row = $this->db->assoc( $resolutions ) )
		{
			$names[] = array( 'id' => $row['resolution_id'], 'name' => $row['resolution_name'] );
		}

		return $names;
	}

	private function get_type_names()
	{
		$names = array();

		$types = $this->db->dbquery( 'SELECT * FROM %ptypes ORDER BY type_position ASC' );

		while( $row = $this->db->assoc( $types ) )
		{
			$names[] = array( 'id' => $row['type_id'], 'name' => $row['type_name'] );
		}

		return $names;
	}
}
?>