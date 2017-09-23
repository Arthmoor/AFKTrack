<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2018 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

require_once $settings['include_path'] . '/lib/database.php';

class db_mysqli extends database
{
	var $current_query;

	function __construct( $db_name, $db_user, $db_pass, $db_host, $db_pre )
	{
		parent::__construct( $db_name, $db_user, $db_pass, $db_host, $db_pre );

		$this->db = new mysqli( $db_host, $db_user, $db_pass, $db_name );

		if (!$this->db->select_db( $db_name ))
			$this->db = false;
	}

	function close()
	{
		if( $this->db )
			$this->db->close();
	}

	function dbquery( $query, $report_error = true )
	{
		$time_now   = explode(' ', microtime());
		$time_start = $time_now[1] + $time_now[0];

		$args = array();
		if (is_array($query)) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		$query = $this->format_query($args);

		if( $report_error )
			$result = $this->db->query($query) or error(AFKTRACK_QUERY_ERROR, $this->db->error, $query, $this->db->errno);
		else
			$result = $this->db->query($query);

		$this->queries++;

		$time_now  = explode(' ', microtime());
		$time_exec = round($time_now[1] + $time_now[0] - $time_start, 5);
		$this->query_time = $time_exec;
		$this->queries_exec += $time_exec;

		return $result;
	}

	function row( $result )
	{
		return $result->fetch_row();
	}

	function assoc( $result )
	{
		return $result->fetch_assoc();
	}

	function quick_query( $query )
	{
		$args = array();
		if (is_array($query)) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		return $this->assoc( $this->dbquery( $args ) );
	}

	function num_rows( $result )
	{
		return $result->num_rows;
	}

	function insert_id()
	{
		return $this->db->insert_id;
	}

	function escape( $str )
	{
		return $this->db->real_escape_string( $str );
	}

	function optimize($tables)
	{
		return $this->dbquery( 'OPTIMIZE TABLE ' . $tables );
	}

	function repair($tables)
	{
		return $this->dbquery( 'REPAIR TABLE ' . $tables );
	}

	function error()
	{
		return $this->db->error;
	}

	function prepare( $query )
	{
		$query = str_replace( '%p', $this->pre, $query );

		if( $this->db->prepare( $query ) == false ) {
			error(AFKTRACK_QUERY_ERROR, $this->db->error, $query, $this->db->errno);
		}

		$this->current_query = $query;

		return $this->db->prepare( $query );
	}

	function execute_query( $stmt )
	{
		$time_now   = explode(' ', microtime());
		$time_start = $time_now[1] + $time_now[0];

		if( !$stmt->execute() ) {
			error(AFKTRACK_QUERY_ERROR, $this->db->error, $this->current_query, $this->db->errno);
		}

		$this->queries++;

		$time_now  = explode(' ', microtime());
		$time_exec = round($time_now[1] + $time_now[0] - $time_start, 5);
		$this->query_time = $time_exec;
		$this->queries_exec += $time_exec;
	}
}
?>