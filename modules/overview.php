<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2020 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class overview extends module
{
	public function execute()
	{
		$projid = 0;

		if( isset( $this->get['project'] ) )
			$projid = intval( $this->get['project'] );

		if( $projid == 0 )
			return $this->all_projects_overview();
        }

	private function all_projects_overview()
	{
		$projects = $this->db->dbquery( 'SELECT * FROM %projects' );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/overview.xtpl' );

		while( $project = $this->db->assoc( $projects ) )
		{
			$xtpl->assign( 'project_name', $project['project_name'] );

			$f1 = ISSUE_CLOSED;
			$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?) AND issue_project=?' );

			$stmt->bind_param( 'ii', $f1, $project['project_id'] );
			$this->db->execute_query( $stmt );

			$open_issues = $stmt->get_result();
			$stmt->close();

			$xtpl->assign( 'open_issues', $open_issues['count'] );

			$f1 = ISSUE_CLOSED;
			$stmt = $this->db->prepare( 'SELECT COUNT(issue_id) count FROM %pissues WHERE (issue_flags & ?) AND issue_project=?' );

			$stmt->bind_param( 'ii', $f1, $project['project_id'] );
			$this->db->execute_query( $stmt );

			$closed_issues = $stmt->get_result();
			$stmt->close();

			$xtpl->assign( 'closed_issues', $closed_issues['count'] );
		}
	}
}
?>