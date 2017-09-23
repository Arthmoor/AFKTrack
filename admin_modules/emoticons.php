<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2018 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if ( !defined('AFKTRACK') || !defined('AFKTRACK_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class emoticons extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.', 403 );

		if (!isset($this->get['s'])) {
			$this->get['s'] = null;
		}

		switch($this->get['s'])
		{
		case null:
		case 'edit':
			$edit_id = isset($this->get['edit']) ? intval($this->get['edit']) : 0;
			$delete_id = isset($this->get['delete']) ? intval($this->get['delete']) : 0;

			if (isset($this->get['delete'])) {
				$stmt = $this->db->prepare( 'DELETE FROM %pemoticons WHERE emote_id=?' );

				$stmt->bind_param( 'i', $delete_id );
				$this->db->execute_query( $stmt );
				$stmt->close();
			}

			if (!isset($this->get['edit'])) {
				$this->get['edit'] = null;
			}

			if (isset($this->post['submit']) && (trim($this->post['new_string']) != '') && (trim($this->post['new_image']) != '')) {
				$new_click = intval( isset($this->post['new_click']) );

				$stmt = $this->db->prepare( 'UPDATE %pemoticons SET emote_string=?, emote_image=?, emote_clickable=? WHERE emote_id=?' );

				$stmt->bind_param( 'ssii', $this->post['new_string'], $this->post['new_image'], $new_click, $edit_id );
				$this->db->execute_query( $stmt );
				$stmt->close();

				$this->get['edit'] = null;
			}

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/emoticons.xtpl' );

			$query = $this->db->dbquery( 'SELECT * FROM %pemoticons ORDER BY emote_clickable,emote_string ASC' );
			while ($data = $this->db->assoc($query))
			{
				$xtpl->assign( 'em_id', $data['emote_id'] );

				$em_string = $data['emote_string'];
				$em_image = $data['emote_image'];

				if( !$this->get['edit'] || ($edit_id != $data['emote_id']) ) {
					$xtpl->assign( 'em_string', $em_string );
					$xtpl->assign( 'em_image', $em_image );
					$xtpl->assign( 'img_src', "<img src=\"{$this->settings['site_address']}files/emoticons/{$em_image}\" alt=\"{$em_string}\" />" );

					if( $data['emote_clickable'] == 0 )
						$xtpl->assign( 'em_clickable', 'No' );
					else
						$xtpl->assign( 'em_clickable', 'Yes' );

					$xtpl->assign( 'em_edit', "<a href=\"{$this->settings['site_address']}admin.php?a=emoticons&amp;s=edit&amp;edit={$data['emote_id']}\">Edit</a>" );
				} else {
					$xtpl->assign( 'em_string', "<input name=\"new_string\" value=\"{$em_string}\" class=\"input\" />" );

					$em_list = $this->list_emoticons( $em_image );
					$xtpl->assign( 'em_image', "<select name=\"new_image\" onchange='document.emot_preview.src=\"../files/emoticons/\"+this.options[selectedIndex].value'>{$em_list}</select>" );
					$xtpl->assign( 'img_src', "<img name=\"emot_preview\" src=\"{$this->settings['site_address']}files/emoticons/{$em_image}\" alt=\"{$em_string}\" />" );

					$checked = '';
					if( $data['emote_clickable'] == 1 )
						$checked = 'checked';
					$xtpl->assign( 'em_clickable', "<input type=\"checkbox\" name=\"new_click\" {$checked} />" );

					$xtpl->assign( 'em_edit', "<input type=\"submit\" name=\"submit\" value=\"Edit\">" );
				}
				$xtpl->assign( 'em_delete', "<a href=\"{$this->settings['site_address']}admin.php?a=emoticons&amp;s=edit&amp;delete={$data['emote_id']}\">Delete</a>" );

				$xtpl->parse( 'Emoticons.SingleEntry' );
			}

			$xtpl->assign( 'form_action', $this->settings['site_address'] . 'admin.php?a=emoticons&amp;s=edit&amp;edit=' . $edit_id );
			$xtpl->assign( 'add_form_action', $this->settings['site_address'] . 'admin.php?a=emoticons&amp;s=add' );
			$xtpl->assign( 'em_add_list', $this->list_emoticons( 'New' ) );

			$xtpl->parse( 'Emoticons' );
			return $xtpl->text( 'Emoticons' );
			break;

		case 'add':
			if (!isset($this->post['submit'])) {
				$this->get['s'] = null;
				$this->execute();
				return;
			} else {
				$new_clickable = intval( isset($this->post['new_click']) );
				$new_string = isset($this->post['new_string']) ? $this->post['new_string'] : '';

				if (trim($new_string) == '') {
					return $this->error('Add New Emoticon', 'No emoticon text was given.');
				}

				$new_image = '';
				if( $this->post['existing_image'] != 'New' )
					$new_image = $this->post['existing_image'];
				else {
					if( isset( $this->files['new_image'] ) && $this->files['new_image']['error'] == UPLOAD_ERR_OK ) {
						$fname = $this->files['new_image']['tmp_name'];
						$system = explode( '.', $this->files['new_image']['name'] );
						$ext = strtolower(end($system));

						if ( !preg_match( '/jpg|jpeg|png|gif/', $ext ) ) {
							return $this->error( 'Add New Emoticon', sprintf('Invalid image type %s. Valid file types are jpg, png and gif.', $ext) );
						} else {
							$new_fname = $this->emote_dir . $this->files['new_image']['name'];
							if ( !move_uploaded_file( $fname, $new_fname ) )
								return $this->error( 'Add New Emoticon', 'Image failed to upload!' );
							else
								$new_image = $this->files['new_image']['name'];
						}
					}
				}

				$stmt = $this->db->prepare( 'INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES (?, ?, ? )' );

				$stmt->bind_param( 'ssi', $new_string, $new_image, $new_clickable );
				$this->db->execute_query( $stmt );
				$stmt->close();

				return $this->message( 'Add New Emoticon', 'Emoticon added.', 'Back to Emoticon Controls', $this->settings['site_address'] . 'admin.php?a=emoticons' );
			}
			break;
		}
	}

	function list_emoticons($select)
	{
		$dirname = $this->emote_dir;

		$out = null;
		$files = array();

		if( $select == 'New' )
			$out .= "\n<option value=\"New\" selected=\"selected\">New</option>";

		$dir = opendir($dirname);
		while (($emo = readdir($dir)) !== false)
		{
			$ext = substr($emo, -3);
			if (($ext != 'png')
			&& ($ext != 'gif')
			&& ($ext != 'jpg')) {
				continue;
			}

			if (is_dir($dirname . $emo)) {
				continue;
			}

			$files[] = $emo;
		}

		sort($files);

		foreach( $files as $key => $name ) {
			$out .= "\n<option value='$name'" . (($name == $select) ? ' selected' : '') . ">$name</option>";
		}
		return $out;
	}
}
?>