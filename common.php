<?php
/***************************************************************************
 *                                common.php
 *                            -------------------
 *   begin                : Saturday, Feb 23, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id$
 *
 *
 ***************************************************************************/


/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

//
// Define some basic configuration arrays this also prevents
// malicious rewriting of language and otherarray values via
// URI params
//
$board_config = Array();
$userdata = Array();
$theme = Array();
$images = Array();
$lang = Array();

if(empty($phpbb_root_path))
{
	$phpbb_root_path = "./";
}
include($phpbb_root_path . 'config.'.$phpEx);
include($phpbb_root_path . 'includes/constants.'.$phpEx);
include($phpbb_root_path . 'includes/template.'.$phpEx);
include($phpbb_root_path . 'includes/message.'.$phpEx);
include($phpbb_root_path . 'includes/sessions.'.$phpEx);
include($phpbb_root_path . 'includes/auth.'.$phpEx);
include($phpbb_root_path . 'includes/functions.'.$phpEx);
include($phpbb_root_path . 'includes/db.'.$phpEx);

//
// Obtain and encode users IP
//
if(!empty($HTTP_CLIENT_IP))
{
	$client_ip = (ereg("[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+", $HTTP_CLIENT_IP)) ? $HTTP_CLIENT_IP : $REMOTE_ADDR; 
}
else if(!empty($HTTP_X_FORWARDED_FOR))
{
	$client_ip = (ereg("([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)", $HTTP_X_FORWARDED_FOR, $ip_list)) ? $ip_list[0] : $REMOTE_ADDR;
}
else if(!empty($HTTP_PROXY_USER))
{
	$client_ip = (ereg("[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+", $HTTP_PROXY_USER)) ? $HTTP_PROXY_USER : $REMOTE_ADDR;
}
else
{
	$client_ip = $REMOTE_ADDR;
}
$user_ip = encode_ip($client_ip);

//
// Setup forum wide options, if this fails
// then we output a CRITICAL_ERROR since
// basic forum information is not available
//
$sql = "SELECT *
	FROM " . CONFIG_TABLE;
if(!$result = $db->sql_query($sql))
{
	message_die(CRITICAL_ERROR, "Could not query config information", "", __LINE__, __FILE__, $sql);
}
else
{
/*
	while($row = $db->sql_fetchrow($result))
	{
		$board_config[$row['config_var_name']] = stripslashes($row['config_var_value']);
	}
*/

	$config = $db->sql_fetchrow($result);

	$board_config['board_disable'] = $config['board_disable'];
	$board_config['board_startdate'] = $config['board_startdate'];
	$board_config['sitename'] = stripslashes($config['sitename']);
	$board_config['allow_html'] = $config['allow_html'];
	$board_config['allow_bbcode'] = $config['allow_bbcode'];
	$board_config['allow_smilies'] = $config['allow_smilies'];
	$board_config['allow_sig'] = $config['allow_sig'];
	$board_config['allow_namechange'] = $config['allow_namechange'];
	$board_config['allow_avatar_local'] = $config['allow_avatar_local'];
	$board_config['allow_avatar_remote'] = $config['allow_avatar_remote'];
	$board_config['allow_avatar_upload'] = $config['allow_avatar_upload'];
	$board_config['require_activation'] = $config['require_activation'];
	$board_config['override_user_themes'] = $config['override_themes'];
	$board_config['posts_per_page'] = $config['posts_per_page'];
	$board_config['topics_per_page'] = $config['topics_per_page'];
	$board_config['default_theme'] = $config['default_theme'];
	$board_config['default_dateformat'] = stripslashes($config['default_dateformat']);
	$board_config['default_template'] = stripslashes($config['sys_template']);
	$board_config['default_timezone'] = $config['system_timezone'];
	$board_config['default_lang'] = stripslashes($config['default_lang']);
	$board_config['board_email'] = stripslashes(str_replace("<br />", "\n", $config['email_sig']));
	$board_config['board_email_from'] = stripslashes($config['email_from']);
	$board_config['flood_interval'] = $config['flood_interval'];
	$board_config['session_length'] = $config['session_length'];
//	$board_config['session_max'] = $config['session_max'];
	$board_config['cookie_name'] = $config['cookie_name'];
	$board_config['cookie_path'] = $config['cookie_path'];
	$board_config['cookie_domain'] = $config['cookie_domain'];
	$board_config['cookie_secure'] = $config['cookie_secure'];
	$board_config['avatar_filesize'] = $config['avatar_filesize'];
	$board_config['avatar_max_width'] = $config['avatar_max_width'];
	$board_config['avatar_max_height'] = $config['avatar_max_height'];
	$board_config['avatar_path'] = $config['avatar_path'];
	$board_config['prune_enable'] = $config['prune_enable'];
	$board_config['gzip_compress'] = $config['gzip_compress'];
	$board_config['smtp_delivery'] = $config['smtp_delivery'];
	$board_config['smtp_host'] = $config['smtp_host'];
}

if($board_config['board_disable'])
{
	include($phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '.'.$phpEx);

	message_die(GENERAL_MESSAGE, $lang['Board_disable'], $lang['Information']);
}

?>