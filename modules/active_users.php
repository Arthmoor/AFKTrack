<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2018 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

function do_active($mod, $module)
{
	$idlers = array();
	$expire = $mod->time - 1800;

	$active = $mod->db->dbquery( 'SELECT * FROM %pactive' );
	while( $user = $mod->db->assoc($active) )
	{
		if( $user['active_time'] < $expire )
			$idlers[] = $user['active_ip'];
	}
	if( $idlers ) {
		$stmt = $mod->db->prepare( 'DELETE FROM %pactive WHERE active_time < ?' );

		$stmt->bind_param( 'i', $expire );
		$stmt->execute();
		$stmt->close();
	}

	$action = 'Lurking in the shadows';
	switch( $module ) {
		case 'issues':
			if( isset($mod->get['i']) ) {
				$action = 'Viewing Issue #' . $mod->get['i'];

				if( isset($mod->get['s']) ) {
					if( $mod->get['s'] == 'create' )
						$action = 'Opening a new issue';

					if( $mod->get['s'] == 'edit' )
						$action = 'Editing Issue #' . $mod->get['i'];

					if( $mod->get['s'] == 'del' )
						$action = 'Deleting Issue #' . $mod->get['i'];

					if( isset($mod->get['c']) ) {
						if( $mod->get['s'] == 'edit_comment' )
							$action = 'Editing Comment #' . $mod->get['c'];

						if( $mod->get['s'] == 'del_comment' )
							$action = 'Deleting Comment #' . $mod->get['c'];
					}

					if( $mod->get['s'] == 'assigned' )
						$action = 'Viewing their assignment list';

					if( $mod->get['s'] == 'myissues' )
						$action = 'Viewing their issue list';
				}
			} else {
				$action = 'Viewing the home page';
			}
			break;
		case 'search':		$action = 'Searching';			break;
		case 'rss':		$action = 'Viewing the rss feed';	break;
		case 'profile':		$action = 'Viewing their profile';	break;
		case 'register':	$action = 'User Registration';		break;
		case 'attachments':	$action = 'Downloading an attachment';	break;
		default:		$action = 'Lurking in the shadows';
	}

	$ip = $mod->ip;
	if( $mod->user['user_level'] > USER_GUEST )
		$ip = $mod->user['user_name'];

	$stmt = $mod->db->prepare( 'REPLACE INTO %pactive (active_action, active_time, active_ip, active_user_agent) VALUES ( ?, ?, ?, ? )' );
	$stmt->bind_param( 'siss', $action, $mod->time, $ip, $mod->agent );
	$mod->db->execute_query( $stmt );
	$stmt->close();
}
?>