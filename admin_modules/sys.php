<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2020 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class sys extends module
{
	public function execute()
	{
		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'phpinfo':
					$this->nohtml = true;
					return phpinfo();
				case 'sql':		return $this->perform_sql();
				case 'stats':		return $this->display_stats();
				case 'optimize':	return $this->opt_tables();
				case 'repair':		return $this->repair_tables();
				case 'recount':		return $this->recount_all();
				case 'backup':		return $this->db_backup();
				case 'restore':		return $this->db_restore();
				case 'keytest':		return $this->test_akismet_key();
				case 'prunewatchlists': return $this->prune_watchlists();
			}
		}
		return $this->display_stats();
	}

	// Counts all comment entries and resets the counters on each issue.
	// Should not be needed unless you are manually removing entries through another database interface.
	private function recount_all()
	{
		$issues = $this->db->dbquery( 'SELECT issue_id FROM %pissues' );

		while( $row = $this->db->assoc( $issues ) )
		{
			$stmt = $this->db->prepare( 'SELECT COUNT(comment_id) count FROM %pcomments WHERE comment_issue=?' );

			$stmt->bind_param( 'i', $row['issue_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$comments = $result->fetch_assoc();

			$stmt->close();

			if( $comments['count'] && $comments['count'] > 0 ) {
				$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_comment_count=? WHERE issue_id=?' );

				$stmt->bind_param( 'ii', $comments['count'], $row['issue_id'] );
				$this->db->execute_query( $stmt );
				$stmt->close();
			} else {
				$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_comment_count=0 WHERE issue_id=?' );

				$stmt->bind_param( 'i', $row['issue_id'] );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}
		}

		$users = $this->db->dbquery( 'SELECT user_id, user_issue_count, user_comment_count FROM %pusers' );

		while( $row = $this->db->assoc( $users ) )
		{
			$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE issue_user=?' );

			$stmt->bind_param( 'i', $row['user_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$c_issues = $result->fetch_assoc();

			$stmt->close();

			$issue_total = $c_issues['count'];

			$stmt = $this->db->prepare( 'UPDATE %pusers SET user_issue_count=? WHERE user_id=?' );

			$stmt->bind_param( 'ii', $issue_total, $row['user_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();

			$stmt = $this->db->prepare( 'SELECT COUNT(comment_id) count FROM %pcomments WHERE comment_user=?' );

			$stmt->bind_param( 'i', $row['user_id'] );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$c_comments = $result->fetch_assoc();

			$stmt->close();

			$comment_total = $c_comments['count'];

			$stmt = $this->db->prepare( 'UPDATE %pusers SET user_comment_count=? WHERE user_id=?' );

			$stmt->bind_param( 'ii', $comment_total, $row['user_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();
		}

		$issue_count = $this->db->quick_query( 'SELECT COUNT(issue_id) count FROM %pissues' );
		$user_count = $this->db->quick_query( 'SELECT COUNT(user_id) count FROM %pusers' );

		$this->settings['total_issues'] = $issue_count['count'];
		$this->settings['user_count'] = $user_count['count'];

		$this->save_settings();

		return $this->message( 'Recount Stats', 'All statistical counts have been corrected.', 'Continue', 'admin.php' );
	}

	/**
	 * Grabs the current list of table names in the database
	 *
	 * @author Roger Libiez [Arthmoor]
	 * @since 1.0
	 * @return array
	 **/
	private function get_db_tables()
	{
		$tarray = array();

		// This looks a bit strange, but it will pull all of the proper prefixed tables.
		$tb = $this->db->dbquery( "SHOW TABLES LIKE '%p%%'" );

		while( $tb1 = $this->db->assoc( $tb ) )
		{
			foreach( $tb1 as $col => $data )
				$tarray[] = $data;
		}

		return $tarray;
	}

	private function repair_tables()
	{
		$tables = implode( ', ', $this->get_db_tables() );

		$result = $this->db->repair( $tables );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/database.xtpl' );

		$xtpl->assign( 'header_text', 'Repair Database' );

		while( $row = $this->db->assoc( $result ) )
		{
			foreach( $row as $col => $data )
			{
				$xtpl->assign( 'table_row_entry', htmlspecialchars( $data ) );
				$xtpl->parse( 'Database.Row.Entry' );
			}
			$xtpl->parse( 'Database.Row' );
		}

		$xtpl->parse( 'Database' );
		return $xtpl->text( 'Database' );
	}

	private function opt_tables()
	{
		$this->title( 'Optimize Database' );

		$tables = implode( ', ', $this->get_db_tables() );

		$result = $this->db->optimize( $tables );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/database.xtpl' );

		$xtpl->assign( 'header_text', 'Optimize Database' );

		while( $row = $this->db->assoc( $result ) )
		{
			foreach( $row as $col => $data )
			{
				$xtpl->assign( 'table_row_entry', htmlspecialchars( $data ) );
				$xtpl->parse( 'Database.Row.Entry' );
			}
			$xtpl->parse( 'Database.Row' );
		}

		$xtpl->parse( 'Database' );
		return $xtpl->text( 'Database' );
	}

	private function display_stats()
	{
		$comment = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pcomments' );
		$spam = isset( $this->settings['spam_count'] ) ? $this->settings['spam_count'] : 0;
		$uspam = isset( $this->settings['register_spam_count'] ) ? $this->settings['register_spam_count'] : 0;
		$ham = isset( $this->settings['ham_count'] ) ? $this->settings['ham_count'] : 0;
		$false_neg = isset( $this->settings['spam_uncaught'] ) ? $this->settings['spam_uncaught'] : 0;

		$total_comments = $comment['count'] + $spam;
		$pct_spam = null;

		if( $total_comments > 0 && $spam > 0 ) {
			$percent = floor( ( $spam / $total_comments ) * 100 );

			$pct_spam = ", {$percent}";
		}

		$active = $this->db->dbquery( 'SELECT * FROM %pactive' );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/stats.xtpl' );

		$xtpl->assign( 'user_count', $this->settings['user_count'] );
		$xtpl->assign( 'issue_count', $this->settings['total_issues'] );
		$xtpl->assign( 'total_comments', $total_comments );
		$xtpl->assign( 'pct_spam', $pct_spam );
		$xtpl->assign( 'spam', $spam );
		$xtpl->assign( 'ham', $ham );
		$xtpl->assign( 'false_neg', $false_neg );
		$xtpl->assign( 'uspam', $uspam );

		while( $user = $this->db->assoc( $active ) )
		{
			$xtpl->assign( 'ip', $user['active_ip'] );
			$xtpl->assign( 'agent', htmlspecialchars( $user['active_user_agent'] ) );
			$xtpl->assign( 'date', $this->t_date( $user['active_time'] ) );
			$xtpl->assign( 'action', $user['active_action'] );

			$xtpl->parse( 'Stats.UserAgent' );
		}

		$xtpl->parse( 'Stats' );
		return $xtpl->text( 'Stats' );
	}

	private function perform_sql()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/database.xtpl' );

		if( !isset( $this->post['submit'] ) )
		{
			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'query', null );

			$xtpl->parse( 'QueryForm' );
			return $xtpl->text( 'QueryForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		if( empty( $this->post['sqlquery'] ) )
			return $this->message( 'SQL Query', 'You cannot supply an empty query.', 'Query Form', 'admin.php?a=sys&s=sql' );

		// Yes, this is probably horribly insecure, but these people are admins and ought to know better.
		$result = $this->db->dbquery( $this->post['sqlquery'], false );

		if( !$result ) {
			$xtpl->assign( 'error', $this->db->error() );
			$xtpl->parse( 'QueryForm.Error' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'query', $this->post['sqlquery'] );

			$xtpl->parse( 'QueryForm' );
			return $xtpl->text( 'QueryForm' );
		}

		$show_fields = true;
		$col_span = 0;

		$xtpl->assign( 'query_result', $this->post['sqlquery'] );

		while( $row = $this->db->assoc( $result ) )
		{
			if( $show_fields ) {
				foreach( $row as $field => $value ) {
					$xtpl->assign( 'result_field', htmlspecialchars( $field ) );
					$xtpl->parse( 'QueryResult.Field' );
					$col_span++;
				}
				$show_fields = false;
			}

			foreach( $row as $value ) {
				$xtpl->assign( 'result_row', htmlspecialchars( $value ) );
				$xtpl->parse( 'QueryResult.Row.Entry' );
			}
			$xtpl->parse( 'QueryResult.Row' );
		}

		$xtpl->assign( 'col_span', $col_span );
		$xtpl->assign( 'num_rows', $this->db->num_rows( $result ) );

		$xtpl->parse( 'QueryResult' );
		return $xtpl->text( 'QueryResult' );
	}

	/**
	 * Generate a backup
	 *
	 * @author Aaron Smith-Hayes <davionkalhen@gmail.com>
	 * @since 2.1
	 * @return string HTML
	 **/
	private function db_backup()
	{
		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/db_backup.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'query', null );

			$xtpl->parse( 'DBBackup.BackupForm' );
			$xtpl->parse( 'DBBackup' );
			return $xtpl->text( 'DBBackup' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		srand();
		$mcookie = sha1( crc32( rand() ) );

		$filename = 'afktrack_backup_' . date( 'd-m-y-H-i-s' ) . '-' . $mcookie . '.sql';
		$options = '';

		foreach( $this->post as $key => $value )
			$$key = $value;

		if( isset( $insert ) )
			$options .= ' -c';

		if( isset( $droptable ) )
			$options .= ' --add-drop-table';

		$tables = implode( ' ', $this->get_db_tables() );

		$mbdump = "mysqldump {$options} -p --host={$this->db->host} --user={$this->db->user}";
		$mbdump .= " --result-file='./files/{$filename}' {$this->db->name}";

		$fds = array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' )
				);

		$pipes = NULL;

		$proc = proc_open( $mbdump, $fds, $pipes );
		if( $proc === false || !is_resource( $proc ) )
			return $this->error( 0, 'Database Backup Failed. System interface is not available.' );

		fwrite( $pipes[0], $this->db->pass . PHP_EOL );
		fclose( $pipes[0] );

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$retval = proc_close( $proc );

		if ( 0 != $retval )
		{
			return $this->error( 0, 'Database Backup Failed!!!<br><br>' . $stderr );
		}

		chmod( "./files/" . $filename, 0440 );
		return $this->message( 'Database Backup', 'Backup successfully created.<br><br>', $filename, './files/' . $filename, 0 );
	}

	/**
	 * Restore a backup
	 *
	 * @author Aaron Smith-Hayes <davionkalhen@gmail.com>
	 * @since 2.1
	 * @return string HTML
	 **/
	private function db_restore()
	{
		if( !isset( $this->get['restore'] ) )
		{
			if( ( $dir = opendir( './files' ) ) === false )
				return $this->error( 0, 'Unable to read database backups folder.' );

			$token = $this->generate_token();
			$backups = array();

			while( ( $file = readdir( $dir ) ) )
			{
				$ext = strtolower( substr( $file, -4 ) );

				if( $ext != '.sql' )
					continue;
				$backups[] = $file;
			}
			closedir( $dir );

			if( count( $backups ) <= 0 )
				return $this->message( 'Restore Database', 'No backup files were found to restore.' );

			$output = '<b>Warning:</b> This will overwrite all existing data used by AFKTrack!<br><br>';
			$output .= 'The following backups were found in the files directory:<br><br>';
			$count = 0;

			foreach( $backups as $bkup )
			{
				$output .= "<a href=\"admin.php?a=sys&amp;s=restore&amp;restore=" . $bkup . "\">" . $bkup . "</a><br>";
			}
			return $this->message( 'Restore Database', $output );
		}

		if( !file_exists( './files/' . $this->get['restore'] ) )
			return $this->message( 'Restore Database', 'Sorry, that backup does not exist.' );

		/* if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		} */

		$filename = $this->get['restore'];
		$mbimport = "mysql --database={$this->db->name} --host={$this->db->host} --user={$this->db->user} -p";

		$fds = array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' )
				);

		$pipes = NULL;

		$proc = proc_open( $mbimport, $fds, $pipes );

		if( $proc === false || !is_resource( $proc ) )
			return $this->error( 0, 'Database restoration failed. System interface is not available.' );

		fwrite( $pipes[0], $this->db->pass . PHP_EOL );
		sleep( 3 );
		fwrite( $pipes[0], "\\. ./files/{$filename}" . PHP_EOL );
		sleep( 3 );
		fwrite( $pipes[0], "\\q" . PHP_EOL );
		fclose( $pipes[0] );

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$retval = proc_close( $proc );

		if ( 0 != $retval )
		{
			return $this->error( 0, 'Database restoration failed to import.<br><br>' . $stderr );
		}

		return $this->message( 'Restore Database', 'Database restoration successful.<br><br>' . $stdout . $stderr );
	}

	private function test_akismet_key()
	{
		require_once( 'lib/akismet.php' );
		$akismet = new Akismet( $this );

		$response = $akismet->is_key_valid() ? 'Key is Valid!' : 'Key is Invalid!';

		return $this->message( 'Test Akismet Key', $response, 'Continue', 'admin.php', 0 );
	}

	private function prune_watchlists()
	{
		$stmt = $this->db->prepare( 'SELECT issue_id FROM %pissues WHERE (issue_flags & ?)' );

		$f1 = ISSUE_CLOSED;
		$stmt->bind_param( 'i', $f1 );
		$this->db->execute_query( $stmt );
		$result = $stmt->get_result();
		$stmt->close();

		while( $row = $this->db->assoc( $result ) ) {
			$stmt = $this->db->prepare( 'DELETE FROM %pwatching WHERE watch_issue=?' );

			$stmt->bind_param( 'i', $row['issue_id'] );
			$this->db->execute_query( $stmt );
			$stmt->close();
		}

		return $this->message( 'Prune Watchlists', 'Closed tickets have been removed from all watchlists.' );
	}
}
?>