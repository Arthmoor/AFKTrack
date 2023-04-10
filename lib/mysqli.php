<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2020 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

require_once $settings['include_path'] . '/lib/database.php';

class db_mysqli extends database
{
	private $current_query;

	public function __construct( $db_name, $db_user, $db_pass, $db_host, $db_pre )
	{
		parent::__construct( $db_name, $db_user, $db_pass, $db_host, $db_pre );

		$this->db = new mysqli( $db_host, $db_user, $db_pass, $db_name );

		if( !$this->db->select_db( $db_name ) )
			$this->db = false;
	}

	public function close()
	{
		if( $this->db )
			$this->db->close();
	}

	public function dbquery( $query, $report_error = true )
	{
		$time_now   = explode( ' ', microtime() );
		$time_start = $time_now[1] + $time_now[0];

		$args = array();
		if( is_array( $query ) ) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		$query = $this->format_query( $args );

		if( $report_error ) {
         try {
            $result = $this->db->query( $query );
         }
         catch( Exception $e ) {
            error( AFKTRACK_QUERY_ERROR, $this->db->error, $query, $this->db->errno );
         }
      }
		else {
			$result = $this->db->query( $query );
      }

		$this->queries++;

		$time_now  = explode( ' ', microtime() );
		$time_exec = round( $time_now[1] + $time_now[0] - $time_start, 5 );
		$this->query_time = $time_exec;
		$this->queries_exec += $time_exec;

		return $result;
	}

	public function row( $result )
	{
		return $result->fetch_row();
	}

	public function assoc( $result )
	{
		return $result->fetch_assoc();
	}

	public function quick_query( $query )
	{
		$args = array();

		if( is_array( $query ) ) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		return $this->assoc( $this->dbquery( $args ) );
	}

	public function num_rows( $result )
	{
		return $result->num_rows;
	}

	public function insert_id()
	{
		return $this->db->insert_id;
	}

	public function optimize( $tables )
	{
		return $this->dbquery( 'OPTIMIZE TABLE ' . $tables );
	}

	public function repair( $tables )
	{
		return $this->dbquery( 'REPAIR TABLE ' . $tables );
	}

	public function error()
	{
		return $this->db->error;
	}

	protected function escape( $str )
	{
		return $this->db->real_escape_string( $str );
	}

	public function prepare( $query )
	{
		$query = str_replace( '%p', $this->pre, $query );

		$stmt = $this->db->prepare( $query );

		if( $stmt == false ) {
			error( AFKTRACK_QUERY_ERROR, $this->db->error, $query, $this->db->errno );
		}

		$this->current_query = $query;

		return $stmt;
	}

	public function execute_query( $stmt )
	{
		$time_now   = explode( ' ', microtime() );
		$time_start = $time_now[1] + $time_now[0];

		if( !$stmt->execute() ) {
			error( AFKTRACK_QUERY_ERROR, $this->db->error, $this->current_query, $this->db->errno );
		}

		$this->queries++;

		$time_now  = explode( ' ', microtime() );
		$time_exec = round( $time_now[1] + $time_now[0] - $time_start, 5 );
		$this->query_time = $time_exec;
		$this->queries_exec += $time_exec;
	}
}
?>