<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class status extends module
{
	public function execute()
	{
		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] ) {
				case 'add':		return $this->add_status();
				case 'edit':		return $this->edit_status();
				case 'delete':		return $this->delete_status();
			}
		}
		return $this->list_status();
	}

	private function list_status()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/status.xtpl' );

		$status = $this->db->dbquery( 'SELECT * FROM %pstatus ORDER BY status_id ASC' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Add New Status' );
		$xtpl->assign( 'action_link', 'admin.php?a=status&amp;s=add' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'status_name', null );
		$xtpl->parse( 'Status.EditForm' );

		while( $stat = $this->db->assoc( $status ) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=status&amp;s=edit&amp;p=' . $stat['status_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=status&amp;s=delete&amp;p=' . $stat['status_id'] . '">Delete</a>' );
			$xtpl->assign( 'status_name', htmlspecialchars( $stat['status_name'] ) );
			$xtpl->assign( 'status_shows', ($stat['status_shows'] == 1) ? 'Yes' : 'No' );

			$xtpl->parse( 'Status.Entry' );
		}

		$xtpl->parse( 'Status' );
		return $xtpl->text( 'Status' );
	}

	private function add_status()
	{
		if( isset( $this->post['status'] ) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( -1 );
			}

			$name = $this->post['status'];
			$shows = isset( $this->post['status_shows'] ) ? 1 : 0;

			$stmt = $this->db->prepare( 'SELECT status_name FROM %pstatus WHERE status_name=?' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$stat = $result->fetch_assoc();

			$stmt->close();

			if( $stat ) {
				return $this->message( 'Add New Status', 'A status called ' . $name . ' already exists.', 'Continue', 'admin.php?a=status' );
			}

			$stmt = $this->db->prepare( 'INSERT INTO %pstatus (status_name, status_shows) VALUES( ?, ? )' );

			$stmt->bind_param( 'si', $name, $shows );
			$this->db->execute_query( $stmt );

			$id = $this->db->insert_id();
			$stmt->close();

			// Ugly hack until a better way can be found to deal with setting positions.
			$stmt = $this->db->prepare( 'UPDATE %pstatus SET status_position=? WHERE status_id=?' );

			$stmt->bind_param( 'ii', $id, $id );
			$this->db->execute_query( $stmt );

			$stmt->close();

			return $this->message( 'Add New Status', 'Status added.', 'Continue', 'admin.php?a=status' );
		}

		return $this->list_status();
	}

	private function edit_status()
	{
		if( !isset( $this->get['p'] ) && !isset( $this->post['submit'] ) )
			return $this->message( 'Edit Status', 'Invalid status specified.', 'Status List', 'admin.php?a=status' );

		$statid = intval($this->get['p']);

		$stmt = $this->db->prepare( 'SELECT * FROM %pstatus WHERE status_id=?' );

		$stmt->bind_param( 'i', $statid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$status = $result->fetch_assoc();

		$stmt->close();

		if( !$status )
			return $this->message( 'Edit Status', 'Invalid status selected.' );

		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/status.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Status' );
			$xtpl->assign( 'action_link', 'admin.php?a=status&amp;s=edit&p=' . $statid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'status_name', htmlspecialchars( $status['status_name'] ) );
			$xtpl->assign( 'status_shows_checked', $status['status_shows'] ? ' checked="checked"' : null );

			$xtpl->parse( 'Status.EditForm' );
			return $xtpl->text( 'Status.EditForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		$name = $this->post['status'];
		$shows = isset( $this->post['status_shows'] ) ? 1 : 0;

		$stmt = $this->db->prepare( 'UPDATE %pstatus SET status_name=?, status_shows=? WHERE status_id=?' );

		$stmt->bind_param( 'sii', $name, $shows, $statid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Edit Status', 'Status data updated.', 'Continue', 'admin.php?a=status' );
	}

	private function delete_status()
	{
		if( !isset( $this->get['p'] ) && !isset( $this->post['p'] ) )
			return $this->message( 'Delete Status', 'Invalid status specified.', 'Status List', 'admin.php?a=status' );

		$statid = isset( $this->get['p'] ) ? intval( $this->get['p'] ) : intval( $this->post['p'] );
		if( $statid == 1 )
			return $this->message( 'Delete Status', 'You may not delete the default status.', 'Status List', 'admin.php?a=status' );

		$stmt = $this->db->prepare( 'SELECT * FROM %pstatus WHERE status_id=?' );

		$stmt->bind_param( 'i', $statid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$status = $result->fetch_assoc();

		$stmt->close();

		if( !$status )
			return $this->message( 'Delete Status', 'Invalid status specified.', 'Status List', 'admin.php?a=status' );

		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/status.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=status&amp;s=delete&amp;p=' . $statid );
			$xtpl->assign( 'status_name', $status['status_name'] );
			$xtpl->assign( 'status_id', $statid );

			$xtpl->parse( 'Status.Delete' );
			return $xtpl->text( 'Status.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		if( $statid == 1 )
			return $this->error( 403, 'You may not delete the default status.' );

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_status=1 WHERE issue_status=?' );

		$stmt->bind_param( 'i', $statid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pstatus WHERE status_id=?' );

		$stmt->bind_param( 'i', $statid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Delete Status', 'Status deleted. All issues within it have been transferred to the Default Status.', 'Continue', 'admin.php?a=status' );
	}
}
?>