<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') || !defined('AFKTRACK_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class projects extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if( isset($this->get['s']) ) {
			switch( $this->get['s'] ) {
				case 'create':		return $this->create_project();
				case 'edit':		return $this->edit_project();
				case 'delete':		return $this->delete_project();
				case 'create_cat':	return $this->create_category();
				case 'edit_cat':	return $this->edit_category();
				case 'delete_cat':	return $this->delete_category();
				case 'create_com':	return $this->create_component();
				case 'edit_com':	return $this->edit_component();
				case 'delete_com':	return $this->delete_component();
			}
		}
		return $this->list_projects();
	}

	function list_projects()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/projects.xtpl' );

		$projects = $this->db->dbquery( 'SELECT * FROM %pprojects ORDER BY project_id ASC' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Create New Project' );
		$xtpl->assign( 'action_link', 'admin.php?a=projects&amp;s=create' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'project_name', null );
		$xtpl->assign( 'project_desc', null );
		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
		$xtpl->parse( 'Projects.AddForm' );

		while( $project = $this->db->assoc( $projects ) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=projects&amp;s=edit&amp;p=' . $project['project_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=projects&amp;s=delete&amp;p=' . $project['project_id'] . '">Delete</a>' );
			$xtpl->assign( 'project_name', htmlspecialchars($project['project_name']) );
			$xtpl->assign( 'project_desc', htmlspecialchars($project['project_description']) );
			$xtpl->assign( 'project_retired', $project['project_retired'] == true ? 'Yes' : 'No' );

			$xtpl->parse( 'Projects.Entry' );
		}

		$xtpl->parse( 'Projects' );
		return $xtpl->text( 'Projects' );
	}

	function create_project()
	{
		if( isset($this->post['project']) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$name = $this->post['project'];
			$desc = isset( $this->post['project_desc'] ) ? $this->post['project_desc'] : '';

			$stmt = $this->db->prepare( 'SELECT project_name FROM %pprojects WHERE project_name=?' );

			$stmt->bind_param( 's', $name );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$proj = $result->fetch_assoc();

			$stmt->close();

			if( $proj ) {
				return $this->message( 'Create Project', 'A project called ' . $this->post['project'] . ' already exists.' );
			}

			$stmt = $this->db->prepare( 'INSERT INTO %pprojects (project_name, project_description) VALUES( ?, ? )' );

			$stmt->bind_param( 'ss', $name, $desc );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Create Project', 'Project added.', 'Continue', 'admin.php?a=projects' );
		}

		return $this->list_projects();
	}

	function edit_project()
	{
		if( !isset($this->get['p']) && !isset($this->post['submit']) )
			return $this->message( 'Edit Project', 'Invalid project specified.', 'Project List', 'admin.php?a=projects' );

		$projid = intval($this->get['p']);

		if(!isset($this->post['submit'])) {
			$stmt = $this->db->prepare( 'SELECT * FROM %pprojects WHERE project_id=?' );

			$stmt->bind_param( 'i', $projid );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$project = $result->fetch_assoc();

			$stmt->close();

			if( !$project )
				return $this->message( 'Edit Project', 'Invalid project selected.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/projects.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Project' );
			$xtpl->assign( 'action_link', 'admin.php?a=projects&amp;s=edit&p=' . $projid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'project_name', htmlspecialchars($project['project_name']) );
			$xtpl->assign( 'project_desc', htmlspecialchars($project['project_description']) );
			$xtpl->assign( 'project_retired_checked', $project['project_retired'] ? ' checked="checked"' : null );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );

			$xtpl->assign( 'cat_link', 'admin.php?a=projects&amp;s=create_cat&p=' . $projid );
			$xtpl->assign( 'com_link', 'admin.php?a=projects&amp;s=create_com&p=' . $projid );

			$stmt = $this->db->prepare( 'SELECT * FROM %pcategories WHERE category_project=? ORDER BY category_position ASC' );

			$stmt->bind_param( 'i', $projid );
			$this->db->execute_query( $stmt );

			$categories = $stmt->get_result();
			$stmt->close();

			while( $category = $this->db->assoc( $categories ) )
			{
				$xtpl->assign( 'edit_cat', '<a href="admin.php?a=projects&amp;s=edit_cat&amp;p=' . $projid . '&amp;c=' . $category['category_id'] . '">Edit</a>' );
				$xtpl->assign( 'delete_cat', '<a href="admin.php?a=projects&amp;s=delete_cat&amp;p=' . $projid . '&amp;c=' . $category['category_id'] . '">Delete</a>' );
				$xtpl->assign( 'cat_name', $category['category_name'] );

				$xtpl->parse( 'Projects.EditForm.CatEntry' );
			}

			$stmt = $this->db->prepare( 'SELECT * FROM %pcomponents WHERE component_project=? ORDER BY component_position ASC' );

			$stmt->bind_param( 'i', $projid );
			$this->db->execute_query( $stmt );

			$components = $stmt->get_result();
			$stmt->close();

			while( $component = $this->db->assoc( $components ) )
			{
				$xtpl->assign( 'edit_com', '<a href="admin.php?a=projects&amp;s=edit_com&amp;p=' . $projid . '&amp;c=' . $component['component_id'] . '">Edit</a>' );
				$xtpl->assign( 'delete_com', '<a href="admin.php?a=projects&amp;s=delete_com&amp;p=' . $projid . '&amp;c=' . $component['component_id'] . '">Delete</a>' );
				$xtpl->assign( 'component_name', $component['component_name'] );

				$xtpl->parse( 'Projects.EditForm.ComEntry' );
			}

			$xtpl->parse( 'Projects.EditForm' );
			return $xtpl->text( 'Projects.EditForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$name = $this->post['project'];
		$retired = isset( $this->post['project_retired'] ) ? 1 : 0;
		$desc = isset( $this->post['project_desc'] ) ? $this->post['project_desc'] : '';

		$stmt = $this->db->prepare( 'UPDATE %pprojects SET project_name=?, project_description=?, project_retired=? WHERE project_id=?' );

		$stmt->bind_param( 'ssii', $name, $desc, $retired, $projid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Edit Project', 'Project data updated.', 'Continue', 'admin.php?a=projects' );
	}

	function delete_project()
	{
		if( !isset($this->get['p']) && !isset($this->post['p']) )
			return $this->message( 'Delete Project', 'Invalid project specified.', 'Project List', 'admin.php?a=projects' );

		$projid = isset($this->get['p']) ? intval($this->get['p']) : intval($this->post['p']);
		if( $projid == 1 )
			return $this->message( 'Delete Project', 'You may not delete the default project.', 'Project List', 'admin.php?a=projects' );

		$stmt = $this->db->prepare( 'SELECT * FROM %pprojects WHERE project_id=?' );

		$stmt->bind_param( 'i', $projid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$project = $result->fetch_assoc();

		$stmt->close();

		if( !$project )
			return $this->message( 'Delete Project', 'Invalid project specified.', 'Project List', 'admin.php?a=projects' );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/projects.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=projects&amp;s=delete&amp;p=' . $projid );
			$xtpl->assign( 'project_name', $project['project_name'] );
			$xtpl->assign( 'project_id', $projid );

			$xtpl->parse( 'Projects.Delete' );
			return $xtpl->text( 'Projects.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( $projid == 1 )
			return $this->error( 'You may not delete the default project.' );

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_project=1 WHERE issue_project=?' );

		$stmt->bind_param( 'i', $projid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pprojects WHERE project_id=?' );

		$stmt->bind_param( 'i', $projid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pcategories WHERE category_project=?' );

		$stmt->bind_param( 'i', $projid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Delete Project', 'Project deleted. All issues within it have been transferred to the Default Project.', 'Continue', 'admin.php?a=projects' );
	}

	function create_category()
	{
		if( isset($this->post['category']) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$projid = intval($this->get['p']);
			$name = $this->post['category'];

			$stmt = $this->db->prepare( 'SELECT category_name FROM %pcategories WHERE category_name=? AND category_project=?' );

			$stmt->bind_param( 'si', $name, $projid );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$cat = $result->fetch_assoc();

			$stmt->close();

			if( $cat ) {
				return $this->message( 'Add Category', 'A category named ' . $name . ' already exists in the project.' );
			}

			$stmt = $this->db->prepare( 'INSERT INTO %pcategories (category_name, category_project) VALUES( ?, ? )' );

			$stmt->bind_param( 'si', $name, $projid );
			$this->db->execute_query( $stmt );
			$id = $this->db->insert_id();
			$stmt->close();

			// Ugly hack until a better way can be found to deal with setting positions.
			$stmt = $this->db->prepare( 'UPDATE %pcategories SET category_position=? WHERE category_id=?' );

			$stmt->bind_param( 'ii', $id, $id );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Add Category', 'Category added.', 'Continue', 'admin.php?a=projects&s=edit&p=' . $projid );
		}

		return $this->list_projects();
	}

	function edit_category()
	{
		if( !isset($this->get['p']) )
			return $this->message( 'Edit Category', 'Invalid project specified.', 'Project List', 'admin.php?a=projects' );

		$projid = intval($this->get['p']);

		if( !isset($this->get['c']) && !isset($this->post['submit']) )
			return $this->message( 'Edit Category', 'Invalid category specified.', 'Category List', 'admin.php?a=projects&s=edit&p=' . $projid );

		$catid = intval($this->get['c']);

		$stmt = $this->db->prepare( 'SELECT c.*, p.project_name FROM %pcategories c LEFT JOIN %pprojects p ON p.project_id=c.category_project WHERE category_id=?' );

		$stmt->bind_param( 'i', $catid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$category = $result->fetch_assoc();

		$stmt->close();

		if( !$category )
			return $this->message( 'Edit Category', 'Invalid category selected.' );

		if(!isset($this->post['submit'])) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/projects.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Project' );
			$xtpl->assign( 'action_link', 'admin.php?a=projects&amp;s=cat_edit&p=' . $projid . '&c=' . $catid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'cat_project_name', $category['project_name'] );
			$xtpl->assign( 'cat_name', htmlspecialchars($category['category_name']) );

			$xtpl->parse( 'Projects.CatEdit' );
			return $xtpl->text( 'Projects.CatEdit' );
		}

		$name = $this->post['category'];

		$stmt = $this->db->prepare( 'UPDATE %pcategories SET category_name=? WHERE category_id=?' );

		$stmt->bind_param( 'si', $name, $catid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Edit Category', 'Category data updated.', 'Continue', 'admin.php?a=projects&s=edit&p=' . $projid );
	}

	function delete_category()
	{
		if( !isset($this->get['p']) )
			return $this->message( 'Edit Category', 'Invalid project specified.', 'Project List', 'admin.php?a=projects' );

		$projid = intval($this->get['p']);

		if( !isset($this->get['c']) && !isset($this->post['c']) )
			return $this->message( 'Delete Category', 'Invalid category specified.', 'Category List', 'admin.php?a=projects&s=edit&p=' . $projid );

		$catid = isset($this->get['c']) ? intval($this->get['c']) : intval($this->post['c']);
		if( $catid == 1 )
			return $this->message( 'Delete Category', 'You may not delete the default category.', 'Category List', 'admin.php?a=projects&s=edit&p=' . $projid );

		$stmt = $this->db->prepare( 'SELECT * FROM %pcategories WHERE category_id=?' );

		$stmt->bind_param( 'i', $catid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$category = $result->fetch_assoc();

		$stmt->close();

		if( !$category )
			return $this->message( 'Delete Category', 'Invalid category specified.', 'Category List', 'admin.php?a=projects&s=edit&p=' . $projid );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/projects.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=projects&amp;s=delete_cat&amp;p=' . $projid . '&amp;c=' . $catid );
			$xtpl->assign( 'cat_name', $category['category_name'] );
			$xtpl->assign( 'cat_id', $catid );
			$xtpl->assign( 'proj_id', $projid );

			$xtpl->parse( 'Projects.CatDelete' );
			return $xtpl->text( 'Projects.CatDelete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( $catid == 1 )
			return $this->error( 'You may not delete the default category.' );

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_category=1 WHERE issue_category=?' );

		$stmt->bind_param( 'i', $catid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pcategories WHERE category_id=?' );

		$stmt->bind_param( 'i', $catid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Delete Category', 'Category deleted. All issues within it have been transferred to the Default Category.', 'Continue', 'admin.php?a=projects&s=edit&p=' . $projid );
	}

	function create_component()
	{
		if( isset($this->post['component']) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$projid = intval($this->get['p']);
			$name = $this->post['component'];

			$stmt = $this->db->prepare( 'SELECT component_name FROM %pcomponents WHERE component_name=? AND component_project=?' );

			$stmt->bind_param( 'si', $name, $projid );
			$this->db->execute_query( $stmt );

			$result = $stmt->get_result();
			$com = $result->fetch_assoc();

			$stmt->close();

			if( $com ) {
				return $this->message( 'Add Component', 'A component named ' . $this->post['component'] . ' already exists in the project.' );
			}

			$stmt = $this->db->prepare( 'INSERT INTO %pcomponents (component_name, component_project) VALUES( ?, ? )' );

			$stmt->bind_param( 'si', $name, $projid );
			$this->db->execute_query( $stmt );
			$id = $this->db->insert_id();
			$stmt->close();

			// Ugly hack until a better way can be found to deal with setting positions.
			$stmt = $this->db->prepare( 'UPDATE %pcomponents SET component_position=? WHERE component_id=?' );

			$stmt->bind_param( 'ii', $id, $id );
			$this->db->execute_query( $stmt );
			$stmt->close();

			return $this->message( 'Add Component', 'Component added.', 'Continue', 'admin.php?a=projects&s=edit&p=' . $projid );
		}

		return $this->list_projects();
	}

	function edit_component()
	{
		if( !isset($this->get['p']) )
			return $this->message( 'Edit Component', 'Invalid project specified.', 'Project List', 'admin.php?a=projects' );

		$projid = intval($this->get['p']);

		if( !isset($this->get['c']) && !isset($this->post['submit']) )
			return $this->message( 'Edit Component', 'Invalid component specified.', 'Component List', 'admin.php?a=projects&s=edit&p=' . $projid );

		$comid = intval($this->get['c']);

		$stmt = $this->db->prepare( 'SELECT c.*, p.project_name FROM %pcomponents c LEFT JOIN %pprojects p ON p.project_id=c.component_project WHERE component_id=?' );

		$stmt->bind_param( 'i', $comid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$component = $result->fetch_assoc();

		$stmt->close();

		if(!isset($this->post['submit'])) {
			if( !$component )
				return $this->message( 'Edit Component', 'Invalid component selected.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/projects.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Project' );
			$xtpl->assign( 'action_link', 'admin.php?a=projects&amp;s=com_edit&p=' . $projid . '&c=' . $comid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'component_project_name', $component['project_name'] );
			$xtpl->assign( 'component_name', htmlspecialchars($component['component_name']) );

			$xtpl->parse( 'Projects.ComEdit' );
			return $xtpl->text( 'Projects.ComEdit' );
		}

		$name = $this->post['component'];

		$stmt = $this->db->prepare( 'UPDATE %pcomponents SET component_name=? WHERE component_id=?' );

		$stmt->bind_param( 'si', $name, $comid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Edit Component', 'Component data updated.', 'Continue', 'admin.php?a=projects&s=edit&p=' . $projid );
	}

	function delete_component()
	{
		if( !isset($this->get['p']) )
			return $this->message( 'Edit Component', 'Invalid project specified.', 'Project List', 'admin.php?a=projects' );

		$projid = intval($this->get['p']);

		if( !isset($this->get['c']) && !isset($this->post['c']) )
			return $this->message( 'Delete Component', 'Invalid component specified.', 'Component List', 'admin.php?a=projects&s=edit&p=' . $projid );

		$comid = isset($this->get['c']) ? intval($this->get['c']) : intval($this->post['c']);
		if( $comid == 1 )
			return $this->message( 'Delete Component', 'You may not delete the default component.', 'Component List', 'admin.php?a=projects&s=edit&p=' . $projid );

		$stmt = $this->db->prepare( 'SELECT * FROM %pcomponents WHERE component_id=?' );

		$stmt->bind_param( 'i', $comid );
		$this->db->execute_query( $stmt );

		$result = $stmt->get_result();
		$component = $result->fetch_assoc();

		$stmt->close();

		if( !$component )
			return $this->message( 'Delete Component', 'Invalid component specified.', 'Component List', 'admin.php?a=projects&s=edit&p=' . $projid );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/projects.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=projects&amp;s=delete_com&amp;p=' . $projid . '&amp;c=' . $comid );
			$xtpl->assign( 'component_name', $component['component_name'] );
			$xtpl->assign( 'com_id', $comid );
			$xtpl->assign( 'proj_id', $projid );

			$xtpl->parse( 'Projects.ComDelete' );
			return $xtpl->text( 'Projects.ComDelete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( $comid == 1 )
			return $this->error( 'You may not delete the default component.' );

		$stmt = $this->db->prepare( 'UPDATE %pissues SET issue_component=1 WHERE issue_component=?', $comid );

		$stmt->bind_param( 'i', $name, $comid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		$stmt = $this->db->prepare( 'DELETE FROM %pcomponents WHERE component_id=?', $comid );

		$stmt->bind_param( 'i', $name, $comid );
		$this->db->execute_query( $stmt );
		$stmt->close();

		return $this->message( 'Delete Component', 'Component deleted. All issues within it have been transferred to the Default Component.', 'Continue', 'admin.php?a=projects&s=edit&p=' . $projid );
	}
}
?>