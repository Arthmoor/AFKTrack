<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class platforms extends module
{
	public function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] ) {
				case 'add':		return $this->add_platform();
				case 'edit':		return $this->edit_platform();
				case 'delete':		return $this->delete_platform();
			}
		}
		return $this->list_platforms();
	}

	private function list_platforms()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/platforms.xtpl' );

		$platforms = $this->db->dbquery( 'SELECT * FROM %pplatforms ORDER BY platform_id ASC' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Add New Platform' );
		$xtpl->assign( 'action_link', 'admin.php?a=platforms&amp;s=add' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'platform_name', null );
		$xtpl->parse( 'Platforms.EditForm' );

		while( $platform = $this->db->assoc( $platforms ) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=platforms&amp;s=edit&amp;p=' . $platform['platform_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=platforms&amp;s=delete&amp;p=' . $platform['platform_id'] . '">Delete</a>' );
			$xtpl->assign( 'platform_name', htmlspecialchars( $platform['platform_name'] ) );

			$xtpl->parse( 'Platforms.Entry' );
		}

		$xtpl->parse( 'Platforms' );
		return $xtpl->text( 'Platforms' );
	}

	private function add_platform()
	{
		if( isset( $this->post['platform'] ) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$name = $this->post['platform'];

			$plat = $this->db->quick_query( "SELECT platform_name FROM %pplatforms WHERE platform_name='%s'", $name );

			if( $plat ) {
				return $this->message( 'Add New Platform', 'A platform called ' . $this->post['platform'] . ' already exists.', 'Continue', 'admin.php?a=platforms' );
			}

			$stmt = $this->db->prepare( 'INSERT INTO %pplatforms (platform_name) VALUES( ? )' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );
			$id = $this->db->insert_id();
			$stmt->close();

			// Ugly hack until a better way can be found to deal with setting positions.
			$stmt = $this->db->prepare( 'UPDATE %pplatforms SET platform_position=? WHERE platform_id=?' );

			$stmt->bind_param( 'ii', $id, $id );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Add New Platform', 'Platform added.', 'Continue', 'admin.php?a=platforms' );
		}

		return $this->list_platforms();
	}

	private function edit_platform()
	{
		if( !isset( $this->get['p'] ) && !isset( $this->post['submit'] ) )
			return $this->message( 'Edit Platform', 'Invalid platform specified.', 'Platform List', 'admin.php?a=platforms' );

		$platid = intval( $this->get['p'] );

		if( !isset( $this->post['submit'] ) ) {
			$stmt = $this->db->prepare( 'SELECT * FROM %pplatforms WHERE platform_id=?' );

			$stmt->bind_param( 'i', $platid );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$platform = $result->fetch_assoc();

			$stmt->close();

			if( !$platform )
				return $this->message( 'Edit Platform', 'Invalid platform selected.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/platforms.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Platform' );
			$xtpl->assign( 'action_link', 'admin.php?a=platforms&amp;s=edit&p=' . $platid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'platform_name', htmlspecialchars($platform['platform_name']) );

			$xtpl->parse( 'Platforms.EditForm' );
			return $xtpl->text( 'Platforms.EditForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$name = $this->post['platform'];

		$stmt = $this->db->prepare( 'UPDATE %pplatforms SET platform_name=? WHERE platform_id=?' );

		$stmt->bind_param( 'si', $name, $platid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Edit Platform', 'Platform data updated.', 'Continue', 'admin.php?a=platforms' );
	}

	private function delete_platform()
	{
		if( !isset( $this->get['p'] ) && !isset( $this->post['p'] ) )
			return $this->message( 'Delete Platform', 'Invalid platform specified.', 'Platform List', 'admin.php?a=platforms' );

		$platid = isset( $this->get['p'] ) ? intval( $this->get['p'] ) : intval( $this->post['p'] );
		if( $platid == 1 )
			return $this->message( 'Delete Platform', 'You may not delete the default platform.', 'Platform List', 'admin.php?a=platforms' );

		$stmt = $this->db->prepare( 'SELECT * FROM %pplatforms WHERE platform_id=?' );

		$stmt->bind_param( 'i', $platid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$platform = $result->fetch_assoc();

		$stmt->close();

		if( !$platform )
			return $this->message( 'Delete Platform', 'Invalid platform specified.', 'Platform List', 'admin.php?a=platforms' );

		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/platforms.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=platforms&amp;s=delete&amp;p=' . $platid );
			$xtpl->assign( 'platform_name', $platform['platform_name'] );
			$xtpl->assign( 'platform_id', $platid );

			$xtpl->parse( 'Platforms.Delete' );
			return $xtpl->text( 'Platforms.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( $platid == 1 )
			return $this->error( 'You may not delete the default platform.' );

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_platform=1 WHERE issue_platform=?' );

		$stmt->bind_param( 'i', $platid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pplatforms WHERE platform_id=?' );

		$stmt->bind_param( 'i', $platid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Delete Platform', 'Platform deleted. All issues within it have been transferred to the Default Platform.', 'Continue', 'admin.php?a=platforms' );
	}
}
?>