<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK' ) || !defined( 'AFKTRACK_ADM' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

class settings extends module
{
	public function execute()
	{
		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'add':		return $this->add_setting();
			}
			return $this->error( 404 );
		}

		$int_fields = array( 'site_open', 'site_issuesperpage', 'site_icon_width', 'site_icon_height', 'site_commentsperpage', 'cookie_logintime',
			'rss_items', 'rss_refresh', 'html_email', 'validate_users', 'global_comments', 'attachment_size_limit_mb', 'admin_notify_accounts',
			'search_flood_time', 'prune_watchlist', 'htts_max_age', 'xfo_policy', 'xss_policy', 'ect_max_age', 'csp_enabled' );

		foreach( $int_fields as $key )
		{
			if( !isset( $this->settings[$key] ) )
				$this->settings[$key] = 0;
		}

		$this->title( 'Site Settings' );
		$sets = &$this->settings;
		if( isset( $this->post['submit'] ) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( -1 );
			}

			foreach( $int_fields as $key )
			{
				if( isset( $this->post[$key] ) )
					$this->settings[$key] = intval( $this->post[$key] );
				else
					$this->settings[$key] = 0;
			}

			$sets['rss_enabled'] = isset( $this->post['rss_enabled'] );
			$sets['cookie_secure'] = isset( $this->post['cookie_secure'] );
			$sets['htts_enabled'] = isset( $this->post['htts_enabled'] );
			$sets['xfo_enabled'] = isset( $this->post['xfo_enabled'] );
			$sets['xss_enabled'] = isset( $this->post['xss_enabled'] );
			$sets['xcto_enabled'] = isset( $this->post['xcto_enabled'] );
			$sets['ect_enabled'] = isset( $this->post['ect_enabled'] );
			$sets['csp_enabled'] = isset( $this->post['csp_enabled'] );

			if( !empty( $this->post['site_address'] ) && $this->post['site_address'][strlen( $this->post['site_address'] )-1] != '/' )
				$this->post['site_address'] = $this->post['site_address'] . '/';

			$valid_fields = array(
				'email_adm', 'email_sys', 'site_name', 'site_address', 'site_analytics', 'site_closedmessage', 'site_spamregmessage',
				'site_meta', 'site_keywords', 'mobile_icons', 'rss_name', 'rss_description', 'site_dateformat', 'site_timezone',
				'wordpress_api_key', 'cookie_prefix', 'cookie_path', 'cookie_domain', 'global_announce', 'footer_text', 'registration_terms',
				'privacy_policy', 'xfo_allowed_origin', 'csp_details' );

			foreach( $valid_fields as $key )
				$this->settings[$key] = $this->post[$key];

			if( in_array( $this->post['site_defaultskin'], $this->get_skins() ) )
				$this->settings['site_defaultskin'] = $this->post['site_defaultskin'];

			if( $this->settings['cookie_path']{0} != '/' )
				$this->settings['cookie_path'] = '/' . $this->settings['cookie_path'];

			if( $this->settings['cookie_path']{strlen($this->settings['cookie_path'])-1} != '/' )
				$this->settings['cookie_path'] .= '/';

			$attachtypes = explode( ",", $this->post['attachment_types_allowed'] );
			$count = count( $attachtypes );

			for( $i = 0; $i < $count; $i++ )
			{
				$attachtypes[$i] = trim( $attachtypes[$i] );
			}
			$this->settings['attachment_types_allowed'] = $attachtypes;

			$logo_type_error = null;
			$logo_upload_error = null;

			if( isset( $this->files['logo_upload'] ) && $this->files['logo_upload']['error'] == UPLOAD_ERR_OK )
			{
				$old_filename = $this->settings['header_logo'];

				$fname = $this->files['logo_upload']['tmp_name'];
				$system = explode( '.', $this->files['logo_upload']['name'] );
				$ext = strtolower( end( $system ) );

				if( !preg_match( '/jpg|jpeg|png|gif/', $ext ) ) {
					$logo_type_error = 'Invalid logo file type ' . $ext . '. Valid file types are jpg, png and gif.';
				} else { 
					$new_fname = $this->banner_dir . $this->files['logo_upload']['name'];

					if( !move_uploaded_file( $fname, $new_fname ) ) {
						$logo_upload_error = 'Header logo failed to upload!';
					} else {
						@unlink( $this->banner_dir . $old_filename );

						$this->settings['header_logo'] = $this->files['logo_upload']['name'];
					}
				}
			}
			$this->save_settings();

			if( $logo_type_error == null && $logo_upload_error == null )
				return $this->message( 'AFKTrack Settings', 'Settings saved.', 'Continue', 'admin.php' );
			else
				return $this->message( 'AFKTRack Settings', 'Settings saved, with errors: ' . $logo_type_error . ' ' . $logo_upload_error );
		}

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/settings.xtpl' );

		$xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->skin}" );
		$xtpl->assign( 'header_logo', "{$this->settings['site_address']}{$this->banner_dir}{$this->settings['header_logo']}" );
		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'site_name', htmlspecialchars( $sets['site_name'] ) );
		$xtpl->assign( 'email_adm', htmlspecialchars( $sets['email_adm'] ) );
		$xtpl->assign( 'email_sys', htmlspecialchars( $sets['email_sys'] ) );
		$xtpl->assign( 'site_address', htmlspecialchars( $sets['site_address'] ) );
		$xtpl->assign( 'site_meta', htmlspecialchars( $sets['site_meta'] ) );
		$xtpl->assign( 'site_keywords', htmlspecialchars( $sets['site_keywords'] ) );
		$xtpl->assign( 'mobile_icons', htmlspecialchars( $sets['mobile_icons'] ) );
		$xtpl->assign( 'site_dateformat', htmlspecialchars( $sets['site_dateformat'] ) );
		$xtpl->assign( 'site_timezone', $this->select_timezones( $sets['site_timezone'], 'site_timezone' ) );
		$xtpl->assign( 'site_defaultskin', $this->select_input( 'site_defaultskin', $sets['site_defaultskin'], $this->get_skins() ) );
		$xtpl->assign( 'site_analytics', htmlspecialchars( $sets['site_analytics'], ENT_QUOTES ) );
		$xtpl->assign( 'wordpress_api_key', htmlspecialchars( $sets['wordpress_api_key'] ) );
		$xtpl->assign( 'attach_size', $sets['attachment_size_limit_mb'] );
		$xtpl->assign( 'search_flood_time', $sets['search_flood_time'] );
		$xtpl->assign( 'registration_terms', htmlspecialchars( $sets['registration_terms'] ) );
		$xtpl->assign( 'privacy_policy', htmlspecialchars( $sets['privacy_policy'] ) );

		$attachtypes = implode( ",", $sets['attachment_types_allowed'] );

		$xtpl->assign( 'attach_types', $attachtypes );

		if( $sets['validate_users'] ) {
			$xtpl->assign( 'valu1', ' checked="checked"' );
			$xtpl->assign( 'valu0', null );
		} else {
			$xtpl->assign( 'valu1', null );
			$xtpl->assign( 'valu0', ' checked="checked"' );
		}

		if( $sets['admin_notify_accounts'] ) {
			$xtpl->assign( 'adm1', ' checked="checked"' );
			$xtpl->assign( 'adm0', null );
		} else {
			$xtpl->assign( 'adm1', null );
			$xtpl->assign( 'adm0', ' checked="checked"' );
		}

		if( $sets['global_comments'] ) {
			$xtpl->assign( 'glob1', ' checked="checked"' );
			$xtpl->assign( 'glob0', null );
		} else {
			$xtpl->assign( 'glob1', null );
			$xtpl->assign( 'glob0', ' checked="checked"' );
		}

		if( $sets['prune_watchlist'] ) {
			$xtpl->assign( 'prun1', ' checked="checked"' );
			$xtpl->assign( 'prun0', null );
		} else {
			$xtpl->assign( 'prun1', null );
			$xtpl->assign( 'prun0', ' checked="checked"' );
		}

		if( $sets['site_open'] ) {
			$xtpl->assign( 'site1', ' checked="checked"' );
			$xtpl->assign( 'site0', null );
		} else {
			$xtpl->assign( 'site1', null );
			$xtpl->assign( 'site0', ' checked="checked"' );
		}
		$xtpl->assign( 'site_closedmessage', htmlspecialchars( $sets['site_closedmessage'] ) );
		$xtpl->assign( 'site_spamregmessage', htmlspecialchars( $sets['site_spamregmessage'] ) );

		$xtpl->assign( 'footer_text', htmlspecialchars( $sets['footer_text'] ) );

		$xtpl->assign( 'cookie_prefix', htmlspecialchars( $sets['cookie_prefix'] ) );
		$xtpl->assign( 'cookie_path', htmlspecialchars( $sets['cookie_path'] ) );
		$xtpl->assign( 'cookie_domain', htmlspecialchars( $sets['cookie_domain'] ) );
		$xtpl->assign( 'cookie_logintime', htmlspecialchars( $sets['cookie_logintime'] ) );
		$xtpl->assign( 'cookie_secure', $sets['cookie_secure'] ? ' checked="checked"' : null );

		$xtpl->assign( 'site_issuesperpage', $sets['site_issuesperpage'] );
		$xtpl->assign( 'site_icon_width', $sets['site_icon_width'] );
		$xtpl->assign( 'site_icon_height', $sets['site_icon_height'] );
		$xtpl->assign( 'site_commentsperpage', $sets['site_commentsperpage'] );

		if( $sets['html_email'] ) {
			$xtpl->assign( 'email1', ' checked="checked"' );
			$xtpl->assign( 'email0', null );
		} else {
			$xtpl->assign( 'email1', null );
			$xtpl->assign( 'email0', ' checked="checked"' );
		}

		$xtpl->assign( 'rss_enabled', $sets['rss_enabled'] ? ' checked="checked"' : null );
		$xtpl->assign( 'rss_items', $sets['rss_items'] );
		$xtpl->assign( 'rss_refresh', $sets['rss_refresh'] );
		$xtpl->assign( 'rss_name', htmlspecialchars( $sets['rss_name'] ) );
		$xtpl->assign( 'rss_description', htmlspecialchars( $sets['rss_description'] ) );

		$xtpl->assign( 'global_announce', htmlspecialchars( $sets['global_announce'] ) );

		$xtpl->assign( 'htts_enabled', $sets['htts_enabled'] ? ' checked="checked"' : null );
		$xtpl->assign( 'htts_max_age', $sets['htts_max_age'] );

		$xtpl->assign( 'xss_enabled', $sets['xss_enabled'] ? ' checked="checked"' : null );
		if( $sets['xss_policy'] == 0 ) {
			$xtpl->assign( 'xss_policy0', ' checked="checked"' );
			$xtpl->assign( 'xss_policy1', null );
			$xtpl->assign( 'xss_policy2', null );
		} elseif( $sets['xss_policy'] == 1 ) {
			$xtpl->assign( 'xss_policy0', null );
			$xtpl->assign( 'xss_policy1', ' checked="checked"' );
			$xtpl->assign( 'xss_policy2', null );
		} elseif( $sets['xss_policy'] == 2 ) {
			$xtpl->assign( 'xss_policy0', null );
			$xtpl->assign( 'xss_policy1', null );
			$xtpl->assign( 'xss_policy2', ' checked="checked"' );
		}

		$xtpl->assign( 'xfo_enabled', $sets['xfo_enabled'] ? ' checked="checked"' : null );
		if( $sets['xfo_policy'] == 0 ) {
			$xtpl->assign( 'xfo_policy0', ' checked="checked"' );
			$xtpl->assign( 'xfo_policy1', null );
			$xtpl->assign( 'xfo_policy2', null );
		} elseif( $sets['xfo_policy'] == 1 ) {
			$xtpl->assign( 'xfo_policy0', null );
			$xtpl->assign( 'xfo_policy1', ' checked="checked"' );
			$xtpl->assign( 'xfo_policy2', null );
		} elseif( $sets['xfo_policy'] == 2 ) {
			$xtpl->assign( 'xfo_policy0', null );
			$xtpl->assign( 'xfo_policy1', null );
			$xtpl->assign( 'xfo_policy2', ' checked="checked"' );
		}
		$xtpl->assign( 'xfo_allowed_origin', $sets['xfo_allowed_origin'] );

		$xtpl->assign( 'xcto_enabled', $sets['xcto_enabled'] ? ' checked="checked"' : null );

		$xtpl->assign( 'ect_enabled', $sets['ect_enabled'] ? ' checked="checked"' : null );
		$xtpl->assign( 'ect_max_age', $sets['ect_max_age'] );

		$xtpl->assign( 'csp_enabled', $sets['csp_enabled'] ? ' checked="checked"' : null );
		$xtpl->assign( 'csp_details', $sets['csp_details'] );

		$xtpl->parse( 'Settings' );
		return $xtpl->text( 'Settings' );
	}

	private function add_setting()
	{
		if( !isset( $this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/settings.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=settings&amp;s=add' );

			$xtpl->parse( 'Settings.AddForm' );
			return $xtpl->text( 'Settings.AddForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( -1 );
		}

		if( !isset( $this->post['new_setting'] ) || empty( $this->post['new_setting'] ) ) {
			return $this->message( 'Add Site Setting', 'An empty setting name is not allowed.' );
		}

		$new_setting = $this->post['new_setting'];

		if( isset( $this->settings[$new_setting] ) ) {
			return $this->message( 'Add Site Setting', 'A setting called ' . $new_setting . ' already exists!' );
		}

		$is_new_array = isset( $this->post['is_array'] );

		if( $is_new_array )
			$new_value = explode( ',', $this->post['new_value'] );
		else
			$new_value = $this->post['new_value'];

		$this->settings[$new_setting] = $new_value;
		$this->save_settings();

		return $this->message( 'Add Site Setting', 'New settings saved.', 'Continue', 'admin.php' );
	}
}
?>