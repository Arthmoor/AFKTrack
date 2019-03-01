<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') || !defined('AFKTRACK_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class attachments extends module
{
	var $folder_array; // Used to generate folder trees

	function execute()
	{
		static $folder_array = false;
		$this->folder_array = &$folder_array;

		if( isset($this->get['s']) ) {
			switch( $this->get['s'] )
			{
				case 'orphans':		return $this->find_orphans();
				case 'delete':		return $this->delete_attachment();
			}
		}

		if( isset( $this->post['delete_orphans'] ) )
			return $this->delete_orphans();

		return $this->list_attachments();
	}

	function list_attachments()
	{
		$num = $this->settings['site_issuesperpage'];
		if( $this->user['user_issues_page'] > 0 )
			$num = $this->user['user_issues_page'];

		if( isset( $this->get['num'] ) )
			$num = intval( $this->get['num'] );

		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		$stmt = $this->db->prepare( 'SELECT a.*, i.issue_summary, c.comment_message, u.user_name FROM %pattachments a
			LEFT JOIN %pissues i ON i.issue_id=a.attachment_issue
			LEFT JOIN %pcomments c ON c.comment_id=a.attachment_comment
			LEFT JOIN %pusers u ON u.user_id=a.attachment_user
			ORDER BY attachment_id ASC LIMIT ?, ?' );

		$stmt->bind_param( 'ii', $min, $num );

		$this->db->execute_query( $stmt );
		$attachments = $stmt->get_result();
		$stmt->close();

		$total = $this->db->quick_query( 'SELECT COUNT(attachment_id) count FROM %pattachments' );
		$list_total = $total['count'];

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/attachments.xtpl' );
		$xtpl->assign( 'heading', 'File Attachments' );

		while( $attachment = $this->db->assoc( $attachments ) )
		{
			$xtpl->assign( 'delete_link', "<a href=\"admin.php?a=attachments&amp;s=delete&amp;f={$attachment['attachment_id']}\">Delete</a>" );
			$xtpl->assign( 'attachment_user', $attachment['user_name'] );

			if( $attachment['attachment_comment'] > 0 ) {
				if( strlen( $attachment['comment_message'] ) > 100 )
					$text = substr( $attachment['comment_message'], 0, 100 ) . '...';
				else
					$text = $attachment['comment_message'];
				$xtpl->assign( 'attachment_issue', "Comment: <a href=\"index.php?a=issues&amp;i={$attachment['attachment_issue']}&c={$attachment['attachment_comment']}#comment-{$attachment['attachment_comment']}\" target=\"_blank\">$text</a>" );
			} else {
				$xtpl->assign( 'attachment_issue', "Issue: <a href=\"index.php?a=issues&amp;i={$attachment['attachment_issue']}\" target=\"_blank\">{$attachment['issue_summary']}</a>" );
			}
			$xtpl->assign( 'attachment_name', substr( $attachment['attachment_name'], 0, 80 ) );
			$xtpl->assign( 'attachment_filename', $attachment['attachment_filename'] );
			$xtpl->assign( 'attachment_type', $attachment['attachment_type'] );
			$xtpl->assign( 'attachment_size', $attachment['attachment_size'] );
			$xtpl->assign( 'attachment_date', $this->t_date( $attachment['attachment_date'] ) );

			$xtpl->parse( 'Attachments.Entry' );
		}

		$pagelinks = $this->make_links( -2, $list_total, $min, $num, null );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Attachments.PageLinks' );

		$xtpl->parse( 'Attachments' );
		return $xtpl->text( 'Attachments' );
	}

	function delete_orphans()
	{
		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		foreach( $this->post['delete'] as $filename => $true )
		{
			$fname = "{$this->file_dir}$filename";
			@unlink( $fname );
		}

		return $this->message( 'Delete Orphaned Attachments', 'All selected files have been deleted.', 'Continue', 'admin.php?a=attachments&amp;s=orphans' );
	}

	function find_orphans()
	{
		$files = array();

		$dp  = opendir( $this->file_dir );

		while( false !== ( $filename = readdir($dp) ) )
			if( !is_dir( $this->file_dir . $filename ) )
				$files[] = $filename;
		closedir( $dp );

		if( !empty( $files ) )
			sort( $files );
		else
			return $this->message( 'Find Orphaned Attachments', 'No file attachments have been uploaded yet.' );

		$orphans = array();

		foreach( $files as $f )
		{
			if( strstr( $f, '_', true ) == false )
			{
				if( $f != 'index.php' )
					$orphans[] = array( 'filename' => $f, 'issue_id' => 0, 'reason' => 'Orphaned during issue or comment creation. This file is not attached to anything.' );

			} else {
				$id = strstr( $f, '_', true );

				$stmt = $this->db->prepare( 'SELECT issue_id FROM %pissues WHERE issue_id=?' );

				$stmt->bind_param( 'i', $id );

				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$issue = $result->fetch_assoc();

				$stmt->close();

				if( !$issue ) {
					$orphans[] = array( 'filename' => $f, 'issue_id' => $id, 'reason' => "Orphaned from deleted issue #$id." );
				}

				$stmt = $this->db->prepare( 'SELECT attachment_issue FROM %pattachments WHERE attachment_issue=?' );

				$stmt->bind_param( 'i', $id );

				$this->db->execute_query( $stmt );

				$result = $stmt->get_result();
				$attachment = $result->fetch_assoc();

				$stmt->close();

				if( !$attachment ) {
					$orphans[] = array( 'filename' => $f, 'issue_id' => $id, 'reason' => "Orphaned from deleted attachment record." );
				}
			}
		}

		if( !empty( $orphans ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/attachments.xtpl' );

			$xtpl->assign( 'action_link', 'admin.php?a=attachments' );
			$xtpl->assign( 'token', $this->generate_token() );

			foreach( $orphans as $o )
			{
				$xtpl->assign( 'orphan_filename', $o['filename'] );
				$xtpl->assign( 'orphan_link', "<a href=\"{$this->settings['site_address']}{$this->file_dir}{$o['filename']}\">{$o['filename']}</a>" );

				$xtpl->assign( 'orphan_issue', $o['reason'] );

				$xtpl->parse( 'Attachments.Orphans.OrphanEntry' );
			}

			$xtpl->parse( 'Attachments.Orphans' );
			return $xtpl->text ( 'Attachments.Orphans' );
		}

		return $this->message( 'Find Orphaned Attachments', 'There are no orphaned attachments.' );
	}
}
?>