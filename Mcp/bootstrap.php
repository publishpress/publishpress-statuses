<?php
/** Optional private Statuses abilities, shared by Free and Pro. */
defined('ABSPATH') || exit;
if (!function_exists('wp_register_ability') || defined('PUBLISHPRESS_STATUSES_MCP_LOADED')) {
    return;
}
define('PUBLISHPRESS_STATUSES_MCP_LOADED', true);
require_once dirname(__DIR__) . '/StatusHandler.php';
require_once __DIR__ . '/Registry.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Actions.php';
add_action('wp_abilities_api_categories_init', static function () {
    if (!wp_has_ability_category('publishpress')) {
        wp_register_ability_category('publishpress', ['label' => __('PublishPress', 'publishpress-statuses'), 'description' => __('PublishPress plugin actions.', 'publishpress-statuses')]);
    }
});
add_action('wp_abilities_api_init', ['PublishPress_Statuses\\Mcp\\Actions', 'register']);
