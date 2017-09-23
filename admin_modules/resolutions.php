<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2018 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') || !defined('AFKTRACK_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class resolutions extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if( isset($this->get['s']) ) {
			switch( $this->get['s'] ) {
				case 'add':		return $this->add_resolution();
				case 'edit':		return $this->edit_resolution();
				case 'delete':		return $this->delete_resolution();
			}
		}
		return $this->list_resolutions();
	}

	function list_resolutions()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/resolutions.xtpl' );

		$resolutions = $this->db->dbquery( 'SELECT * FROM %presolutions ORDER BY resolution_id ASC' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Add New Resolution' );
		$xtpl->assign( 'action_link', 'admin.php?a=resolutions&amp;s=add' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'resolution_name', null );
		$xtpl->parse( 'Resolutions.EditForm' );

		while( $resolution = $this->db->assoc( $resolutions ) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=resolutions&amp;s=edit&amp;p=' . $resolution['resolution_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=resolution&amp;s=delete&amp;p=' . $resolution['resolution_id'] . '">Delete</a>' );
			$xtpl->assign( 'resolution_name', htmlspecialchars($resolution['resolution_name']) );

			$xtpl->parse( 'Resolutions.Entry' );
		}

		$xtpl->parse( 'Resolutions' );
		return $xtpl->text( 'Resolutions' );
	}

	function add_resolution()
	{
		if( isset($this->post['resolution']) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$name = $this->post['resolution'];

			$stmt = $this->db->prepare( 'SELECT resolution_name FROM %presolutions WHERE resolution_name=?' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$res = $result->fetch_assoc();

			$stmt->close();

			if( $res ) {
				return $this->message( 'Add New Resolution', 'A resolution called ' . $name . ' already exists.', 'Continue', 'admin.php?a=resolutions' );
			}

			$stmt = $this->db->prepare( 'INSERT INTO %presolutions (resolution_name) VALUES( ? )' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$id = $this->db->insert_id();
			$stmt->close();

			// Ugly hack until a better way can be found to deal with setting positions.
			$stmt = $this->db->prepare( 'UPDATE %presolutions SET resolution_position=? WHERE resolution_id=?' );

			$stmt->bind_param( 'ii', $id, $id );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Add New Resolution', 'Resolution added.', 'Continue', 'admin.php?a=resolutions' );
		}

		return $this->list_resolutions();
	}

	function edit_resolution()
	{
		if( !isset($this->get['p']) && !isset($this->post['submit']) )
			return $this->message( 'Edit Resolution', 'Invalid resolution specified.', 'Resolution List', 'admin.php?a=resolutions' );

		$resid = intval($this->get['p']);

		$stmt = $this->db->prepare( 'SELECT * FROM %presolutions WHERE resolution_id=?' );

		$stmt->bind_param( 'i', $resid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$resolution = $result->fetch_assoc();

		$stmt->close();

		if( !$resolution )
			return $this->message( 'Edit Resolution', 'Invalid resolution selected.' );

		if(!isset($this->post['submit'])) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/resolutions.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Resolution' );
			$xtpl->assign( 'action_link', 'admin.php?a=resolutions&amp;s=edit&p=' . $resid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'resolution_name', htmlspecialchars($resolution['resolution_name']) );

			$xtpl->parse( 'Resolutions.EditForm' );
			return $xtpl->text( 'Resolutions.EditForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$name = $this->post['resolution'];

		$stmt = $this->db->prepare( 'UPDATE %presolutions SET resolution_name=? WHERE resolution_id=?' );

		$stmt->bind_param( 'si', $name, $resid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Edit Resolution', 'Resolution data updated.', 'Continue', 'admin.php?a=resolutions' );
	}

	function delete_resolution()
	{
		if( !isset($this->get['p']) && !isset($this->post['p']) )
			return $this->message( 'Delete Resolution', 'Invalid resolution specified.', 'Resolution List', 'admin.php?a=resolutions' );

		$resid = isset($this->get['p']) ? intval($this->get['p']) : intval($this->post['p']);
		if( $resid == 1 )
			return $this->message( 'Delete Resolution', 'You may not delete the default resolution.', 'Resolution List', 'admin.php?a=resolutions' );

		$stmt = $this->db->prepare( 'SELECT * FROM %presolutions WHERE resolution_id=?' );

		$stmt->bind_param( 'i', $resid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$resolution = $result->fetch_assoc();

		$stmt->close();

		if( !$resolution )
			return $this->message( 'Delete Resolution', 'Invalid resolution specified.', 'Resolution List', 'admin.php?a=resolutions' );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/resolutions.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=resolutions&amp;s=delete&amp;p=' . $resid );
			$xtpl->assign( 'resolution_name', $resolution['resolution_name'] );
			$xtpl->assign( 'resolution_id', $resid );

			$xtpl->parse( 'Resolutions.Delete' );
			return $xtpl->text( 'Resolutions.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( $platid == 1 )
			return $this->error( 'You may not delete the default resolution.' );

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_resolution=1 WHERE issue_resolution=?' );

		$stmt->bind_param( 'i', $resid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %presolutions WHERE resolution_id=?' );

		$stmt->bind_param( 'i', $resid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Delete Resolution', 'Resolution deleted. All issues within it have been transferred to the Default Resolution.', 'Continue', 'admin.php?a=resolutions' );
	}
}
?>