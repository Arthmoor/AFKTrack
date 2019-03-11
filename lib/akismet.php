<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 * Implements the Akismet API found at https://akismet.com/development/api/#detailed-docs
 */

if ( !defined('AFKTRACK') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

// Akismet anti-spam library for AFKTrack
class Akismet
{
	private $site_url;
	private $api_key;
	private $akismet_domain = 'rest.akismet.com';
	private $akismet_server_port = 443;
	private $akismet_version = '2.0';
	private $akismet_api_server;
	private $akismet_request_path;
	private $akismet_useragent;
	private $comment_data;

	// This list of server variables often contain sensitive information. Even though this uses SSL there's no good reason to transmit it.
	private $server_vars_ignored = array( 'HTTP_COOKIE', 'PATH', 'SERVER_SIGNATURE', 'SERVER_SOFTWARE', 'DOCUMENT_ROOT', 'CONTEXT_PREFIX', 'SERVER_ADMIN', 'SCRIPT_FILENAME', 'SCRIPT_NAME', 'PHP_SELF', 'PATH_TRANSLATED', 'PATH_INFO', 'ORIG_PATH_INFO' );

	// Constructor takes the AFKTrack global instance which has all of the needed data to initalize with.
	public function __construct( $afktrack )
	{
		$this->site_url = $afktrack->settings['site_address'];
		$this->api_key = $afktrack->settings['wordpress_api_key'];
		$this->akismet_useragent = 'AFKTrack/' . $afktrack->version . ' | Akismet/' . $this->akismet_version;

		$this->akismet_api_server = $this->api_key . '.' . $this->akismet_domain;
		$this->akismet_request_path = '/' . $this->akismet_version;

		$this->comment_data['blog'] = $this->site_url;
		$this->comment_data['user_ip'] = $afktrack->ip;
		$this->comment_data['user_agent'] = $afktrack->agent;
		$this->comment_data['referrer'] = $afktrack->referrer;
		$this->comment_data['blog_lang'] = 'en';
		$this->comment_data['blog_charset'] = 'UTF-8';
	}

	private function send_request( $request, $path, $server )
	{
		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $server\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n";
		$http_request .= "Content-Length: " . strlen( $request ) . "\r\n";
		$http_request .= "User-Agent: {$this->akismet_useragent}\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;

		$response = '';
		if( false != ( $fs = @fsockopen( 'ssl://' . $server, $this->akismet_server_port, $errno, $errstr, 10 ) ) ) {
 			fwrite( $fs, $http_request );

			while( !feof( $fs ) )
				$response .= fgets( $fs, 1160 );
			fclose( $fs );

			$response = explode( "\r\n\r\n", $response, 2 );
		}
		return $response;
	}

	private function create_query_string( $query )
	{
		foreach( $_SERVER as $key => $value ) {
			if( !in_array( $key, $this->server_vars_ignored ) ) {
				$this->comment_data[$key] = $value;
			}
		}

		$query_string = '';

		foreach( $this->comment_data as $key => $data ) {
			if( !is_array( $data ) ) {
				$query_string .= $key . '=' . urlencode( stripslashes( $data ) ) . '&';
			}
		}
		return $query_string;
	}

	// Used to check if your Akismet API Key is valid.
	public function is_key_valid()
	{
		$request = 'key=' . $this->api_key . '&blog=' . urlencode( stripslashes( $this->site_url ) );

		$response = $this->send_request( $request, $this->akismet_request_path . '/verify-key', $this->akismet_domain );

		return $response[1] == 'valid';
	}

	// Check if a submitted issue or comment might be spam.
	public function is_this_spam() {
		$query = $this->create_query_string();

		$response = $this->send_request( $query, $this->akismet_request_path . '/comment-check', $this->akismet_api_server );

		if( $response[1] == 'invalid' && !$this->is_key_valid() ) {
			throw new exception( 'The Akismet API Key for this site is not valid. If this issue persists, notify the site administrators.' );
		}

		return( $response[1] == 'true' );
	}

	// Used to report something that did not get marked as spam, but really is. [False Negative]
	public function submit_spam() {
		$query = $this->create_query_string();

		$response = $this->send_request( $query, $this->akismet_request_path . '/submit-spam', $this->akismet_api_server );

		if( $response[1] != 'Thanks for making the web a better place.' && !$this->is_key_valid() ) {
			throw new exception( 'The Akismet API Key for this site is not valid. If this issue persists, notify the site administrators.' );
		}
	}

	// Used to report something that isn't spam, but got marked as such. [False Positive]
	public function submit_ham() {
		$query = $this->create_query_string();

		$response = $this->send_request( $query, $this->akismet_request_path . '/submit-ham', $this->akismet_api_server );

		if( $response[1] != 'Thanks for making the web a better place.' && !$this->is_key_valid() ) {
			throw new exception( 'The Akismet API Key for this site is not valid. If this issue persists, notify the site administrators.' );
		}
	}

	// Override the reported IP Address. Useful for submitting spam/ham after the fact.
	public function set_comment_ip( $ip ) {
		$this->comment_data['user_ip'] = $ip;
	}

	// Override the useragent. Useful for submitting spam/ham after the fact.
	public function set_comment_useragent( $agent ) {
		$this->comment_data['user_agent'] = $agent;
	}

	// Override the referrer. Useful for submitting spam/ham after the fact.
	public function set_comment_referrer( $referrer ) {
		$this->comment_data['referrer'] = $referrer;
	}

	// The full permanent URL of the entry the comment was submitted to.
	public function set_permalink( $permalink ) {
		$this->comment_data['permalink'] = $permalink;
	}

	// A string that describes the type of content being sent.
	public function set_comment_type( $type ) {
		$this->comment_data['comment_type'] = $type;
	}

	// Name submitted with the comment.
	public function set_comment_author( $author ) {
		$this->comment_data['comment_author'] = $author;
	}

	// Email address submitted with the comment.
	public function set_comment_author_email( $email ) {
		$this->comment_data['comment_author_email'] = $email;
	}

	// URL submitted with comment. AFKTrack should only have received this from a bot since URL's are not collected from users.
	public function set_comment_author_url( $url ) {
		$this->comment_data['comment_author_url'] = $url;
	}

	// The content that was submitted.
	public function set_comment_content( $comment ) {
		$this->comment_data['comment_content'] = $comment;
	}

	// The time the original content was posted. Converts to ISO 8601 format. The 2 time values will be the same for AFKTrack.
	public function set_comment_time( $time ) {
		$date = new DateTime();
		$date->setTimestamp( $time );

		$iso_time = $date->format( 'c' );

		$this->comment_data['comment_date_gmt'] = $iso_time;
		$this->comment_data['comment_post_modified_gmt'] = $iso_time;
	}
}
?>