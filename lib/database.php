<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class database
{
	var $name = null;
	var $user = null;
	var $host = null;
	var $pass = null;
	var $pre = null;
	var $queries = 0;
	var $queries_exec = 0;
	var $query_time = 0;

	function __construct( $db_name, $db_user, $db_pass, $db_host, $db_pre )
	{
		$this->name = $db_name;
		$this->user = $db_user;
		$this->pass = $db_pass;
		$this->host = $db_host;
		$this->pre  = $db_pre;
	}

	function dbquery( $query )
	{
		return null;
	}

	function row( $query )
	{
		return null;
	}

	function assoc( $result )
	{
		return array();
	}

	function quick_query( $query )
	{
		return $this->assoc( $this->dbquery( $query ) );
	}

	function num_rows( $result )
	{
		return 0;
	}

	function insert_id()
	{
		return 0;
	}

	function escape( $str )
	{
		return addslashes( $str );
	}

	function error()
	{
		return 'Yep, busted!';
	}

	function prepare( $query )
	{
		return false;
	}

	function execute_query( $stmt )
	{
		return false;
	}

	protected function format_query($query)
	{
		// Format the query string
		$args = array();
		if (is_array($query)) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		$query = array_shift($args);
		$query = str_replace('%p', $this->pre, $query);
		
		for( $i = 0; $i < count($args); $i++) {
			$args[$i] = $this->escape($args[$i]);
		}
		array_unshift($args, $query);

		return call_user_func_array('sprintf', $args);
	}
}
?>