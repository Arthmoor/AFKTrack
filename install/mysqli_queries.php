<?php
/* AFKTrack v0.1-1.0 https://github.com/Arthmoor/AFKTrack
 * Copyright (c) 2017-2019 Roger Libiez aka Arthmoor
 * Based on the Sandbox package: https://github.com/Arthmoor/Sandbox
 */

if( !defined( 'AFKTRACK_INSTALLER' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

$queries[] = "DROP TABLE IF EXISTS %pactive";
$queries[] = "CREATE TABLE %pactive (
  active_action varchar(50) NOT NULL,
  active_time int(10) unsigned NOT NULL,
  active_ip varchar(40) NOT NULL,
  active_user_agent varchar(255) NOT NULL,
  PRIMARY KEY (active_ip)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pprojects";
$queries[] = "CREATE TABLE %pprojects (
  project_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  project_name varchar(100) NOT NULL DEFAULT '',
  project_description text NOT NULL,
  project_retired tinyint(2) unsigned NOT NULL DEFAULT '0',
  project_position int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (project_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pcategories";
$queries[] = "CREATE TABLE %pcategories (
  category_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  category_project int(10) unsigned NOT NULL DEFAULT '0',
  category_position int(10) unsigned NOT NULL DEFAULT '0',
  category_name varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (category_id),
  KEY category_project (category_project)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pcomponents";
$queries[] = "CREATE TABLE %pcomponents (
  component_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  component_project int(10) unsigned NOT NULL DEFAULT '0',
  component_name varchar(50) NOT NULL DEFAULT '',
  component_position int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (component_id),
  KEY component_project (component_project)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pissues";
$queries[] = "CREATE TABLE %pissues (
  issue_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  issue_summary varchar(100) NOT NULL DEFAULT '',
  issue_category int(10) unsigned NOT NULL DEFAULT '0',
  issue_date int(10) unsigned NOT NULL DEFAULT '0',
  issue_text mediumtext NOT NULL,
  issue_ruling mediumtext DEFAULT NULL,
  issue_user int(10) unsigned NOT NULL DEFAULT '0',
  issue_status int(10) unsigned NOT NULL DEFAULT '0',
  issue_component int(10) unsigned NOT NULL DEFAULT '0',
  issue_edited_date int(10) unsigned NOT NULL DEFAULT '0',
  issue_type int(10) unsigned NOT NULL DEFAULT '0',
  issue_project int(10) unsigned NOT NULL DEFAULT '0',
  issue_closed_date int(10) unsigned NOT NULL DEFAULT '0',
  issue_user_closed int(10) unsigned NOT NULL DEFAULT '0',
  issue_closed_comment tinytext,
  issue_resolution int(10) unsigned NOT NULL DEFAULT '0',
  issue_platform int(10) unsigned NOT NULL DEFAULT '1',
  issue_severity int(10) unsigned NOT NULL DEFAULT '0',
  issue_user_edited int(10) unsigned NOT NULL DEFAULT '0',
  issue_user_assigned int(10) unsigned NOT NULL DEFAULT '0',
  issue_flags int(10) unsigned NOT NULL DEFAULT '0',
  issue_votes int(10) unsigned NOT NULL DEFAULT '0',
  issue_comment_count int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (issue_id),
  KEY issue_date (issue_date)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pcomments";
$queries[] = "CREATE TABLE %pcomments (
  comment_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  comment_issue int(10) unsigned NOT NULL DEFAULT '0',
  comment_user int(10) unsigned NOT NULL DEFAULT '1',
  comment_date int(10) unsigned NOT NULL DEFAULT '0',
  comment_editdate int(10) unsigned NOT NULL DEFAULT '0',
  comment_message mediumtext NOT NULL,
  comment_editedby int(10) unsigned NOT NULL DEFAULT '0',
  comment_ip varchar(40) NOT NULL DEFAULT '127.0.0.1',
  comment_referrer tinytext,
  comment_agent tinytext,
  PRIMARY KEY (comment_id),
  KEY comment_issue (comment_issue),
  KEY comment_user (comment_user),
  KEY comment_date (comment_date)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pplatforms";
$queries[] = "CREATE TABLE %pplatforms (
  platform_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  platform_name varchar(30) NOT NULL DEFAULT '',
  platform_position int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (platform_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pstatus";
$queries[] = "CREATE TABLE %pstatus (
  status_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  status_name varchar(50) NOT NULL DEFAULT '',
  status_position int(10) unsigned NOT NULL DEFAULT '0',
  status_shows int(2) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (status_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pseverities";
$queries[] = "CREATE TABLE %pseverities (
  severity_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  severity_name varchar(50) NOT NULL DEFAULT '',
  severity_position int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (severity_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %presolutions";
$queries[] = "CREATE TABLE %presolutions (
  resolution_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  resolution_name varchar(50) NOT NULL DEFAULT '',
  resolution_position int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (resolution_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %ptypes";
$queries[] = "CREATE TABLE %ptypes (
  type_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  type_name varchar(50) NOT NULL DEFAULT '',
  type_position int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (type_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %prelated";
$queries[] = "CREATE TABLE %prelated (
  related_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  related_this int(10) unsigned NOT NULL DEFAULT '0',
  related_other int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (related_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pvotes";
$queries[] = "CREATE TABLE %pvotes (
  vote_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  vote_user int(10) unsigned NOT NULL DEFAULT '0',
  vote_issue int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (vote_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pwatching";
$queries[] = "CREATE TABLE %pwatching (
  watch_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  watch_issue int(10) unsigned NOT NULL DEFAULT '0',
  watch_user int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (watch_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pattachments";
$queries[] = "CREATE TABLE %pattachments (
  attachment_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  attachment_issue int(10) unsigned NOT NULL DEFAULT '0',
  attachment_comment int(10) unsigned NOT NULL DEFAULT '0',
  attachment_name varchar(255) NOT NULL,
  attachment_filename varchar(100) NOT NULL,
  attachment_type varchar(50) NOT NULL DEFAULT '',
  attachment_size int(20) unsigned NOT NULL,
  attachment_user int(10) unsigned NOT NULL DEFAULT '0',
  attachment_date int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (attachment_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pemojis";
$queries[] = "CREATE TABLE %pemojis (
  emoji_id int(10) unsigned NOT NULL auto_increment,
  emoji_string varchar(15) NOT NULL default '',
  emoji_image varchar(255) NOT NULL default '',
  emoji_clickable tinyint(1) unsigned NOT NULL default '1',
  PRIMARY KEY  (emoji_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %psettings";
$queries[] = "CREATE TABLE %psettings (
  settings_id tinyint(2) NOT NULL AUTO_INCREMENT,
  settings_version smallint(2) NOT NULL default 1,
  settings_value text NOT NULL,
  PRIMARY KEY (settings_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pspam";
$queries[] = "CREATE TABLE %pspam (
  spam_id int(12) unsigned NOT NULL AUTO_INCREMENT,
  spam_issue int(10) unsigned NOT NULL DEFAULT '0',
  spam_user int(10) unsigned NOT NULL DEFAULT '0',
  spam_type int(10) unsigned NOT NULL DEFAULT '0',
  spam_date int(10) unsigned NOT NULL,
  spam_url varchar(100) DEFAULT '',
  spam_ip varchar(40) NOT NULL,
  spam_comment text NOT NULL,
  spam_server text NOT NULL,
  PRIMARY KEY (spam_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pusers";
$queries[] = "CREATE TABLE %pusers (
  user_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  user_name varchar(50) NOT NULL DEFAULT '',
  user_email varchar(100) NOT NULL DEFAULT '',
  user_joined int(10) unsigned NOT NULL DEFAULT '0',
  user_last_visit int(10) unsigned NOT NULL DEFAULT '0',
  user_issue_count int(10) unsigned NOT NULL DEFAULT '0',
  user_comment_count int(10) unsigned NOT NULL DEFAULT '0',
  user_password varchar(255) NOT NULL DEFAULT '',
  user_issues_page int(10) unsigned NOT NULL DEFAULT '0',
  user_icon_type smallint(2) unsigned NOT NULL DEFAULT '1',
  user_icon varchar(50) DEFAULT NULL,
  user_level smallint(2) unsigned NOT NULL DEFAULT '1',
  user_perms smallint(4) unsigned NOT NULL DEFAULT '1',
  user_comments_page int(10) unsigned DEFAULT '0',
  user_ip varchar(40) NOT NULL DEFAULT '127.0.0.1',
  user_url varchar(100) DEFAULT '',
  user_timezone varchar(255) NOT NULL DEFAULT 'Europe/London',
  PRIMARY KEY (user_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %preopen";
$queries[] = "CREATE TABLE %preopen (
  reopen_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  reopen_issue int(10) unsigned NOT NULL DEFAULT '0',
  reopen_project int(10) unsigned NOT NULL DEFAULT '0',
  reopen_user int(10) unsigned NOT NULL DEFAULT '0',
  reopen_date int(10) unsigned NOT NULL DEFAULT '0',
  reopen_reason mediumtext NOT NULL,
  PRIMARY KEY (reopen_id),
  KEY reopen_issue (reopen_issue)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
?>