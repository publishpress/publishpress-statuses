<?php
namespace PublishPress_Statuses\Mcp;

\defined('ABSPATH') || exit;

/** Native settings discovery and typed partial updates. */
class Settings
{
    public static function definitions()
    {
        $bool = ['type' => 'boolean'];
        $types = array_keys(\PublishPress_Statuses::instance()->get_supported_post_types());
        $map = Registry::object(array_fill_keys($types, $bool));
        $visibility = array_merge([''], array_keys(get_post_stati(['private' => true])));
        $fields = [
            'post_types' => ['label' => __('Use on these Post Types', 'publishpress-statuses'), 'schema' => $map],
            'moderation_statuses_default_by_sequence' => ['label' => __('Workflow Guidance', 'publishpress-statuses'), 'schema' => ['type' => 'string', 'enum' => ['0', '1', 'none']]],
            'hide_manual_status_selectors' => ['label' => __('Manual Status Selection: hide selectors', 'publishpress-statuses'), 'schema' => $bool],
            'status_dropdown_show_current_branch_only' => ['label' => __('Hide unrelated sub-statuses', 'publishpress-statuses'), 'schema' => $bool],
            'new_statuses_main_workflow' => ['label' => __('Add new statuses to Main Workflow', 'publishpress-statuses'), 'schema' => $bool],
            'default_privacy' => ['label' => __('Default Visibility Status', 'publishpress-statuses'), 'schema' => Registry::object(array_fill_keys($types, ['type' => 'string', 'enum' => $visibility]))],
            'force_default_privacy' => ['label' => __('Lock default visibility by post type', 'publishpress-statuses'), 'schema' => $map],
            'lock_publication' => ['label' => __('Lock publication visibility', 'publishpress-statuses'), 'schema' => $bool],
            'pending_status_regulation' => ['label' => __('Pending Review: available to specified roles only', 'publishpress-statuses'), 'schema' => $bool],
            'force_editor_detection' => ['label' => __('Gutenberg / Classic Editor', 'publishpress-statuses'), 'schema' => ['type' => 'string', 'enum' => ['', 'classic', 'gutenberg']]],
            'label_storage' => ['label' => __('Status Label Customization', 'publishpress-statuses'), 'schema' => ['type' => 'string', 'enum' => ['', 'user']]],
        ];
        if (Registry::pro()) {
            $fields['privacy_statuses_enabled'] = ['label' => __('Custom Visibility', 'publishpress-statuses'), 'schema' => $bool];
            if (!defined('PPS_CUSTOM_PRIVACY_EDIT_CAPS')) {
                $fields['custom_privacy_edit_caps'] = ['label' => __('Custom Visibility Editing', 'publishpress-statuses'), 'schema' => $bool];
            }
        }
        if (defined('PUBLISHPRESS_VERSION')) {
            $fields['planner_add_post_custom_statuses'] = ['label' => __('Planner Integration', 'publishpress-statuses'), 'schema' => $bool];
        }
        if (function_exists('presspermit') && defined('PRESSPERMIT_COLLAB_VERSION') && defined('PRESSPERMIT_STATUSES_VERSION')) {
            $fields['supplemental_cap_moderate_any'] = ['label' => __('Permissions Integration', 'publishpress-statuses'), 'schema' => $bool];
        }
        return $fields;
    }

    public static function schemas($registration = false)
    {
        $schemas = array_map(static function ($field) { return $field['schema']; }, self::definitions());
        if ($registration) {
            foreach ($schemas['default_privacy']['properties'] as &$child) {
                unset($child['enum']);
            }
            unset($child);
        }
        return $schemas;
    }

    public static function state()
    {
        return [get_option(\PublishPress_Statuses::SETTINGS_SLUG, new \stdClass()), get_option('presspermit_privacy_statuses_enabled', 1)];
    }

    private static function project($value, $schema)
    {
        if ($schema['type'] === 'object') {
            $result = [];
            foreach ($schema['properties'] ?? [] as $key => $child) {
                $result[$key] = self::project(((array) $value)[$key] ?? ($child['type'] === 'boolean' ? false : ''), $child);
            }
            return (object) $result;
        }
        return Registry::project($value, $schema);
    }

    public static function read($input = [])
    {
        $module = \PublishPress_Statuses::instance();
        $module->load_options(\PublishPress_Statuses::SETTINGS_SLUG);
        $fields = self::definitions();
        $values = [];
        foreach ($fields as $key => &$field) {
            $value = $key === 'privacy_statuses_enabled' ? get_option('presspermit_privacy_statuses_enabled', 1) : ($module->options->$key ?? '');
            $values[$key] = self::project($value, $field['schema']);
            $field['default'] = self::project($key === 'privacy_statuses_enabled' ? 1 : ($module->default_options[$key] ?? ''), $field['schema']);
        }
        unset($field);
        return ['settings' => (object) $values, 'fields' => (object) $fields, 'pro' => Registry::pro(), 'expected_hash' => Registry::hash(self::state())];
    }

    public static function save($input)
    {
        $patch = Registry::validate($input['settings'], Registry::object(self::schemas()));
        if (is_wp_error($patch) || is_wp_error($check = Registry::stale($input, self::state()))) {
            return is_wp_error($patch) ? $patch : $check;
        }
        $current = (array) get_option(\PublishPress_Statuses::SETTINGS_SLUG, new \stdClass());
        $changes = [];
        foreach ($patch as $key => $value) {
            if (is_array($value) && !$value) {
                continue;
            }
            $changes[$key] = \PublishPress_Statuses\StatusHandler::sanitizeSetting($key, $value);
            if (is_array($value)) {
                $changes[$key] = array_replace((array) (\PublishPress_Statuses::instance()->default_options[$key] ?? []), (array) ($current[$key] ?? []), $changes[$key]);
            }
            if ($key !== 'privacy_statuses_enabled') {
                $current[$key] = $changes[$key];
            }
        }
        if (!$changes) {
            return ($input['dry_run'] ?? false) ? ['dry_run' => true, 'changes' => (object) []] : self::read() + ['complete' => true];
        }
        if ($input['dry_run'] ?? false) {
            return ['dry_run' => true, 'changes' => (object) $changes];
        }
        if (!current_user_can('manage_options') || is_wp_error($check = Registry::stale($input, self::state()))) {
            return is_wp_error($check) ? $check : Registry::denied();
        }
        update_option(\PublishPress_Statuses::SETTINGS_SLUG, (object) $current);
        if (array_key_exists('privacy_statuses_enabled', $changes)) {
            if (!current_user_can('manage_options')) {
                return ['complete' => false, 'message' => __('Settings permissions changed. Some changes may have been saved.', 'publishpress-statuses')];
            }
            update_option('presspermit_privacy_statuses_enabled', $changes['privacy_statuses_enabled']);
        }
        $module = \PublishPress_Statuses::instance();
        $module->clearStatusCache();
        $module->load_options(\PublishPress_Statuses::SETTINGS_SLUG);
        $module->workflow_disabled = $module->options->moderation_statuses_default_by_sequence === 'none';
        $module->workflow_by_sequence = !$module->workflow_disabled && (bool) $module->options->moderation_statuses_default_by_sequence;
        $complete = true;
        foreach ($changes as $key => $value) {
            $stored = $key === 'privacy_statuses_enabled' ? get_option('presspermit_privacy_statuses_enabled') : ((array) get_option(\PublishPress_Statuses::SETTINGS_SLUG))[$key];
            $complete = ($stored == $value) && $complete;
        }
        return self::read() + ['complete' => $complete];
    }
}
