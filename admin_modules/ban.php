<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class ban extends module
{
	public function execute()
	{
		$this->title( 'Banned IPs' );

		if( !isset( $this->post['submit'] ) )
		{
			$ips = null;
			if( isset( $this->settings['banned_ips'] ) )
				$ips = implode( "\n", $this->settings['banned_ips'] );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/ban.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'ip_addresses', $ips );

			$xtpl->parse( 'Bans' );
			return $xtpl->text( 'Bans' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		$banned_ips = trim( $this->post['banned_ips'] );
		if( $banned_ips )
			$banned_ips = explode( "\n", $banned_ips );
		else
			$banned_ips = array();

		$this->settings['banned_ips'] = $banned_ips;
		$this->save_settings();

		return $this->message( 'Banned IPs', 'Bans updated.', 'Continue', 'admin.php?a=ban' );
	}
}
?>