<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class comments
{
	public function __construct(&$module)
	{
		$this->module = &$module;
		$this->user = &$module->user;
		$this->db = &$module->db;
		$this->settings = &$module->settings;
	}

	public function generate_comment_form( $author, $subject, $action_link, $closed, $message = null )
	{
		$xtpl = new XTemplate( './skins/' . $this->module->skin . '/comment_form.xtpl' );

		if( $closed ) {
			$xtpl->parse( 'CommentForm.Closed' );
			return $xtpl->text( 'CommentForm.Closed' );
		}
		$xtpl->assign( 'action_link', $action_link );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'author', $author );
		$xtpl->assign( 'subject', $subject );

		if( $this->user['user_level'] <= USER_MEMBER )
			$xtpl->parse( 'CommentForm.SpamControl' );

		$xtpl->assign( 'message', $message );
		$xtpl->assign( 'emoticons', $this->module->bbcode->generate_emote_links() );
		$xtpl->assign( 'bbcode_menu', $this->module->bbcode->get_bbcode_menu() );

		$xtpl->parse( 'CommentForm' );
		return $xtpl->text( 'CommentForm' );
	}

	public function list_comments( $p, $subject, $u, $count, $min, $num, $link )
	{
		$stmt = $this->db->prepare( '
			SELECT c.comment_id, c.comment_date, c.comment_editdate, c.comment_editedby, c.comment_user, c.comment_message, c.comment_ip,
				u.user_id, u.user_name, u.user_icon
			  FROM %pcomments c
			  LEFT JOIN %pusers u ON u.user_id=c.comment_user
			 WHERE comment_issue=? ORDER BY comment_date LIMIT ?, ?' );

		$stmt->bind_param( 'iii', $p, $min, $num );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$stmt->close();

		if( !$result )
			return '';

		$xtpl = new XTemplate( './skins/' . $this->module->skin . '/comment_view.xtpl' );

		$pos = $min + 1;
		while ( $comment = $this->db->assoc($result) )
		{
			$icon = $this->module->display_icon( $comment['user_icon'] );
			$xtpl->assign( 'icon', $icon );

			$cid = $comment['comment_id'];
			$clink = $link . '&amp;c=' . $cid . '#comment-' . $cid;
			$xtpl->assign( 'link', $clink );
			$xtpl->assign( 'cid', $cid );

			$author = htmlspecialchars( $comment['user_name'] );
			$xtpl->assign( 'author', $author );

			$params = ISSUE_BBCODE | ISSUE_EMOTICONS;
			$xtpl->assign( 'message', $this->module->format( $comment['comment_message'], $params ) );

			$date = $this->module->t_date( $comment['comment_date'] );
			$date = 'Comment #' . $pos . ' ' . $date;
			$xtpl->assign( 'date', $date );

			$edited = null;
			if( $comment['comment_editdate'] > 0 ) {
				$xtpl->assign( 'editdate', $this->module->t_date( $comment['comment_editdate'] ) );

				$stmt = $this->db->prepare( 'SELECT user_name FROM %pusers WHERE user_id=?' );

				$stmt->bind_param( 'i', $comment['comment_editedby'] );
				$this->db->execute_query( $stmt );

				$u_result = $stmt->get_result();
				$edit_user = $u_result->fetch_assoc();

				$stmt->close();

				$xtpl->assign( 'editedby', $edit_user['user_name'] );

				$xtpl->parse( 'CList.Comment.EditedBy' );
			}

			$mod_controls = null;
			if( $this->user['user_level'] >= USER_DEVELOPER )
				$mod_controls = '&nbsp;<div class="mod_controls">[ ' . $comment['comment_ip'] . ' ] | [ <a href="' . $this->settings['site_address'] . 'index.php?a=issues&amp;s=edit_comment&amp;c=' . $cid . '">Edit</a> ] | [ <a href="' . $this->settings['site_address'] . 'index.php?a=issues&amp;s=del_comment&amp;c=' . $cid . '">Delete</a> ] | [ <a href="' . $this->settings['site_address'] . 'index.php?a=issues&amp;s=del_comment&amp;t=spam&amp;c=' . $cid . '">Spam</a> ]</div>';
			elseif( $this->user['user_level'] == USER_MEMBER && $this->user['user_id'] == $comment['comment_user'] && $this->module->time - $comment['comment_date'] <= 1800 )
				$mod_controls = '&nbsp;<div class="mod_controls">[ <a href="' . $this->settings['site_address'] . 'index.php?a=issues&amp;s=edit_comment&amp;c=' . $cid . '">Edit</a> ]</div>';
			else
				$mod_controls = null;

			$has_files = false;
			$file_list = null;

			$stmt = $this->db->prepare( 'SELECT * FROM %pattachments WHERE attachment_comment=?' );

			$stmt->bind_param( 'i', $comment['comment_id'] );
			$this->db->execute_query( $stmt );

			$attachments = $stmt->get_result();
			$stmt->close();

			while( $attachment = $this->db->assoc($attachments) )
			{
				$has_files = true;

				$file_icon = $this->module->get_file_icon( $attachment['attachment_type'] );

				$file_list .= "<img src=\"{$this->settings['site_address']}skins/{$this->module->skin}$file_icon\" alt=\"\" /> <a href=\"{$this->settings['site_address']}index.php?a=attachments&amp;f={$attachment['attachment_id']}\" rel=\"nofollow\">{$attachment['attachment_name']}</a><br />\n";
			}
			if( $has_files ) {
				$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->module->skin}" );
				$xtpl->assign( 'attached_comment_files', $file_list );
				$xtpl->parse( 'CList.Comment.Attachments' );
			}

			$xtpl->assign( 'mod_controls', $mod_controls );

			$xtpl->parse( 'CList.Comment' );
			$pos++;
		}

		$pagelinks = $this->make_links( $p, $subject, $count, $min, $num );
		$xtpl->assign( 'pagelinks', $pagelinks );

		$xtpl->parse( 'CList' );
		return $xtpl->text( 'CList' );
	}

	public function post_comment( $issue )
	{
		$uid = $this->user['user_id'];
		$com_time = $this->module->time;
		$ip = $this->module->ip;
		$author = '';
		$return_data = array();

		if( isset( $this->module->post['preview'] ) || isset( $this->module->post['attach'] ) || isset( $this->module->post['detach'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->module->skin . '/comment_preview.xtpl' );

			$xtpl->assign( 'icon', $this->module->display_icon( $this->user['user_icon'] ) );

			$xtpl->assign( 'date', $this->module->t_date( $this->module->time ) );
			$xtpl->assign( 'subject', $issue['issue_summary'] );

			$text = null;
			$message = null;
			if( isset($this->module->post['comment_message']) ) {
				$params = ISSUE_BBCODE | ISSUE_EMOTICONS;
				$text = $this->module->format( $this->module->post['comment_message'], $params );
				$message = htmlspecialchars($this->module->post['comment_message']);
			}
			$xtpl->assign( 'text', $text );
			$xtpl->assign( 'message', $message );

			if( $this->user['user_level'] <= USER_MEMBER )
				$xtpl->parse( 'Comment.SpamControl' );

			$xtpl->assign( 'author', htmlspecialchars( $this->user['user_name']) );

			$action_link = "{$this->settings['site_address']}index.php?a=issues&amp;i={$issue['issue_id']}#newcomment";

			$xtpl->assign( 'action_link', $action_link );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'emoticons', $this->module->bbcode->generate_emote_links() );
			$xtpl->assign( 'bbcode_menu', $this->module->bbcode->get_bbcode_menu() );

			// Time to deal with icky file attachments.
			$attached = null;
			$attached_data = null;
			$upload_status = null;

			if( !isset( $this->module->post['attached_data'] ) ) {
				$this->module->post['attached_data'] = array();
			}

			if( isset( $this->module->post['attach'] ) ) {
				$upload_status = $this->attach_file( $this->module->files['attach_upload'], $this->module->post['attached_data'] );
			}

			if( isset( $this->module->post['detach'] ) ) {
				$this->delete_attachment( $this->module->post['attached'], $this->module->post['attached_data'] );
			}

			$this->make_attached_options( $attached, $attached_data, $this->module->post['attached_data'] );

			if( $attached != null ) {
				$xtpl->assign( 'attached_files', $attached );
				$xtpl->assign( 'attached_data', $attached_data );

				$xtpl->parse( 'Comment.AttachedFiles' );
			}

			$xtpl->assign( 'upload_status', $upload_status );

			$xtpl->parse( 'Comment' );
			return $xtpl->text( 'Comment' );
		}

		$author = $this->user['user_name'];

		if (!isset($this->module->post['comment_message']) || empty($this->module->post['comment_message']) )
			return $this->module->error( 'You cannot post an empty comment!' );

		$message = $this->module->post['comment_message'];

		// I'm not sure if the anti-spam code needs to use the escaped strings or not, so I'll feed them whatever the spammer fed me.
		require_once( 'lib/akismet.php' );
		$spam_checked = false;
		$akismet = null;

		if( !empty( $this->settings['wordpress_api_key'] ) ) {
			if( $this->user['user_level'] < USER_DEVELOPER ) {
				try {
					$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->module->version);

					$akismet->setCommentAuthor($author);

					if( isset($this->module->post['email']) && !empty($this->module->post['email']) )
						$akismet->setCommentAuthorEmail($this->module->post['email']);
					else
						$akismet->setCommentAuthorEmail( '' );

					if( isset($this->module->post['url']) && !empty($this->module->post['url']) )
						$akismet->setCommentAuthorURL($this->module->post['url']);
					else
						$akismet->setCommentAuthorURL( '' );

					$akismet->setCommentContent($this->module->post['comment_message']);
					$akismet->setCommentType('comment');

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

				$stmt = $this->db->prepare( 'INSERT INTO %pspam (spam_issue, spam_user, spam_comment, spam_date, spam_type, spam_ip, spam_server)
				   VALUES (?, ?, ?, ?, ?, ?, ?)' );

				$type = SPAM_COMMENT;
				$stmt->bind_param( 'iisiiss', $issue['issue_id'], $uid, $message, $com_time, $type, $ip, $svars );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$this->settings['spam_count']++;
				$this->module->save_settings();
				$this->purge_old_spam();
				return $this->module->message( 'Akismet Warning', 'Your comment has been flagged as potential spam and must be evaluated by the administration.' );
			}
		}

		$stmt = $this->db->prepare( 'INSERT INTO %pcomments (comment_user, comment_issue, comment_date, comment_ip, comment_message, comment_referrer, comment_agent)
			     VALUES ( ?, ?, ?, ?, ?, ?, ?)' );

		$stmt->bind_param( 'iiissss', $uid, $issue['issue_id'], $com_time, $this->module->ip, $message, $this->module->referrer, $this->module->agent );
		$this->db->execute_query( $stmt );

		$cid = $this->db->insert_id();
		$stmt->close();

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_comment_count=issue_comment_count+1 WHERE issue_id=?' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'UPDATE %pusers SET user_comment_count=user_comment_count+1 WHERE user_id=?' );

		$stmt->bind_param( 'i', $this->user['user_id'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		if( isset( $this->module->post['attached_data'] ) ) {
			$this->attach_files_db( $issue['issue_id'], $cid, $this->module->post['attached_data'] );
		}

		// You posted a comment. Therefore you'll be added to the watch list for the issue if you aren't already on it.
		if( isset( $this->module->post['comment_notify'] ) ) {
			$stmt = $this->db->prepare( 'SELECT w.* FROM %pwatching w WHERE watch_issue=? AND watch_user=?' );

			$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
			$this->db->execute_query( $stmt );

			$watch_list = $stmt->get_result();
			$watching = $watch_list->fetch_assoc();
			$stmt->close();

			if( !$watching ) {
				$stmt = $this->db->prepare( 'INSERT INTO %pwatching (watch_issue, watch_user) VALUES ( ?, ? )' );

				$stmt->bind_param( 'ii', $issue['issue_id'], $this->user['user_id'] );
				$this->db->execute_query( $stmt );

				$stmt->close();	
			}
		}

		// Notify all users watching the issue that a new comment has been posted.
		$stmt = $this->db->prepare( 'SELECT w.*, u.user_id, u.user_name, u.user_email FROM %pwatching w
			LEFT JOIN %pusers u ON u.user_id=w.watch_user WHERE watch_issue=?' );

		$stmt->bind_param( 'i', $issue['issue_id'] );
		$this->db->execute_query( $stmt );

		$notify_list = $stmt->get_result();
		$stmt->close();

		if( $notify_list ) {
			// Tack on the full comment.
			$notify_message = "A new comment was posted:\n\n";
			$notify_message .= $message;

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
		return $cid; // Returns the comment ID so the originating page can header to it immediately.
	}

	public function edit_comment()
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_MEMBER )
			return $this->module->error( 'Access Denied: You do not have permission to perform that action.', 403 );

		if( !isset($this->module->get['c']) )
			return $this->module->message( 'Edit Comment', 'No comment was specified for editing.' );

		$c = intval($this->module->get['c']);

		$stmt = $this->db->prepare( 'SELECT c.*, u.* FROM %pcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user	WHERE comment_id=?' );

		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$comment = $result->fetch_assoc();

		$stmt->close();

		if( !$comment )
			return $this->module->message( 'Edit Comment', 'No such comment was found for editing.' );

		if( $this->user['user_id'] != $comment['comment_user'] && $this->user['user_level'] < USER_DEVELOPER )
			return $this->module->error( 'Access Denied: You do not own the comment you are attempting to edit.', 403 );

		// After 3 hours, you're stuck with it if you're a regular member.
		if( $this->user['user_level'] == USER_MEMBER && $this->module->time - $comment['comment_date'] > 10800 )
			return $this->module->error( 'Access Denied: You cannot edit your comments after 3 hours have gone by.', 403 );

		$xtpl = new XTemplate( './skins/' . $this->module->skin . '/comment_edit.xtpl' );

		if( !isset( $this->module->post['submit'] ) || isset( $this->post['attach'] ) || isset( $this->post['detach'] ) ) {
			$xtpl->assign( 'author', htmlspecialchars($comment['user_name']) );

			$message = null;
			$text = null;
			$params = ISSUE_BBCODE | ISSUE_EMOTICONS;
			if( isset( $this->module->post['post_text'] ) ) {
				$text = $this->module->post['post_text'];
				$message = $this->module->format( $this->module->post['post_text'], $params );
			} else {
				$text = $comment['comment_message'];
				$message = $this->module->format( $comment['comment_message'], $params );
			}
			$xtpl->assign( 'text', htmlspecialchars($text) );

			$xtpl->assign( 'emoticons', $this->module->bbcode->generate_emote_links() );
			$xtpl->assign( 'bbcode_menu', $this->module->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=issues&amp;s=edit_comment&amp;c=$c" );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );

			if( isset($this->module->post['preview']) ) {
				$xtpl->assign( 'icon', $this->module->icon_dir . $comment['user_icon'] );
				$xtpl->assign( 'date', $this->module->t_date( $comment['comment_date'] ) );
				$xtpl->assign( 'message', $message );

				$xtpl->parse( 'Comment.Preview' );
			}

			// Time to deal with icky file attachments.
			$attached = null;
			$attached_data = null;
			$upload_status = null;

			if( !isset( $this->module->post['attached_data'] ) ) {
				$this->module->post['attached_data'] = array();
			}

			if( isset( $this->module->post['attach'] ) ) {
				$upload_status = $this->attach_file( $this->module->files['attach_upload'], $this->module->post['attached_data'] );
			}

			if( isset( $this->module->post['detach'] ) ) {
				$this->delete_attachment( $this->module->post['attached'], $this->module->post['attached_data'] );
			}

			$this->make_attached_options( $attached, $attached_data, $this->module->post['attached_data'] );

			if( $attached != null ) {
				$xtpl->assign( 'attached_files', $attached );
				$xtpl->assign( 'attached_data', $attached_data );

				$xtpl->parse( 'Comment.AttachedFiles' );
			}

			$existing_files = null;
			$stmt = $this->db->prepare( 'SELECT attachment_id, attachment_name, attachment_filename FROM %pattachments WHERE attachment_comment=?' );

			$stmt->bind_param( 'i', $comment['comment_id'] );
			$this->db->execute_query( $stmt );

			$attachments = $stmt->get_result();
			$stmt->close();

			while( $row = $this->db->assoc($attachments) )
			{
				$existing_files .= "<input type=\"checkbox\" name=\"file_array[]\" value=\"{$row['attachment_id']}\" /> Delete Attachment - {$row['attachment_name']}<br />\n";
			}
			$xtpl->assign( 'existing_comment_attachments', $existing_files );
			$xtpl->assign( 'upload_status', $upload_status );

			$xtpl->parse( 'Comment' );
			return $xtpl->text( 'Comment' );
		}

		if (!isset($this->module->post['post_text']) || empty($this->module->post['post_text']) )
			return $this->module->error( 'You cannot post an empty comment!' );

		$text = $this->module->post['post_text'];

		$stmt = $this->db->prepare( 'UPDATE %pcomments SET comment_editdate=?, comment_editedby=?, comment_message=? WHERE comment_id=?' );

		$stmt->bind_param( 'iisi', $this->module->time, $this->user['user_id'], $text, $c );
		$this->db->execute_query( $stmt );
		$stmt->close();

		if( isset( $this->module->post['attached_data'] ) ) {
			$this->attach_files_db( $comment['comment_issue'], $comment['comment_id'], $this->module->post['attached_data'] );
		}

		// Delete attachments selected for removal
		if( isset( $this->module->post['file_array'] ) ) {
			foreach( $this->module->post['file_array'] as $fileval )
			{
				$file = intval($fileval);

				$stmt = $this->db->prepare( 'SELECT attachment_filename FROM %pattachments WHERE attachment_id=?' );

				$stmt->bind_param( 'i', $file );
				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$attachment = $result->fetch_assoc();

				$stmt->close();

				@unlink( $this->module->file_dir . $attachment['attachment_filename'] );

				$stmt = $this->db->prepare( 'DELETE FROM %pattachments WHERE attachment_id=?' );
				$stmt->bind_param( 'i', $file );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}
		}

		if( isset( $this->module->post['request_uri'] ) ) {
			header( 'Location: ' . $this->module->post['request_uri'] );
		} else {
			$link = "{$this->settings['site_address']}index.php?a=issues&i={$comment['comment_issue']}&c=$c#comment-$c";
			header( 'Location: ' . $link );
		}
	}

	public function delete_comment()
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_DEVELOPER )
			return $this->module->error( 'Access Denied: You do not have permission to perform that action.', 403 );

		if( !isset($this->module->get['c']) )
			return $this->module->message( 'Delete Comment', 'No comment was specified for editing.' );

		$c = intval($this->module->get['c']);

		$stmt = $this->db->prepare( 'SELECT c.*, u.* FROM %pcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user	WHERE comment_id=?' );

		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$comment = $result->fetch_assoc();

		$stmt->close();

		if( !$comment )
			return $this->module->message( 'Delete Comment', 'No such comment was found for deletion.' );

		if( !isset($this->module->get['confirm']) ) {
			$author = htmlspecialchars($comment['user_name']);
			$params = ISSUE_BBCODE | ISSUE_EMOTICONS;
			$text = $this->module->format( $comment['comment_message'], $params );
			$date = $this->module->t_date( $comment['comment_date'] );

			$msg = "<div class=\"title\">Comment by {$author} Posted on: {$date}</div><div class=\"article\">{$text}</div>";
			$link = "index.php?a=issues&amp;s=del_comment&amp;c=$c&amp;confirm=1";
			$sp = null;
			if( isset($this->module->get['t']) && $this->module->get['t'] == 'spam' ) {
				$link .= '&amp;t=spam';
				$sp = '<br />This comment will be reported as spam.';
			}
			$msg .= "<div class=\"title\" style=\"text-align:center\">Are you sure you want to delete this comment?$sp</div>";

			return $this->module->message( 'DELETE COMMENT', $msg, 'Delete', $link, 0 );
		}

		$out = null;

		if( isset($this->module->get['t']) && $this->module->get['t'] == 'spam' ) {
			// Time to report the spammer before we delete the comment. Hopefully this is enough info to strike back with.
			require_once( 'lib/akismet.php' );
			$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->module->version);
			$akismet->setCommentAuthor($comment['user_name']);
			$akismet->setCommentContent($comment['comment_message']);
			$akismet->setUserIP($comment['comment_ip']);
			$akismet->setReferrer($comment['comment_referrer']);
			$akismet->setCommentUserAgent($comment['comment_agent']);
			$akismet->setCommentType('comment');

			$akismet->submitSpam();

			$this->settings['spam_count']++;
			$this->settings['spam_uncaught']++;
			$this->module->save_settings();

			$out .= 'Comment tagged as spam and reported.<br />';
		}

		$stmt = $this->db->prepare( 'SELECT attachment_filename FROM %pattachments WHERE attachment_comment=?' );

		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );

		$c_attachments = $stmt->get_result();
		$stmt->close();

		while( $c_attachment = $this->db->assoc($c_attachments) )
		{
			@unlink( $this->module->file_dir . $c_attachment['attachment_filename'] );
		}

		$stmt = $this->db->prepare( 'DELETE FROM %pattachments WHERE attachment_comment=?' );
		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'UPDATE %pusers SET user_comment_count=user_comment_count-1 WHERE user_id=?' );
		$stmt->bind_param( 'i', $comment['comment_user'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pcomments WHERE comment_id=?' );

		$stmt->bind_param( 'i', $c );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_comment_count=issue_comment_count-1 WHERE issue_id=?' );

		$stmt->bind_param( 'i', $comment['comment_issue'] );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$out .= 'Comment has been deleted.';

		return $this->module->message( 'Delete Comment', $out, 'Continue', "index.php?a=issues&i={$comment['comment_issue']}" );
	}

	private function make_links( $p, $subject, $count, $min, $num )
	{
		if( $num < 1 ) $num = 1; // No more division by zero please.

		$current = ceil( $min / $num );
		$string  = null;
		$pages   = ceil( $count / $num );
		$end     = ($pages - 1) * $num;
		$link = '';

		$link = "{$this->settings['site_address']}index.php?a=issues&amp;i=$p";

		// check if there's previous articles
		if($min == 0) {
			$startlink = '&lt;&lt;';
			$previouslink = '';
		} else {
			$startlink = "<a href=\"$link&amp;min=0&amp;num=$num#comments\">&lt;&lt;</a>";
			$prev = $min - $num;
			$previouslink = "<a href=\"$link&amp;min=$prev&amp;num=$num#comments\">prev</a> ";
		}

		// check for next/end
		if(($min + $num) < $count) {
			$next = $min + $num;
  			$nextlink = "<a href=\"$link&amp;min=$next&amp;num=$num#comments\">next</a>";
  			$endlink = "<a href=\"$link&amp;min=$end&amp;num=$num#comments\">&gt;&gt;</a>";
		} else {
  			$nextlink = '';
  			$endlink = '&gt;&gt;';
		}

		// setup references
		$b = $current - 2;
		$e = $current + 2;

		// set end and beginning of loop
		if ($b < 0) {
  			$e = $e - $b;
  			$b = 0;
		}

		// check that end coheres to the issues
		if ($e > $pages - 1) {
  			$b = $b - ($e - $pages + 1);
  			$e = ($pages - 1 < $current) ? $pages : $pages - 1;
  			// b may need adjusting again
  			if ($b < 0) {
				$b = 0;
			}
		}

 		// ellipses
		if ($b != 0) {
			$badd = '...';
		} else {
			$badd = '';
		}

		if (($e != $pages - 1) && $count) {
			$eadd = '...';
		} else {
			$eadd = '';
		}

		// run loop for numbers to the page
		for ($i = $b; $i < $current; $i++)
		{
			$where = $num * $i;
			$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num#comments\">" . ($i + 1) . '</a>';
		}

		// add in page
		$string .= ', <strong>' . ($current + 1) . '</strong>';

		// run to the end
		for ($i = $current + 1; $i <= $e; $i++)
		{
			$where = $num * $i;
			$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num#comments\">" . ($i + 1) . '</a>';
		}

		// get rid of preliminary comma.
		if (substr($string, 0, 1) == ',') {
			$string = substr($string, 1);
		}

		if( $pages == 1 ) {
			$string = '';
			$startlink = '';
			$previouslink = '';
			$nextlink = '';
			$endlink = '';
		}

		$newmin = $min + 1;
		$newnum = $min + $num;

		if( $num > $count )
			$newnum = $count;

		if( $min + $num > $count )
			$newnum = $count;

		$showing = "Showing Comments $newmin - $newnum of $count ";

		return "$showing $startlink $previouslink $badd $string $eadd $nextlink $endlink";
	}

	// Stuff for attaching files to issues.
	function attach_file( &$file, &$attached_data )
	{
		$upload_error = null; // Null is no error

		if( !isset($file) ) {
			$upload_error = 'The attachment upload failed. The file you specified may not exist.';
		} else {
			$md5 = md5( $file['name'] . microtime() );

			$ret = $this->upload( $file, $this->module->file_dir . $md5, $this->settings['attachment_size_limit_mb'], $this->settings['attachment_types_allowed'] );

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
		@unlink( $this->module->file_dir . $filename );
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

	function attach_files_db( $issue_id, $comment_id, $attached_data )
	{
		foreach( $attached_data as $md5 => $filename )
		{
			$renamed = $issue_id . '_' . $md5;
			rename( $this->module->file_dir . $md5, $this->module->file_dir . $renamed );

			$temp = explode( '.', $filename );
			$ext = strtolower( end( $temp ) );

			$stmt = $this->db->prepare( 'INSERT INTO %pattachments( attachment_issue, attachment_comment, attachment_name, attachment_filename, attachment_type, attachment_size, attachment_user, attachment_date ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ? )' );

			$size = filesize( $this->module->file_dir . $renamed );
			$stmt->bind_param( 'iisssiii', $issue_id, $comment_id, $filename, $renamed, $ext, $size, $this->user['user_id'], $this->module->time );
			$this->db->execute_query( $stmt );
			$stmt->close();
		}
	}

	// Automatically drop spam posts in the spam DB that are older than 30 days.
	private function purge_old_spam()
	{
		$diff = 2592000; // 30 days * 86400 secs
		$cut_off = $this->module->time - $diff;
		$this->db->dbquery( 'DELETE FROM %pspam WHERE spam_date <= %d', $cut_off );
	}
}
?>