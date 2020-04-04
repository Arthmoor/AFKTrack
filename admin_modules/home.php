<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2020 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class home extends module
{
	public function execute()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/home.xtpl' );

		$xtpl->parse( 'Home' );
		return $xtpl->text( 'Home' );
	}
}		
?>