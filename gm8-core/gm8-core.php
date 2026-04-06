<?php
/**
 * Plugin Name: GM8 Core
 * Description: Managed admin cleanup for hosted client sites (dashboard widgets, welcome panel, selective notices). For Gas Mark 8 hosting clients only.
 * Version: 0.1.1
 * Author: Gas Mark 8, Ltd.
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Read a wp-config.php constant, otherwise use a default.
 *
 * @template T
 * @param string $name
 * @param T $default
 * @return T
 */
function gm8_cleanup_const($name, $default) {
	if (defined($name)) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internal config values
		return constant($name);
	}
	return $default;
}

function gm8_cleanup_enabled() {
	return (bool) gm8_cleanup_const('GM8_CLEANUP_ENABLED', true);
}

/**
 * Normalize dashboard removal list into tuples: [id, context].
 *
 * Supported inputs:
 * - [['id' => 'dashboard_primary', 'context' => 'side'], ...]
 * - ['dashboard_primary' => 'side', 'dashboard_activity' => 'normal']
 * - ['dashboard_primary', 'dashboard_activity'] (context defaults to 'normal')
 *
 * @param mixed $value
 * @return array<int, array{0:string,1:string}>
 */
function gm8_cleanup_normalize_dashboard_remove($value) {
	$out = [];

	if (is_array($value)) {
		$is_assoc = array_keys($value) !== range(0, count($value) - 1);

		if ($is_assoc) {
			foreach ($value as $id => $context) {
				if (!is_string($id) || $id === '') {
					continue;
				}
				$ctx = is_string($context) && $context !== '' ? $context : 'normal';
				$out[] = [$id, $ctx];
			}
			return $out;
		}

		foreach ($value as $item) {
			if (is_string($item) && $item !== '') {
				$out[] = [$item, 'normal'];
				continue;
			}

			if (is_array($item) && isset($item['id'])) {
				$id = is_string($item['id']) ? $item['id'] : '';
				if ($id === '') {
					continue;
				}
				$ctx = (isset($item['context']) && is_string($item['context']) && $item['context'] !== '') ? $item['context'] : 'normal';
				$out[] = [$id, $ctx];
			}
		}
	}

	return $out;
}

/**
 * Turn a WP hook callback into a stable-ish string for comparisons.
 *
 * @param mixed $callback
 * @return string
 */
function gm8_cleanup_callback_id($callback) {
	if (is_string($callback)) {
		return $callback;
	}
	if (is_array($callback) && count($callback) === 2) {
		$left = is_object($callback[0]) ? get_class($callback[0]) : (string) $callback[0];
		$right = (string) $callback[1];
		return $left . '::' . $right;
	}
	if ($callback instanceof Closure) {
		return 'closure';
	}
	if (is_object($callback) && method_exists($callback, '__invoke')) {
		return get_class($callback) . '::__invoke';
	}
	return 'unknown';
}

/**
 * Dashboard cleanup (widgets + welcome panel).
 */
function gm8_cleanup_dashboard() {
	if (!gm8_cleanup_enabled()) {
		return;
	}
	if (!is_admin()) {
		return;
	}

	if ((bool) gm8_cleanup_const('GM8_CLEANUP_REMOVE_WELCOME_PANEL', true)) {
		remove_action('welcome_panel', 'wp_welcome_panel');
	}

	if (!(bool) gm8_cleanup_const('GM8_CLEANUP_DASHBOARD_ENABLED', true)) {
		return;
	}

	$default_remove = [
		['id' => 'wp-dashboard-widget-news', 'context' => 'normal'],
		['id' => 'wpseo-wincher-dashboard-overview', 'context' => 'normal'],
		['id' => 'tribe_dashboard_widget', 'context' => 'normal'],
		['id' => 'dashboard_site_health', 'context' => 'normal'],
		['id' => 'fluentsmtp_reports_widget', 'context' => 'side'],
		['id' => 'dashboard_objectcache', 'context' => 'side'],

		['id' => 'dashboard_primary', 'context' => 'side'],
		['id' => 'dashboard_secondary', 'context' => 'side'],
		['id' => 'dashboard_quick_press', 'context' => 'side'],
		['id' => 'dashboard_recent_drafts', 'context' => 'side'],

		['id' => 'dashboard_php_nag', 'context' => 'normal'],
		['id' => 'dashboard_browser_nag', 'context' => 'normal'],
		['id' => 'health_check_status', 'context' => 'normal'],
		['id' => 'dashboard_activity', 'context' => 'normal'],
		['id' => 'dashboard_right_now', 'context' => 'normal'],
		['id' => 'network_dashboard_right_now', 'context' => 'normal'],
		['id' => 'dashboard_recent_comments', 'context' => 'normal'],
		['id' => 'dashboard_incoming_links', 'context' => 'normal'],
		['id' => 'dashboard_plugins', 'context' => 'normal'],

		['id' => 'e-dashboard-overview', 'context' => 'side'],
		['id' => 'e-dashboard-overview', 'context' => 'normal'],
		['id' => 'cn_dashboard_stats', 'context' => 'side'],
		['id' => 'rg_forms_dashboard', 'context' => 'side'],
		['id' => 'themeisle', 'context' => 'side'],
		['id' => 'wpseo-dashboard-overview', 'context' => 'side'],
		['id' => 'wpseo-dashboard-overview', 'context' => 'normal'],
		['id' => 'wordfence_activity_report_widget', 'context' => 'side'],
		['id' => 'wpdm_social_overview', 'context' => 'side'],
	];

	$remove = gm8_cleanup_const('GM8_CLEANUP_DASHBOARD_REMOVE', $default_remove);
	$pairs = gm8_cleanup_normalize_dashboard_remove($remove);

	foreach ($pairs as $pair) {
		[$id, $context] = $pair;
		remove_meta_box($id, 'dashboard', $context);
	}
}
add_action('wp_dashboard_setup', 'gm8_cleanup_dashboard', 99);

/**
 * Admin notices suppression (safer than remove_all_actions('admin_notices')).
 */
function gm8_cleanup_should_suppress_notices_for_user() {
	$mode = (string) gm8_cleanup_const('GM8_CLEANUP_NOTICES_MODE', 'allow');
	if ($mode === 'allow') {
		return false;
	}

	if ($mode === 'block_non_admins') {
		return !current_user_can('manage_options');
	}

	if ($mode === 'block_except_user_ids') {
		$allow = gm8_cleanup_const('GM8_CLEANUP_NOTICES_ALLOW_USER_IDS', [1]);
		$allow = is_array($allow) ? $allow : [1];
		$user_id = get_current_user_id();
		return !in_array((int) $user_id, array_map('intval', $allow), true);
	}

	return false;
}

function gm8_cleanup_notice_screen_allowed($screen_id) {
	$allowed = gm8_cleanup_const('GM8_CLEANUP_NOTICES_SCREEN_IDS', ['dashboard']);
	$allowed = is_array($allowed) ? $allowed : ['dashboard'];
	return in_array((string) $screen_id, array_map('strval', $allowed), true);
}

function gm8_cleanup_prune_notice_hooks() {
	if (!gm8_cleanup_enabled() || !is_admin()) {
		return;
	}
	if (!gm8_cleanup_should_suppress_notices_for_user()) {
		return;
	}

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	$screen_id = ($screen && isset($screen->id)) ? (string) $screen->id : '';

	if ($screen_id === '' || !gm8_cleanup_notice_screen_allowed($screen_id)) {
		return;
	}

	global $wp_filter;
	if (!isset($wp_filter['admin_notices']) && !isset($wp_filter['all_admin_notices'])) {
		return;
	}

	$keep_callbacks = [
		'update_nag',
		'maintenance_nag',
	];

	$hooks = ['admin_notices', 'all_admin_notices'];
	foreach ($hooks as $hook) {
		if (empty($wp_filter[$hook])) {
			continue;
		}

		$filter = $wp_filter[$hook];
		if (!is_object($filter) || !isset($filter->callbacks) || !is_array($filter->callbacks)) {
			continue;
		}

		foreach ($filter->callbacks as $priority => $callbacks) {
			if (!is_array($callbacks)) {
				continue;
			}

			foreach ($callbacks as $key => $cb) {
				if (!isset($cb['function'])) {
					continue;
				}
				$id = gm8_cleanup_callback_id($cb['function']);
				if (in_array($id, $keep_callbacks, true)) {
					continue;
				}
				if ($id === 'gm8_cleanup_prune_notice_hooks') {
					continue;
				}

				unset($filter->callbacks[$priority][$key]);
			}

			if (empty($filter->callbacks[$priority])) {
				unset($filter->callbacks[$priority]);
			}
		}
	}
}
add_action('admin_notices', 'gm8_cleanup_prune_notice_hooks', 0);
add_action('all_admin_notices', 'gm8_cleanup_prune_notice_hooks', 0);

/**
 * Optional: strip generator + remove ?ver= from script/style URLs.
 *
 * Trade-off: removing ?ver= can reduce cache-busting and cause stale assets behind aggressive caches/CDNs.
 */
function gm8_cleanup_strip_generator() {
	return '';
}

function gm8_cleanup_strip_asset_ver($src) {
	if (!is_string($src) || $src === '') {
		return $src;
	}
	if (strpos($src, 'ver=') === false) {
		return $src;
	}
	return remove_query_arg('ver', $src);
}

if (gm8_cleanup_enabled() && (bool) gm8_cleanup_const('GM8_CLEANUP_STRIP_GENERATOR', false)) {
	add_filter('the_generator', 'gm8_cleanup_strip_generator');
}

if (gm8_cleanup_enabled() && (bool) gm8_cleanup_const('GM8_CLEANUP_STRIP_ASSET_VER', false)) {
	add_filter('style_loader_src', 'gm8_cleanup_strip_asset_ver', 9999);
	add_filter('script_loader_src', 'gm8_cleanup_strip_asset_ver', 9999);
}

/**
 * Optional: hide admin menu items/admin bar items by slug/node id.
 */
function gm8_cleanup_hide_admin_menu_items() {
	if (!gm8_cleanup_enabled() || !is_admin()) {
		return;
	}
	$items = gm8_cleanup_const('GM8_CLEANUP_HIDE_MENU_ITEMS', []);
	if (!is_array($items) || empty($items)) {
		return;
	}
	foreach ($items as $slug) {
		if (is_string($slug) && $slug !== '') {
			remove_menu_page($slug);
		}
	}
}
add_action('admin_menu', 'gm8_cleanup_hide_admin_menu_items', 999);

function gm8_cleanup_hide_admin_bar_items($wp_admin_bar) {
	if (!gm8_cleanup_enabled()) {
		return;
	}
	$items = gm8_cleanup_const('GM8_CLEANUP_HIDE_ADMIN_BAR_ITEMS', []);
	if (!is_array($items) || empty($items)) {
		return;
	}
	foreach ($items as $id) {
		if (is_string($id) && $id !== '') {
			$wp_admin_bar->remove_node($id);
		}
	}
}
add_action('admin_bar_menu', 'gm8_cleanup_hide_admin_bar_items', 999);

/**
 * Silent GitHub update mechanism.
 *
 * Defaults:
 * - Uses repo 'mrichwalsky/gm8core' unless overridden.
 *
 * Overrides:
 * - GM8_CLEANUP_GITHUB_REPO = 'owner/repo'
 *
 * Release expectation:
 * - Attach an asset zip named 'gm8-core.zip' (recommended).
 *   GitHub “Source code” zipballs unpack to a non-stable folder name and are not reliable for WP upgrades.
 */
function gm8_cleanup_updates_enabled() {
	$repo = (string) gm8_cleanup_const('GM8_CLEANUP_GITHUB_REPO', 'mrichwalsky/gm8core');
	return gm8_cleanup_enabled() && $repo !== '';
}

function gm8_cleanup_plugin_file() {
	return plugin_basename(__FILE__); // gm8-core/gm8-core.php
}

function gm8_cleanup_github_latest_release() {
	$cache_key = 'gm8_cleanup_github_release';
	$cached = get_site_transient($cache_key);
	if (is_array($cached) && isset($cached['tag_name'])) {
		return $cached;
	}

	$repo = (string) gm8_cleanup_const('GM8_CLEANUP_GITHUB_REPO', 'mrichwalsky/gm8core');
	if ($repo === '') {
		return null;
	}

	// GitHub API wants plain "owner/repo" in the URL path; don't rawurlencode slashes.
	$repo = trim($repo);
	$url = 'https://api.github.com/repos/' . $repo . '/releases/latest';
	$res = wp_remote_get($url, [
		'timeout' => 15,
		'headers' => [
			'Accept' => 'application/vnd.github+json',
			'User-Agent' => 'gm8-core-wordpress',
		],
	]);

	if (is_wp_error($res)) {
		set_site_transient($cache_key, ['error' => $res->get_error_message()], 30 * MINUTE_IN_SECONDS);
		return null;
	}

	$code = (int) wp_remote_retrieve_response_code($res);
	$body = (string) wp_remote_retrieve_body($res);
	if ($code < 200 || $code >= 300 || $body === '') {
		set_site_transient($cache_key, ['error' => 'http_' . $code], 30 * MINUTE_IN_SECONDS);
		return null;
	}

	$data = json_decode($body, true);
	if (!is_array($data) || empty($data['tag_name'])) {
		set_site_transient($cache_key, ['error' => 'bad_json'], 30 * MINUTE_IN_SECONDS);
		return null;
	}

	set_site_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);
	return $data;
}

function gm8_cleanup_release_version($tag) {
	$tag = (string) $tag;
	return ltrim($tag, "vV \t\n\r\0\x0B");
}

function gm8_cleanup_find_release_zip_url($release) {
	if (!is_array($release)) {
		return '';
	}
	if (!empty($release['assets']) && is_array($release['assets'])) {
		foreach ($release['assets'] as $asset) {
			if (!is_array($asset) || empty($asset['name']) || empty($asset['browser_download_url'])) {
				continue;
			}
			$name = (string) $asset['name'];
			$url = (string) $asset['browser_download_url'];
			if (strtolower($name) === 'gm8-core.zip') {
				return $url;
			}
		}
		// Fall back to first .zip asset.
		foreach ($release['assets'] as $asset) {
			if (!is_array($asset) || empty($asset['name']) || empty($asset['browser_download_url'])) {
				continue;
			}
			$name = (string) $asset['name'];
			$url = (string) $asset['browser_download_url'];
			if (function_exists('str_ends_with') && str_ends_with(strtolower($name), '.zip')) {
				return $url;
			}
			if (!function_exists('str_ends_with') && substr(strtolower($name), -4) === '.zip') {
				return $url;
			}
		}
	}

	return '';
}

function gm8_cleanup_silent_update_event_name() {
	return 'gm8_cleanup_silent_update_check';
}

function gm8_cleanup_schedule_updates() {
	if (!gm8_cleanup_updates_enabled()) {
		return;
	}
	if (!wp_next_scheduled(gm8_cleanup_silent_update_event_name())) {
		wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'twicedaily', gm8_cleanup_silent_update_event_name());
	}
}
add_action('init', 'gm8_cleanup_schedule_updates');

function gm8_cleanup_do_silent_update() {
	if (!gm8_cleanup_updates_enabled()) {
		return;
	}

	if (!function_exists('get_plugin_data')) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if (!class_exists('Plugin_Upgrader')) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	$plugin_file = gm8_cleanup_plugin_file();
	$plugin_path = WP_PLUGIN_DIR . '/' . dirname($plugin_file) . '/' . basename($plugin_file);
	$plugin_data = get_plugin_data($plugin_path, false, false);
	$current = isset($plugin_data['Version']) ? (string) $plugin_data['Version'] : '0.0.0';

	$release = gm8_cleanup_github_latest_release();
	if (!$release) {
		return;
	}

	$latest = gm8_cleanup_release_version($release['tag_name']);
	if ($latest === '' || version_compare($latest, $current, '<=')) {
		return;
	}

	$package = gm8_cleanup_find_release_zip_url($release);
	if ($package === '') {
		return;
	}

	$skin = new Automatic_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader($skin);
	$result = $upgrader->upgrade($plugin_file, [
		'package' => $package,
		'clear_update_cache' => true,
	]);

	if (is_wp_error($result)) {
		error_log('[gm8-core] update failed: ' . $result->get_error_message());
	}
}
add_action(gm8_cleanup_silent_update_event_name(), 'gm8_cleanup_do_silent_update');

