<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class types extends module
{
	public function execute()
	{
		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] ) {
				case 'add':		return $this->add_type();
				case 'edit':		return $this->edit_type();
				case 'delete':		return $this->delete_type();
			}
		}
		return $this->list_types();
	}

	private function list_types()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/types.xtpl' );

		$types = $this->db->dbquery( 'SELECT * FROM %ptypes ORDER BY type_id ASC' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Add New Issue Type' );
		$xtpl->assign( 'action_link', 'admin.php?a=types&amp;s=add' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'type_name', null );
		$xtpl->parse( 'Types.EditForm' );

		while( $type = $this->db->assoc( $types ) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=types&amp;s=edit&amp;t=' . $type['type_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=types&amp;s=delete&amp;t=' . $type['type_id'] . '">Delete</a>' );
			$xtpl->assign( 'type_name', htmlspecialchars( $type['type_name'] ) );

			$xtpl->parse( 'Types.Entry' );
		}

		$xtpl->parse( 'Types' );
		return $xtpl->text( 'Types' );
	}

	private function add_type()
	{
		if( isset( $this->post['type'] ) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( -1 );
			}

			$name = $this->post['type'];

			$stmt = $this->db->prepare( 'SELECT type_name FROM %ptypes WHERE type_name=?' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$t = $result->fetch_assoc();

			$stmt->close();

			if( $t ) {
				return $this->message( 'Add New Issue Type', 'A type called ' . $name . ' already exists.', 'Continue', 'admin.php?a=types' );
			}

			$stmt = $this->db->prepare( 'INSERT INTO %ptypes (type_name) VALUES( ? )' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$id = $this->db->insert_id();
			$stmt->close();

			// Ugly hack until a better way can be found to deal with setting positions.
			$stmt = $this->db->prepare( 'UPDATE %ptypes SET type_position=? WHERE type_id=?' );

			$stmt->bind_param( 'ii', $id, $id );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Add New Issue Type', 'Type added.', 'Continue', 'admin.php?a=types' );
		}

		return $this->list_types();
	}

	private function edit_type()
	{
		if( !isset( $this->get['t'] ) && !isset( $this->post['submit'] ) )
			return $this->message( 'Edit Issue Type', 'Invalid type specified.', 'Type List', 'admin.php?a=types' );

		$tid = intval( $this->get['t'] );

		$stmt = $this->db->prepare( 'SELECT * FROM %ptypes WHERE type_id=?' );

		$stmt->bind_param( 'i', $tid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$type = $result->fetch_assoc();

		$stmt->close();

		if( !$type )
			return $this->message( 'Edit Issue Type', 'Invalid type selected.' );

		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/types.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Issue Type' );
			$xtpl->assign( 'action_link', 'admin.php?a=types&amp;s=edit&t=' . $tid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'type_name', htmlspecialchars( $type['type_name'] ) );

			$xtpl->parse( 'Types.EditForm' );
			return $xtpl->text( 'Types.EditForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		$name = $this->post['type'];

		$stmt = $this->db->prepare( 'UPDATE %ptypes SET type_name=? WHERE type_id=?' );

		$stmt->bind_param( 'si', $name, $tid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Edit Issue Type', 'Type data updated.', 'Continue', 'admin.php?a=types' );
	}

	private function delete_type()
	{
		if( !isset( $this->get['t'] ) && !isset( $this->post['t'] ) )
			return $this->message( 'Delete Issue Type', 'Invalid type specified.', 'Type List', 'admin.php?a=types' );

		$tid = isset( $this->get['t'] ) ? intval( $this->get['t'] ) : intval( $this->post['t'] );
		if( $tid == 1 )
			return $this->message( 'Delete ISsue Type', 'You may not delete the default type.', 'Type List', 'admin.php?a=types' );

		$stmt = $this->db->prepare( 'SELECT * FROM %ptypes WHERE type_id=?' );

		$stmt->bind_param( 'i', $tid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$type = $result->fetch_assoc();

		$stmt->close();

		if( !$type )
			return $this->message( 'Delete Issue Type', 'Invalid type specified.', 'Type List', 'admin.php?a=types' );

		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/types.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=types&amp;s=delete&amp;t=' . $tid );
			$xtpl->assign( 'type_name', $type['type_name'] );
			$xtpl->assign( 'type_id', $tid );

			$xtpl->parse( 'Types.Delete' );
			return $xtpl->text( 'Types.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		if( $tid == 1 )
			return $this->error( 403, 'You may not delete the default type.' );

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_type=1 WHERE issue_type=?' );

		$stmt->bind_param( 'i', $tid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %ptypes WHERE type_id=?' );

		$stmt->bind_param( 'i', $tid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Delete Issue Type', 'Type deleted. All issues within it have been transferred to the Default Issue Type.', 'Continue', 'admin.php?a=types' );
	}
}
?>