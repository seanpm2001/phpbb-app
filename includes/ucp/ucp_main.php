<?php
/** 
*
* @package ucp
* @version $Id$
* @copyright (c) 2005 phpBB Group 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
* ucp_main
* UCP Front Panel
* @package ucp
*/
class ucp_main
{
	var $p_master;
	var $u_action;
	
	function ucp_main(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template, $phpbb_root_path, $phpEx;

		switch ($mode)
		{
			case 'front':

				$user->add_lang('memberlist');

				$sql_from = TOPICS_TABLE . ' t ';
				$sql_select = '';

				if ($config['load_db_track'])
				{
					$sql_from .= ' LEFT JOIN ' . TOPICS_POSTED_TABLE . ' tp ON (tp.topic_id = t.topic_id 
						AND tp.user_id = ' . $user->data['user_id'] . ')';
					$sql_select .= ', tp.topic_posted';
				}

				if ($config['load_db_lastread'])
				{
					$sql_from .= ' LEFT JOIN ' . TOPICS_TRACK_TABLE . ' tt ON (tt.topic_id = t.topic_id
						AND tt.user_id = ' . $user->data['user_id'] . ')';
					$sql_select .= ', tt.mark_time';
				}

				$topic_type = $user->lang['VIEW_TOPIC_GLOBAL'];
				$folder = 'folder_global';
				$folder_new = $folder . '_new';

				// Get cleaned up list... return only those forums not having the f_read permission
				$forum_ary = $auth->acl_getf('!f_read', true);
				$forum_ary = array_unique(array_keys($forum_ary));

				// Determine first forum the user is able to read into - for global announcement link
				$sql = 'SELECT forum_id 
					FROM ' . FORUMS_TABLE . '
					WHERE forum_type = ' . FORUM_POST;
	
				if (sizeof($forum_ary))
				{
					$sql .= ' AND forum_id NOT IN ( ' . implode(', ', $forum_ary) . ')';
				}
				$result = $db->sql_query_limit($sql, 1);
				$g_forum_id = (int) $db->sql_fetchfield('forum_id');
				$db->sql_freeresult($result);

				$sql = "SELECT t.* $sql_select 
					FROM $sql_from
					WHERE t.forum_id = 0
						AND t.topic_type = " . POST_GLOBAL . '
					ORDER BY t.topic_last_post_time DESC';
				$result = $db->sql_query($sql);

				$topic_list = $rowset = array();
				while ($row = $db->sql_fetchrow($result))
				{
					$topic_list[] = $row['topic_id'];
					$rowset[$row['topic_id']] = $row;
				}
				$db->sql_freeresult($result);

				$topic_tracking_info = array();
				if ($config['load_db_lastread'])
				{
					$topic_tracking_info = get_topic_tracking(0, $topic_list, $rowset, false, $topic_list);
				}
				else
				{
					$topic_tracking_info = get_complete_topic_tracking(0, $topic_list, $topic_list);
				}

				foreach ($topic_list as $topic_id)
				{
					$row = &$rowset[$topic_id];

					$forum_id = $row['forum_id'];
					$topic_id = $row['topic_id'];

					$unread_topic = (isset($topic_tracking_info[$topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;

					$folder_img = ($unread_topic) ? $folder_new : $folder;
					$folder_alt = ($unread_topic) ? 'NEW_POSTS' : (($row['topic_status'] == ITEM_LOCKED) ? 'TOPIC_LOCKED' : 'NO_NEW_POSTS');

					// Posted image?
					if (!empty($row['topic_posted']) && $row['topic_posted'])
					{
						$folder_img .= '_posted';
					}

					$template->assign_block_vars('topicrow', array(
						'FORUM_ID'			=> $forum_id,
						'TOPIC_ID'			=> $topic_id,
						'LAST_POST_TIME'	=> $user->format_date($row['topic_last_post_time']),
						'LAST_POST_AUTHOR'	=> ($row['topic_last_poster_id'] == ANONYMOUS) ? (($row['topic_last_poster_name'] != '') ? $row['topic_last_poster_name'] . ' ' : $user->lang['GUEST'] . ' ') : $row['topic_last_poster_name'],
						'TOPIC_TITLE'		=> censor_text($row['topic_title']),
						'TOPIC_TYPE'		=> $topic_type,

						'LAST_POST_IMG'			=> $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
						'NEWEST_POST_IMG'		=> $user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
						'TOPIC_FOLDER_IMG'		=> $user->img($folder_img, $folder_alt),
						'TOPIC_FOLDER_IMG_SRC'	=> $user->img($folder_img, $folder_alt, false, '', 'src'),
						'ATTACH_ICON_IMG'		=> ($auth->acl_gets('f_download', 'u_download', $forum_id) && $row['topic_attachment']) ? $user->img('icon_topic_attach', '') : '',

						'S_USER_POSTED'		=> (!empty($row['topic_posted']) && $row['topic_posted']) ? true : false,
						'S_UNREAD'			=> $unread_topic,

						'U_LAST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$g_forum_id&amp;t=$topic_id&amp;p=" . $row['topic_last_post_id']) . '#p' . $row['topic_last_post_id'],
						'U_LAST_POST_AUTHOR'	=> ($row['topic_last_poster_id'] != ANONYMOUS) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&amp;u='  . $row['topic_last_poster_id']) : '',
						'U_NEWEST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$g_forum_id&amp;t=$topic_id&amp;view=unread") . '#unread',
						'U_VIEW_TOPIC'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$g_forum_id&amp;t=$topic_id"))
					);
				}

				if ($config['load_user_activity'])
				{
					if (!function_exists('display_user_activity'))
					{
						include_once($phpbb_root_path . 'includes/functions_display.' . $phpEx);
					}
					display_user_activity($user->data);
				}

				// Do the relevant calculations 
				$memberdays = max(1, round((time() - $user->data['user_regdate']) / 86400));
				$posts_per_day = $user->data['user_posts'] / $memberdays;
				$percentage = ($config['num_posts']) ? min(100, ($user->data['user_posts'] / $config['num_posts']) * 100) : 0;

				$template->assign_vars(array(
					'USER_COLOR'		=> (!empty($user->data['user_colour'])) ? $user->data['user_colour'] : '', 
					'JOINED'			=> $user->format_date($user->data['user_regdate']),
					'VISITED'			=> (empty($last_visit)) ? ' - ' : $user->format_date($last_visit),
					'WARNINGS'			=> ($user->data['user_warnings']) ? $user->data['user_warnings'] : 0,
					'POSTS'				=> ($user->data['user_posts']) ? $user->data['user_posts'] : 0,
					'POSTS_DAY'			=> sprintf($user->lang['POST_DAY'], $posts_per_day),
					'POSTS_PCT'			=> sprintf($user->lang['POST_PCT'], $percentage),

					'OCCUPATION'	=> (!empty($row['user_occ'])) ? $row['user_occ'] : '',
					'INTERESTS'		=> (!empty($row['user_interests'])) ? $row['user_interests'] : '',

//					'S_GROUP_OPTIONS'	=> $group_options, 
					'S_SHOW_ACTIVITY'	=> ($config['load_user_activity']) ? true : false,

					'U_SEARCH_USER'		=> ($auth->acl_get('u_search')) ? append_sid("{$phpbb_root_path}search.$phpEx", 'author_id=' . $user->data['user_id'] . '&amp;sr=posts') : '')
				);
			break;

			case 'subscribed':

				include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

				$user->add_lang('viewforum');

				$unwatch = (isset($_POST['unwatch'])) ? true : false;

				if ($unwatch)
				{
					$forums = (isset($_POST['f'])) ? implode(', ', array_map('intval', array_keys($_POST['f']))) : false;
					$topics = (isset($_POST['t'])) ? implode(', ', array_map('intval', array_keys($_POST['t']))) : false;

					if ($forums || $topics)
					{
						$l_unwatch = '';
						if ($forums)
						{
							$sql = 'DELETE FROM ' . FORUMS_WATCH_TABLE . "
								WHERE forum_id IN ($forums) 
									AND user_id = " . $user->data['user_id'];
							$db->sql_query($sql);

							$l_unwatch .= '_FORUMS';
						}

						if ($topics)
						{
							$sql = 'DELETE FROM ' . TOPICS_WATCH_TABLE . "
								WHERE topic_id IN ($topics) 
									AND user_id = " . $user->data['user_id'];
							$db->sql_query($sql);

							$l_unwatch .= '_TOPICS';
						}

						$message = $user->lang['UNWATCHED' . $l_unwatch] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . append_sid("{$phpbb_root_path}ucp.$phpEx", "i=$id&amp;mode=subscribed") . '">', '</a>');

						meta_refresh(3, append_sid("{$phpbb_root_path}ucp.$phpEx", "i=$id&amp;mode=subscribed"));
						trigger_error($message);
					}
				}

				$sql_array = array(
					'SELECT'	=> 'f.*',

					'FROM'		=> array(
						FORUMS_WATCH_TABLE	=> 'fw',
						FORUMS_TABLE		=> 'f'
					),

					'WHERE'		=> 'fw.user_id = ' . $user->data['user_id'] . ' 
						AND f.forum_id = fw.forum_id',

					'ORDER_BY'	=> 'left_id'
				);

				if ($config['load_db_lastread'])
				{
					$sql_array['LEFT_JOIN'] = array(
						array(
							'FROM'	=> array(FORUMS_TRACK_TABLE => 'ft'),
							'ON'	=> 'ft.user_id = ' . $user->data['user_id'] . ' AND ft.forum_id = f.forum_id'
						)
					);

					$sql_array['SELECT'] .= ', ft.mark_time ';
				}
				else
				{
					$tracking_topics = (isset($_COOKIE[$config['cookie_name'] . '_track'])) ? ((STRIP) ? stripslashes($_COOKIE[$config['cookie_name'] . '_track']) : $_COOKIE[$config['cookie_name'] . '_track']) : '';
					$tracking_topics = ($tracking_topics) ? unserialize($tracking_topics) : array();
				}

				$sql = $db->sql_build_query('SELECT', $sql_array);
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$forum_id = $row['forum_id'];

					if ($config['load_db_lastread'])
					{
						$forum_check = (!empty($row['mark_time'])) ? $row['mark_time'] : $user->data['user_lastmark'];
					}
					else
					{
						$forum_check = (isset($tracking_topics['f'][$forum_id])) ? (int) (base_convert($tracking_topics['f'][$forum_id], 36, 10) + $config['board_startdate']) : $user->data['user_lastmark'];
					}

					$unread_forum = ($row['forum_last_post_time'] > $forum_check) ? true : false;

					// Which folder should we display?
					if ($row['forum_status'] == ITEM_LOCKED)
					{
						$folder_image = ($unread_forum) ? 'folder_lock_new' : 'folder_lock';
						$folder_alt = 'FORUM_LOCKED';
					}
					else
					{
						$folder_image = ($unread_forum) ? 'folder_new' : 'folder';
						$folder_alt = ($unread_forum) ? 'NEW_POSTS' : 'NO_NEW_POSTS';
					}

					// Create last post link information, if appropriate
					if ($row['forum_last_post_id'])
					{
						$last_post_time = $user->format_date($row['forum_last_post_time']);

						$last_poster = ($row['forum_last_poster_name'] != '') ? $row['forum_last_poster_name'] : $user->lang['GUEST'];
						$last_poster_url = ($row['forum_last_poster_id'] == ANONYMOUS) ? '' : append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&amp;u='  . $row['forum_last_poster_id']);

						$last_post_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;p=" . $row['forum_last_post_id']) . '#p' . $row['forum_last_post_id'];
					}
					else
					{
						$last_post_time = $last_poster = $last_poster_url = $last_post_url = '';
					}

					$template->assign_block_vars('forumrow', array(
						'FORUM_ID'				=> $forum_id, 
						'FORUM_FOLDER_IMG'		=> $user->img($folder_image, $folder_alt),
						'FORUM_FOLDER_IMG_SRC'	=> $user->img($folder_image, $folder_alt, false, '', 'src'),
						'FORUM_NAME'			=> $row['forum_name'],
						'LAST_POST_IMG'			=> $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
						'LAST_POST_TIME'		=> $last_post_time,
						'LAST_POST_AUTHOR'		=> $last_poster,

						'U_LAST_POST_AUTHOR'	=> $last_poster_url, 
						'U_LAST_POST'			=> $last_post_url, 
						'U_VIEWFORUM'			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $row['forum_id']))
					);
				}
				$db->sql_freeresult($result);

				// Subscribed Topics
				$start = request_var('start', 0);
	
				$sql = 'SELECT COUNT(topic_id) as topics_count
					FROM ' . TOPICS_WATCH_TABLE . '
					WHERE user_id = ' . $user->data['user_id'];
				$result = $db->sql_query($sql);
				$topics_count = (int) $db->sql_fetchfield('topics_count');
				$db->sql_freeresult($result);

				if ($topics_count)
				{
					$template->assign_vars(array(
						'PAGINATION'	=> generate_pagination($this->u_action, $topics_count, $config['topics_per_page'], $start),
						'PAGE_NUMBER'	=> on_page($topics_count, $config['topics_per_page'], $start),
						'TOTAL_TOPICS'	=> ($topics_count == 1) ? $user->lang['VIEW_FORUM_TOPIC'] : sprintf($user->lang['VIEW_FORUM_TOPICS'], $topics_count))
					);
				}

				$sql_array = array(
					'SELECT'	=> 't.*',

					'FROM'		=> array(
						TOPICS_WATCH_TABLE	=> 'tw',
						TOPICS_TABLE		=> 't'
					),

					'WHERE'		=> 'tw.user_id = ' . $user->data['user_id'] . '
						AND t.topic_id = tw.topic_id',

					'ORDER_BY'	=> 't.topic_last_post_time DESC'
				);

				if ($config['load_db_lastread'])
				{
					$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TRACK_TABLE => 'ft'), 'ON' => 'ft.forum_id = t.forum_id AND ft.user_id = ' . $user->data['user_id']);
					$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_TRACK_TABLE => 'tt'), 'ON' => 'tt.topic_id = t.topic_id AND tt.user_id = ' . $user->data['user_id']);
					$sql_array['SELECT'] .= ', tt.mark_time, ft.mark_time AS forum_mark_time';
				}

				if ($config['load_db_track'])
				{
					$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_POSTED_TABLE => 'tp'), 'ON' => 'tp.topic_id = t.topic_id AND tp.user_id = ' . $user->data['user_id']);
					$sql_array['SELECT'] .= ', tp.topic_posted';
				}

				$sql = $db->sql_build_query('SELECT', $sql_array);
				$result = $db->sql_query_limit($sql, $config['topics_per_page'], $start);

				$topic_list = $topic_forum_list = $global_announce_list = $rowset = array();
				while ($row = $db->sql_fetchrow($result))
				{
					$topic_list[] = $row['topic_id'];
					$rowset[$row['topic_id']] = $row;

					$topic_forum_list[$row['forum_id']]['forum_mark_time'] = ($config['load_db_lastread']) ? $row['forum_mark_time'] : 0;
					$topic_forum_list[$row['forum_id']]['topics'][] = $row['topic_id'];

					if ($row['topic_type'] == POST_GLOBAL)
					{
						$global_announce_list[] = $row['topic_id'];
					}
				}
				$db->sql_freeresult($result);

				$topic_tracking_info = array();
				if ($config['load_db_lastread'])
				{
					foreach ($topic_forum_list as $f_id => $topic_row)
					{
						$topic_tracking_info += get_topic_tracking($f_id, $topic_row['topics'], $rowset, array($f_id => $topic_row['forum_mark_time']), ($f_id == 0) ? $global_announce_list : false);
					}
				}
				else
				{
					foreach ($topic_forum_list as $f_id => $topic_row)
					{
						$topic_tracking_info += get_complete_topic_tracking($f_id, $topic_row['topics'], $global_announce_list);
					}
				}

				foreach ($topic_list as $topic_id)
				{
					$row = &$rowset[$topic_id];

					$forum_id = $row['forum_id'];
					$topic_id = $row['topic_id'];

					$unread_topic = (isset($topic_tracking_info[$topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;

					// Replies
					$replies = ($auth->acl_get('m_approve', $forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];

					if ($row['topic_status'] == ITEM_MOVED)
					{
						$topic_id = $row['topic_moved_id'];
					}

					// Get folder img, topic status/type related informations
					$folder_img = $folder_alt = $topic_type = '';
					topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);
					
					$view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id");
					
					// Send vars to template
					$template->assign_block_vars('topicrow', array(
						'FORUM_ID'			=> $forum_id,
						'TOPIC_ID'			=> $topic_id,
						'TOPIC_AUTHOR'		=> topic_topic_author($row),
						'FIRST_POST_TIME'	=> $user->format_date($row['topic_time']),
						'LAST_POST_TIME'	=> $user->format_date($row['topic_last_post_time']),
						'LAST_VIEW_TIME'	=> $user->format_date($row['topic_last_view_time']),
						'LAST_POST_AUTHOR'	=> ($row['topic_last_poster_name'] != '') ? $row['topic_last_poster_name'] : $user->lang['GUEST'],
						'PAGINATION'		=> topic_generate_pagination($replies, append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . (($row['forum_id']) ? $row['forum_id'] : $forum_id) . "&amp;t=$topic_id")),
						'REPLIES'			=> $replies,
						'VIEWS'				=> $row['topic_views'],
						'TOPIC_TITLE'		=> censor_text($row['topic_title']),
						'TOPIC_TYPE'		=> $topic_type,

						'LAST_POST_IMG'			=> $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
						'NEWEST_POST_IMG'		=> $user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
						'TOPIC_FOLDER_IMG'		=> $user->img($folder_img, $folder_alt),
						'TOPIC_FOLDER_IMG_SRC'	=> $user->img($folder_img, $folder_alt, false, '', 'src'),
						'TOPIC_ICON_IMG'		=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['img'] : '',
						'TOPIC_ICON_IMG_WIDTH'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['width'] : '',
						'TOPIC_ICON_IMG_HEIGHT'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['height'] : '',
						'ATTACH_ICON_IMG'		=> ($auth->acl_gets('f_download', 'u_download', $forum_id) && $row['topic_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',

						'S_TOPIC_TYPE'			=> $row['topic_type'],
						'S_USER_POSTED'			=> (!empty($row['topic_posted'])) ? true : false,
						'S_UNREAD_TOPIC'		=> $unread_topic,

						'U_NEWEST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id&amp;view=unread") . '#unread',
						'U_LAST_POST'			=> $view_topic_url . '&amp;p=' . $row['topic_last_post_id'] . '#p' . $row['topic_last_post_id'],
						'U_LAST_POST_AUTHOR'	=> ($row['topic_last_poster_id'] != ANONYMOUS && $row['topic_last_poster_id']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&amp;u=' . $row['topic_last_poster_id']) : '',
						'U_VIEW_TOPIC'			=> $view_topic_url)
					);
				}

			break;

			case 'bookmarks':

				if (!$config['allow_bookmarks'])
				{
					$template->assign_vars(array(
						'S_NO_DISPLAY_BOOKMARKS'	=> true)
					);
					break;
				}

				include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

				$user->add_lang('viewforum');

				$move_up = request_var('move_up', 0);
				$move_down = request_var('move_down', 0);

				$sql = 'SELECT MAX(order_id) as max_order_id
					FROM ' . BOOKMARKS_TABLE . '
					WHERE user_id = ' . $user->data['user_id'];
				$result = $db->sql_query($sql);
				$max_order_id = (int) $db->sql_fetchfield('max_order_id');
				$db->sql_freeresult($result);

				if ($move_up || $move_down)
				{
					if (($move_up && $move_up != 1) || ($move_down && $move_down != $max_order_id))
					{
						$order = ($move_up) ? $move_up : $move_down;
						$order_total = $order * 2 + (($move_up) ? -1 : 1);
		
						$sql = 'UPDATE ' . BOOKMARKS_TABLE . "
							SET order_id = $order_total - order_id
							WHERE order_id IN ($order, " . (($move_up) ? $order - 1 : $order + 1) . ')
								AND user_id = ' . $user->data['user_id'];
						$db->sql_query($sql);
					}
				}

				if (isset($_POST['unbookmark']))
				{
					$s_hidden_fields = array('unbookmark' => 1);
					$topics = (isset($_POST['t'])) ? array_map('intval', array_keys($_POST['t'])) : array();
					$url = $this->u_action;

					if (!sizeof($topics))
					{
						trigger_error('NO_BOOKMARKS_SELECTED');
					}

					foreach ($topics as $topic_id)
					{
						$s_hidden_fields['t'][$topic_id] = 1;
					}

					if (confirm_box(true))
					{
						$sql = 'DELETE FROM ' . BOOKMARKS_TABLE . '
							WHERE user_id = ' . $user->data['user_id'] . '
								AND topic_id IN (' . implode(', ', $topics) . ')';
						$db->sql_query($sql);

						// Re-Order bookmarks (possible with one query? This query massaker is not really acceptable...)
						$sql = 'SELECT topic_id FROM ' . BOOKMARKS_TABLE . '
							WHERE user_id = ' . $user->data['user_id'] . '
							ORDER BY order_id ASC';
						$result = $db->sql_query($sql);

						$i = 1;
						while ($row = $db->sql_fetchrow($result))
						{
							$sql = 'UPDATE ' . BOOKMARKS_TABLE . "
								SET order_id = $i
								WHERE topic_id = {$row['topic_id']}
									AND user_id = {$user->data['user_id']}";
							$db->sql_query($sql);

							$i++;
						}
						$db->sql_freeresult($result);

						meta_refresh(3, $url);
						$message = $user->lang['BOOKMARKS_REMOVED'] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . $url . '">', '</a>');
						trigger_error($message);
					}
					else
					{
						confirm_box(false, 'REMOVE_SELECTED_BOOKMARKS', build_hidden_fields($s_hidden_fields));
					}
				}

				// We grab deleted topics here too...
				// NOTE: At the moment bookmarks are not removed with topics, might be useful later (not really sure how though. :D)
				// But since bookmarks are sensible to the user, they should not be deleted without notice.
				$sql = 'SELECT b.order_id, b.topic_id as b_topic_id, t.*, f.forum_name
					FROM ' . BOOKMARKS_TABLE . ' b
						LEFT JOIN ' . TOPICS_TABLE . ' t ON (b.topic_id = t.topic_id)
						LEFT JOIN ' . FORUMS_TABLE . ' f ON (t.forum_id = f.forum_id)
					WHERE b.user_id = ' . $user->data['user_id'] . '
					ORDER BY b.order_id ASC';
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$forum_id = $row['forum_id'];
					$topic_id = $row['b_topic_id'];

					$replies = ($auth->acl_get('m_approve', $forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];

					// Get folder img, topic status/type related informations
					$folder_img = $folder_alt = $topic_type = '';
					$unread_topic = false;

					topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);
					$view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f=$forum_id&amp;t=$topic_id");

					$template->assign_block_vars('topicrow', array(
						'FORUM_ID'			=> $forum_id,
						'TOPIC_ID'			=> $topic_id,
						'TOPIC_TITLE'		=> censor_text($row['topic_title']),
						'TOPIC_TYPE'		=> $topic_type,
						'FORUM_NAME'		=> $row['forum_name'],

						'S_DELETED_TOPIC'	=> (!$row['topic_id']) ? true : false,
						'S_GLOBAL_TOPIC'	=> (!$forum_id) ? true : false,

						'TOPIC_AUTHOR'		=> topic_topic_author($row),
						'FIRST_POST_TIME'	=> $user->format_date($row['topic_time']),
						'LAST_POST_TIME'	=> $user->format_date($row['topic_last_post_time']),
						'LAST_VIEW_TIME'	=> $user->format_date($row['topic_last_view_time']),
						'LAST_POST_AUTHOR'	=> ($row['topic_last_poster_name'] != '') ? $row['topic_last_poster_name'] : $user->lang['GUEST'],
						'PAGINATION'		=> topic_generate_pagination($replies, append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . (($row['forum_id']) ? $row['forum_id'] : $forum_id) . "&amp;t=$topic_id")),

						'POSTED_AT'			=> $user->format_date($row['topic_time']),

						'TOPIC_FOLDER_IMG'		=> $user->img($folder_img, $folder_alt),
						'TOPIC_FOLDER_IMG_SRC'	=> $user->img($folder_img, $folder_alt, false, '', 'src'),
						'ATTACH_ICON_IMG'		=> ($auth->acl_gets('f_download', 'u_download', $forum_id) && $row['topic_attachment']) ? $user->img('icon_topic_attach', '') : '',
						'LAST_POST_IMG'			=> $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),

						'U_LAST_POST'			=> $view_topic_url . '&amp;p=' . $row['topic_last_post_id'] . '#p' . $row['topic_last_post_id'],
						'U_LAST_POST_AUTHOR'	=> ($row['topic_last_poster_id'] != ANONYMOUS && $row['topic_last_poster_id']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&amp;u=' . $row['topic_last_poster_id']) : '',
						'U_VIEW_TOPIC'			=> $view_topic_url,
						'U_VIEW_FORUM'			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),
						'U_MOVE_UP'				=> ($row['order_id'] != 1) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=main&amp;mode=bookmarks&amp;move_up=' . $row['order_id']) : '',
						'U_MOVE_DOWN'			=> ($row['order_id'] != $max_order_id) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=main&amp;mode=bookmarks&amp;move_down=' . $row['order_id']) : '')
					);
				}

			break;

			case 'drafts':

				$pm_drafts = ($this->p_master->p_name == 'pm') ? true : false;
				$template->assign_var('S_SHOW_DRAFTS', true);

				$user->add_lang('posting');

				$edit		= (isset($_REQUEST['edit'])) ? true : false;
				$submit		= (isset($_POST['submit'])) ? true : false;
				$draft_id	= ($edit) ? intval($_REQUEST['edit']) : 0;
				$delete		= (isset($_POST['delete'])) ? true : false;

				$s_hidden_fields = ($edit) ? '<input type="hidden" name="edit" value="' . $draft_id . '" />' : '';
				$draft_subject = $draft_message = '';

				if ($delete)
				{
					$drafts = (isset($_POST['d'])) ? implode(', ', array_map('intval', array_keys($_POST['d']))) : '';

					if ($drafts)
					{
						$sql = 'DELETE FROM ' . DRAFTS_TABLE . "
							WHERE draft_id IN ($drafts) 
								AND user_id = " .$user->data['user_id'];
						$db->sql_query($sql);

						$message = $user->lang['DRAFTS_DELETED'] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');

						meta_refresh(3, $this->u_action);
						trigger_error($message);
					}
				}

				if ($submit && $edit)
				{
					$draft_subject = request_var('subject', '', true);
					$draft_message = request_var('message', '', true);

					if ($draft_message && $draft_subject)
					{
						$draft_row = array(
							'draft_subject' => $draft_subject,
							'draft_message' => $draft_message
						);

						$sql = 'UPDATE ' . DRAFTS_TABLE . ' 
							SET ' . $db->sql_build_array('UPDATE', $draft_row) . " 
							WHERE draft_id = $draft_id
								AND user_id = " . $user->data['user_id'];
						$db->sql_query($sql);

						$message = $user->lang['DRAFT_UPDATED'] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');

						meta_refresh(3, $this->u_action);
						trigger_error($message);
					}
					else
					{
						$template->assign_var('ERROR', ($draft_message == '') ? $user->lang['EMPTY_DRAFT'] : (($draft_subject == '') ? $user->lang['EMPTY_DRAFT_TITLE'] : ''));
					}
				}

				if (!$pm_drafts)
				{
					$sql = 'SELECT d.*, f.forum_name
						FROM ' . DRAFTS_TABLE . ' d, ' . FORUMS_TABLE . ' f
						WHERE d.user_id = ' . $user->data['user_id'] . ' ' .
							(($edit) ? "AND d.draft_id = $draft_id" : '') . '
							AND f.forum_id = d.forum_id
						ORDER BY d.save_time DESC';
				}
				else
				{
					$sql = 'SELECT * FROM ' . DRAFTS_TABLE . '
						WHERE user_id = ' . $user->data['user_id'] . ' ' .
							(($edit) ? "AND draft_id = $draft_id" : '') . '
							AND forum_id = 0 
							AND topic_id = 0
						ORDER BY save_time DESC';
				}
				$result = $db->sql_query($sql);

				$draftrows = $topic_ids = array();

				while ($row = $db->sql_fetchrow($result))
				{
					if ($row['topic_id'])
					{
						$topic_ids[] = (int) $row['topic_id'];
					}
					$draftrows[] = $row;
				}
				$db->sql_freeresult($result);

				if (sizeof($topic_ids))
				{
					$sql = 'SELECT topic_id, forum_id, topic_title
						FROM ' . TOPICS_TABLE . '
						WHERE topic_id IN (' . implode(',', array_unique($topic_ids)) . ')';
					$result = $db->sql_query($sql);

					while ($row = $db->sql_fetchrow($result))
					{
						$topic_rows[$row['topic_id']] = $row;
					}
					$db->sql_freeresult($result);
				}
				unset($topic_ids);

				$template->assign_var('S_EDIT_DRAFT', $edit);

				$row_count = 0;
				foreach ($draftrows as $draft)
				{
					$link_topic = $link_forum = $link_pm = false;
					$insert_url = $view_url = $title = '';

					if (isset($topic_rows[$draft['topic_id']]) && $auth->acl_get('f_read', $topic_rows[$draft['topic_id']]['forum_id']))
					{
						$link_topic = true;
						$view_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $topic_rows[$draft['topic_id']]['forum_id'] . '&amp;t=' . $draft['topic_id']);
						$title = $topic_rows[$draft['topic_id']]['topic_title'];

						$insert_url = append_sid("{$phpbb_root_path}posting.$phpEx", 'f=' . $topic_rows[$draft['topic_id']]['forum_id'] . '&amp;t=' . $draft['topic_id'] . '&amp;mode=reply&amp;d=' . $draft['draft_id']);
					}
					else if ($auth->acl_get('f_read', $draft['forum_id']))
					{
						$link_forum = true;
						$view_url = append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $draft['forum_id']);
						$title = $draft['forum_name'];

						$insert_url = append_sid("{$phpbb_root_path}posting.$phpEx", 'f=' . $draft['forum_id'] . '&amp;mode=post&amp;d=' . $draft['draft_id']);
					}
					else if ($pm_drafts)
					{
						$link_pm = true;
						$insert_url = append_sid("{$phpbb_root_path}ucp.$phpEx", "i=$id&amp;mode=compose&amp;d=" . $draft['draft_id']);
					}

					$template_row = array(
						'DATE'			=> $user->format_date($draft['save_time']),
						'DRAFT_MESSAGE'	=> ($submit) ? $draft_message : $draft['draft_message'],
						'DRAFT_SUBJECT'	=> ($submit) ? $draft_subject : $draft['draft_subject'],
						'TITLE'			=> $title,

						'DRAFT_ID'	=> $draft['draft_id'],
						'FORUM_ID'	=> $draft['forum_id'],
						'TOPIC_ID'	=> $draft['topic_id'],

						'U_VIEW'		=> $view_url,
						'U_VIEW_EDIT'	=> $this->u_action . '&amp;edit=' . $draft['draft_id'],
						'U_INSERT'		=> $insert_url,

						'S_LINK_TOPIC'		=> $link_topic,
						'S_LINK_FORUM'		=> $link_forum,
						'S_LINK_PM'			=> $link_pm,
						'S_HIDDEN_FIELDS'	=> $s_hidden_fields
					);
					$row_count++;

					($edit) ? $template->assign_vars($template_row) : $template->assign_block_vars('draftrow', $template_row);
				}

				if (!$edit)
				{
					$template->assign_var('S_DRAFT_ROWS', $row_count);
				}

			break;
		}


		$template->assign_vars(array( 
			'L_TITLE'			=> $user->lang['UCP_MAIN_' . strtoupper($mode)],

			'S_DISPLAY_MARK_ALL'	=> ($mode == 'watched' || ($mode == 'drafts' && !isset($_GET['edit']))) ? true : false, 
			'S_HIDDEN_FIELDS'		=> (isset($s_hidden_fields)) ? $s_hidden_fields : '',
			'S_UCP_ACTION'			=> $this->u_action)
		);

		// Set desired template
		$this->tpl_name = 'ucp_main_' . $mode;
		$this->page_title = 'UCP_MAIN_' . strtoupper($mode);
	}
}

?>