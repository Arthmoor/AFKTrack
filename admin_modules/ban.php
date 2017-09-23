<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2018 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') || !defined('AFKTRACK_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class ban extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.', 403 );

		$this->title( 'Banned IPs' );

		if( !isset($this->post['submit']) )
		{
			$ips = null;
			if( isset($this->settings['banned_ips']) )
				$ips = implode("\n", $this->settings['banned_ips']);

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/ban.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'ip_addresses', $ips );

			$xtpl->parse( 'Bans' );
			return $xtpl->text( 'Bans' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$banned_ips = trim($this->post['banned_ips']);
		if ( $banned_ips )
			$banned_ips = explode("\n", $banned_ips);
		else
			$banned_ips = array();
		$this->settings['banned_ips'] = $banned_ips;
		$this->save_settings();
		return $this->message( 'Banned IPs', 'Bans updated.', 'Continue', 'admin.php?a=ban' );
	}
}
?>
