<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2025 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class reopen extends module
{
	public function execute( $index_template )
	{
		$i = 0;

		if( isset( $this->get['i'] ) ) {
			if( !$this->is_valid_integer( $this->get['i'] ) ) {
				return $this->error( 404 );
			}

			$i = intval( $this->get['i'] );
		}

		if( isset( $this->get['s'] ) && $i > 0 ) {
			if( $this->get['s'] == 'request' ) {
				return $this->initiate_request( $i, $index_template );
			}
		} else if( $i > 0 ) {
			return $this->view_request( $i, $index_template );
		}					

		return $this->list_reopen_requests();
	}

	private function list_reopen_requests( )
	{
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 404 );

		$this->title = $this->settings['site_name'] . ' :: Requests To Reopen';

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

		$stmt = $this->db->prepare_query( 'SELECT r.*, i.issue_id, i.issue_summary, i.issue_flags, i.issue_date, p.project_name, u.user_name, u.user_icon, u.user_icon_type FROM %preopen r
			LEFT JOIN %pissues i ON i.issue_id=r.reopen_issue
			LEFT JOIN %pprojects p ON p.project_id=r.reopen_project
			LEFT JOIN %pusers u ON u.user_id=r.reopen_user
			LIMIT ?, ?' );

		$stmt->bind_param( 'ii', $min, $num );

		$this->db->execute_query( $stmt );
		$result = $stmt->get_result();
		$stmt->close();

		$stmt = $this->db->prepare_query( 'SELECT COUNT(reopen_id) count FROM %preopen' );

		$this->db->execute_query( $stmt );

		$t_result = $stmt->get_result();
		$total = $t_result->fetch_assoc();

		$stmt->close();

		if( $total )
			$list_total = $total['count'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/reopen_list.xtpl' );

		$this->navselect = 6;

		while( $row = $this->db->assoc( $result ) )
		{
			$xtpl->assign( 'icon', $this->display_icon( $row ) );

			$issue_link = "{$this->settings['site_address']}index.php?a=reopen&amp;i={$row['issue_id']}";

			$colorclass = 'article';

			if( $row['issue_flags'] & ISSUE_SPAM )
				$colorclass = 'articlealert';

			if( $row['issue_flags'] & ISSUE_RESTRICTED )
				$colorclass = 'articleredalert';

			$xtpl->assign( 'colorclass', $colorclass );

			$xtpl->assign( 'issue_id', $row['issue_id'] );

			$xtpl->assign( 'request_date', $this->t_date( $row['reopen_date'] ) );
			$xtpl->assign( 'requested_by', $row['user_name'] );
			$xtpl->assign( 'issue_project', $row['project_name'] );
			$xtpl->assign( 'issue_summary', $row['issue_summary'] );
			$xtpl->assign( 'issue_link', $issue_link );

			$xtpl->parse( 'Reopen.Post' );
		}

		$pagelinks = $this->make_links( 0, $list_total, $min, $num, null );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Reopen.PageLinks' );

		$xtpl->parse( 'Reopen' );
		return $xtpl->text( 'Reopen' );
	}

	private function view_request( $i, $index_template )
	{
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->error( 404 );

		$stmt = $this->db->prepare_query( 'SELECT r.*, u.user_name, u.user_icon FROM %preopen r
			LEFT JOIN %pusers u ON u.user_id=r.reopen_user
			WHERE reopen_issue=?' );

		$stmt->bind_param( 'i', $i );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$reopen = $result->fetch_assoc();

		$stmt->close();

		if ( !$reopen )
			return $this->error( 404 );

		$stmt = $this->db->prepare_query( 'SELECT i.*, c.category_name, p.project_id, p.project_name, b.component_name, s.platform_name, t.status_name, r.severity_name, v.resolution_name, x.type_name, u.user_name, u.user_icon, u.user_icon_type FROM %pissues i
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

		$this->title( '#' . $issue['issue_id'] . ' '. $issue['issue_summary'] );

		// If this condition is true, a developer denied the reopen request.
		if( isset( $this->post['reopen_denied'] ) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( -1 );
			}

			if( $issue['issue_flags'] & ISSUE_REOPEN_RESOLVED )
				return $this->message( 'Reopen Issue', 'This request has already been denied.' );

			if( !($issue['issue_flags'] & ISSUE_REOPEN_REQUEST) )
				return $this->message( 'Reopen Issue', 'This request was already approved.' );

			if( !isset( $this->post['reopen_comment'] ) || empty( $this->post['reopen_comment'] ) )
				return $this->message( 'Reopen Issue', 'A reason for denying this request must be provided.' );

			$issue['issue_flags'] |= ISSUE_REOPEN_RESOLVED;

			$date = $this->t_date( $this->time, false, true );
			$notify_message = "\nYour request to reopen this issue was denied by {$this->user['user_name']} on $date";
			$notify_message .= "\n\nAdditional comments: {$this->post['reopen_comment']}";

			$stmt = $this->db->prepare_query( 'UPDATE %pissues SET issue_flags=?, issue_edited_date=?, issue_user_edited=?, issue_ruling=? WHERE issue_id=?' );

			$stmt->bind_param( 'iiisi', $issue['issue_flags'], $this->time, $this->user['user_id'], $this->post['reopen_comment'], $issue['issue_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();

			$stmt = $this->db->prepare_query( 'SELECT user_id, user_name, user_email FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $reopen['reopen_user'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$notify = $result->fetch_assoc();

			$stmt->close();

			if( $notify ) {
				$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
				$subject = ":: [{$issue['project_name']}] Issue Tracking Update: Issue #{$issue['issue_id']} - {$issue['issue_summary']}";
				$message = "An issue you are watching at {$this->settings['site_name']} has been updated.\n\n";
				$message .= "{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}\n";
				$message .= "$notify_message\n\n";

				mail( $notify['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
			}

			$stmt = $this->db->prepare_query( 'DELETE FROM %preopen WHERE reopen_id=?' );
			$stmt->bind_param( 'i', $reopen['reopen_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Reopen Issue', "Request to reopen issue {$issue['issue_id']} was denied.", 'Continue', 'index.php?a=reopen' );
		}

		// If this condition is true, a developer approved a reopen request.
		if( isset( $this->post['reopen_approved'] ) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( -1 );
			}

			if( $issue['issue_flags'] & ISSUE_REOPEN_RESOLVED )
				return $this->message( 'Reopen Issue', 'This request has already been denied.' );

			if( !($issue['issue_flags'] & ISSUE_REOPEN_REQUEST) )
				return $this->message( 'Reopen Issue', 'This request was already approved.' );

			$reopen_comment = '';

			$issue['issue_flags'] &= ~ISSUE_REOPEN_REQUEST;
			$issue['issue_flags'] &= ~ISSUE_CLOSED;

			$date = $this->t_date( $this->time, false, true );
			$notify_message = "\nYour request to reopen this issue was approved by {$this->user['user_name']} on $date";

			if( isset( $this->post['reopen_comment'] ) && $this->post['reopen_comment'] != '' ) {
				$reopen_comment = $this->post['reopen_comment'];
				$notify_message .= "\n\nAdditional comments: $reopen_comment";
			}

			$stmt = $this->db->prepare_query( 'UPDATE %pissues SET issue_flags=? WHERE issue_id=?' );

			$stmt->bind_param( 'ii', $issue['issue_flags'], $issue['issue_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();

			$stmt = $this->db->prepare_query( 'SELECT user_id, user_name, user_email FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $reopen['reopen_user'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$notify = $result->fetch_assoc();

			$stmt->close();

			if( $notify ) {
				$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
				$subject = ":: [{$issue['project_name']}] Issue Tracking Update: Issue #{$issue['issue_id']} - {$issue['issue_summary']}";
				$message = "An issue you are watching at {$this->settings['site_name']} has been updated.\n\n";
				$message .= "{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}\n";
				$message .= "$notify_message\n\n";

				mail( $notify['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
			}

         $reopen_reason = "REOPEN REQUEST:\n\n{$reopen['reopen_reason']}";

         $stmt = $this->db->prepare_query( 'INSERT INTO %pcomments (comment_user, comment_issue, comment_date, comment_ip, comment_message)
			     VALUES ( ?, ?, ?, ?, ?)' );

         $stmt->bind_param( 'iiiss', $reopen['reopen_user'], $issue['issue_id'], $this->time, $reopen['reopen_ip'], $reopen_reason );
         $this->db->execute_query( $stmt );

         $stmt = $this->db->prepare_query( 'UPDATE %pissues SET issue_comment_count=issue_comment_count+1 WHERE issue_id=?' );

         $stmt->bind_param( 'i', $issue['issue_id'] );
         $this->db->execute_query( $stmt );
         $stmt->close();

         $stmt = $this->db->prepare_query( 'UPDATE %pusers SET user_comment_count=user_comment_count+1 WHERE user_id=?' );

         $stmt->bind_param( 'i', $reopen['reopen_user'] );
         $this->db->execute_query( $stmt );
         $stmt->close();

         $stmt = $this->db->prepare_query( 'SELECT * FROM %pwatching WHERE watch_issue=? AND watch_user=?' );

         $stmt->bind_param( 'ii', $issue['issue_id'], $reopen['reopen_user'] );
         $this->db->execute_query( $stmt );

         $result = $stmt->get_result();
         $watching = $result->fetch_assoc();

         $stmt->close();

         if( !$watching ) {
            $stmt = $this->db->prepare_query( 'INSERT INTO %pwatching (watch_issue, watch_user) VALUES ( ?, ? )' );

            $stmt->bind_param( 'ii', $issue['issue_id'], $reopen['reopen_user'] );
            $this->db->execute_query( $stmt );

            $stmt->close();
         }

			$stmt = $this->db->prepare_query( 'DELETE FROM %preopen WHERE reopen_id=?' );
			$stmt->bind_param( 'i', $reopen['reopen_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Reopen Issue', "Issue {$issue['issue_id']} has been reopened.", 'Continue', 'index.php?a=reopen' );
		}

		$index_template->assign( 'site_name', $issue['project_name'] );
		$this->projectid = $issue['project_id'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/reopen_view.xtpl' );

		$older = null;
		$newer = null;
		$next_issue = null;
		$prev_issue = null;

		$stmt = $this->db->prepare_query( 'SELECT reopen_issue FROM %preopen WHERE reopen_date > ? ORDER BY reopen_date ASC LIMIT 1' );

		$stmt->bind_param( 'i', $reopen['reopen_date'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$next_issue = $result->fetch_assoc();

		$stmt->close();

		if( $next_issue ) {
			$new_sub_link = "{$this->settings['site_address']}index.php?a=reopen&amp;i={$next_issue['reopen_issue']}";
			$new_sub = 'Next Request';
			$newer = "<a href=\"$new_sub_link\">$new_sub</a> &raquo;";
		}

		$stmt = $this->db->prepare_query( 'SELECT reopen_issue FROM %preopen WHERE reopen_date < ? ORDER BY reopen_date DESC LIMIT 1' );

		$stmt->bind_param( 'i', $reopen['reopen_date'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$prev_issue = $result->fetch_assoc();

		$stmt->close();

		if( $prev_issue ) {
			$new_sub_link = "{$this->settings['site_address']}index.php?a=reopen&amp;i={$prev_issue['reopen_issue']}";
			$new_sub = 'Previous Request';
			$older = "&laquo; <a href=\"$new_sub_link\">$new_sub</a>";
		}

		if( $older || $newer ) {
			$xtpl->assign( 'older', $older );
			$xtpl->assign( 'newer', $newer );

			$xtpl->parse( 'ReopenPost.NavLinks' );
		}

		$xtpl->assign( 'id', $issue['issue_id'] );

		$summary = htmlspecialchars($issue['issue_summary']);
		$xtpl->assign( 'summary', $summary );
		$xtpl->assign( 'restricted', ($issue['issue_flags'] & ISSUE_RESTRICTED) ? ' <span style="color:yellow"> [RESTRICTED ENTRY]</span>' : null );

		$xtpl->assign( 'icon', $this->display_icon( $issue ) );

		$text = $this->format( $issue['issue_text'], $issue['issue_flags'] );
		$reason = $this->format( $reopen['reopen_reason'], $issue['issue_flags'] );

		$xtpl->assign( 'text', $text );
		$xtpl->assign( 'reason', $reason );

		$related = null;
		$stmt = $this->db->prepare_query( 'SELECT * FROM %prelated WHERE related_this=? ORDER BY related_other ASC' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$stmt->close();

      $related_query = $this->db->prepare_query( 'SELECT issue_summary, issue_flags FROM %pissues WHERE issue_id=?' );
      $related_query->bind_param( 'i', $related_other );

		while( $row = $this->db->assoc( $result ) )
		{
			$related_other = $row['related_other'];
			$this->db->execute_query( $related_query );

			$o_result = $related_query->get_result();
			$other = $o_result->fetch_assoc();

			if( $other['issue_flags'] & ISSUE_CLOSED )
				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']} [closed]\" style=\"text-decoration:line-through;\">{$row['related_other']}</a>&nbsp;&nbsp;";
			else
				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']}\">{$row['related_other']}</a>&nbsp;&nbsp;";
		}
		$related_query->close();

		if( $related ) {
			$xtpl->assign( 'related', $related );
			$xtpl->parse( 'IssuePost.Related' );
		}

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
			$xtpl->parse( 'ReopenPost.Attachments' );
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

		$xtpl->assign( 'issue_watch', 'N/A' );

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

			$xtpl->parse( 'ReopenPost.EditedBy' );
		}

		$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

		$stmt->bind_param( 'i', $issue['issue_user_closed'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$closed_by = $result->fetch_assoc();

		$stmt->close();

		$xtpl->assign( 'issue_closed_by', $closed_by['user_name'] );
		$xtpl->assign( 'closed_date', $this->t_date( $issue['issue_closed_date'] ) );
		$xtpl->assign( 'issue_resolution', $issue['resolution_name'] );

		$xtpl->parse( 'ReopenPost.Closed' );

		if( !empty( $issue['issue_closed_comment'] ) ) {
			$xtpl->assign( 'issue_closed_comment', $issue['issue_closed_comment'] );
			$xtpl->parse( 'ReopenPost.ClosedComment' );
		}

		$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

		$stmt->bind_param( 'i', $reopen['reopen_user'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$requested_by = $result->fetch_assoc();

		$stmt->close();

		$xtpl->assign( 'requested_by', $requested_by['user_name'] );
		$xtpl->assign( 'request_date', $this->t_date( $reopen['reopen_date'] ) );

		$related = null;
		$stmt = $this->db->prepare_query( 'SELECT * FROM %prelated WHERE related_this=? ORDER BY related_other ASC' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$stmt->close();

      $related_query = $this->db->prepare_query( 'SELECT issue_summary, issue_flags FROM %pissues WHERE issue_id=?' );
      $related_query->bind_param( 'i', $related_other );

		while( $row = $this->db->assoc( $result ) )
		{
			$related_other = $row['related_other'];
			$this->db->execute_query( $related_query );

			$o_result = $related_query->get_result();
			$other = $o_result->fetch_assoc();

			if( $other['issue_flags'] & ISSUE_CLOSED )
				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']} [closed]\" style=\"text-decoration:line-through;\">{$row['related_other']}</a>&nbsp;&nbsp;";
			else
				$related .= "<a href=\"{$this->settings['site_address']}index.php?a=issues&amp;i={$row['related_other']}\" title=\"{$other['issue_summary']}\">{$row['related_other']}</a>&nbsp;&nbsp;";
		}
		$related_query->close();

		if( $related ) {
			$xtpl->assign( 'related', $related );
			$xtpl->parse( 'IssuePost.Related' );
		}

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=reopen&amp;i={$issue['issue_id']}" );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
		$xtpl->assign( 'emojis', $this->bbcode->generate_emoji_links() );

		$xtpl->parse( 'ReopenPost' );
		return $xtpl->text( 'ReopenPost' );
	}

	private function initiate_request( $i, $index_template )
	{
		if( $this->user['user_level'] < USER_MEMBER )
			return $this->error( 404 );

		$errors = array();

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

		if ( !$issue )
			return $this->error( 404 );

		if( $this->user['user_level'] < USER_DEVELOPER ) {
			if( ( ( $issue['issue_flags'] & ISSUE_RESTRICTED ) || ( $issue['issue_flags'] & ISSUE_SPAM ) ) )
				return $this->error( 404 );

			if( $issue['project_retired'] == true )
				return $this->error( 403, $project['project_name'] . ' has been retired. No issue reviews are being accepted for it.' );
		}

		if( $issue['issue_flags'] & ISSUE_REOPEN_RESOLVED )
			return $this->message( 'Reopen Issue', "Issue #{$i} has already had a reopen request denied and will not be elligible for further review." );

		if( $issue['issue_flags'] & ISSUE_REOPEN_REQUEST )
			return $this->message( 'Reopen Issue', "A request to reopen Issue #{$i} already exists." );

		if( !( $issue['issue_flags'] & ISSUE_CLOSED ) )
			return $this->message( 'Reopen Issue', "Issue #{$i} has not been closed yet." );

		if( isset( $this->post['reopen_submit'] ) ) {
			if( !isset( $this->post['reopen_text'] ) || $this->post['reopen_text'] == '' || !is_string( $this->post['reopen_text'] ) ) {
				return $this->message( 'Reopen Issue', 'You must provide a reason for requesting an issue be reopened.' );
			}

			$notify_message = trim( $this->post['reopen_text'] );

			$stmt = $this->db->prepare_query( 'INSERT INTO %preopen (reopen_issue, reopen_project, reopen_user, reopen_date, reopen_reason )
			     VALUES ( ?, ?, ?, ?, ? )' );

			$stmt->bind_param( 'iiiis', $i, $issue['project_id'], $this->user['user_id'], $this->time, $notify_message );
			$this->db->execute_query( $stmt );

			$stmt->close();

			$issue['issue_flags'] |= ISSUE_REOPEN_REQUEST;

			$stmt = $this->db->prepare_query( 'UPDATE %pissues SET issue_flags=? WHERE issue_id=?' );

			$stmt->bind_param( 'ii', $issue['issue_flags'], $i );
			$this->db->execute_query( $stmt );
			$stmt->close();

			$stmt = $this->db->prepare_query( 'SELECT user_id, user_name, user_email FROM %pusers WHERE user_id=?' );

			$stmt->bind_param( 'i', $issue['issue_user_closed'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$notify = $result->fetch_assoc();

			$stmt->close();

			if( $notify ) {
				$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
				$subject = ":: [{$issue['project_name']}] Issue Tracking Update: Issue #{$issue['issue_id']} - {$issue['issue_summary']}";
				$message = "An issue you closed at {$this->settings['site_name']} has a pending request to reopen it.\n\n";
				$message .= "{$this->settings['site_address']}index.php?a=issues&i={$issue['issue_id']}";
				$message .= "\n\n$notify_message\n\n";

				mail( $notify['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
			}

			return $this->message( 'Request Reopen', "Issue #{$i} has been flagged for a reopen request.", 'Continue', "index.php?a=issues&i={$i}" );
		}

		$index_template->assign( 'site_name', $issue['project_name'] );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/reopen_request.xtpl' );

		$xtpl->assign( 'id', $i );
		$xtpl->assign( 'summary', $issue['issue_summary'] );
		$xtpl->assign( 'icon', $this->display_icon( $issue ) );
		$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->skin}" );
		$xtpl->assign( 'restricted', ($issue['issue_flags'] & ISSUE_RESTRICTED) ? ' <span style="color:yellow"> [RESTRICTED ENTRY]</span>' : null );
		$xtpl->assign( 'issue_status', 'Closed' );
		$xtpl->assign( 'issue_type', $issue['type_name'] );
		$xtpl->assign( 'issue_component', $issue['component_name'] );
		$xtpl->assign( 'issue_project', $issue['project_name'] );
		$xtpl->assign( 'issue_category', $issue['category_name'] );

		$text = $this->format( $issue['issue_text'], $issue['issue_flags'] );
		$xtpl->assign( 'text', $text );

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

			$xtpl->parse( 'ReopenPost.EditedBy' );
		}

		$stmt = $this->db->prepare_query( 'SELECT user_name FROM %pusers WHERE user_id=?' );

		$stmt->bind_param( 'i', $issue['issue_user_closed'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$closed_by = $result->fetch_assoc();

		$stmt->close();

		$xtpl->assign( 'issue_closed_by', $closed_by['user_name'] );
		$xtpl->assign( 'closed_date', $this->t_date( $issue['issue_closed_date'] ) );
		$xtpl->assign( 'issue_resolution', $issue['resolution_name'] );

		if( !empty( $issue['issue_closed_comment'] ) ) {
			$xtpl->assign( 'issue_closed_comment', $issue['issue_closed_comment'] );
			$xtpl->parse( 'ReopenPost.ClosedComment' );
		}

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=reopen&amp;s=request&amp;i={$issue['issue_id']}" );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
		$xtpl->assign( 'emojis', $this->bbcode->generate_emoji_links() );

		$has_files = false;
		$file_list = null;

		$stmt = $this->db->prepare_query( 'SELECT * FROM %pattachments WHERE attachment_issue=? AND attachment_comment=0' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$attachments = $stmt->get_result();
		$stmt->close();

		while( $attachment = $this->db->assoc( $attachments ) )
		{
			$has_files = true;

			$file_icon = $this->file_tools->get_file_icon( $attachment['attachment_type'] );

			$file_list .= "<img src=\"{$this->settings['site_address']}skins/{$this->skin}$file_icon\" alt=\"\"> <a href=\"{$this->settings['site_address']}index.php?a=attachments&amp;f={$attachment['attachment_id']}\" rel=\"nofollow\">{$attachment['attachment_name']}</a><br>\n";
		}
		if( $has_files ) {
			$xtpl->assign( 'attached_files', $file_list );
			$xtpl->parse( 'ReopenPost.Attachments' );
		}

		$xtpl->parse( 'ReopenPost' );
		return $xtpl->text( 'ReopenPost' );
	}
}
?>