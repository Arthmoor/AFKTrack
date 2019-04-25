<?php
if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}
$settings = array(
	'db_name'	=> 'DB_NAME',
	'db_user'	=> 'DB_USER',
	'db_pass'	=> 'DB_PASS',
	'db_host'	=> 'localhost',
	'db_pre'	=> 'afktrack_',
	'db_type'	=> '',
	'error_email'	=> 'webmaster@localhost'
	);
?>