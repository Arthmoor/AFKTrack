<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
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

			$open_issues = $this->db->dbquery( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & %d) AND issue_project=%d', ISSUE_CLOSED, $project['project_id'] );
			$xtpl->assign( 'open_issues', $open_issues['count'] );

			$closed = $this->db->dbquery( 'SELECT COUNT(issue_id) count FROM %pissues WHERE (issue_flags & %d) AND issue_project=%d', ISSUE_CLOSED, $project['project_id'] );
			$xtpl->assign( 'closed_issues', $closed_issues['count'] );
		}
	}
}
?>