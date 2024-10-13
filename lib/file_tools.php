<?php
/* AFKTrack https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2020 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class file_tools
{
   public $module;
   public $db;
   public $settings;
   public $user;
   public $file_dir;

	public function __construct( &$module )
	{
		$this->module = &$module;
		$this->db = &$module->db;
		$this->settings = &$module->settings;
		$this->user = &$module->user;
		$this->file_dir = &$module->file_dir;
	}

	public function get_file_icon( $type )
	{
		$file_icon = '/images/disk.png';

		switch( $type )
		{
			case 'jpg':
			case 'png':
			case 'bmp':
			case 'gif':
				$file_icon = '/images/photos.png';
				break;

			case 'txt':
			case 'log':
				$file_icon = '/images/page_white_text.png';
				break;

			case 'psc':
				$file_icon = '/images/script_code_red.png';
				break;

			case 'esp':
			case 'esm':
			case 'esl':
			case 'ess':
			case 'fos':
				$file_icon = '/images/ck.png';
				break;

			case '7z':
			case 'rar':
			case 'zip':
				$file_icon = '/images/zip.png';
				break;

			case 'mov':
				$file_icon = '/images/film.png';
				break;

			default: break;
		}

		return $file_icon;
	}

	// Stuff for attaching files to issues.
	public function attach_file( &$file, &$attached_data )
	{
		$upload_error = null; // Null is no error

		if( !isset( $file ) ) {
			$upload_error = 'The attachment upload failed. The file you specified may not exist.';
		} else {
			$md5 = md5( $file['name'] . microtime() );

			$ret = $this->upload( $file, $this->file_dir . $md5, $this->settings['attachment_size_limit_mb'], $this->settings['attachment_types_allowed'] );

			switch( $ret )
			{
				case UPLOAD_TOO_LARGE:
					$upload_error = sprintf( 'The specified file is too large. The maximum size is %d MB.', $this->settings['attachment_size_limit_mb'] );
				break;

				case UPLOAD_NOT_ALLOWED:
					$upload_error = 'You cannot attach files of that type.';
				break;

				case UPLOAD_SUCCESS:
					$attached_data[$md5] = $file['name'];
				break;

				default:
					$upload_error = 'The attachment upload failed. The file you specified may not exist.';
			}
		}
		return $upload_error;
	}

	public function delete_attachment( $filename, &$attached_data )
	{
		unset( $attached_data[$filename] );

		@unlink( $this->file_dir . $filename );
	}

	public function make_attached_options( &$options, &$hiddennames, $attached_data )
	{
		foreach( $attached_data as $md5 => $file )
		{
			$file = htmlspecialchars( $file );

			$options .= "<option value='$md5'>$file</option>\n";
			$hiddennames .= "<input type='hidden' name='attached_data[$md5]' value='$file'>\n";
		}
	}

	public function attach_files_db( $issue_id, $comment_id = 0, $attached_data )
	{
		foreach( $attached_data as $md5 => $filename )
		{
			$renamed = $issue_id . '_' . $md5;
			rename( $this->file_dir . $md5, $this->file_dir . $renamed );

			$temp = explode( '.', $filename );
			$ext = strtolower( end( $temp ) );

			$stmt = $this->db->prepare( 'INSERT INTO %pattachments( attachment_issue, attachment_comment, attachment_name, attachment_filename, attachment_type, attachment_size, attachment_user, attachment_date ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ? )' );

			$size = filesize( $this->file_dir . $renamed );
			$stmt->bind_param( 'iisssiii', $issue_id, $comment_id, $filename, $renamed, $ext, $size, $this->user['user_id'], $this->module->time );
			$this->db->execute_query( $stmt );
			$stmt->close();
		}
	}

	private function upload( $file, $destination, $max_size, $allowed_types )
	{
		if( $file['size'] > ( $max_size * 1024 * 1024 ) ) {
			return UPLOAD_TOO_LARGE;
		}

		$temp = explode( '.', $file['name'] );
		$ext = strtolower( end( $temp ) );

		if( !in_array( $ext, $allowed_types ) ) {
			return UPLOAD_NOT_ALLOWED;
		}

		if( is_uploaded_file( $file['tmp_name'] ) ) {
			$result = @move_uploaded_file( $file['tmp_name'], str_replace( '\\', '/', $destination ) );

			if( $result ) {
				return UPLOAD_SUCCESS;
			}
		}
		return UPLOAD_FAILURE;
	}
}
?>