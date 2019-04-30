<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class severities extends module
{
	public function execute()
	{
		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] ) {
				case 'add':		return $this->add_severity();
				case 'edit':		return $this->edit_severity();
				case 'delete':		return $this->delete_severity();
			}
		}
		return $this->list_severities();
	}

	private function list_severities()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/severities.xtpl' );

		$severity = $this->db->dbquery( 'SELECT * FROM %pseverities ORDER BY severity_id ASC' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Add New Severity' );
		$xtpl->assign( 'action_link', 'admin.php?a=severities&amp;s=add' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'severity_name', null );
		$xtpl->parse( 'Severities.EditForm' );

		while( $sev = $this->db->assoc( $severity ) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=severities&amp;s=edit&amp;p=' . $sev['severity_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=severities&amp;s=delete&amp;p=' . $sev['severity_id'] . '">Delete</a>' );
			$xtpl->assign( 'severity_name', htmlspecialchars( $sev['severity_name'] ) );

			$xtpl->parse( 'Severities.Entry' );
		}

		$xtpl->parse( 'Severities' );
		return $xtpl->text( 'Severities' );
	}

	private function add_severity()
	{
		if( isset ($this->post['severity'] ) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( -1 );
			}

			$name = $this->post['severity'];

			$stmt = $this->db->prepare( 'SELECT severity_name FROM %pseverities WHERE severity_name=?' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$sev = $result->fetch_assoc();

			$stmt->close();

			if( $sev ) {
				return $this->message( 'Add New Severity', 'A severity called ' . $name . ' already exists.', 'Continue', 'admin.php?a=severities' );
			}

			$stmt = $this->db->prepare( 'INSERT INTO %pseverities (severity_name) VALUES( ? )' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$id = $this->db->insert_id();
			$stmt->close();

			// Ugly hack until a better way can be found to deal with setting positions.
			$stmt = $this->db->prepare( 'UPDATE %pseverities SET severity_position=? WHERE severity_id=?' );

			$stmt->bind_param( 'ii', $id, $id );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Add New Severity', 'Severity added.', 'Continue', 'admin.php?a=severities' );
		}

		return $this->list_severities();
	}

	private function edit_severity()
	{
		if( !isset( $this->get['p'] ) && !isset( $this->post['submit'] ) )
			return $this->message( 'Edit Severity', 'Invalid severity specified.', 'Severity List', 'admin.php?a=severities' );

		$sevid = intval( $this->get['p'] );

		$stmt = $this->db->prepare( 'SELECT * FROM %pseverities WHERE severity_id=?' );

		$stmt->bind_param( 'i', $sevid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$severity = $result->fetch_assoc();

		$stmt->close();

		if( !$severity )
			return $this->message( 'Edit Severity', 'Invalid severity selected.' );

		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/severities.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Severity' );
			$xtpl->assign( 'action_link', 'admin.php?a=severities&amp;s=edit&p=' . $sevid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'severity_name', htmlspecialchars( $severity['severity_name'] ) );

			$xtpl->parse( 'Severities.EditForm' );
			return $xtpl->text( 'Severities.EditForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		$name = $this->post['severity'];

		$stmt = $this->db->prepare( 'UPDATE %pseverities SET severity_name=? WHERE severity_id=?' );

		$stmt->bind_param( 'si', $name, $sevid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Edit Severity', 'Severity data updated.', 'Continue', 'admin.php?a=severities' );
	}

	private function delete_severity()
	{
		if( !isset( $this->get['p'] ) && !isset( $this->post['p'] ) )
			return $this->message( 'Delete Severity', 'Invalid severity specified.', 'Severity List', 'admin.php?a=severities' );

		$sevid = isset( $this->get['p'] ) ? intval( $this->get['p'] ) : intval( $this->post['p'] );
		if( $sevid == 1 )
			return $this->message( 'Delete Severity', 'You may not delete the default severity.', 'Severity List', 'admin.php?a=severities' );

		$stmt = $this->db->prepare( 'SELECT * FROM %pseverities WHERE severity_id=?', $sevid );

		$stmt->bind_param( 'i', $sevid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$severity = $result->fetch_assoc();

		$stmt->close();

		if( !$severity )
			return $this->message( 'Delete Severity', 'Invalid severity specified.', 'Severity List', 'admin.php?a=severities' );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/severities.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=severities&amp;s=delete&amp;p=' . $sevid );
			$xtpl->assign( 'severity_name', $severity['severity_name'] );
			$xtpl->assign( 'severity_id', $sevid );

			$xtpl->parse( 'Severities.Delete' );
			return $xtpl->text( 'Severities.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		if( $sevid == 1 )
			return $this->error( 403, 'You may not delete the default severity.' );

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_severity=1 WHERE issue_severity=?' );

		$stmt->bind_param( 'i', $sevid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pseverities WHERE severity_id=?' );

		$stmt->bind_param( 'i', $sevid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Delete Severity', 'Severity deleted. All issues within it have been transferred to the Default Severity.', 'Continue', 'admin.php?a=severities' );
	}
}
?>