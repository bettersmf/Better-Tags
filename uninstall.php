<?php
if (!defined('SMF'))
	die('No direct access...');

global $sourcedir, $smcFunc, $modSettings;
require_once($sourcedir . '/Subs-Admin.php');

$hooks = array(
	'integrate_actions' => 'better_tags_actions',
	'integrate_post_end' => 'better_tags_post_fields',
	'integrate_create_topic' => 'better_tags_create_topic',
	'integrate_post2_end' => 'better_tags_post2_end',
	'integrate_modify_post' => 'better_tags_modify_post',
	'integrate_display_topic' => 'better_tags_display_topic',
	'integrate_prepare_display_context' => 'better_tags_prepare_display',
	'integrate_load_permissions' => 'better_tags_permissions',
	'integrate_admin_areas' => 'better_tags_admin_areas',
	'integrate_remove_topics' => 'better_tags_remove_topics',
	'integrate_fetch_alerts' => 'better_tags_fetch_alerts',
	'integrate_menu_buttons' => 'better_tags_menu_buttons',
	'integrate_bettergallery_album_form' => 'better_tags_gallery_album_form',
	'integrate_bettergallery_album_saved' => 'better_tags_gallery_album_saved',
	'integrate_bettergallery_album_view' => 'better_tags_gallery_album_view',
	'integrate_bettergallery_album_deleted' => 'better_tags_gallery_album_deleted',
	'integrate_bettergallery_media_form' => 'better_tags_gallery_media_form',
	'integrate_bettergallery_media_saved' => 'better_tags_gallery_media_saved',
	'integrate_bettergallery_media_info' => 'better_tags_gallery_media_info',
	'integrate_bettergallery_media_deleted' => 'better_tags_gallery_media_deleted',
	'integrate_betterdownloads_item_form' => 'better_tags_download_form',
	'integrate_betterdownloads_item_saved' => 'better_tags_download_saved',
	'integrate_betterdownloads_item_view' => 'better_tags_download_view',
	'integrate_betterdownloads_item_deleted' => 'better_tags_download_deleted',
);

foreach ($hooks as $hook => $function)
	remove_integration_function($hook, $function, true, '$sourcedir/BetterTags.php');

if (!empty($modSettings['better_tags_remove_data_on_uninstall']))
{
	$tables = array(
		'{db_prefix}better_tag_followers',
		'{db_prefix}better_content_tags',
		'{db_prefix}better_topic_tags',
		'{db_prefix}better_tags',
	);

	foreach ($tables as $table)
		$smcFunc['db_query']('', 'DROP TABLE IF EXISTS ' . $table, array());

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}settings
		WHERE variable LIKE {string:prefix}',
		array('prefix' => 'better_tags_%')
	);
}
