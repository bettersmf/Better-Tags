<?php
if (!defined('SMF'))
	die('No direct access...');

global $smcFunc, $sourcedir, $db_type, $modSettings;

require_once($sourcedir . '/Subs-Admin.php');
require_once($sourcedir . '/DbPackages-' . $db_type . '.php');

// Initialize SMF's package database helpers before using db_create_table.
db_packages_init();

$smcFunc['db_create_table']('{db_prefix}better_tags', array(
	array('name' => 'id_tag', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
	array('name' => 'tag_name', 'type' => 'varchar', 'size' => 80),
	array('name' => 'tag_norm', 'type' => 'varchar', 'size' => 80),
	array('name' => 'created_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
), array(
	array('type' => 'primary', 'columns' => array('id_tag')),
	array('type' => 'unique', 'columns' => array('tag_norm')),
), array(), 'ignore');

$smcFunc['db_create_table']('{db_prefix}better_topic_tags', array(
	array('name' => 'id_topic', 'type' => 'int', 'size' => 10, 'unsigned' => true),
	array('name' => 'id_tag', 'type' => 'int', 'size' => 10, 'unsigned' => true),
), array(
	array('type' => 'primary', 'columns' => array('id_topic', 'id_tag')),
	array('type' => 'index', 'columns' => array('id_tag')),
), array(), 'ignore');

$smcFunc['db_create_table']('{db_prefix}better_content_tags', array(
	array('name' => 'content_type', 'type' => 'varchar', 'size' => 32),
	array('name' => 'content_id', 'type' => 'bigint', 'size' => 20, 'unsigned' => true),
	array('name' => 'id_tag', 'type' => 'int', 'size' => 10, 'unsigned' => true),
), array(
	array('type' => 'primary', 'columns' => array('content_type', 'content_id', 'id_tag')),
	array('type' => 'index', 'columns' => array('id_tag', 'content_type')),
	array('type' => 'index', 'columns' => array('content_type', 'content_id')),
), array(), 'ignore');

$smcFunc['db_create_table']('{db_prefix}better_tag_followers', array(
	array('name' => 'id_member', 'type' => 'int', 'size' => 10, 'unsigned' => true),
	array('name' => 'id_tag', 'type' => 'int', 'size' => 10, 'unsigned' => true),
	array('name' => 'followed_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
), array(
	array('type' => 'primary', 'columns' => array('id_member', 'id_tag')),
	array('type' => 'index', 'columns' => array('id_tag')),
), array(), 'ignore');

$better_tags_defaults = array(
	'better_tags_enabled' => 1,
	'better_tags_max_per_topic' => 8,
	'better_tags_max_length' => 40,
	'better_tags_gallery_enabled' => 0,
	'better_tags_gallery_media' => 1,
	'better_tags_gallery_albums' => 1,
	'better_tags_downloads_enabled' => 0,
	'better_tags_download_items' => 1,
	'better_tags_remove_data_on_uninstall' => 0,
);

$better_tags_new_settings = array();
foreach ($better_tags_defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$better_tags_new_settings[$key] = $value;

if (!netty($better_tags_new_settings))
	updateSettings($better_tags_new_settings);

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
{
	// During an upgrade, BetterTags.php may already have been loaded earlier in
// this same PHP request. The package can replace the file on disk, but PHP
	// cannot replace functions already in memory. Persist newly introduced hooks
	// for the next request without making them callable against stale in-memory code.
	$old_runtime_hook = $modSettings[$hook] ?? null;

	add_integration_function($hook, $function, true, '$sourcedir/BetterTags.php');

	if ($hook === 'integrate_menu_buttons')
	{
		if ($old_runtime_hook === null)
			unset($modSettings[$hook]);
		else
			$modSettings[$hook] = $old_runtime_hook;
	}
}
