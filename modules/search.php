<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2025 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class search extends module
{
	public function execute()
	{
		$this->title = $this->settings['site_name'] . ' :: Search';

		if( $this->user['user_level'] < USER_DEVELOPER ) {
			if( $this->user['user_level'] == USER_GUEST ) {
				if( !isset( $this->cookie[$this->settings['cookie_prefix'] . 'skin'] ) )
					return $this->message( 'Search', 'You must enable cookies to allow searching while not logged in.' );
			}

			if( isset( $_SESSION['last_search'] ) ) {
				if( $_SESSION['last_search'] > $this->time ) {
					$seconds = $_SESSION['last_search'] - $this->time;

					return $this->message( 'Search', "You must wait $seconds seconds before you can execute a new search." );
				}
			}
		}

		$this->navselect = 7;

		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/search.xtpl' );

			$xtpl->assign( 'action_link', $this->settings['site_address'] . 'index.php?a=search' );
			$xtpl->assign( 'search_icon', "{$this->settings['site_address']}skins/{$this->skin}/images/search.png" );
			$xtpl->assign( 'token', $this->generate_token() );

			$xtpl->parse( 'Search.QueryFormSimple' );
			$xtpl->parse( 'Search' );

			return $xtpl->text( 'Search' );
		}

		if( isset( $this->post['simple_search'] ) ) {
			if( !isset( $this->post['search_word'] ) || !is_string( $this->post['search_word'] ) || empty( $this->post['search_word'] ) )
				return $this->message( 'Search', 'You must enter something to search for.' );

			$search_word = trim( $this->post['search_word'] );

			if( strlen( $search_word ) < 3 )
				return $this->message( 'Search', 'You cannot search on a word smaller than 3 letters.' );

			$details = false;
			$summaries = false;
			$comments = false;

			if( isset( $this->post['search_details'] ) )
				$details = true;
			if( isset( $this->post['search_summary'] ) )
				$summaries = true;
			if( isset( $this->post['search_comments'] ) )
				$comments = true;

			if( !$details && !$summaries && !$comments )
				return $this->message( 'Search', 'You must search details, summaries, or comments in order to find something.' );

			$issue_result = null;
			$summary_result = null;
			$comment_result = null;

			if( $details ) {
				if( $this->user['user_level'] >= USER_DEVELOPER ) {
					$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, r.severity_name, x.type_name, t.status_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
						LEFT JOIN %pusers u ON u.user_id=i.issue_user
						LEFT JOIN %ptypes x ON x.type_id=i.issue_type
						LEFT JOIN %pstatus t ON t.status_id=i.issue_status
						LEFT JOIN %pprojects p ON p.project_id=i.issue_project
						LEFT JOIN %pcategories c ON c.category_id=i.issue_category
						LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
						LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
						WHERE (issue_text LIKE ?) ORDER BY i.issue_date DESC' );

					$word = "%{$search_word}%";
					$stmt->bind_param( 's', $word );

					$this->db->execute_query( $stmt );
					$issue_result = $stmt->get_result();
               $stmt->close();
				} elseif( $this->user['user_level'] >= USER_GUEST ) {
					$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, r.severity_name, x.type_name, t.status_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
						LEFT JOIN %pusers u ON u.user_id=i.issue_user
						LEFT JOIN %ptypes x ON x.type_id=i.issue_type
						LEFT JOIN %pstatus t ON t.status_id=i.issue_status
						LEFT JOIN %pprojects p ON p.project_id=i.issue_project
						LEFT JOIN %pcategories c ON c.category_id=i.issue_category
						LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
						LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
						WHERE (issue_text LIKE ?) AND !(issue_flags & ?) AND !(issue_flags & ?) ORDER BY i.issue_date DESC' );

					$word = "%{$search_word}%";
					$f1 = ISSUE_RESTRICTED;
					$f2 = ISSUE_SPAM;
					$stmt->bind_param( 'sii', $word, $f1, $f2 );

					$this->db->execute_query( $stmt );
					$issue_result = $stmt->get_result();
               $stmt->close();
				}
			}

			if( $summaries ) {
				if( $this->user['user_level'] >= USER_DEVELOPER ) {
					$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, r.severity_name, x.type_name, t.status_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
						LEFT JOIN %pusers u ON u.user_id=i.issue_user
						LEFT JOIN %ptypes x ON x.type_id=i.issue_type
						LEFT JOIN %pstatus t ON t.status_id=i.issue_status
						LEFT JOIN %pprojects p ON p.project_id=i.issue_project
						LEFT JOIN %pcategories c ON c.category_id=i.issue_category
						LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
						LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
						WHERE (issue_summary LIKE ?) ORDER BY i.issue_date DESC' );

					$word = "%{$search_word}%";
					$stmt->bind_param( 's', $word );

					$this->db->execute_query( $stmt );
					$summary_result = $stmt->get_result();
               $stmt->close();
				} elseif( $this->user['user_level'] >= USER_GUEST ) {
					$stmt = $this->db->prepare_query( 'SELECT i.*, p.project_name, c.category_name, s.platform_name, r.severity_name, x.type_name, t.status_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
						LEFT JOIN %pusers u ON u.user_id=i.issue_user
						LEFT JOIN %ptypes x ON x.type_id=i.issue_type
						LEFT JOIN %pstatus t ON t.status_id=i.issue_status
						LEFT JOIN %pprojects p ON p.project_id=i.issue_project
						LEFT JOIN %pcategories c ON c.category_id=i.issue_category
						LEFT JOIN %pplatforms s ON s.platform_id=i.issue_platform
						LEFT JOIN %pseverities r ON r.severity_id=i.issue_severity
						WHERE (issue_summary LIKE ?) AND !(issue_flags & ?) AND !(issue_flags & ?) ORDER BY i.issue_date DESC' );

					$word = "%{$search_word}%";
					$f1 = ISSUE_RESTRICTED;
					$f2 = ISSUE_SPAM;
					$stmt->bind_param( 'sii', $word, $f1, $f2 );

					$this->db->execute_query( $stmt );
					$summary_result = $stmt->get_result();
               $stmt->close();
				}
			}

			if( $comments ) {
				$stmt = $this->db->prepare_query( 'SELECT c.comment_id, c.comment_date, c.comment_user, c.comment_message, c.comment_issue, u.user_name, u.user_icon, u.user_icon_type FROM %pcomments c
					LEFT JOIN %pusers u ON u.user_id=c.comment_user
					WHERE (comment_message LIKE ?) ORDER BY c.comment_date DESC' );

					$word = "%{$search_word}%";
					$stmt->bind_param( 's', $word );

					$this->db->execute_query( $stmt );
					$comment_result = $stmt->get_result();
               $stmt->close();
			}

			if( !$issue_result && !$summary_result && !$comment_result )
				return $this->message( 'Search', "No results matching: {$search_word}" );

			$issue_count = 0;
			$summary_count = 0;
			$comment_count = 0;

			$xtpl = new XTemplate( './skins/' . $this->skin . '/search.xtpl' );

			if( $issue_result ) {
				while( $row = $this->db->assoc( $issue_result ) )
				{
					$xtpl->assign( 'icon', $this->display_icon( $row ) );

					$colorclass = 'article';

					if( $row['issue_flags'] & ISSUE_SPAM )
						$colorclass = 'articlealert';

					if( $row['issue_flags'] & ISSUE_RESTRICTED )
						$colorclass = 'articleredalert';

					$xtpl->assign( 'colorclass', $colorclass );

					$item_link = "index.php?a=issues&amp;i={$row['issue_id']}";
					$xtpl->assign( 'issue_link', $item_link );

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
					$issue_count++;

					$xtpl->parse( 'Search.Issues.Result' );
				}

				if( $issue_count > 0 ) {
					$xtpl->assign( 'issue_count', $issue_count );
					$xtpl->assign( 'issues', ( $issue_count > 1 ? 'issues' : 'issue' ) );
					$xtpl->assign( 'issue_search_word', htmlspecialchars( $search_word ) );

					$xtpl->parse( 'Search.Issues' );
				}
			}

			if( $summary_result ) {
				while( $row = $this->db->assoc( $summary_result ) )
				{
					$xtpl->assign( 'icon', $this->display_icon( $row ) );

					$colorclass = 'article';

					if( $row['issue_flags'] & ISSUE_SPAM )
						$colorclass = 'articlealert';

					if( $row['issue_flags'] & ISSUE_RESTRICTED )
						$colorclass = 'articleredalert';

					$xtpl->assign( 'colorclass', $colorclass );

					$item_link = "index.php?a=issues&amp;i={$row['issue_id']}";
					$xtpl->assign( 'issue_link', $item_link );

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
					$summary_count++;

					$xtpl->parse( 'Search.Summaries.Result' );
				}

				if( $summary_count > 0 ) {
					$xtpl->assign( 'summary_count', $summary_count );
					$xtpl->assign( 'summaries', ( $summary_count > 1 ? 'summaries' : 'summary' ) );
					$xtpl->assign( 'summary_search_word', htmlspecialchars( $search_word ) );

					$xtpl->parse( 'Search.Summaries' );
				}
			}

			if( $comment_result ) {
				while( $row = $this->db->assoc( $comment_result ) )
				{
					$xtpl->assign( 'icon', $this->display_icon( $row ) );

					$item_link = "index.php?a=issues&amp;i={$row['comment_issue']}&amp;c={$row['comment_id']}#comment-{$row['comment_id']}";
					$xtpl->assign( 'comment_link', $item_link );

					$xtpl->assign( 'comment_id', $row['comment_id'] );
					$xtpl->assign( 'comment_posted_by', $row['user_name'] );
					$comment_count++;

					$text = '';
					if( strlen( $row['comment_message'] ) < 200 )
						$text = $row['comment_message'];
					else
						$text = substr( $row['comment_message'], 0, 197 ) . '...';
					$xtpl->assign( 'comment_message', htmlspecialchars( $text ) );

					$xtpl->parse( 'Search.Comments.Result' );
				}

				if( $comment_count > 0 ) {
					$xtpl->assign( 'comment_count', $comment_count );
					$xtpl->assign( 'comments', ( $comment_count > 1 ? 'comments' : 'comment' ) );
					$xtpl->assign( 'comment_search_word', htmlspecialchars( $search_word ) );

					$xtpl->parse( 'Search.Comments' );
				}
			}

			if( $this->user['user_level'] < USER_MEMBER )
				$_SESSION['last_search'] = $this->time + ( $this->settings['search_flood_time'] * 2 );
			else
				$_SESSION['last_search'] = $this->time + $this->settings['search_flood_time'];

			if( $issue_count == 0 && $summary_count == 0 && $comment_count == 0 ) {
				return $this->message( 'Search', 'No results found for: ' . $search_word );
			}

			$xtpl->parse( 'Search' );
			return $xtpl->text( 'Search' );
		} elseif( isset( $this->post['advanced_search'] ) ) {
			return $this->message( 'Search', 'Advanced search options are not available yet.' );
		} else {
			// Sending us bogus POST requests? You get to wait 2 hours before trying again now.
			$_SESSION['last_search'] = $this->time + 7200;
			return $this->message( 'Search', 'What are you even doing?' );
		}
	}
}
?>