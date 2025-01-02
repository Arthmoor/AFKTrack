<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2025 Roger Libiez aka Arthmoor
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
		$projects = $this->db->dbquery( 'SELECT * FROM %pprojects' );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/overview.xtpl' );

      $f1 = intval( ISSUE_CLOSED );
      $open_query = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE !(issue_flags & ?) AND issue_project=?' );
      $open_query->bind_param( 'ii', $f1, $project_id );

      $closed_query = $this->db->prepare_query( 'SELECT COUNT(issue_id) count FROM %pissues WHERE (issue_flags & ?) AND issue_project=?' );
      $closed_query->bind_param( 'ii', $f1, $project_id );

		while( $project = $this->db->assoc( $projects ) )
		{
			$xtpl->assign( 'project_name', $project['project_name'] );

			$project_id = $project['project_id'];

			$this->db->execute_query( $open_query );

			$result = $open_query->get_result();
         $open_issues = $result->fetch_assoc();

			$xtpl->assign( 'open_issues', $open_issues['count'] );

			$this->db->execute_query( $closed_query );

			$result = $closed_query->get_result();
         $closed_issues = $result->fetch_assoc();

			$xtpl->assign( 'closed_issues', $closed_issues['count'] );
		}
		$open_query->close();
		$closed_query->close();
	}
}
?>