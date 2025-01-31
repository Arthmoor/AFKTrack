<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2025 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class attachments extends module
{
	public function execute()
	{
		if( !isset( $this->get['f'] ) )
			return $this->error( 404 );

		$file = intval( $this->get['f'] );

		$stmt = $this->db->prepare_query( 'SELECT * FROM %pattachments WHERE attachment_id=?' );

		$stmt->bind_param( 'i', $file );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$attachment = $result->fetch_assoc();

		$stmt->close();

		if( !$attachment )
			return $this->error( 404 );

		$stmt = $this->db->prepare_query( 'SELECT issue_flags FROM %pissues WHERE issue_id=?' );

		$stmt->bind_param( 'i', $attachment['attachment_issue'] );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$issue = $result->fetch_assoc();

		$stmt->close();

		if( $this->user['user_level'] < USER_DEVELOPER && ( ( $issue['issue_flags'] & ISSUE_RESTRICTED ) || ( $issue['issue_flags'] & ISSUE_SPAM ) ) )
			return $this->error( 404 );

		$this->nohtml = true;

		// Need to terminate and unlock the session at this point or the site will stall for the current user.
		session_write_close();

		ini_set( "zlib.output_compression", "Off" );
		header( 'Connection: close' );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( "Content-Disposition: attachment; filename=\"{$attachment['attachment_name']}\"" );
		header( 'Content-Length: ' . $attachment['attachment_size'] );
		header( 'X-Robots-Tag: noarchive, nosnippet, noindex' );

		// directly pass through file to output buffer
		@readfile ( $this->file_dir . $attachment['attachment_filename'] );
	}
}
?>