<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2020 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class database
{
	public $name = null;
	public $user = null;
	public $host = null;
	public $pass = null;
	protected $pre = null;
	public $queries = 0;
	public $queries_exec = 0;
	public $query_time = 0;

	public function __construct( $db_name, $db_user, $db_pass, $db_host, $db_pre )
	{
		$this->name = $db_name;
		$this->user = $db_user;
		$this->pass = $db_pass;
		$this->host = $db_host;
		$this->pre  = $db_pre;
	}

	public function dbquery( $query )
	{
		return null;
	}

	public function row( $query )
	{
		return null;
	}

	public function assoc( $result )
	{
		return array();
	}

	public function quick_query( $query )
	{
		return $this->assoc( $this->dbquery( $query ) );
	}

	public function num_rows( $result )
	{
		return 0;
	}

	public function insert_id()
	{
		return 0;
	}

	public function error()
	{
		return 'Yep, busted!';
	}

	public function prepare( $query )
	{
		return false;
	}

	public function execute_query( $stmt )
	{
		return false;
	}

	protected function escape( $str )
	{
		return addslashes( $str );
	}

	protected function format_query( $query )
	{
		// Format the query string
		$args = array();
		if( is_array( $query ) ) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		$query = array_shift( $args );
		$query = str_replace( '%p', $this->pre, $query );

		for( $i = 0; $i < count( $args ); $i++ ) {
			$args[$i] = $this->escape( $args[$i] );
		}
		array_unshift( $args, $query );

		return call_user_func_array( 'sprintf', $args );
	}
}
?>