<?php
/**
 * Better Tags 0.1.1
 * Topic-level tagging foundation for SMF 2.1.8+
 */

if (!defined('SMF'))
	die('No direct access...');


function better_tags_load_language($force_reload = false)
{
	global $txt, $settings, $sourcedir;

	loadLanguage('BetterTags', '', true, $force_reload);

	// A custom theme may contain an older copied Better Tags language file.
	// SMF stops at the first matching theme file, which can hide newer keys
	// added by an updated Better Tags package. Backfill any missing current
	// package strings from the default theme's English file.
	$required = array(
		'better_tags_admin_title',
		'better_tags_admin_settings',
		'better_tags_admin_manager',
		'better_tags_manager_heading',
		'better_tags_manager_count',
		'better_tags_manager_empty',
		'better_tags_manager_meta',
		'better_tags_search_placeholder',
		'better_tags_sort_topics',
		'better_tags_sort_name',
		'better_tags_sort_newest',
		'better_tags_rename',
		'better_tags_merge',
		'better_tags_merge_into',
		'better_tags_delete',
		'better_tags_delete_confirm',
		'better_tags_cleanup_orphans',
		'better_tags_follow',
		'better_tags_unfollow',
		'better_tags_my_followed',
		'better_tags_followed_empty',
		'better_tags_followed_meta',
		'better_tags_follow_success',
		'better_tags_unfollow_success',
		'better_tags_follow_login',
		'better_tags_alert_new_topic',
		'better_tags_following',
		'better_tags_following_intro',
		'better_tags_latest_topic',
		'better_tags_admin_integrations',
		'better_tags_integrations_description',
		'better_tags_integration_gallery',
		'better_tags_integration_downloads',
		'better_tags_integration_detected',
		'better_tags_integration_not_detected',
		'better_tags_integration_enable',
		'better_tags_integration_gallery_media',
		'better_tags_integration_gallery_albums',
		'better_tags_integration_download_items',
		'better_tags_integrations_saved',
		'better_tags_integration_foundation_note',
		'better_tags_gallery_album_tags',
		'better_tags_gallery_albums_heading',
		'better_tags_gallery_album_meta',
		'better_tags_no_gallery_albums',
		'better_tags_gallery_media_tags',
		'better_tags_gallery_media_heading',
		'better_tags_gallery_media_meta',
		'better_tags_no_gallery_media',
		'better_tags_download_tags',
		'better_tags_downloads_heading',
		'better_tags_download_meta',
		'better_tags_no_downloads',
		'better_tags_select_all',
		'better_tags_clear_selection',
		'better_tags_bulk_merge_target',
		'better_tags_merge_selected',
		'better_tags_delete_selected',
		'better_tags_bulk_none_selected',
		'better_tags_bulk_merge_invalid',
		'better_tags_bulk_merge_done',
		'better_tags_bulk_delete_done',
		'better_tags_bulk_delete_confirm',
		'better_tags_bulk_merge_confirm',
	);

	$missing = false;
	foreach ($required as $key)
	{
		if (!isset($txt[$key]))
		{
			$missing = true;
			break;
		}
	}

	if (!$missing)
		return;

	if (empty($settings['default_theme_dir']))
	{
		require_once($sourcedir . '/ScheduledTasks.php');
		loadEssentialThemeData();
	}

	$fallback = $settings['default_theme_dir'] . '/languages/BetterTags.english.php';
	if (file_exists($fallback))
		template_include($fallback);
}

function better_tags_actions(&$actionArray)
{
	$actionArray['tags'] = array('BetterTags.php', 'better_tags_action');
}


function better_tags_menu_buttons(&$buttons)
{
	global $txt, $scripturl, $user_info;

	if (!empty($user_info['is_guest']) || !allowedTo('better_tags_use'))
		return;

	better_tags_load_language();

	$following = array(
		'title' => $txt['better_tags_following'] ?? 'Following',
		'href' => $scripturl . '?action=tags;sa=following',
		'show' => true,
		'sub_buttons' => array(),
	);

	// Following is a personal way to browse forum content, so keep it inside
	// the Forum menu instead of giving it top-level navigation weight.
	if (isset($buttons['forum']))
	{
		if (empty($buttons['forum']['sub_buttons']) || !is_array($buttons['forum']['sub_buttons']))
			$buttons['forum']['sub_buttons'] = array();

		$buttons['forum']['sub_buttons']['better_tags_following'] = $following;
		return;
	}

	// Some themes/mods may rename the Forum button key. Fall back to the first
	// button whose href points to action=forum.
	foreach ($buttons as &$button)
	{
		if (empty($button['href']) || strpos($button['href'], 'action=forum') === false)
			continue;

		if (empty($button['sub_buttons']) || !is_array($button['sub_buttons']))
			$button['sub_buttons'] = array();

		$button['sub_buttons']['better_tags_following'] = $following;
		break;
	}
	unset($button);
}

function better_tags_fetch_alerts(&$alerts, &$formats)
{
	global $txt;

	better_tags_load_language();

	// The core alert renderer will use this string for our topic-based alerts.
	if (!isset($txt['alert_topic_better_tag']))
		$txt['alert_topic_better_tag'] = $txt['better_tags_alert_new_topic'] ?? '{member_link} posted a new topic under followed tag {tag_name}: {topic_msg}.';
}

function better_tags_post_fields()
{
	global $context, $txt, $modSettings, $topic, $scripturl;

	better_tags_load_language();

	if (empty($modSettings['better_tags_enabled']) || empty($context['is_first_post']) || !allowedTo('better_tags_use'))
		return;

	$values = '';

	if (!empty($_POST['better_tags']))
		$values = (string) $_POST['better_tags'];
	elseif (!empty($topic))
		$values = implode(', ', better_tags_get_topic_names((int) $topic));

	$max_tags = max(1, (int) ($modSettings['better_tags_max_per_topic'] ?? 8));
	$max_length = max(12, (int) ($modSettings['better_tags_max_length'] ?? 40));

	$suggest_url = $scripturl . '?action=tags&sa=suggest';
	$initial_suggestions = better_tags_get_suggestions('', 100);
	$initial_json = json_encode(
		$initial_suggestions,
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);

	$context['posting_fields']['better_tags'] = array(
		'label' => array(
			'html' => '<label for="better_tags_entry_input" id="caption_better_tags">' . $txt['better_tags_field'] . '</label>',
		),
		'input' => array(
			'html' => '<input type="hidden" class="better_tags_value" id="better_tags" name="better_tags" value="' . htmlspecialchars($values, ENT_QUOTES) . '">'
				. '<div class="better_tags_entry" data-max-tags="' . $max_tags . '" data-max-length="' . $max_length . '" data-suggest-url="' . htmlspecialchars($suggest_url, ENT_QUOTES) . '" data-placeholder="' . htmlspecialchars($txt['better_tags_entry_placeholder'], ENT_QUOTES) . '">'
				. '<script type="application/json" class="better_tags_preload">' . $initial_json . '</script>'
				. '<div class="better_tags_selected"></div>'
				. '<input type="text" id="better_tags_entry_input" class="better_tags_entry_input" maxlength="' . $max_length . '" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="better_tags_suggestions">'
				. '<div id="better_tags_suggestions" class="better_tags_suggestions windowbg" role="listbox" hidden></div>'
				. '</div>'
				. '<div class="smalltext">' . sprintf($txt['better_tags_field_help'], $max_tags, $max_length) . '</div>',
		),
	);

	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
	loadJavaScriptFile('better_tags.js', array('minimize' => true, 'defer' => true, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_create_topic(&$msgOptions, &$topicOptions, &$posterOptions)
{
	// Kept as a compatibility fallback. The primary save path is integrate_post2_end,
	// where SMF has finished the complete posting lifecycle.
	if (empty($topicOptions['id']) || !isset($_POST['better_tags']))
		return;

	better_tags_save_from_request((int) $topicOptions['id'], true);
}

function better_tags_post2_end()
{
	global $topic;

	// At this point SMF has completed the post/topic write and $topic is final.
	if (empty($topic) || !isset($_POST['better_tags']))
		return;

	better_tags_save_from_request((int) $topic);
}

function better_tags_modify_post(&$messages_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions, &$messageInts)
{
	global $smcFunc;

	if (empty($topicOptions['id']) || empty($msgOptions['id']) || !isset($_POST['better_tags']))
		return;

	$request = $smcFunc['db_query']('', '
		SELECT id_first_msg
		FROM {db_prefix}topics
		WHERE id_topic = {int:topic}
		LIMIT 1',
		array('topic' => (int) $topicOptions['id'])
	);
	list($first_msg) = $smcFunc['db_fetch_row']($request) ?: array(0);
	$smcFunc['db_free_result']($request);

	if ((int) $first_msg === (int) $msgOptions['id'])
		better_tags_save_from_request((int) $topicOptions['id']);
}

function better_tags_display_topic(&$topic_selects, &$topic_tables, &$topic_parameters)
{
	global $context, $topic;

	if (empty($topic))
		return;

	$context['better_tags_topic'] = better_tags_get_topic((int) $topic);
	if (!empty($context['better_tags_topic']))
		loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_prepare_display(&$output, &$message, $counter)
{
	global $context, $scripturl;

	// SMF increments/decrements the display counter before this hook runs, so the
	// counter is not a reliable way to identify the topic starter. Use the actual
	// first-message id instead; this also works with newest-first display order.
	if (empty($context['better_tags_topic']) || empty($output['body'])
		|| empty($context['topic_first_message']) || empty($output['id'])
		|| (int) $output['id'] !== (int) $context['topic_first_message'])
		return;

	$chips = '';
	foreach ($context['better_tags_topic'] as $tag)
	{
		$chips .= '<a class="better_tag_chip" href="' . $scripturl . '?action=tags;tag=' . (int) $tag['id_tag'] . '">'
			. htmlspecialchars($tag['tag_name'], ENT_QUOTES) . '</a>';
	}

	if ($chips !== '')
		$output['body'] = '<div class="better_tags_topicbar">' . $chips . '</div>' . $output['body'];
}


function better_tags_entry_html($field_name, $field_id, $values, $label, $help = '')
{
	global $txt, $modSettings, $scripturl;

	$max_tags = max(1, (int) ($modSettings['better_tags_max_per_topic'] ?? 8));
	$max_length = max(12, (int) ($modSettings['better_tags_max_length'] ?? 40));
	$suggest_url = $scripturl . '?action=tags&sa=suggest';
	$initial_json = json_encode(
		better_tags_get_suggestions('', 100),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);

	$input_id = $field_id . '_entry';
	$suggestions_id = $field_id . '_suggestions';

	return '<label class="better_tags_external_field"><strong>' . htmlspecialchars($label, ENT_QUOTES) . '</strong>'
		. '<input type="hidden" class="better_tags_value" id="' . htmlspecialchars($field_id, ENT_QUOTES) . '" name="' . htmlspecialchars($field_name, ENT_QUOTES) . '" value="' . htmlspecialchars($values, ENT_QUOTES) . '">'
		. '<div class="better_tags_entry" data-max-tags="' . $max_tags . '" data-max-length="' . $max_length . '" data-suggest-url="' . htmlspecialchars($suggest_url, ENT_QUOTES) . '" data-placeholder="' . htmlspecialchars($txt['better_tags_entry_placeholder'], ENT_QUOTES) . '">'
		. '<script type="application/json" class="better_tags_preload">' . $initial_json . '</script>'
		. '<div class="better_tags_selected"></div>'
		. '<input type="text" id="' . htmlspecialchars($input_id, ENT_QUOTES) . '" class="better_tags_entry_input" maxlength="' . $max_length . '" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="' . htmlspecialchars($suggestions_id, ENT_QUOTES) . '">'
		. '<div id="' . htmlspecialchars($suggestions_id, ENT_QUOTES) . '" class="better_tags_suggestions windowbg" role="listbox" hidden></div>'
		. '</div>'
		. ($help !== '' ? '<span class="smalltext">' . $help . '</span>' : '')
		. '</label>';
}

function better_tags_gallery_album_enabled()
{
	global $modSettings;

	return !empty($modSettings['better_tags_enabled'])
		&& !empty($modSettings['better_tags_gallery_enabled'])
		&& !empty($modSettings['better_tags_gallery_albums']);
}

function better_tags_gallery_album_form(&$html, $album, $is_edit)
{
	global $txt, $modSettings, $context;

	$context['better_tags_gallery_album_form_ran'] = true;

	if (!better_tags_gallery_album_enabled() || !allowedTo('better_tags_use'))
		return;

	better_tags_load_language();

	$id_album = !empty($album['id_album']) ? (int) $album['id_album'] : 0;
	if (isset($_POST['better_tags_gallery_album']))
		$values = (string) $_POST['better_tags_gallery_album'];
	elseif ($id_album > 0)
	{
		$existing = better_tags_content_get('gallery_album', $id_album);
		$values = implode(', ', array_column($existing, 'tag_name'));
	}
	else
		$values = '';

	$max_tags = max(1, (int) ($modSettings['better_tags_max_per_topic'] ?? 8));
	$max_length = max(12, (int) ($modSettings['better_tags_max_length'] ?? 40));

	$html .= better_tags_entry_html(
		'better_tags_gallery_album',
		'better_tags_gallery_album',
		$values,
		$txt['better_tags_gallery_album_tags'],
		sprintf($txt['better_tags_field_help'], $max_tags, $max_length)
	);

	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
	loadJavaScriptFile('better_tags.js', array('minimize' => true, 'defer' => true, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_gallery_album_saved($id_album, $is_edit)
{
	global $modSettings;

	$GLOBALS['better_tags_gallery_album_saved_ran'][(int) $id_album] = true;

	$id_album = (int) $id_album;
	if ($id_album <= 0 || !better_tags_gallery_album_enabled() || !allowedTo('better_tags_use')
		|| !isset($_POST['better_tags_gallery_album']))
		return;

	$max_tags = max(1, min(30, (int) ($modSettings['better_tags_max_per_topic'] ?? 8)));
	$max_length = max(12, min(80, (int) ($modSettings['better_tags_max_length'] ?? 40)));
	$raw = (string) $_POST['better_tags_gallery_album'];
	$parts = preg_split('/\s*,\s*/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: array();

	$clean = array();
	foreach ($parts as $name)
	{
		$name = better_tags_clean_name($name, $max_length);
		if ($name === '')
			continue;

		$norm = better_tags_normalize($name);
		if (!isset($clean[$norm]))
			$clean[$norm] = $name;

		if (count($clean) >= $max_tags)
			break;
	}

	better_tags_content_assign('gallery_album', $id_album, array_values($clean));
}

function better_tags_gallery_album_view(&$html, $album)
{
	global $scripturl, $context;

	$context['better_tags_gallery_album_view_ran'] = true;

	if (!better_tags_gallery_album_enabled() || empty($album['id_album']))
		return;

	$tags = better_tags_content_get('gallery_album', (int) $album['id_album']);
	if (empty($tags))
		return;

	$chips = '';
	foreach ($tags as $tag)
		$chips .= '<a class="better_tag_chip" href="' . $scripturl . '?action=tags;tag=' . (int) $tag['id_tag'] . '">'
			. htmlspecialchars($tag['tag_name'], ENT_QUOTES) . '</a>';

	$html .= '<div class="better_tags_gallery_album_bar">' . $chips . '</div>';
	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_gallery_album_deleted($id_album, $media_ids = array())
{
	$GLOBALS['better_tags_gallery_album_deleted_ran'][(int) $id_album] = true;
	if ((int) $id_album > 0)
		better_tags_content_remove('gallery_album', (int) $id_album);

	// Stage 5C can use the media id list when individual Gallery media tags land.
	if (!empty($media_ids))
		foreach ((array) $media_ids as $id_media)
			better_tags_content_remove('gallery_media', (int) $id_media);
}

function better_tags_gallery_album_results($id_tag)
{
	global $smcFunc, $modSettings, $user_info, $scripturl, $sourcedir;

	if (!better_tags_gallery_album_enabled()
		|| !is_file($sourcedir . '/BetterGallery.php'))
		return array();

	require_once($sourcedir . '/BetterGallery.php');

	if (!function_exists('bg_album_is_discoverable') || !function_exists('bg_album_load'))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT ct.content_id
		FROM {db_prefix}better_content_tags AS ct
		WHERE ct.content_type = {string:type}
			AND ct.id_tag = {int:tag}
		ORDER BY ct.content_id DESC
		LIMIT 100',
		array('type' => 'gallery_album', 'tag' => (int) $id_tag)
	);

	$results = array();
	$is_admin = !empty($user_info['is_admin'])
		|| (function_exists('bg_allowed') && bg_allowed('bg_moderate_gallery'));

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$album = bg_album_load((int) $row['content_id']);
		if (!$album)
			continue;

		if (function_exists('bg_portfolio_album_visible') && !bg_portfolio_album_visible($album))
			continue;

		$is_owner = !empty($user_info['id']) && (int) $album['id_owner'] === (int) $user_info['id'];
		if (!bg_album_is_discoverable($album, (int) ($user_info['id'] ?? 0), $is_admin) && !$is_owner)
			continue;

		$results[] = array(
			'id_album' => (int) $album['id_album'],
			'title' => $album['title'],
			'href' => $scripturl . '?action=gallery;sa=album;album=' . (int) $album['id_album'],
			'owner_name' => $album['owner_name'] ?: '',
			'category_name' => $album['category_name'] ?: '',
		);
	}
	$smcFunc['db_free_result']($request);

	return $results;
}


function better_tags_gallery_media_enabled()
{
	global $modSettings;

	return !empty($modSettings['better_tags_enabled'])
		&& !empty($modSettings['better_tags_gallery_enabled'])
		&& !empty($modSettings['better_tags_gallery_media']);
}

function better_tags_gallery_media_form(&$html, $media)
{
	global $txt, $modSettings, $context;

	$context['better_tags_gallery_media_form_ran'] = true;

	if (!better_tags_gallery_media_enabled() || !allowedTo('better_tags_use') || empty($media['id_media']))
		return;

	better_tags_load_language();
	$id_media = (int) $media['id_media'];

	if (isset($_POST['better_tags_gallery_media']))
		$values = (string) $_POST['better_tags_gallery_media'];
	else
	{
		$existing = better_tags_content_get('gallery_media', $id_media);
		$values = implode(', ', array_column($existing, 'tag_name'));
	}

	$max_tags = max(1, (int) ($modSettings['better_tags_max_per_topic'] ?? 8));
	$max_length = max(12, (int) ($modSettings['better_tags_max_length'] ?? 40));

	$html .= better_tags_entry_html(
		'better_tags_gallery_media',
		'better_tags_gallery_media',
		$values,
		$txt['better_tags_gallery_media_tags'],
		sprintf($txt['better_tags_field_help'], $max_tags, $max_length)
	);

	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
	loadJavaScriptFile('better_tags.js', array('minimize' => true, 'defer' => true, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_gallery_media_saved($id_media)
{
	$GLOBALS['better_tags_gallery_media_saved_ran'][(int) $id_media] = true;
	$id_media = (int) $id_media;

	if ($id_media <= 0 || !better_tags_gallery_media_enabled() || !allowedTo('better_tags_use')
		|| !isset($_POST['better_tags_gallery_media']))
		return;

	$raw = (string) $_POST['better_tags_gallery_media'];
	$parts = preg_split('/\s*,\s*/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: array();
	better_tags_content_assign('gallery_media', $id_media, $parts);
}

function better_tags_gallery_media_info(&$html, $media)
{
	global $scripturl;

	$id_media = !empty($media['id_media']) ? (int) $media['id_media'] : 0;
	$GLOBALS['better_tags_gallery_media_info_ran'][$id_media] = true;

	if ($id_media <= 0 || !better_tags_gallery_media_enabled())
		return;

	$tags = better_tags_content_get('gallery_media', $id_media);
	if (empty($tags))
		return;

	$chips = '';
	foreach ($tags as $tag)
		$chips .= '<a class="better_tag_chip" href="' . $scripturl . '?action=tags;tag=' . (int) $tag['id_tag'] . '">'
			. htmlspecialchars($tag['tag_name'], ENT_QUOTES) . '</a>';

	$html .= '<div class="better_tags_gallery_media_bar">' . $chips . '</div>';
	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_gallery_media_deleted($media_ids)
{
	$GLOBALS['better_tags_gallery_media_deleted_ran'] = true;

	foreach ((array) $media_ids as $id_media)
	{
		$id_media = (int) $id_media;
		if ($id_media > 0)
			better_tags_content_remove('gallery_media', $id_media);
	}
}

function better_tags_gallery_media_results($id_tag)
{
	global $smcFunc, $user_info, $scripturl, $sourcedir;

	if (!better_tags_gallery_media_enabled() || !is_file($sourcedir . '/BetterGallery.php'))
		return array();

	require_once($sourcedir . '/BetterGallery.php');
	if (!function_exists('bg_media_load'))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT ct.content_id
		FROM {db_prefix}better_content_tags AS ct
		WHERE ct.content_type = {string:type}
			AND ct.id_tag = {int:tag}
		ORDER BY ct.content_id DESC
		LIMIT 100',
		array('type' => 'gallery_media', 'tag' => (int) $id_tag)
	);

	$results = array();
	$is_admin = !empty($user_info['is_admin'])
		|| (function_exists('bg_allowed') && bg_allowed('bg_moderate_gallery'));

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$media = bg_media_load((int) $row['content_id']);
		if (!$media)
			continue;

		$album = array(
			'id_album' => (int) $media['id_album'],
			'id_owner' => (int) $media['album_owner'],
			'discovery_visibility' => (int) $media['discovery_visibility'],
			'access_mode' => (int) $media['access_mode'],
			'password_hash' => $media['password_hash'],
		);

		if (function_exists('bg_portfolio_album_visible') && !bg_portfolio_album_visible($album))
			continue;
		if (function_exists('bg_album_content_accessible')
			&& !bg_album_content_accessible($album, (int) ($user_info['id'] ?? 0), $is_admin))
			continue;

		$album_full = function_exists('bg_album_load') ? bg_album_load((int) $media['id_album']) : false;
		$results[] = array(
			'id_media' => (int) $media['id_media'],
			'title' => $media['title'] !== '' ? $media['title'] : $media['filename'],
			'href' => $scripturl . '?action=gallery;sa=album;album=' . (int) $media['id_album'] . ';media=' . (int) $media['id_media'],
			'album_title' => !empty($album_full['title']) ? $album_full['title'] : '',
			'owner_name' => !empty($album_full['owner_name']) ? $album_full['owner_name'] : '',
		);
	}
	$smcFunc['db_free_result']($request);

	return $results;
}


function better_tags_downloads_enabled()
{
	global $modSettings;

	return !empty($modSettings['better_tags_enabled'])
		&& !empty($modSettings['better_tags_downloads_enabled'])
		&& !empty($modSettings['better_tags_download_items']);
}

function better_tags_download_form(&$html, $download)
{
	global $txt, $modSettings, $context;

	$context['better_tags_download_form_ran'] = true;

	if (!better_tags_downloads_enabled() || !allowedTo('better_tags_use'))
		return;

	better_tags_load_language();
	$id_download = !empty($download['id_download']) ? (int) $download['id_download'] : 0;

	if (isset($_POST['better_tags_download']))
		$values = (string) $_POST['better_tags_download'];
	elseif ($id_download > 0)
	{
		$existing = better_tags_content_get('download', $id_download);
		$values = implode(', ', array_column($existing, 'tag_name'));
	}
	else
		$values = '';

	$max_tags = max(1, (int) ($modSettings['better_tags_max_per_topic'] ?? 8));
	$max_length = max(12, (int) ($modSettings['better_tags_max_length'] ?? 40));

	$html .= '<div class="bd_form_row better_tags_download_form_row">'
		. better_tags_entry_html(
			'better_tags_download',
			'better_tags_download',
			$values,
			$txt['better_tags_download_tags'],
			sprintf($txt['better_tags_field_help'], $max_tags, $max_length)
		)
		. '</div>';

	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
	loadJavaScriptFile('better_tags.js', array('minimize' => true, 'defer' => true, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_download_saved($id_download)
{
	$GLOBALS['better_tags_download_saved_ran'][(int) $id_download] = true;
	$id_download = (int) $id_download;

	if ($id_download <= 0 || !better_tags_downloads_enabled() || !allowedTo('better_tags_use')
		|| !isset($_POST['better_tags_download']))
		return;

	$raw = (string) $_POST['better_tags_download'];
	$parts = preg_split('/\s*,\s*/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: array();
	better_tags_content_assign('download', $id_download, $parts);
}

function better_tags_download_view(&$html, $download)
{
	global $scripturl, $context;

	$context['better_tags_download_view_ran'] = true;

	if (!better_tags_downloads_enabled() || empty($download['id_download']))
		return;

	$tags = better_tags_content_get('download', (int) $download['id_download']);
	if (empty($tags))
		return;

	$chips = '';
	foreach ($tags as $tag)
		$chips .= '<a class="better_tag_chip" href="' . $scripturl . '?action=tags;tag=' . (int) $tag['id_tag'] . '">'
			. htmlspecialchars($tag['tag_name'], ENT_QUOTES) . '</a>';

	$html .= '<div class="better_tags_download_bar">' . $chips . '</div>';
	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_download_deleted($id_download)
{
	$GLOBALS['better_tags_download_deleted_ran'][(int) $id_download] = true;

	$id_download = (int) $id_download;
	if ($id_download > 0)
		better_tags_content_remove('download', $id_download);
}

function better_tags_download_results($id_tag)
{
	global $smcFunc, $scripturl, $sourcedir;

	if (!better_tags_downloads_enabled() || !is_file($sourcedir . '/BetterDownloads.php'))
		return array();

	require_once($sourcedir . '/BetterDownloads.php');
	if (!function_exists('bd_get_download'))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT ct.content_id
		FROM {db_prefix}better_content_tags AS ct
		WHERE ct.content_type = {string:type}
			AND ct.id_tag = {int:tag}
		ORDER BY ct.content_id DESC
		LIMIT 100',
		array('type' => 'download', 'tag' => (int) $id_tag)
	);

	$results = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$download = bd_get_download((int) $row['content_id'], false);
		if (!$download)
			continue;

		$results[] = array(
			'id_download' => (int) $download['id_download'],
			'title' => $download['title'],
			'href' => $scripturl . '?action=downloads;sa=item;id=' . (int) $download['id_download'],
			'category_name' => $download['category_name'] ?? '',
			'version' => $download['version'] ?? '',
		);
	}
	$smcFunc['db_free_result']($request);

	return $results;
}


function better_tags_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	global $txt;

	better_tags_load_language();

	if (!in_array('better_tags', $permissionGroups['membergroup'], true))
		$permissionGroups['membergroup'][] = 'better_tags';

	$permissionList['membergroup']['better_tags_use'] = array(false, 'better_tags');
	$txt['permissiongroup_better_tags'] = $txt['better_tags_permission_group'];
	$txt['permissionname_better_tags_use'] = $txt['better_tags_permission_use'];
}

function better_tags_admin_areas(&$admin_areas)
{
	global $txt;

	// Admin areas can be rebuilt during Package Manager requests after an older
	// Better Tags language file has already been loaded. Force-refresh our
	// language file and keep fallbacks so upgrades/removals never emit notices.
	better_tags_load_language(true);

	if (!isset($admin_areas['config']['areas']) || !is_array($admin_areas['config']['areas']))
		return;

	$admin_title = $txt['better_tags_admin_title'] ?? 'Better Tags';
	$settings_label = $txt['better_tags_admin_settings'] ?? 'Settings';
	$manager_label = $txt['better_tags_admin_manager'] ?? 'Tag Manager';
	$integrations_label = $txt['better_tags_admin_integrations'] ?? 'Integrations';

	$admin_areas['config']['areas']['bettertags'] = array(
		'label' => $admin_title,
		'file' => 'BetterTags.php',
		'function' => 'better_tags_admin',
		'permission' => array('admin_forum'),
		'icon' => 'better_tags.png',
		'subsections' => array(
			'settings' => array($settings_label),
			'manager' => array($manager_label),
			'integrations' => array($integrations_label),
		),
	);
}

function better_tags_admin()
{
	global $context, $txt;

	better_tags_load_language(true);

	$subactions = array(
		'settings' => 'better_tags_admin_settings',
		'manager' => 'better_tags_admin_manager',
		'integrations' => 'better_tags_admin_integrations',
	);

	$sa = isset($_REQUEST['sa']) && isset($subactions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'settings';
	$_REQUEST['sa'] = $sa;

	better_tags_setup_admin_tabs($sa);

	call_helper($subactions[$sa]);
}


function better_tags_setup_admin_tabs($sa)
{
	global $context, $txt;

	$tab_data = array(
		'title' => $txt['better_tags_admin_title'] ?? 'Better Tags',
		'description' => $txt['better_tags_admin_description'] ?? 'Configure and manage topic tags.',
		'tabs' => array(
			'settings' => array(
				'description' => $txt['better_tags_settings_description'] ?? 'Configure Better Tags behavior and limits.',
			),
			'manager' => array(
				'description' => $txt['better_tags_manager_description'] ?? 'Rename, merge, delete, search, and clean up tags.',
			),
			'integrations' => array(
				'description' => $txt['better_tags_integrations_description'] ?? 'Connect Better Tags to supported Better SMF content mods.',
			),
		),
	);

	$menu_key = null;

	// Prefer SMF's canonical admin menu key when it points to this area.
	if (!empty($context['admin_menu_name'])
		&& isset($context[$context['admin_menu_name']])
		&& is_array($context[$context['admin_menu_name']])
		&& (($context[$context['admin_menu_name']]['current_area'] ?? '') === 'bettertags'))
	{
		$menu_key = $context['admin_menu_name'];
	}

	// Some custom admin templates resolve menu_data_N directly via cur_menu_id.
	// Locate the active Better Tags menu object by its actual current_area instead
	// of assuming the generated menu ID always matches admin_menu_name.
	if ($menu_key === null)
	{
		foreach ($context as $key => $value)
		{
			if (strpos((string) $key, 'menu_data_') !== 0 || !is_array($value))
				continue;

			if (($value['current_area'] ?? '') === 'bettertags')
			{
				$menu_key = $key;
				break;
			}
		}
	}

	if ($menu_key === null)
		return;

	$context[$menu_key]['current_subsection'] = $sa;
	$context[$menu_key]['tab_data'] = $tab_data;

	// Keep the canonical pointer synchronized for core/default templates.
	$context['admin_menu_name'] = $menu_key;
}

function better_tags_admin_settings()
{
	global $context, $txt, $scripturl;

	better_tags_load_language();
	require_once($GLOBALS['sourcedir'] . '/ManageServer.php');

	$config_vars = array(
		array('check', 'better_tags_enabled'),
		array('int', 'better_tags_max_per_topic', 'subtext' => $txt['better_tags_max_per_topic_help']),
		array('int', 'better_tags_max_length', 'subtext' => $txt['better_tags_max_length_help']),
		array('check', 'better_tags_remove_data_on_uninstall', 'subtext' => $txt['better_tags_remove_data_on_uninstall_help']),
	);

	$context['page_title'] = $txt['better_tags_admin_title'];
	$context['settings_title'] = $txt['better_tags_admin_title'];
	$context['post_url'] = $scripturl . '?action=admin;area=bettertags;sa=settings;save';
	$context['sub_template'] = 'show_settings';

	if (isset($_GET['save']))
	{
		checkSession();
		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=bettertags;sa=settings');
	}

	prepareDBSettingContext($config_vars);
}


function better_tags_detect_integrations()
{
	global $sourcedir;

	return array(
		'gallery' => is_file($sourcedir . '/BetterGallery.php'),
		'downloads' => is_file($sourcedir . '/BetterDownloads.php'),
	);
}

function better_tags_admin_integrations()
{
	global $context, $txt, $modSettings;

	better_tags_load_language();
	isAllowedTo('admin_forum');

	$detected = better_tags_detect_integrations();

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['better_tags_save_integrations']))
	{
		checkSession('post');

		$updates = array(
			'better_tags_gallery_enabled' => !empty($detected['gallery']) && !empty($_POST['better_tags_gallery_enabled']) ? 1 : 0,
			'better_tags_gallery_media' => !empty($detected['gallery']) && !empty($_POST['better_tags_gallery_media']) ? 1 : 0,
			'better_tags_gallery_albums' => !empty($detected['gallery']) && !empty($_POST['better_tags_gallery_albums']) ? 1 : 0,
			'better_tags_downloads_enabled' => !empty($detected['downloads']) && !empty($_POST['better_tags_downloads_enabled']) ? 1 : 0,
			'better_tags_download_items' => !empty($detected['downloads']) && !empty($_POST['better_tags_download_items']) ? 1 : 0,
		);

		updateSettings($updates);
		$modSettings = array_merge($modSettings, $updates);
		$_SESSION['better_tags_integrations_saved'] = true;
		redirectexit('action=admin;area=bettertags;sa=integrations');
	}

	$context['better_tags_integrations_detected'] = $detected;
	$context['better_tags_integrations_saved'] = !empty($_SESSION['better_tags_integrations_saved']);
	unset($_SESSION['better_tags_integrations_saved']);

	$context['page_title'] = $txt['better_tags_admin_integrations'];
	$context['sub_template'] = 'better_tags_integrations';

	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function template_better_tags_integrations()
{
	global $context, $txt, $modSettings, $scripturl;

	$detected = $context['better_tags_integrations_detected'];

	if (!empty($context['better_tags_integrations_saved']))
		echo '<div class="infobox">', $txt['better_tags_integrations_saved'], '</div>';

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['better_tags_admin_integrations'], '</h3></div>
	<div class="windowbg better_tags_integrations_intro">', $txt['better_tags_integration_foundation_note'], '</div>

	<form action="', $scripturl, '?action=admin;area=bettertags;sa=integrations" method="post" class="better_tags_integrations_form">';

	$gallery_detected = !empty($detected['gallery']);
	echo '
		<section class="windowbg better_tags_integration_card">
			<div class="better_tags_integration_header">
				<div>
					<strong>', $txt['better_tags_integration_gallery'], '</strong>
					<div class="smalltext">', $gallery_detected ? $txt['better_tags_integration_detected'] : $txt['better_tags_integration_not_detected'], '</div>
				</div>
				<span class="better_tags_detection ', $gallery_detected ? 'detected' : 'missing', '">', $gallery_detected ? $txt['better_tags_integration_detected'] : $txt['better_tags_integration_not_detected'], '</span>
			</div>
			<label class="better_tags_toggle_row">
				<input type="checkbox" name="better_tags_gallery_enabled" value="1"', !empty($modSettings['better_tags_gallery_enabled']) ? ' checked' : '', $gallery_detected ? '' : ' disabled', '>
				<span>', $txt['better_tags_integration_enable'], '</span>
			</label>
			<label class="better_tags_toggle_row better_tags_subtoggle">
				<input type="checkbox" name="better_tags_gallery_media" value="1"', !empty($modSettings['better_tags_gallery_media']) ? ' checked' : '', $gallery_detected ? '' : ' disabled', '>
				<span>', $txt['better_tags_integration_gallery_media'], '</span>
			</label>
			<label class="better_tags_toggle_row better_tags_subtoggle">
				<input type="checkbox" name="better_tags_gallery_albums" value="1"', !empty($modSettings['better_tags_gallery_albums']) ? ' checked' : '', $gallery_detected ? '' : ' disabled', '>
				<span>', $txt['better_tags_integration_gallery_albums'], '</span>
			</label>
		</section>';

	$downloads_detected = !empty($detected['downloads']);
	echo '
		<section class="windowbg better_tags_integration_card">
			<div class="better_tags_integration_header">
				<div>
					<strong>', $txt['better_tags_integration_downloads'], '</strong>
					<div class="smalltext">', $downloads_detected ? $txt['better_tags_integration_detected'] : $txt['better_tags_integration_not_detected'], '</div>
				</div>
				<span class="better_tags_detection ', $downloads_detected ? 'detected' : 'missing', '">', $downloads_detected ? $txt['better_tags_integration_detected'] : $txt['better_tags_integration_not_detected'], '</span>
			</div>
			<label class="better_tags_toggle_row">
				<input type="checkbox" name="better_tags_downloads_enabled" value="1"', !empty($modSettings['better_tags_downloads_enabled']) ? ' checked' : '', $downloads_detected ? '' : ' disabled', '>
				<span>', $txt['better_tags_integration_enable'], '</span>
			</label>
			<label class="better_tags_toggle_row better_tags_subtoggle">
				<input type="checkbox" name="better_tags_download_items" value="1"', !empty($modSettings['better_tags_download_items']) ? ' checked' : '', $downloads_detected ? '' : ' disabled', '>
				<span>', $txt['better_tags_integration_download_items'], '</span>
			</label>
		</section>

		<div class="buttonlist better_tags_integrations_save">
			<button type="submit" class="button primary" name="better_tags_save_integrations" value="1">', $txt['save'], '</button>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

function better_tags_content_types()
{
	return array('gallery_media', 'gallery_album', 'download');
}

function better_tags_content_type_valid($content_type)
{
	return in_array((string) $content_type, better_tags_content_types(), true);
}

function better_tags_content_get($content_type, $content_id)
{
	global $smcFunc;

	$content_type = (string) $content_type;
	$content_id = (int) $content_id;

	if (!better_tags_content_type_valid($content_type) || $content_id <= 0)
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT t.id_tag, t.tag_name
		FROM {db_prefix}better_content_tags AS ct
			INNER JOIN {db_prefix}better_tags AS t ON (t.id_tag = ct.id_tag)
		WHERE ct.content_type = {string:type}
			AND ct.content_id = {int:id}
		ORDER BY t.tag_name ASC',
		array('type' => $content_type, 'id' => $content_id)
	);

	$tags = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$tags[] = array('id_tag' => (int) $row['id_tag'], 'tag_name' => $row['tag_name']);
	$smcFunc['db_free_result']($request);

	return $tags;
}

function better_tags_content_assign($content_type, $content_id, array $tags)
{
	global $smcFunc, $modSettings;

	$content_type = (string) $content_type;
	$content_id = (int) $content_id;

	if (!better_tags_content_type_valid($content_type) || $content_id <= 0)
		return false;

	$max_tags = max(1, min(30, (int) ($modSettings['better_tags_max_per_topic'] ?? 8)));
	$max_length = max(12, min(80, (int) ($modSettings['better_tags_max_length'] ?? 40)));

	$clean = array();
	foreach ($tags as $name)
	{
		$name = better_tags_clean_name((string) $name, $max_length);
		if ($name === '')
			continue;

		$norm = better_tags_normalize($name);
		if (!isset($clean[$norm]))
			$clean[$norm] = $name;

		if (count($clean) >= $max_tags)
			break;
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_content_tags
		WHERE content_type = {string:type}
			AND content_id = {int:id}',
		array('type' => $content_type, 'id' => $content_id)
	);

	foreach ($clean as $norm => $name)
	{
		$id_tag = better_tags_get_or_create_tag($name, $norm);

		if (empty($id_tag))
			continue;

		$smcFunc['db_insert']('ignore',
			'{db_prefix}better_content_tags',
			array('content_type' => 'string', 'content_id' => 'int', 'id_tag' => 'int'),
			array($content_type, $content_id, $id_tag),
			array('content_type', 'content_id', 'id_tag')
		);
	}

	better_tags_prune_unused();
	return true;
}

function better_tags_content_remove($content_type, $content_id)
{
	global $smcFunc;

	$content_type = (string) $content_type;
	$content_id = (int) $content_id;

	if (!better_tags_content_type_valid($content_type) || $content_id <= 0)
		return;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_content_tags
		WHERE content_type = {string:type}
			AND content_id = {int:id}',
		array('type' => $content_type, 'id' => $content_id)
	);

	better_tags_prune_unused();
}

function better_tags_move_content_relations($source, $target)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT content_type, content_id
		FROM {db_prefix}better_content_tags
		WHERE id_tag = {int:source}',
		array('source' => (int) $source)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}better_content_tags',
			array('content_type' => 'string', 'content_id' => 'int', 'id_tag' => 'int'),
			array($row['content_type'], (int) $row['content_id'], (int) $target),
			array('content_type', 'content_id', 'id_tag')
		);
	}
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_content_tags
		WHERE id_tag = {int:source}',
		array('source' => (int) $source)
	);
}

function better_tags_admin_manager()
{
	global $context, $txt, $scripturl, $smcFunc;

	better_tags_load_language();
	isAllowedTo('admin_forum');

	if ($_SERVER['REQUEST_METHOD'] === 'POST')
	{
		checkSession('post');

		$op = isset($_POST['tag_op']) ? (string) $_POST['tag_op'] : '';

		if ($op === 'rename')
		{
			$id_tag = isset($_POST['id_tag']) ? (int) $_POST['id_tag'] : 0;
			$new_name = better_tags_clean_name($_POST['new_name'] ?? '', 80);
			better_tags_admin_rename($id_tag, $new_name);
		}
		elseif ($op === 'merge')
		{
			$source = isset($_POST['id_tag']) ? (int) $_POST['id_tag'] : 0;
			$target = isset($_POST['target_tag']) ? (int) $_POST['target_tag'] : 0;
			better_tags_admin_merge($source, $target);
		}
		elseif ($op === 'delete')
		{
			$id_tag = isset($_POST['id_tag']) ? (int) $_POST['id_tag'] : 0;
			better_tags_admin_delete($id_tag);
		}
		elseif ($op === 'bulk_delete')
		{
			$selected = better_tags_admin_selected_ids($_POST['selected_tags'] ?? array());
			better_tags_admin_bulk_delete($selected);
		}
		elseif ($op === 'bulk_merge')
		{
			$selected = better_tags_admin_selected_ids($_POST['selected_tags'] ?? array());
			$target = isset($_POST['bulk_target_tag']) ? (int) $_POST['bulk_target_tag'] : 0;
			better_tags_admin_bulk_merge($selected, $target);
		}
		elseif ($op === 'cleanup')
		{
			list($relations, $tags) = better_tags_cleanup_orphans();
			better_tags_admin_flash('success', sprintf($txt['better_tags_cleanup_done'], $relations, $tags));
		}

		redirectexit('action=admin;area=bettertags;sa=manager');
	}

	$search = isset($_GET['search']) ? better_tags_clean_name((string) $_GET['search'], 80) : '';
	$sort = isset($_GET['sort']) ? (string) $_GET['sort'] : 'topics';
	if (!in_array($sort, array('topics', 'name', 'newest'), true))
		$sort = 'topics';

	$order = $sort === 'name'
		? 't.tag_name ASC'
		: ($sort === 'newest' ? 't.created_time DESC, t.tag_name ASC' : 'topic_count DESC, t.tag_name ASC');

	$params = array();
	$where = '';
	if ($search !== '')
	{
		$norm = better_tags_normalize($search);
		$escaped = isset($smcFunc['db_escape_wildcard_string'])
			? $smcFunc['db_escape_wildcard_string']($norm)
			: addcslashes($norm, '%_');
		$where = 'WHERE t.tag_norm LIKE {string:search}';
		$params['search'] = '%' . $escaped . '%';
	}

	$request = $smcFunc['db_query']('', '
		SELECT t.id_tag, t.tag_name, t.created_time, COUNT(DISTINCT bt.id_topic) AS topic_count
		FROM {db_prefix}better_tags AS t
			LEFT JOIN {db_prefix}better_topic_tags AS bt ON (bt.id_tag = t.id_tag)
		' . $where . '
		GROUP BY t.id_tag, t.tag_name, t.created_time
		ORDER BY ' . $order,
		$params
	);

	$context['better_tags_manager'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['better_tags_manager'][] = array(
			'id_tag' => (int) $row['id_tag'],
			'tag_name' => $row['tag_name'],
			'topic_count' => (int) $row['topic_count'],
			'created_time' => (int) $row['created_time'],
			'created' => !empty($row['created_time']) ? strip_tags(timeformat((int) $row['created_time'])) : $txt['not_applicable'],
		);
	}
	$smcFunc['db_free_result']($request);

	$context['better_tags_manager_search'] = $search;
	$context['better_tags_manager_sort'] = $sort;
	$context['better_tags_manager_total'] = count($context['better_tags_manager']);
	$context['better_tags_notice'] = $_SESSION['better_tags_notice'] ?? null;
	unset($_SESSION['better_tags_notice']);

	$context['page_title'] = $txt['better_tags_admin_manager'];
	$context['sub_template'] = 'better_tags_manager';

	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');
}

function better_tags_admin_flash($type, $message)
{
	$_SESSION['better_tags_notice'] = array(
		'type' => $type === 'error' ? 'error' : 'success',
		'message' => (string) $message,
	);
}

function better_tags_admin_get($id_tag)
{
	global $smcFunc;

	if ($id_tag <= 0)
		return null;

	$request = $smcFunc['db_query']('', '
		SELECT id_tag, tag_name, tag_norm
		FROM {db_prefix}better_tags
		WHERE id_tag = {int:id}
		LIMIT 1',
		array('id' => $id_tag)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $row ?: null;
}

function better_tags_admin_rename($id_tag, $new_name)
{
	global $smcFunc, $txt;

	$current = better_tags_admin_get($id_tag);
	if (empty($current))
	{
		better_tags_admin_flash('error', $txt['better_tags_manager_not_found']);
		return;
	}

	if ($new_name === '')
	{
		better_tags_admin_flash('error', $txt['better_tags_manager_invalid_name']);
		return;
	}

	$new_norm = better_tags_normalize($new_name);
	$request = $smcFunc['db_query']('', '
		SELECT id_tag
		FROM {db_prefix}better_tags
		WHERE tag_norm = {string:norm}
			AND id_tag != {int:id}
		LIMIT 1',
		array('norm' => $new_norm, 'id' => $id_tag)
	);
	$collision = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($collision))
	{
		better_tags_admin_flash('error', sprintf($txt['better_tags_rename_collision'], $new_name));
		return;
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}better_tags
		SET tag_name = {string:name}, tag_norm = {string:norm}
		WHERE id_tag = {int:id}',
		array('name' => $new_name, 'norm' => $new_norm, 'id' => $id_tag)
	);

	better_tags_admin_flash('success', sprintf($txt['better_tags_rename_done'], $current['tag_name'], $new_name));
}

function better_tags_admin_merge($source, $target)
{
	global $smcFunc, $txt;

	if ($source <= 0 || $target <= 0 || $source === $target)
	{
		better_tags_admin_flash('error', $txt['better_tags_merge_invalid']);
		return;
	}

	$source_tag = better_tags_admin_get($source);
	$target_tag = better_tags_admin_get($target);
	if (empty($source_tag) || empty($target_tag))
	{
		better_tags_admin_flash('error', $txt['better_tags_manager_not_found']);
		return;
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_topic
		FROM {db_prefix}better_topic_tags
		WHERE id_tag = {int:source}',
		array('source' => $source)
	);

	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topics[] = (int) $row['id_topic'];
	$smcFunc['db_free_result']($request);

	foreach ($topics as $id_topic)
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}better_topic_tags',
			array('id_topic' => 'int', 'id_tag' => 'int'),
			array($id_topic, $target),
			array('id_topic', 'id_tag')
		);
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_member, followed_time
		FROM {db_prefix}better_tag_followers
		WHERE id_tag = {int:source}',
		array('source' => $source)
	);
	while ($follow = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}better_tag_followers',
			array('id_member' => 'int', 'id_tag' => 'int', 'followed_time' => 'int'),
			array((int) $follow['id_member'], $target, (int) $follow['followed_time']),
			array('id_member', 'id_tag')
		);
	}
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_tag_followers
		WHERE id_tag = {int:source}',
		array('source' => $source)
	);

	better_tags_move_content_relations($source, $target);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_topic_tags
		WHERE id_tag = {int:source}',
		array('source' => $source)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_tags
		WHERE id_tag = {int:source}',
		array('source' => $source)
	);

	better_tags_admin_flash('success', sprintf($txt['better_tags_merge_done'], $source_tag['tag_name'], $target_tag['tag_name']));
}

function better_tags_admin_delete($id_tag)
{
	global $smcFunc, $txt;

	$tag = better_tags_admin_get($id_tag);
	if (empty($tag))
	{
		better_tags_admin_flash('error', $txt['better_tags_manager_not_found']);
		return;
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_tag_followers
		WHERE id_tag = {int:id}',
		array('id' => $id_tag)
	);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_topic_tags
		WHERE id_tag = {int:id}',
		array('id' => $id_tag)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_content_tags
		WHERE id_tag = {int:id}',
		array('id' => $id_tag)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_tags
		WHERE id_tag = {int:id}',
		array('id' => $id_tag)
	);

	better_tags_admin_flash('success', sprintf($txt['better_tags_delete_done'], $tag['tag_name']));
}


function better_tags_admin_selected_ids($values)
{
	$ids = array_values(array_unique(array_filter(array_map('intval', (array) $values))));
	return array_values(array_filter($ids, static function ($id) {
		return $id > 0;
	}));
}

function better_tags_admin_bulk_delete($selected)
{
	global $smcFunc, $txt;

	if (empty($selected))
	{
		better_tags_admin_flash('error', $txt['better_tags_bulk_none_selected']);
		return;
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_tag_followers
		WHERE id_tag IN ({array_int:ids})',
		array('ids' => $selected)
	);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_topic_tags
		WHERE id_tag IN ({array_int:ids})',
		array('ids' => $selected)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_content_tags
		WHERE id_tag IN ({array_int:ids})',
		array('ids' => $selected)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_tags
		WHERE id_tag IN ({array_int:ids})',
		array('ids' => $selected)
	);

	better_tags_admin_flash('success', sprintf($txt['better_tags_bulk_delete_done'], count($selected)));
}

function better_tags_admin_bulk_merge($selected, $target)
{
	global $smcFunc, $txt;

	if (count($selected) < 2 || $target <= 0 || !in_array($target, $selected, true))
	{
		better_tags_admin_flash('error', $txt['better_tags_bulk_merge_invalid']);
		return;
	}

	$target_tag = better_tags_admin_get($target);
	if (empty($target_tag))
	{
		better_tags_admin_flash('error', $txt['better_tags_manager_not_found']);
		return;
	}

	$sources = array_values(array_diff($selected, array($target)));

	foreach ($sources as $source)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}better_topic_tags
			WHERE id_tag = {int:source}',
			array('source' => $source)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}better_topic_tags',
				array('id_topic' => 'int', 'id_tag' => 'int'),
				array((int) $row['id_topic'], $target),
				array('id_topic', 'id_tag')
			);
		}
		$smcFunc['db_free_result']($request);
		better_tags_move_content_relations($source, $target);
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_tag, followed_time
		FROM {db_prefix}better_tag_followers
		WHERE id_tag IN ({array_int:sources})',
		array('sources' => $sources)
	);
	while ($follow = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}better_tag_followers',
			array('id_member' => 'int', 'id_tag' => 'int', 'followed_time' => 'int'),
			array((int) $follow['id_member'], $target, (int) $follow['followed_time']),
			array('id_member', 'id_tag')
		);
	}
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_tag_followers
		WHERE id_tag IN ({array_int:sources})',
		array('sources' => $sources)
	);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_topic_tags
		WHERE id_tag IN ({array_int:sources})',
		array('sources' => $sources)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_tags
		WHERE id_tag IN ({array_int:sources})',
		array('sources' => $sources)
	);

	better_tags_admin_flash('success', sprintf($txt['better_tags_bulk_merge_done'], count($sources), $target_tag['tag_name']));
}

function better_tags_cleanup_orphans()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}better_topic_tags AS bt
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = bt.id_topic)
		WHERE t.id_topic IS NULL',
		array()
	);
	list($relation_count) = $smcFunc['db_fetch_row']($request) ?: array(0);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}better_tags AS t
			LEFT JOIN {db_prefix}better_topic_tags AS bt ON (bt.id_tag = t.id_tag)
		WHERE bt.id_tag IS NULL',
		array()
	);
	list($tag_count) = $smcFunc['db_fetch_row']($request) ?: array(0);
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		DELETE bt
		FROM {db_prefix}better_topic_tags AS bt
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = bt.id_topic)
		WHERE t.id_topic IS NULL',
		array()
	);

	$smcFunc['db_query']('', '
		DELETE ct
		FROM {db_prefix}better_content_tags AS ct
			LEFT JOIN {db_prefix}better_tags AS t ON (t.id_tag = ct.id_tag)
		WHERE t.id_tag IS NULL',
		array()
	);

	better_tags_prune_unused();

	return array((int) $relation_count, (int) $tag_count);
}

function template_better_tags_manager()
{
	global $context, $txt, $scripturl;

	if (!empty($context['better_tags_notice']))
	{
		$class = $context['better_tags_notice']['type'] === 'error' ? 'errorbox' : 'infobox';
		echo '<div class="', $class, '">', htmlspecialchars($context['better_tags_notice']['message'], ENT_QUOTES), '</div>';
	}

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['better_tags_manager_heading'], '</h3></div>
	<div class="windowbg better_tags_manager_tools">
		<form action="', $scripturl, '" method="get" class="better_tags_manager_filter">
			<input type="hidden" name="action" value="admin">
			<input type="hidden" name="area" value="bettertags">
			<input type="hidden" name="sa" value="manager">
			<div class="better_tags_filter_search">
				<input type="search" name="search" value="', htmlspecialchars($context['better_tags_manager_search'], ENT_QUOTES), '" placeholder="', htmlspecialchars($txt['better_tags_search_placeholder'], ENT_QUOTES), '">
				<select name="sort">
					<option value="topics"', $context['better_tags_manager_sort'] === 'topics' ? ' selected' : '', '>', $txt['better_tags_sort_topics'], '</option>
					<option value="name"', $context['better_tags_manager_sort'] === 'name' ? ' selected' : '', '>', $txt['better_tags_sort_name'], '</option>
					<option value="newest"', $context['better_tags_manager_sort'] === 'newest' ? ' selected' : '', '>', $txt['better_tags_sort_newest'], '</option>
				</select>
				<button type="submit" class="button">', $txt['search'], '</button>
			</div>
		</form>

		<form action="', $scripturl, '?action=admin;area=bettertags;sa=manager" method="post" class="better_tags_cleanup_form">
			<input type="hidden" name="tag_op" value="cleanup">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<button type="submit" class="button">', $txt['better_tags_cleanup_orphans'], '</button>
		</form>
	</div>

	<div class="cat_bar"><h3 class="catbg">', sprintf($txt['better_tags_manager_count'], (int) $context['better_tags_manager_total']), '</h3></div>
	<div class="windowbg better_tags_manager_shell">';

	if (empty($context['better_tags_manager']))
	{
		echo '<p class="information">', $txt['better_tags_manager_empty'], '</p></div>';
		return;
	}

	echo '
		<form id="better_tags_bulk_form" action="', $scripturl, '?action=admin;area=bettertags;sa=manager" method="post" class="better_tags_bulk_toolbar">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">

			<strong id="better_tags_selected_count" class="better_tags_bulk_count">0 selected</strong>

			<select name="bulk_target_tag" id="better_tags_bulk_target" disabled>
				<option value="">', $txt['better_tags_bulk_merge_target'], '</option>';

	foreach ($context['better_tags_manager'] as $tag)
		echo '<option value="', $tag['id_tag'], '" disabled>', htmlspecialchars($tag['tag_name'], ENT_QUOTES), '</option>';

	echo '
			</select>

			<button type="submit" name="tag_op" value="bulk_merge" id="better_tags_merge_selected" class="button" disabled onclick="return confirm(', JavaScriptEscape($txt['better_tags_bulk_merge_confirm']), ');">', $txt['better_tags_merge_selected'], '</button>
			<button type="submit" name="tag_op" value="bulk_delete" id="better_tags_delete_selected" class="button better_tags_bulk_delete" disabled onclick="return confirm(', JavaScriptEscape($txt['better_tags_bulk_delete_confirm']), ');">', $txt['better_tags_delete_selected'], '</button>

			<span class="better_tags_bulk_spacer"></span>

			<button type="button" class="button" id="better_tags_clear_selection">', $txt['better_tags_clear_selection'], '</button>
			<button type="button" class="button" id="better_tags_select_all">', $txt['better_tags_select_all'], '</button>
		</form>

		<div class="better_tags_manager_list">';

	foreach ($context['better_tags_manager'] as $tag)
	{
		echo '
			<div class="better_tags_manager_row">
				<div class="better_tags_manager_check">
					<input type="checkbox" class="better_tags_row_check" name="selected_tags[]" value="', $tag['id_tag'], '" form="better_tags_bulk_form" aria-label="', htmlspecialchars($tag['tag_name'], ENT_QUOTES), '">
				</div>

				<div class="better_tags_manager_primary">
					<strong>', htmlspecialchars($tag['tag_name'], ENT_QUOTES), '</strong>
					<span class="smalltext">', sprintf($txt['better_tags_manager_meta'], $tag['topic_count'], htmlspecialchars($tag['created'], ENT_QUOTES)), '</span>
				</div>

				<div class="better_tags_manager_actions">
					<form action="', $scripturl, '?action=admin;area=bettertags;sa=manager" method="post" class="better_tags_inline_form">
						<input type="hidden" name="tag_op" value="rename">
						<input type="hidden" name="id_tag" value="', $tag['id_tag'], '">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="text" name="new_name" value="', htmlspecialchars($tag['tag_name'], ENT_QUOTES), '" maxlength="80" aria-label="', htmlspecialchars($txt['better_tags_rename'], ENT_QUOTES), '">
						<button type="submit" class="button">', $txt['better_tags_rename'], '</button>
					</form>
				</div>
			</div>';
	}

	echo '
		</div>
	</div>

	<script>
	(function () {
		var checks = Array.prototype.slice.call(document.querySelectorAll(".better_tags_row_check"));
		var selectAll = document.getElementById("better_tags_select_all");
		var clear = document.getElementById("better_tags_clear_selection");
		var count = document.getElementById("better_tags_selected_count");
		var target = document.getElementById("better_tags_bulk_target");
		var mergeButton = document.getElementById("better_tags_merge_selected");
		var deleteButton = document.getElementById("better_tags_delete_selected");

		function refresh() {
			var selected = checks.filter(function (box) { return box.checked; });
			var selectedIds = selected.map(function (box) { return box.value; });

			count.textContent = selected.length + " selected";
			deleteButton.disabled = selected.length === 0;
			target.disabled = selected.length < 2;

			Array.prototype.forEach.call(target.options, function (option, index) {
				if (index === 0) {
					option.disabled = false;
					return;
				}
				option.disabled = selectedIds.indexOf(option.value) === -1;
			});

			if (target.value && selectedIds.indexOf(target.value) === -1)
				target.value = "";

			mergeButton.disabled = selected.length < 2 || !target.value;

			checks.forEach(function (box) {
				var row = box.closest(".better_tags_manager_row");
				if (row)
					row.classList.toggle("better_tags_manager_row_selected", box.checked);
			});
		}

		checks.forEach(function (box) { box.addEventListener("change", refresh); });
		target.addEventListener("change", refresh);

		selectAll.addEventListener("click", function () {
			checks.forEach(function (box) { box.checked = true; });
			refresh();
		});

		clear.addEventListener("click", function () {
			checks.forEach(function (box) { box.checked = false; });
			refresh();
		});

		refresh();
	}());
	</script>';
}

function better_tags_remove_topics($topics)
{
	global $smcFunc;

	$topics = array_values(array_filter(array_map('intval', (array) $topics)));
	if (empty($topics))
		return;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_topic_tags
		WHERE id_topic IN ({array_int:topics})',
		array('topics' => $topics)
	);

	better_tags_prune_unused();
}

function better_tags_action()
{
	global $context, $txt, $smcFunc, $scripturl, $modSettings, $user_info;

	better_tags_load_language();

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] === 'suggest')
	{
		better_tags_suggest_action();
		return;
	}

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] === 'follow')
	{
		better_tags_follow_action(true);
		return;
	}

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] === 'unfollow')
	{
		better_tags_follow_action(false);
		return;
	}

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] === 'following')
	{
		better_tags_following_action();
		return;
	}

	loadTemplate('GenericList');
	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');

	$id_tag = isset($_REQUEST['tag']) ? (int) $_REQUEST['tag'] : 0;
	if ($id_tag <= 0)
		fatal_lang_error('better_tags_not_found', false);

	$request = $smcFunc['db_query']('', '
		SELECT tag_name
		FROM {db_prefix}better_tags
		WHERE id_tag = {int:tag}
		LIMIT 1',
		array('tag' => $id_tag)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (empty($row))
		fatal_lang_error('better_tags_not_found', false);

	$context['page_title'] = sprintf($txt['better_tags_page_title'], $row['tag_name']);
	$context['better_tag_name'] = $row['tag_name'];
	$context['better_tag_id'] = $id_tag;
	$context['better_tag_followed'] = false;
	if (!empty($user_info['id']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}better_tag_followers
			WHERE id_member = {int:member}
				AND id_tag = {int:tag}
			LIMIT 1',
			array('member' => (int) $user_info['id'], 'tag' => $id_tag)
		);
		$context['better_tag_followed'] = (bool) $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}

	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic, t.num_replies, t.num_views, m.subject, m.poster_time, m.id_member, m.poster_name, b.id_board, b.name AS board_name
		FROM {db_prefix}better_topic_tags AS bt
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = bt.id_topic)
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE bt.id_tag = {int:tag}
			AND {query_wanna_see_board}' . (!empty($modSettings['postmod_active']) ? '
			AND t.approved = {int:approved}' : '') . '
		ORDER BY t.id_last_msg DESC
		LIMIT 100',
		array(
			'tag' => $id_tag,
			'approved' => 1,
		)
	);

	$context['better_tag_topics'] = array();
	while ($topic_row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['better_tag_topics'][] = array(
			'id_topic' => (int) $topic_row['id_topic'],
			'subject' => $topic_row['subject'],
			'href' => $scripturl . '?topic=' . (int) $topic_row['id_topic'] . '.0',
			'board_name' => $topic_row['board_name'],
			'board_href' => $scripturl . '?board=' . (int) $topic_row['id_board'] . '.0',
			'poster_name' => $topic_row['poster_name'],
			'poster_time' => timeformat((int) $topic_row['poster_time']),
			'replies' => (int) $topic_row['num_replies'],
			'views' => (int) $topic_row['num_views'],
		);
	}
	$smcFunc['db_free_result']($request);

	$context['better_tag_gallery_albums'] = better_tags_gallery_album_results($id_tag);
	$context['better_tag_gallery_media'] = better_tags_gallery_media_results($id_tag);
	$context['better_tag_downloads'] = better_tags_download_results($id_tag);

	$context['better_tags_member_notice'] = null;
	if (!empty($_SESSION['better_tags_member_notice']))
	{
		if ($_SESSION['better_tags_member_notice'] === 'follow')
			$context['better_tags_member_notice'] = $txt['better_tags_follow_success'];
		elseif ($_SESSION['better_tags_member_notice'] === 'unfollow')
			$context['better_tags_member_notice'] = $txt['better_tags_unfollow_success'];

		unset($_SESSION['better_tags_member_notice']);
	}

	$context['sub_template'] = 'better_tags_results';
}


function better_tags_follow_action($follow)
{
	global $smcFunc, $user_info;

	if (empty($user_info['id']) || !allowedTo('better_tags_use'))
		fatal_lang_error('no_access', false);

	checkSession('get');

	$id_tag = isset($_REQUEST['tag']) ? (int) $_REQUEST['tag'] : 0;
	$tag = better_tags_admin_get($id_tag);
	if (empty($tag))
		fatal_lang_error('better_tags_not_found', false);

	if ($follow)
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}better_tag_followers',
			array('id_member' => 'int', 'id_tag' => 'int', 'followed_time' => 'int'),
			array((int) $user_info['id'], $id_tag, time()),
			array('id_member', 'id_tag')
		);
		$_SESSION['better_tags_member_notice'] = 'follow';
	}
	else
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}better_tag_followers
			WHERE id_member = {int:member}
				AND id_tag = {int:tag}',
			array('member' => (int) $user_info['id'], 'tag' => $id_tag)
		);
		$_SESSION['better_tags_member_notice'] = 'unfollow';
	}

	redirectexit('action=tags;tag=' . $id_tag);
}

function better_tags_following_action()
{
	global $context, $txt, $smcFunc, $scripturl, $user_info, $modSettings;

	if (empty($user_info['id']) || !allowedTo('better_tags_use'))
		fatal_lang_error('no_access', false);

	better_tags_load_language();
	loadCSSFile('better_tags.css', array('minimize' => true, 'order_pos' => 500, 'seed' => 'better-tags-0.1.1'), 'better_tags');

	$context['page_title'] = $txt['better_tags_following'];
	$context['better_tags_followed'] = array();

	$request = $smcFunc['db_query']('', '
		SELECT tg.id_tag, tg.tag_name, f.followed_time,
			COUNT(DISTINCT bt.id_topic) AS topic_count,
			MAX(tp.id_last_msg) AS newest_msg
		FROM {db_prefix}better_tag_followers AS f
			INNER JOIN {db_prefix}better_tags AS tg ON (tg.id_tag = f.id_tag)
			LEFT JOIN {db_prefix}better_topic_tags AS bt ON (bt.id_tag = tg.id_tag)
			LEFT JOIN {db_prefix}topics AS tp ON (tp.id_topic = bt.id_topic)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = tp.id_board)
		WHERE f.id_member = {int:member}
			AND (tp.id_topic IS NULL OR {query_wanna_see_board})' . (!empty($modSettings['postmod_active']) ? '
			AND (tp.id_topic IS NULL OR tp.approved = {int:approved})' : '') . '
		GROUP BY tg.id_tag, tg.tag_name, f.followed_time
		ORDER BY tg.tag_name ASC',
		array(
			'member' => (int) $user_info['id'],
			'approved' => 1,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$newest = null;
		if (!empty($row['newest_msg']))
		{
			$req2 = $smcFunc['db_query']('', '
				SELECT m.id_topic, m.subject
				FROM {db_prefix}messages AS m
				WHERE m.id_msg = {int:msg}
				LIMIT 1',
				array('msg' => (int) $row['newest_msg'])
			);
			$newest = $smcFunc['db_fetch_assoc']($req2) ?: null;
			$smcFunc['db_free_result']($req2);
		}

		$context['better_tags_followed'][] = array(
			'id_tag' => (int) $row['id_tag'],
			'tag_name' => $row['tag_name'],
			'topic_count' => (int) $row['topic_count'],
			'newest' => $newest,
		);
	}
	$smcFunc['db_free_result']($request);

	$context['sub_template'] = 'better_tags_following';
}

function template_better_tags_following()
{
	global $context, $txt, $scripturl;

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['better_tags_following'], '</h3></div>
	<div class="windowbg better_tags_following_intro">', $txt['better_tags_following_intro'], '</div>
	<div class="windowbg better_tags_following">';

	if (empty($context['better_tags_followed']))
	{
		echo '<p class="information">', $txt['better_tags_followed_empty'], '</p></div>';
		return;
	}

	foreach ($context['better_tags_followed'] as $tag)
	{
		echo '
		<div class="better_tags_followed_row">
			<div class="better_tags_followed_identity">
				<a class="better_tags_followed_name" href="', $scripturl, '?action=tags;tag=', $tag['id_tag'], '">', htmlspecialchars($tag['tag_name'], ENT_QUOTES), '</a>
				<span class="better_tags_followed_count">', sprintf($txt['better_tags_followed_meta'], $tag['topic_count']), '</span>
			</div>

			<div class="better_tags_followed_latest">';

		if (!empty($tag['newest']))
			echo '<span class="smalltext">', $txt['better_tags_latest_topic'], '</span> <a href="', $scripturl, '?topic=', (int) $tag['newest']['id_topic'], '.0">', htmlspecialchars($tag['newest']['subject'], ENT_QUOTES), '</a>';
		else
			echo '<span class="smalltext">—</span>';

		echo '
			</div>

			<div class="better_tags_followed_action">
				<a class="button" href="', $scripturl, '?action=tags;sa=unfollow;tag=', $tag['id_tag'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['better_tags_unfollow'], '</a>
			</div>
		</div>';
	}

	echo '</div>';
}

function better_tags_suggest_action()
{
	global $modSettings;

	if (empty($modSettings['better_tags_enabled']) || !allowedTo('better_tags_use'))
	{
		better_tags_json_response(array());
		return;
	}

	$query = isset($_GET['q']) ? better_tags_clean_name((string) $_GET['q'], 80) : '';
	if ($query === '')
	{
		better_tags_json_response(array());
		return;
	}

	better_tags_json_response(better_tags_get_suggestions($query, 10));
}

function better_tags_get_suggestions($query = '', $limit = 100)
{
	global $smcFunc, $modSettings, $sourcedir, $user_info;

	$limit = max(1, min(250, (int) $limit));
	$query = better_tags_clean_name((string) $query, 80);
	$params = array('limit' => 250);
	$where = '';

	if ($query !== '')
	{
		$norm = better_tags_normalize($query);
		$escaped = isset($smcFunc['db_escape_wildcard_string'])
			? $smcFunc['db_escape_wildcard_string']($norm)
			: addcslashes($norm, '%_');
		$where = 'WHERE t.tag_norm LIKE {string:contains}';
		$params['contains'] = '%' . $escaped . '%';
	}

	$request = $smcFunc['db_query']('', '
		SELECT t.id_tag, t.tag_name, t.tag_norm
		FROM {db_prefix}better_tags AS t
		' . $where . '
		ORDER BY t.tag_name ASC
		LIMIT {int:limit}',
		$params
	);

	$candidates = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$candidates[(int) $row['id_tag']] = array(
			'id_tag' => (int) $row['id_tag'],
			'tag_name' => $row['tag_name'],
			'tag_norm' => $row['tag_norm'],
			'topic_count' => 0,
			'usage_count' => 0,
		);
	$smcFunc['db_free_result']($request);

	if (empty($candidates))
		return array();

	$ids = array_keys($candidates);

	$request = $smcFunc['db_query']('', '
		SELECT bt.id_tag, COUNT(DISTINCT bt.id_topic) AS uses
		FROM {db_prefix}better_topic_tags AS bt
			INNER JOIN {db_prefix}topics AS tp ON (tp.id_topic = bt.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = tp.id_board)
		WHERE bt.id_tag IN ({array_int:tags})
			AND {query_wanna_see_board}' . (!empty($modSettings['postmod_active']) ? '
			AND tp.approved = {int:approved}' : '') . '
		GROUP BY bt.id_tag',
		array('tags' => $ids, 'approved' => 1)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$id = (int) $row['id_tag'];
		if (isset($candidates[$id]))
		{
			$candidates[$id]['topic_count'] = (int) $row['uses'];
			$candidates[$id]['usage_count'] += (int) $row['uses'];
		}
	}
	$smcFunc['db_free_result']($request);

	if (better_tags_gallery_album_enabled() && is_file($sourcedir . '/BetterGallery.php'))
	{
		require_once($sourcedir . '/BetterGallery.php');

		if (function_exists('bg_album_load') && function_exists('bg_album_is_discoverable'))
		{
			$request = $smcFunc['db_query']('', '
				SELECT ct.id_tag, ct.content_id
				FROM {db_prefix}better_content_tags AS ct
				WHERE ct.content_type = {string:type}
					AND ct.id_tag IN ({array_int:tags})',
				array('type' => 'gallery_album', 'tags' => $ids)
			);

			$is_admin = !empty($user_info['is_admin'])
				|| (function_exists('bg_allowed') && bg_allowed('bg_moderate_gallery'));

			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$id = (int) $row['id_tag'];
				if (!isset($candidates[$id]))
					continue;

				$album = bg_album_load((int) $row['content_id']);
				if (!$album)
					continue;
				if (function_exists('bg_portfolio_album_visible') && !bg_portfolio_album_visible($album))
					continue;

				$is_owner = !empty($user_info['id']) && (int) $album['id_owner'] === (int) $user_info['id'];
				if (!bg_album_is_discoverable($album, (int) ($user_info['id'] ?? 0), $is_admin) && !$is_owner)
					continue;

				$candidates[$id]['usage_count']++;
			}
			$smcFunc['db_free_result']($request);
		}
	}


	if (better_tags_gallery_media_enabled() && is_file($sourcedir . '/BetterGallery.php'))
	{
		require_once($sourcedir . '/BetterGallery.php');
		if (function_exists('bg_media_load'))
		{
			$request = $smcFunc['db_query']('', '
				SELECT ct.id_tag, ct.content_id
				FROM {db_prefix}better_content_tags AS ct
				WHERE ct.content_type = {string:type}
					AND ct.id_tag IN ({array_int:tags})',
				array('type' => 'gallery_media', 'tags' => $ids)
			);

			$is_admin = !empty($user_info['is_admin'])
				|| (function_exists('bg_allowed') && bg_allowed('bg_moderate_gallery'));

			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$id = (int) $row['id_tag'];
				if (!isset($candidates[$id]))
					continue;

				$media = bg_media_load((int) $row['content_id']);
				if (!$media)
					continue;

				$album = array(
					'id_album' => (int) $media['id_album'],
					'id_owner' => (int) $media['album_owner'],
					'discovery_visibility' => (int) $media['discovery_visibility'],
					'access_mode' => (int) $media['access_mode'],
					'password_hash' => $media['password_hash'],
				);

				if (function_exists('bg_portfolio_album_visible') && !bg_portfolio_album_visible($album))
					continue;
				if (function_exists('bg_album_content_accessible')
					&& !bg_album_content_accessible($album, (int) ($user_info['id'] ?? 0), $is_admin))
					continue;

				$candidates[$id]['usage_count']++;
			}
			$smcFunc['db_free_result']($request);
		}
	}


	if (better_tags_downloads_enabled() && is_file($sourcedir . '/BetterDownloads.php'))
	{
		require_once($sourcedir . '/BetterDownloads.php');
		if (function_exists('bd_get_download'))
		{
			$request = $smcFunc['db_query']('', '
				SELECT ct.id_tag, ct.content_id
				FROM {db_prefix}better_content_tags AS ct
				WHERE ct.content_type = {string:type}
					AND ct.id_tag IN ({array_int:tags})',
				array('type' => 'download', 'tags' => $ids)
			);

			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$id = (int) $row['id_tag'];
				if (!isset($candidates[$id]))
					continue;

				if (bd_get_download((int) $row['content_id'], false))
					$candidates[$id]['usage_count']++;
			}
			$smcFunc['db_free_result']($request);
		}
	}


	$norm_query = $query !== '' ? better_tags_normalize($query) : '';
	$results = array_values(array_filter($candidates, function ($row) {
		return !empty($row['usage_count']);
	}));

	usort($results, function ($a, $b) use ($norm_query) {
		if ($norm_query !== '')
		{
			$ar = $a['tag_norm'] === $norm_query ? 0 : (strpos($a['tag_norm'], $norm_query) === 0 ? 1 : 2);
			$br = $b['tag_norm'] === $norm_query ? 0 : (strpos($b['tag_norm'], $norm_query) === 0 ? 1 : 2);
			if ($ar !== $br)
				return $ar <=> $br;
		}
		if ($a['usage_count'] !== $b['usage_count'])
			return $b['usage_count'] <=> $a['usage_count'];
		return strcasecmp($a['tag_name'], $b['tag_name']);
	});

	return array_slice($results, 0, $limit);
}

function better_tags_json_response(array $payload)
{
	if (!headers_sent())
	{
		header('Content-Type: application/json; charset=UTF-8');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	}

	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	obExit(false);
}

function template_better_tags_results()
{
	global $context, $txt, $scripturl;

	if (!empty($context['better_tags_member_notice']))
		echo '<div class="infobox">', htmlspecialchars($context['better_tags_member_notice'], ENT_QUOTES), '</div>';

	echo '
	<div class="cat_bar"><h3 class="catbg">', sprintf($txt['better_tags_page_title'], htmlspecialchars($context['better_tag_name'], ENT_QUOTES)), '</h3></div>
	<div class="windowbg better_tags_results">
		<div class="better_tags_follow_bar">';

	if (empty($context['user']['is_guest']) && allowedTo('better_tags_use'))
	{
		echo '<a class="button" href="', $scripturl, '?action=tags;sa=', $context['better_tag_followed'] ? 'unfollow' : 'follow', ';tag=', (int) $context['better_tag_id'], ';', $context['session_var'], '=', $context['session_id'], '">',
			$context['better_tag_followed'] ? $txt['better_tags_unfollow'] : $txt['better_tags_follow'], '</a>
			<a class="button" href="', $scripturl, '?action=tags;sa=following">', $txt['better_tags_my_followed'], '</a>';
	}
	else
		echo '<span class="smalltext">', $txt['better_tags_follow_login'], '</span>';

	echo '</div>';

	echo '<h4 class="better_tags_results_heading">', $txt['better_tags_forum_topics_heading'] ?? 'Forum Topics', '</h4>';

	if (empty($context['better_tag_topics']))
		echo '<p class="information">', $txt['better_tags_no_topics'], '</p>';
	else
	{
		echo '<ul class="better_tags_topic_list">';
		foreach ($context['better_tag_topics'] as $topic)
		{
			echo '<li><a class="better_tags_topic_subject" href="', $topic['href'], '">', htmlspecialchars($topic['subject'], ENT_QUOTES), '</a>',
				'<div class="smalltext">', sprintf($txt['better_tags_topic_meta'], '<a href="' . $topic['board_href'] . '">' . htmlspecialchars($topic['board_name'], ENT_QUOTES) . '</a>', htmlspecialchars($topic['poster_name'], ENT_QUOTES), $topic['poster_time'], $topic['replies'], $topic['views']), '</div></li>';
		}
		echo '</ul>';
	}

	if (!empty($context['better_tag_gallery_albums']) || better_tags_gallery_album_enabled())
	{
		echo '<h4 class="better_tags_results_heading">', $txt['better_tags_gallery_albums_heading'], '</h4>';

		if (empty($context['better_tag_gallery_albums']))
			echo '<p class="information">', $txt['better_tags_no_gallery_albums'], '</p>';
		else
		{
			echo '<ul class="better_tags_topic_list better_tags_gallery_album_results">';
			foreach ($context['better_tag_gallery_albums'] as $album)
			{
				$meta = sprintf(
					$txt['better_tags_gallery_album_meta'],
					htmlspecialchars($album['owner_name'], ENT_QUOTES),
					htmlspecialchars($album['category_name'] !== '' ? $album['category_name'] : 'Uncategorized', ENT_QUOTES)
				);
				echo '<li><a class="better_tags_topic_subject" href="', $album['href'], '">',
					htmlspecialchars($album['title'], ENT_QUOTES), '</a><div class="smalltext">', $meta, '</div></li>';
			}
			echo '</ul>';
		}
	}


	if (!empty($context['better_tag_gallery_media']) || better_tags_gallery_media_enabled())
	{
		echo '<h4 class="better_tags_results_heading">', $txt['better_tags_gallery_media_heading'], '</h4>';

		if (empty($context['better_tag_gallery_media']))
			echo '<p class="information">', $txt['better_tags_no_gallery_media'], '</p>';
		else
		{
			echo '<ul class="better_tags_topic_list better_tags_gallery_media_results">';
			foreach ($context['better_tag_gallery_media'] as $media)
			{
				$meta = sprintf(
					$txt['better_tags_gallery_media_meta'],
					htmlspecialchars($media['owner_name'], ENT_QUOTES),
					htmlspecialchars($media['album_title'], ENT_QUOTES)
				);
				echo '<li><a class="better_tags_topic_subject" href="', $media['href'], '">',
					htmlspecialchars($media['title'], ENT_QUOTES), '</a><div class="smalltext">', $meta, '</div></li>';
			}
			echo '</ul>';
		}
	}


	if (!empty($context['better_tag_downloads']) || better_tags_downloads_enabled())
	{
		echo '<h4 class="better_tags_results_heading">', $txt['better_tags_downloads_heading'], '</h4>';

		if (empty($context['better_tag_downloads']))
			echo '<p class="information">', $txt['better_tags_no_downloads'], '</p>';
		else
		{
			echo '<ul class="better_tags_topic_list better_tags_download_results">';
			foreach ($context['better_tag_downloads'] as $download)
			{
				$meta = sprintf(
					$txt['better_tags_download_meta'],
					htmlspecialchars($download['category_name'] !== '' ? $download['category_name'] : 'Uncategorized', ENT_QUOTES),
					htmlspecialchars($download['version'] !== '' ? $download['version'] : '—', ENT_QUOTES)
				);
				echo '<li><a class="better_tags_topic_subject" href="', $download['href'], '">',
					htmlspecialchars($download['title'], ENT_QUOTES), '</a><div class="smalltext">', $meta, '</div></li>';
			}
			echo '</ul>';
		}
	}

	echo '</div>';
}

function better_tags_save_from_request($id_topic, $notify_followers = false)
{
	global $modSettings;

	if (empty($modSettings['better_tags_enabled']) || !allowedTo('better_tags_use') || !isset($_POST['better_tags']))
		return;

	$raw = (string) $_POST['better_tags'];
	$max_tags = max(1, min(30, (int) ($modSettings['better_tags_max_per_topic'] ?? 8)));
	$max_length = max(12, min(80, (int) ($modSettings['better_tags_max_length'] ?? 40)));

	$names = preg_split('/\s*,\s*/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: array();
	$clean = array();
	foreach ($names as $name)
	{
		$name = better_tags_clean_name($name, $max_length);
		if ($name === '')
			continue;

		$norm = better_tags_normalize($name);
		if (!isset($clean[$norm]))
			$clean[$norm] = $name;

		if (count($clean) >= $max_tags)
			break;
	}

	better_tags_assign((int) $id_topic, $clean, !empty($notify_followers));
}

function better_tags_clean_name($name, $max_length)
{
	$name = html_entity_decode(strip_tags((string) $name), ENT_QUOTES, 'UTF-8');
	$name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
	$name = preg_replace('/\s+/u', ' ', trim($name));
	$name = trim($name, " \t\n\r\0\x0B,#");

	if ($name === '')
		return '';

	if (function_exists('mb_substr'))
		$name = mb_substr($name, 0, $max_length, 'UTF-8');
	else
		$name = substr($name, 0, $max_length);

	return trim($name);
}

function better_tags_normalize($name)
{
	$name = preg_replace('/\s+/u', ' ', trim((string) $name));
	return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

/**
 * Resolve or create the canonical tag row without racing another request.
 *
 * tag_norm is UNIQUE. INSERT IGNORE plus a final lookup makes simultaneous
 * first-use requests safe and prevents duplicate-key errors.
 */
function better_tags_get_or_create_tag($name, $norm)
{
	global $smcFunc;

	$name = (string) $name;
	$norm = (string) $norm;

	if ($name === '' || $norm === '')
		return 0;

	$request = $smcFunc['db_query']('', '
		SELECT id_tag
		FROM {db_prefix}better_tags
		WHERE tag_norm = {string:norm}
		LIMIT 1',
		array('norm' => $norm)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($row['id_tag']))
		return (int) $row['id_tag'];

	$smcFunc['db_insert']('ignore',
		'{db_prefix}better_tags',
		array('tag_name' => 'string', 'tag_norm' => 'string', 'created_time' => 'int'),
		array($name, $norm, time()),
		array('tag_norm')
	);

	$request = $smcFunc['db_query']('', '
		SELECT id_tag
		FROM {db_prefix}better_tags
		WHERE tag_norm = {string:norm}
		LIMIT 1',
		array('norm' => $norm)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return !empty($row['id_tag']) ? (int) $row['id_tag'] : 0;
}


function better_tags_assign($id_topic, array $tags, $notify_followers = false)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}better_topic_tags
		WHERE id_topic = {int:topic}',
		array('topic' => $id_topic)
	);

	foreach ($tags as $norm => $name)
	{
		$id_tag = better_tags_get_or_create_tag($name, $norm);

		if (!empty($id_tag))
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}better_topic_tags',
				array('id_topic' => 'int', 'id_tag' => 'int'),
				array($id_topic, $id_tag),
				array('id_topic', 'id_tag')
			);
		}
	}

	if (!empty($notify_followers))
		better_tags_notify_followers($id_topic);

	better_tags_prune_unused();
}

function better_tags_notify_followers($id_topic)
{
	global $smcFunc, $scripturl, $user_info;

	$id_topic = (int) $id_topic;
	if ($id_topic <= 0)
		return;

	$request = $smcFunc['db_query']('', '
		SELECT t.id_board, m.subject
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:topic}
		LIMIT 1',
		array('topic' => $id_topic)
	);
	$topic = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (empty($topic))
		return;

	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT f.id_member, f.id_tag, tg.tag_name
		FROM {db_prefix}better_tag_followers AS f
			INNER JOIN {db_prefix}better_topic_tags AS bt ON (bt.id_tag = f.id_tag)
			INNER JOIN {db_prefix}better_tags AS tg ON (tg.id_tag = f.id_tag)
		WHERE bt.id_topic = {int:topic}
			AND f.id_member != {int:poster}',
		array(
			'topic' => $id_topic,
			'poster' => (int) ($user_info['id'] ?? 0),
		)
	);

	$followers = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$followers[] = $row;
	$smcFunc['db_free_result']($request);

	if (empty($followers))
		return;

	$seen_members = array();
	$alert_rows = array();

	foreach ($followers as $row)
	{
		$id_member = (int) $row['id_member'];
		if ($id_member <= 0 || isset($seen_members[$id_member]))
			continue;

		$qb = build_query_board($id_member);
		$vis_params = array('board' => (int) $topic['id_board']) + ($qb['params'] ?? array());
		$vis = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE b.id_board = {int:board}
				AND ' . $qb['query_see_board'] . '
			LIMIT 1',
			$vis_params
		);
		$can_see = (bool) $smcFunc['db_fetch_assoc']($vis);
		$smcFunc['db_free_result']($vis);

		if (!$can_see)
			continue;

		$extra = array(
			'tag_name' => $row['tag_name'],
			'tag_id' => (int) $row['id_tag'],
			'content_subject' => $topic['subject'],
			'topic' => $id_topic,
			'content_link' => $scripturl . '?topic=' . $id_topic . '.0',
			'show_links' => true,
		);

		$alert_rows[] = array(
			'alert_time' => time(),
			'id_member' => $id_member,
			'id_member_started' => (int) ($user_info['id'] ?? 0),
			'member_name' => (string) ($user_info['name'] ?? ''),
			'content_type' => 'topic',
			'content_id' => $id_topic,
			'content_action' => 'better_tag',
			'is_read' => 0,
			'extra' => $smcFunc['json_encode']($extra),
		);
		$seen_members[$id_member] = true;
	}

	if (empty($alert_rows))
		return;

	$smcFunc['db_insert']('',
		'{db_prefix}user_alerts',
		array(
			'alert_time' => 'int',
			'id_member' => 'int',
			'id_member_started' => 'int',
			'member_name' => 'string',
			'content_type' => 'string',
			'content_id' => 'int',
			'content_action' => 'string',
			'is_read' => 'int',
			'extra' => 'string',
		),
		$alert_rows,
		array()
	);

	updateMemberData(array_column($alert_rows, 'id_member'), array('alerts' => '+'));
}

function better_tags_get_topic($id_topic)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT bt.id_tag, t.tag_name
		FROM {db_prefix}better_topic_tags AS bt
			INNER JOIN {db_prefix}better_tags AS t ON (t.id_tag = bt.id_tag)
		WHERE bt.id_topic = {int:topic}
		ORDER BY t.tag_name',
		array('topic' => $id_topic)
	);

	$tags = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$tags[] = array('id_tag' => (int) $row['id_tag'], 'tag_name' => $row['tag_name']);
	$smcFunc['db_free_result']($request);

	return $tags;
}

function better_tags_get_topic_names($id_topic)
{
	return array_column(better_tags_get_topic($id_topic), 'tag_name');
}

function better_tags_prune_unused()
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE f
		FROM {db_prefix}better_tag_followers AS f
			LEFT JOIN {db_prefix}better_tags AS t ON (t.id_tag = f.id_tag)
			LEFT JOIN {db_prefix}members AS m ON (m.id_member = f.id_member)
		WHERE t.id_tag IS NULL
			OR m.id_member IS NULL',
		array()
	);

	$smcFunc['db_query']('', '
		DELETE t
		FROM {db_prefix}better_tags AS t
		LEFT JOIN {db_prefix}better_topic_tags AS bt ON (bt.id_tag = t.id_tag)
		LEFT JOIN {db_prefix}better_content_tags AS ct ON (ct.id_tag = t.id_tag)
		WHERE bt.id_tag IS NULL
			AND ct.id_tag IS NULL',
		array()
	);
}
