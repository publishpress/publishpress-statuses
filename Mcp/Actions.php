<?php
namespace PublishPress_Statuses\Mcp;

use PublishPress_Statuses\StatusHandler;

\defined('ABSPATH') || exit;

/** Status configuration operations reuse native term, role and workflow services. */
class Actions
{
    public static function manage()
    {
        return current_user_can('pp_manage_statuses') || current_user_can('manage_options');
    }

    public static function settingsPermission()
    {
        return current_user_can('manage_options');
    }

    public static function register()
    {
        $slug = ['type' => 'string', 'minLength' => 1, 'maxLength' => 20, 'pattern' => '^[a-z0-9][a-z0-9_-]*$'];
        $revision = ['expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']];
        $register = static function ($name, $label, $description, $schema, $method, $write = false, $destructive = false, $permission = 'manage') {
            Registry::add($name, __($label, 'publishpress-statuses'), __($description, 'publishpress-statuses'), $schema, is_array($method) ? $method : [self::class, $method], [self::class, $permission], $write, $destructive, self::outputSchema($name));
        };
        $register('list-features', 'Discover Statuses features and permissions', 'Read native feature areas, edition/dependency availability, related actions and required capabilities. Native-only maintenance and revision lifecycle boundaries are explicit.', Registry::object(), 'features');
        $register('get-plugin-settings', 'Read Statuses settings', 'Discover native Workflow Guidance, visibility defaults, Pending Review role regulation and editor settings. Exact choices and defaults are returned; post-type maps are keyed objects.', Registry::object(), [Settings::class, 'read'], false, false, 'settingsPermission');
        $register('update-plugin-settings', 'Update Statuses settings', 'Patch settings using discovery types. JSON booleans, string choices and keyed post-type objects are distinct. Omitted fields and object entries are preserved. Read expected_hash first; dry_run previews without writes.', Registry::object($revision + ['settings' => Registry::object(Settings::schemas(true))], ['settings', 'expected_hash']), [Settings::class, 'save'], true, false, 'settingsPermission');
        $register('list-statuses', 'Discover editorial statuses and workflow', 'Read status labels, colors, icons, button captions, post-type availability, hierarchy, disabled status and main/alternate/revision workflow order. Active Pro adds visibility and revision statuses.', Registry::object(), 'listing');
        $register('get-status', 'Read an editorial status', 'Read one native status, its editable field contract and revision. Use save-status for properties and set-status-roles for assignment permissions.', Registry::object(['name' => Registry::text(40)], ['name']), 'read');
        $register('save-status', 'Create or update an editorial status', 'Create a custom moderation status or patch native properties, labels, color, icon, post types or parent. Slugs cannot be renamed. post_type [] means all enabled post types. Pro visibility and revision creation requires the active native taxonomy. expected_hash is required for edits; dry_run previews.', Registry::object($revision + ['name' => $slug, 'taxonomy' => ['type' => 'string', 'enum' => self::taxonomies()], 'properties' => Registry::object(self::propertySchemas())], ['name', 'properties']), 'save', true);
        $register('set-workflow-order', 'Arrange main and alternate workflows', 'Supply the complete names array from list-statuses in the desired order, retaining every section marker. Moving statuses after the disabled marker disables them. Hierarchy uses save-status status_parent. Pro revision markers remain in native order.', Registry::object($revision + ['names' => Registry::listOf(Registry::text(40), 1000)], ['names', 'expected_hash']), 'order', true, true);
        $register('delete-status', 'Delete a custom editorial status', 'Delete a custom status and reassign all affected posts to an explicit destination using the native deletion service. Built-in statuses cannot be deleted. Every affected post requires read/edit and target-status permissions; at most 1,000 posts. Inspect complete after saving.', Registry::object($revision + ['name' => $slug, 'reassign_to' => Registry::text(40)], ['name', 'reassign_to', 'expected_hash']), 'deleteStatus', true, true);
        $register('get-status-roles', 'Read roles allowed to assign a status', 'Read editable role assignment flags and, with Pro, declared status-specific content capabilities. Never exposes arbitrary role capability dictionaries.', Registry::object(['name' => Registry::text(40)], ['name']), 'roles');
        $register('set-status-roles', 'Set roles allowed to assign a status', 'Patch native assignment flags by editable role slug using JSON booleans. Requires manage_options and native status-management authority. Only this status assignment capability is changed. Existing unrelated role capabilities are preserved.', Registry::object($revision + ['name' => Registry::text(40), 'roles' => Registry::object(array_fill_keys(array_keys(wp_roles()->roles), ['type' => 'boolean']))], ['name', 'roles', 'expected_hash']), 'saveRoles', true, true, 'settingsPermission');
        $register('get-post-workflow', 'Read a post editorial workflow', 'Read current and next editorial status and available status choices for an explicit editable post, without reading post content. Requires post read/edit permission; disabled post types and revision objects are excluded.', Registry::object(['id' => Registry::id()], ['id']), 'postRead', false, false, 'loggedIn');
        $bulkRow = Registry::object(['id' => Registry::id(), 'status' => Registry::text(40), 'expected_hash' => Registry::revision()], ['id', 'status', 'expected_hash']);
        $register('bulk-set-post-status', 'Change multiple post statuses', 'Validate up to 20 explicit posts and revisions before writing. Native workflow and object permissions apply to every row. dry_run previews; complete, applied_ids and failed_id describe nontransactional partial results.', Registry::object(['items' => Registry::listOf($bulkRow, 20) + ['minItems' => 1], 'dry_run' => ['type' => 'boolean']], ['items']), 'bulkPosts', true, true, 'loggedIn');
        $register('set-post-status', 'Change a post editorial status', 'Use native WordPress REST controller and Statuses workflow filters to change one post status. Requires object edit, status assignment, publication authority and an unlocked post. Does not edit content or revisions. Native filters may choose another status; inspect complete and read back.', Registry::object($revision + ['id' => Registry::id(), 'status' => Registry::text(40)], ['id', 'status', 'expected_hash']), 'postSave', true, true, 'loggedIn');
        if (Registry::pro()) {
            $register('set-status-capabilities', 'Set Pro status content permissions', 'Patch only the exact status-specific capabilities returned by get-status-roles, keyed by editable role then capability. Requires manage_options and native status-management authority. Grants require the connected account to hold the capability; administrator status cannot bypass a denial. Arbitrary or administrator-level capabilities are rejected.', Registry::object($revision + ['name' => Registry::text(40), 'roles' => Registry::object(array_fill_keys(array_keys(wp_roles()->roles), ['type' => 'object', 'additionalProperties' => ['type' => 'boolean']]))], ['name', 'roles', 'expected_hash']), 'saveCapabilities', true, true, 'settingsPermission');
            $register('set-capability-status', 'Configure Pro status permission inheritance', 'Set the native capability status to an empty string for Standard, the same status for Custom, or a declared compatible status for Same as. Requires manage_options and native status-management authority. Role capabilities are not automatically granted.', Registry::object($revision + ['name' => Registry::text(40), 'capability_status' => Registry::text(40)], ['name', 'capability_status', 'expected_hash']), 'capabilityStatus', true, true, 'settingsPermission');
        }
    }

    /** Output envelopes are explicit; native discovery dictionaries retain their own schemas. */
    private static function outputSchema($name)
    {
        $text = ['type' => 'string'];
        $bool = ['type' => 'boolean'];
        $object = ['type' => 'object', 'additionalProperties' => true];
        $list = ['type' => 'array', 'items' => $text];
        $objects = ['type' => 'array', 'items' => $object];
        $base = ['complete' => $bool, 'dry_run' => $bool, 'changes' => $object, 'message' => $text];
        $settings = ['settings' => $object, 'fields' => $object, 'pro' => $bool, 'expected_hash' => Registry::revision()];
        $status = ['status' => $object, 'fields' => $object, 'locked_fields' => $list, 'expected_hash' => Registry::revision()];
        $roles = ['name' => $text, 'roles' => ['type' => 'object', 'additionalProperties' => ['type' => ['boolean', 'object']]], 'capabilities' => $object, 'capability_status_choices' => $list, 'capability_names' => $list, 'capability_status' => $text, 'expected_hash' => Registry::revision()];
        $post = ['id' => Registry::id(), 'status' => $text, 'available' => $objects, 'next' => $text, 'expected_hash' => Registry::revision()];
        $contracts = [
            'list-features' => ['items' => $objects, 'pro' => $bool],
            'get-plugin-settings' => $settings,
            'update-plugin-settings' => $settings,
            'list-statuses' => ['items' => $objects, 'names' => $list, 'stored_order' => $list, 'taxonomies' => $list, 'pro' => $bool, 'expected_hash' => Registry::revision()],
            'get-status' => $status,
            'save-status' => $status,
            'set-workflow-order' => ['items' => $objects, 'names' => $list, 'stored_order' => $list, 'taxonomies' => $list, 'pro' => $bool, 'expected_hash' => Registry::revision()],
            'get-status-roles' => $roles,
            'set-status-roles' => $roles,
            'set-status-capabilities' => $roles,
            'set-capability-status' => $roles,
            'get-post-workflow' => $post,
            'set-post-status' => $post,
            'bulk-set-post-status' => ['items' => $objects, 'applied_ids' => ['type' => 'array', 'items' => Registry::id()], 'failed_id' => Registry::id()],
            'delete-status' => ['deleted' => $bool, 'affected_posts' => ['type' => 'integer', 'minimum' => 0], 'reassign_to' => $text],
        ];
        return Registry::object($base + $contracts[$name]);
    }

    public static function features($input)
    {
        $manage = ['pp_manage_statuses', 'manage_options'];
        $pro = Registry::pro();
        $items = [
            ['id' => 'settings', 'label' => __('Statuses settings', 'publishpress-statuses'), 'available' => true, 'required_any' => ['manage_options'], 'actions' => ['get-plugin-settings', 'update-plugin-settings']],
            ['id' => 'statuses', 'label' => __('Editorial status properties and labels', 'publishpress-statuses'), 'available' => true, 'required_any' => $manage, 'actions' => ['list-statuses', 'get-status', 'save-status', 'delete-status']],
            ['id' => 'workflow', 'label' => __('Main, alternate and disabled workflows', 'publishpress-statuses'), 'available' => true, 'required_any' => $manage, 'actions' => ['list-statuses', 'set-workflow-order', 'save-status']],
            ['id' => 'roles', 'label' => __('Roles allowed to assign statuses', 'publishpress-statuses'), 'available' => true, 'read_required_any' => $manage, 'write_required_all' => ['manage_options'], 'actions' => ['get-status-roles', 'set-status-roles']],
            ['id' => 'post_workflow', 'label' => __('Post editorial workflow', 'publishpress-statuses'), 'available' => true, 'object_permissions' => ['read_post', 'edit_post', 'native target-status capabilities', 'publication capability when applicable', 'post lock'], 'actions' => ['get-post-workflow', 'set-post-status', 'bulk-set-post-status']],
            ['id' => 'visibility', 'label' => __('Custom visibility statuses', 'publishpress-statuses'), 'available' => $pro && taxonomy_exists(\PublishPress_Statuses::TAXONOMY_PRIVACY), 'edition' => 'pro', 'enabled' => (bool) get_option('presspermit_privacy_statuses_enabled', 1), 'required_any' => $manage, 'actions' => ['get-plugin-settings', 'update-plugin-settings', 'save-status']],
            ['id' => 'content_permissions', 'label' => __('Status-specific content permissions', 'publishpress-statuses'), 'available' => $pro && method_exists('PublishPress\\StatusCapabilities', 'getStatusCapabilityNames'), 'edition' => 'pro', 'read_required_any' => $manage, 'write_required_all' => ['manage_options'], 'new_grants_require_held_capability' => true, 'actions' => ['get-status-roles', 'set-status-capabilities', 'set-capability-status']],
            ['id' => 'revision_definitions', 'label' => __('Revision status definitions', 'publishpress-statuses'), 'available' => $pro && taxonomy_exists('pp_revision_status'), 'edition' => 'pro', 'dependency' => 'compatible PublishPress Revisions', 'required_any' => $manage, 'actions' => ['list-statuses', 'get-status', 'save-status']],
            ['id' => 'maintenance', 'label' => __('Import, backup, restore and reset', 'publishpress-statuses'), 'available' => false, 'native_only' => true, 'actions' => []],
            ['id' => 'per_post_visibility_conditions', 'label' => __('Inherited and descendant visibility conditions', 'publishpress-statuses'), 'available' => false, 'edition' => 'pro', 'native_only' => true, 'actions' => []],
            ['id' => 'revision_lifecycle', 'label' => __('Revision approval and scheduling', 'publishpress-statuses'), 'available' => false, 'native_only' => true, 'owner' => 'PublishPress Revisions', 'actions' => []],
            ['id' => 'notification_workflows', 'label' => __('Notification workflows', 'publishpress-statuses'), 'available' => false, 'native_only' => true, 'owner' => 'PublishPress Planner', 'actions' => []],
        ];
        return ['items' => $items, 'pro' => $pro];
    }

    public static function loggedIn()
    {
        return is_user_logged_in();
    }

    public static function taxonomies()
    {
        $types = [\PublishPress_Statuses::TAXONOMY_PRE_PUBLISH];
        if (Registry::pro()) {
            $types = apply_filters('publishpress_statuses_taxonomies', array_merge($types, [\PublishPress_Statuses::TAXONOMY_PRIVACY]));
        }
        return array_values(array_filter(array_unique($types), 'taxonomy_exists'));
    }

    private static function statuses()
    {
        \PublishPress_Statuses::instance()->clearStatusCache();
        return \PublishPress_Statuses::getPostStati([], ['output' => 'object'], ['show_disabled' => true, 'context' => 'load']);
    }

    private static function status($name)
    {
        $statuses = self::statuses();
        return $statuses[$name] ?? null;
    }

    private static function state()
    {
        $taxonomies = array_unique(array_merge(self::taxonomies(), [\PublishPress_Statuses::TAXONOMY_CORE_STATUS, \PublishPress_Statuses::TAXONOMY_PSEUDO_STATUS]));
        $terms = get_terms(['taxonomy' => $taxonomies, 'hide_empty' => false]);
        $meta = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $meta[$term->term_id] = get_term_meta($term->term_id);
            }
        }
        return [$terms, $meta, get_option('publishpress_status_positions'), Settings::state(), wp_roles()->roles, get_option('presspermit_status_capability_status')];
    }

    private static function summary($status)
    {
        $result = [];
        foreach (['name', 'label', 'description', 'color', 'icon', 'status_parent', 'taxonomy'] as $key) {
            $result[$key] = (string) ($status->$key ?? '');
        }
        foreach (['private', 'public', 'moderation', 'disabled', 'for_revision', '_builtin', 'pp_builtin'] as $key) {
            $result[$key] = !empty($status->$key);
        }
        $result['post_type'] = array_values((array) ($status->post_type ?? []));
        $result['labels'] = (object) array_intersect_key((array) ($status->labels ?? []), ['save_as' => true, 'publish' => true]);
        $taxonomy = !empty($status->_builtin) ? \PublishPress_Statuses::TAXONOMY_CORE_STATUS : ($status->taxonomy ?? \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH);
        $term = get_term_by('slug', $status->name, $taxonomy);
        if ($term) {
            // An explicit empty override restores the native display default, but remains
            // an empty stored control value. Preserve that distinction in discovery.
            foreach (['color', 'icon', 'status_parent'] as $key) {
                if (metadata_exists('term', $term->term_id, $key)) {
                    $stored = get_term_meta($term->term_id, $key, true);
                    if (is_string($stored)) {
                        $result[$key] = $stored;
                    }
                }
            }
            if (metadata_exists('term', $term->term_id, 'labels')) {
                $stored = get_term_meta($term->term_id, 'labels', true);
                if (is_array($stored) || is_object($stored)) {
                    $result['labels'] = (object) array_intersect_key((array) $stored, ['save_as' => true, 'publish' => true]);
                }
            }
        }
        return $result;
    }

    public static function listing($input)
    {
        $statuses = self::statuses();
        return ['items' => array_values(array_map([self::class, 'summary'], $statuses)), 'names' => array_keys($statuses), 'stored_order' => array_values((array) get_option('publishpress_status_positions', [])), 'taxonomies' => self::taxonomies(), 'pro' => Registry::pro(), 'expected_hash' => Registry::hash(self::state())];
    }

    public static function propertySchemas()
    {
        return [
            'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 20],
            'description' => Registry::text(5000),
            'color' => ['type' => 'string', 'pattern' => '^$|^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$'],
            'icon' => ['type' => 'string', 'maxLength' => 100, 'pattern' => '^[a-z0-9_-]*$'],
            'post_type' => Registry::listOf(['type' => 'string', 'enum' => array_keys(\PublishPress_Statuses::instance()->get_supported_post_types())]),
            'status_parent' => Registry::text(40),
            'labels' => Registry::object(['save_as' => Registry::text(100), 'publish' => Registry::text(100)]),
        ];
    }

    public static function read($input)
    {
        $status = self::status($input['name']);
        if (!$status) {
            return Registry::error(__('Unknown status.', 'publishpress-statuses'), 404);
        }
        return ['status' => self::summary($status), 'fields' => (object) self::propertySchemas(), 'locked_fields' => StatusHandler::canCustomizeLabels($status) ? [] : ['label', 'labels'], 'expected_hash' => Registry::hash(self::state())];
    }

    public static function save($input)
    {
        $status = self::status($input['name']);
        $patch = $input['properties'];
        if ($status && is_wp_error($check = Registry::stale($input, self::state()))) {
            return $check;
        }
        if (isset($patch['labels']) && !$patch['labels']) {
            unset($patch['labels']);
        }
        if ($status && !$patch) {
            if (!self::metadataAllowed($status, [])) {
                return Registry::denied();
            }
            return ($input['dry_run'] ?? false) ? ['dry_run' => true, 'changes' => (object) []] : self::read($input) + ['complete' => true];
        }
        if ($status && isset($input['taxonomy']) && $input['taxonomy'] !== $status->taxonomy) {
            return Registry::error(__('An existing status cannot change taxonomy.', 'publishpress-statuses'));
        }
        if (!$status && (!isset($patch['label']) || get_post_status_object($input['name']) || is_numeric($input['name']))) {
            return Registry::error(__('Supply a label and an unused nonnumeric status slug.', 'publishpress-statuses'));
        }
        if ($status && in_array($input['name'], ['_disabled', '_pre-publish-alternate', '_revision-alternate', '_revision-status'], true)) {
            return Registry::error(__('Workflow section markers are not editable statuses.', 'publishpress-statuses'));
        }
        if ((isset($patch['label']) || isset($patch['labels'])) && $status && !StatusHandler::canCustomizeLabels($status)) {
            return Registry::error(__('This native status label is locked.', 'publishpress-statuses'), 403);
        }
        if (isset($patch['label']) && $status && is_numeric($patch['label'])) {
            return Registry::error(__('Use a nonnumeric native status label.', 'publishpress-statuses'));
        }
        if (isset($patch['labels']) && $status) {
            $taxonomy = !empty($status->_builtin) ? \PublishPress_Statuses::TAXONOMY_CORE_STATUS : ($status->taxonomy ?: \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH);
            $term = get_term_by('slug', $status->name, $taxonomy);
            $labels = $term && metadata_exists('term', $term->term_id, 'labels') ? get_term_meta($term->term_id, 'labels', true) : ($status->labels ?? []);
            $patch['labels'] = (object) array_replace((array) $labels, array_map('sanitize_text_field', $patch['labels']));
            $patch['label'] = $patch['label'] ?? $status->label;
        }
        if (isset($patch['status_parent']) && $patch['status_parent'] !== '') {
            $parent = self::status($patch['status_parent']);
            if (!$parent || strpos($parent->name, '_') === 0 || !empty($parent->private) || !empty($parent->status_parent) || $parent->name === $input['name'] || ($status && !empty($status->private)) || (($input['taxonomy'] ?? '') === \PublishPress_Statuses::TAXONOMY_PRIVACY) || (!empty($parent->for_revision) !== ($status ? !empty($status->for_revision) : ($input['taxonomy'] ?? '') === 'pp_revision_status')) || !empty(\PublishPress_Statuses::getStatusChildren($input['name']))) {
                return Registry::error(__('Choose a top-level moderation parent without creating a cycle or third nesting level.', 'publishpress-statuses'));
            }
        }
        foreach (['label', 'description', 'icon'] as $key) {
            if (isset($patch[$key])) {
                $patch[$key] = sanitize_text_field($patch[$key]);
            }
        }
        if (isset($patch['label']) && $patch['label'] === '') {
            return Registry::error();
        }
        if ($status && !self::metadataAllowed($status, $patch)) {
            return Registry::denied();
        }
        if ($input['dry_run'] ?? false) {
            $preview = $patch;
            if (isset($preview['labels'])) {
                $preview['labels'] = (object) array_intersect_key((array) $preview['labels'], ['save_as' => true, 'publish' => true]);
            }
            return ['dry_run' => true, 'changes' => (object) $preview];
        }
        if (!self::manage()) {
            return Registry::denied();
        }
        if ($status) {
            $result = StatusHandler::updateCustomStatus($input['name'], $patch, [self::class, 'authorizeTerm']);
        } else {
            $taxonomy = $input['taxonomy'] ?? \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH;
            $result = \PublishPress_Statuses::instance()->addStatus($taxonomy, $patch['label'], ['slug' => $input['name']] + $patch, [self::class, 'authorizeTerm']);
        }
        if (is_wp_error($result) || !$result) {
            if (is_wp_error($result) && in_array($result->get_error_code(), ['status-create-denied', 'status-edit-denied'], true)) {
                return Registry::error(__('Native status authorization changed. Some changes may have been saved; read the status before retrying.', 'publishpress-statuses'), 403);
            }
            // Native WP_Error data may contain database or third-party diagnostics.
            return Registry::unconfirmed();
        }
        if (!$status) {
            StatusHandler::initializeCreatedStatus($input['name'], $input['taxonomy'] ?? \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH);
        }
        \PublishPress_Statuses::instance()->refreshStatusRegistrations();
        $read = self::read(['name' => $input['name']]);
        if (is_wp_error($read)) {
            return Registry::unconfirmed();
        }
        $complete = true;
        foreach ($patch as $key => $value) {
            if ($key === 'labels') {
                $value = (object) array_intersect_key((array) $value, ['save_as' => true, 'publish' => true]);
            }
            $complete = (($read['status'][$key] ?? null) == $value) && $complete;
        }
        return $read + ['complete' => $complete];
    }

    public static function authorizeTerm($termId, $args)
    {
        if (!self::manage() || !current_user_can('edit_term', $termId)) {
            return false;
        }
        foreach (array_intersect(array_keys($args), ['labels', 'post_type', 'status_parent', 'color', 'icon']) as $key) {
            if (!current_user_can('edit_term_meta', $termId, $key)) {
                return false;
            }
        }
        return true;
    }

    private static function metadataAllowed($status, $patch)
    {
        $taxonomy = !empty($status->_builtin) ? \PublishPress_Statuses::TAXONOMY_CORE_STATUS : ($status->taxonomy ?: \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH);
        $term = get_term_by('slug', $status->name, $taxonomy);
        return !$term || self::authorizeTerm($term->term_id, $patch);
    }

    public static function order($input)
    {
        $names = array_keys(self::statuses());
        $markers = static function ($name) { return strpos($name, '_') === 0; };
        if (array_values(array_filter($names, $markers)) !== array_values(array_filter($input['names'], $markers))) {
            return Registry::error(__('Retain the native section-marker order.', 'publishpress-statuses'));
        }
        if (array_diff($names, $input['names']) || array_diff($input['names'], $names) || is_wp_error($check = Registry::stale($input, self::state()))) {
            return isset($check) && is_wp_error($check) ? $check : Registry::error(__('Retain every current status and section marker exactly once.', 'publishpress-statuses'));
        }
        if ($input['dry_run'] ?? false) {
            return ['dry_run' => true, 'names' => $input['names']];
        }
        StatusHandler::saveStatusPositions($input['names']);
        return self::listing([]) + ['complete' => get_option('publishpress_status_positions') === $input['names']];
    }

    private static function roleState($name)
    {
        return [self::state(), $name];
    }

    public static function roles($input)
    {
        if (!$status = self::status($input['name'])) {
            return Registry::error();
        }
        $cap = str_replace('-', '_', 'status_change_' . $status->name);
        $roles = [];
        foreach (wp_roles()->roles as $role => $details) {
            if (\PP_Statuses_Functions::isEditableRole($role, ['force_refresh' => true])) {
                $roles[$role] = !empty($details['capabilities'][$cap]);
            }
        }
        $modes = (array) get_option('presspermit_status_capability_status', []);
        $caps = Registry::pro() && method_exists('PublishPress\\StatusCapabilities', 'getStatusCapabilityNames') ? \PublishPress\StatusCapabilities::getStatusCapabilityNames($status->name) : [];
        $capabilities = [];
        foreach (array_keys($roles) as $role) {
            foreach ($caps as $cap) {
                $capabilities[$role][$cap] = get_role($role)->has_cap($cap);
            }
        }
        return ['name' => $status->name, 'roles' => (object) $roles, 'capability_status_choices' => Registry::pro() && method_exists('PublishPress\\StatusCapabilities', 'getCapabilityStatusChoices') ? \PublishPress\StatusCapabilities::getCapabilityStatusChoices($status->name) : [], 'capability_names' => $caps, 'capabilities' => (object) array_map(static function ($values) { return (object) $values; }, $capabilities), 'capability_status' => Registry::pro() ? ($status->capability_status ?? ($modes[$status->name] ?? '')) : '', 'expected_hash' => Registry::hash(self::roleState($status->name))];
    }

    public static function saveRoles($input)
    {
        if (!self::manage() || !self::status($input['name']) || is_wp_error($check = Registry::stale($input, self::roleState($input['name'])))) {
            return isset($check) && is_wp_error($check) ? $check : Registry::denied();
        }
        foreach ($input['roles'] as $role => $enabled) {
            if (!\PP_Statuses_Functions::isEditableRole($role, ['force_refresh' => true])) {
                return Registry::denied();
            }
        }
        if ($input['dry_run'] ?? false) {
            return ['dry_run' => true, 'roles' => (object) $input['roles']];
        }
        foreach ($input['roles'] as $role => $enabled) {
            if (!current_user_can('manage_options') || !self::manage() || !StatusHandler::setRoleAssignment($input['name'], $role, $enabled)) {
                return ['complete' => false, 'name' => $input['name']];
            }
        }
        $read = self::roles($input);
        $complete = true;
        foreach ($input['roles'] as $role => $enabled) {
            $complete = (($read['roles']->$role ?? null) === $enabled) && $complete;
        }
        return $read + ['complete' => $complete];
    }

    public static function saveCapabilities($input)
    {
        if (!Registry::pro() || !self::manage() || !method_exists('PublishPress\\StatusCapabilities', 'getStatusCapabilityNames') || !self::status($input['name'])) {
            return Registry::denied();
        }
        if (is_wp_error($check = Registry::stale($input, self::roleState($input['name'])))) {
            return $check;
        }
        $allowed = \PublishPress\StatusCapabilities::getStatusCapabilityNames($input['name']);
        $schemas = Registry::object(array_fill_keys(array_keys(wp_roles()->roles), Registry::object(array_fill_keys($allowed, ['type' => 'boolean']))));
        $patch = Registry::validate($input['roles'], $schemas);
        if (is_wp_error($patch)) {
            return $patch;
        }
        foreach ($patch as $role => $caps) {
            if (!\PP_Statuses_Functions::isEditableRole($role, ['force_refresh' => true])) {
                return Registry::denied();
            }
            foreach ($caps as $cap => $enabled) {
                if (preg_match('/^(manage_|edit_users$|promote_users$|create_users$|delete_users$|remove_users$|install_|activate_|update_|switch_themes$|edit_plugins$|edit_themes$|edit_files$|edit_theme_options$|delete_plugins$|delete_themes$|resume_plugins$|resume_themes$|unfiltered_)/', $cap) || ($enabled && !current_user_can($cap))) {
                    return Registry::denied();
                }
            }
        }
        if ($input['dry_run'] ?? false) {
            return ['dry_run' => true, 'roles' => (object) array_map(static function ($caps) { return (object) $caps; }, $patch)];
        }
        require_once PUBLISHPRESS_STATUSES_PRO_ABSPATH . '/modules/status-capabilities/StatusSavePro.php';
        foreach ($patch as $role => $caps) {
            if (!current_user_can('manage_options') || !self::manage() || !\PublishPress\StatusCapabilities\StatusSavePro::applyRoleCapabilities($role, $caps, static function ($cap, $enabled) { return current_user_can('manage_options') && self::manage() && (!$enabled || current_user_can($cap)); })) {
                return ['complete' => false, 'name' => $input['name']];
            }
        }
        $read = self::roles($input);
        $complete = true;
        foreach ($patch as $role => $caps) {
            foreach ($caps as $cap => $enabled) {
                $complete = (get_role($role)->has_cap($cap) === $enabled) && $complete;
            }
        }
        return $read + ['complete' => $complete];
    }

    public static function capabilityStatus($input)
    {
        if (!Registry::pro() || !self::manage() || !$status = self::status($input['name'])) {
            return Registry::denied();
        }
        if (is_wp_error($check = Registry::stale($input, self::roleState($input['name'])))) {
            return $check;
        }
        if (!method_exists('PublishPress\\StatusCapabilities', 'getCapabilityStatusChoices') || !in_array($input['capability_status'], \PublishPress\StatusCapabilities::getCapabilityStatusChoices($status->name), true)) {
            return Registry::error(__('Select a native permission-inheritance choice from discovery.', 'publishpress-statuses'));
        }
        if ($input['dry_run'] ?? false) {
            return ['dry_run' => true, 'capability_status' => $input['capability_status']];
        }
        \PublishPress\StatusCapabilities::updateCapabilityStatus($status->name, $input['capability_status']);
        $read = self::roles($input);
        return $read + ['complete' => $read['capability_status'] === $input['capability_status']];
    }

    private static function post($id)
    {
        $post = get_post($id);
        return $post && !(function_exists('rvy_in_revision_workflow') && rvy_in_revision_workflow($post)) && !wp_is_post_revision($id) && !get_post_meta($id, '_rvy_base_post_id', true) && !\PublishPress_Statuses::isPostBlacklisted($id) && !\PublishPress_Statuses::DisabledForPostType($post->post_type) && Registry::editable($id) ? $post : null;
    }

    private static function canAssign($post, $name)
    {
        $status = self::status($name);
        if (!$status || !empty($status->disabled) || !empty($status->for_revision) || !empty($status->internal) || in_array($name, ['trash', 'auto-draft', 'inherit', 'new', 'future'], true) || (!empty($status->post_type) && !in_array($post->post_type, $status->post_type, true))) {
            return false;
        }
        if (!empty($status->private) && $name !== 'private' && !get_option('presspermit_privacy_statuses_enabled', 1)) {
            return false;
        }
        $type = get_post_type_object($post->post_type);
        $caps = ['draft' === $name ? $type->cap->edit_posts : str_replace('-', '_', 'status_change_' . $name)];
        if (!empty($status->public) || !empty($status->private)) {
            $caps = [$type->cap->publish_posts];
        } elseif ($name === 'pending' && empty(\PublishPress_Statuses::instance()->options->pending_status_regulation)) {
            $caps = [$type->cap->edit_posts];
        }
        if ($name !== 'draft' && empty($status->public) && empty($status->private) && ($name !== 'pending' || !empty(\PublishPress_Statuses::instance()->options->pending_status_regulation))) {
            $caps = apply_filters('publishpress_statuses_required_caps', $caps, 'set_status', $name, $post->post_type);
        }
        if (!empty($status->private) && $name !== 'private') {
            $assignment = apply_filters('publishpress_statuses_required_caps', [str_replace('-', '_', 'status_change_' . $name)], 'set_status', $name, $post->post_type);
            $caps = array_unique(array_merge($caps, $assignment));
        }
        foreach ($caps as $cap) {
            if (!current_user_can($cap, $post->ID)) {
                return false;
            }
        }
        return true;
    }

    public static function postRead($input)
    {
        if (!$post = self::post($input['id'])) {
            return Registry::denied();
        }
        $available = [];
        foreach (self::statuses() as $name => $status) {
            if (self::canAssign($post, $name)) {
                $available[] = ['name' => $name, 'label' => $status->label];
            }
        }
        $next = \PublishPress_Statuses::getNextStatusObject($post->ID);
        return ['id' => $post->ID, 'status' => $post->post_status, 'available' => $available, 'next' => $next && self::canAssign($post, $next->name) ? $next->name : '', 'expected_hash' => Registry::hash([(array) $post, get_post_meta($post->ID), self::state()])];
    }

    public static function postSave($input)
    {
        if (!$post = self::post($input['id'])) {
            return Registry::denied();
        }
        if (is_wp_error($check = Registry::stale($input, [(array) $post, get_post_meta($post->ID), self::state()])) || !self::canAssign($post, $input['status'])) {
            return is_wp_error($check) ? $check : Registry::denied();
        }
        $type = get_post_type_object($post->post_type);
        if (!$type || !$type->show_in_rest) {
            return Registry::error(__('Use native editing for post types without REST support.', 'publishpress-statuses'));
        }
        $controller = $type->get_rest_controller();
        if (!$controller instanceof \WP_REST_Posts_Controller) {
            return Registry::error(__('This post type uses a custom controller; use its native editor.', 'publishpress-statuses'));
        }
        $request = new \WP_REST_Request('POST', '/' . $type->rest_namespace . '/' . ($type->rest_base ?: $type->name) . '/' . $post->ID);
        $request->set_param('id', $post->ID);
        $request->set_param('status', $input['status']);
        $request->set_param('context', 'edit');
        $permission = $controller->update_item_permissions_check($request);
        if (is_wp_error($permission) || !$permission) {
            return is_wp_error($permission) ? $permission : Registry::denied();
        }
        if ($input['dry_run'] ?? false) {
            return ['dry_run' => true, 'id' => $post->ID, 'status' => $input['status']];
        }
        if (!self::post($post->ID) || !self::canAssign($post, $input['status']) || is_wp_error($check = Registry::stale($input, [(array) get_post($post->ID), get_post_meta($post->ID), self::state()]))) {
            return is_wp_error($check ?? null) ? $check : Registry::denied();
        }
        // Dispatch through native REST filters as well as the controller, preserving
        // Statuses workflow/visibility decisions and other plugins' object restrictions.
        require_once dirname(__DIR__) . '/REST.php';
        $wasDoingRest = \PublishPress_Statuses::instance()->doing_rest;
        $rest = \PublishPress_Statuses\REST::instance();
        $restState = get_object_vars($rest);
        try {
            $result = rest_do_request($request);
        } finally {
            foreach ($restState as $key => $value) {
                $rest->$key = $value;
            }
            \PublishPress_Statuses::instance()->doing_rest = $wasDoingRest;
        }
        if (is_wp_error($result) || $result->get_status() >= 400) {
            return Registry::unconfirmed();
        }
        $read = self::postRead($input);
        return is_wp_error($read) ? ['complete' => false, 'id' => $post->ID] : $read + ['complete' => get_post_status($post->ID) === $input['status']];
    }

    public static function bulkPosts($input)
    {
        $seen = [];
        foreach ($input['items'] as $row) {
            if (isset($seen[$row['id']])) {
                return Registry::error(__('A post may occur only once in a batch.', 'publishpress-statuses'));
            }
            $seen[$row['id']] = true;
            $preview = self::postSave($row + ['dry_run' => true]);
            if (is_wp_error($preview)) {
                return $preview;
            }
        }
        if ($input['dry_run'] ?? false) {
            return ['dry_run' => true, 'items' => $input['items']];
        }
        $applied = [];
        foreach ($input['items'] as $row) {
            $result = self::postSave($row);
            if (is_wp_error($result) || empty($result['complete'])) {
                return ['complete' => false, 'applied_ids' => $applied, 'failed_id' => $row['id']];
            }
            $applied[] = $row['id'];
        }
        return ['complete' => true, 'applied_ids' => $applied];
    }

    public static function deleteStatus($input)
    {
        global $wpdb;
        $status = self::status($input['name']);
        $target = self::status($input['reassign_to']);
        if (!$status || !$target || $status->name === $target->name || !empty($status->_builtin) || !empty($status->pp_builtin)) {
            return Registry::error(__('Only user-created statuses can be deleted through MCP.', 'publishpress-statuses'));
        }
        if (is_wp_error($check = Registry::stale($input, self::state()))) {
            return $check;
        }
        if (!empty($status->private) && Registry::pro()) {
            if (!method_exists('PublishPress\\StatusCapabilities', 'statusHasVisibilityConditions') || \PublishPress\StatusCapabilities::statusHasVisibilityConditions($status->name) !== false) {
                return Registry::error(__('Remove native visibility conditions before deleting this status.', 'publishpress-statuses'), 403);
            }
        }
        $taxonomy = $status->taxonomy ?: \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH;
        $term = get_term_by('slug', $status->name, $taxonomy);
        if (!$term || !current_user_can('delete_term', $term->term_id)) {
            return Registry::denied();
        }
        // Native deletion reassigns every matching row, including revisions and private objects.
        // Preflight its exact SQL scope without capability-filtered WP_Query results.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE (post_status = %s OR (%s <> '' AND post_mime_type = %s)) ORDER BY ID LIMIT 1001", $status->name, !empty($status->for_revision) ? $status->name : '', !empty($status->for_revision) ? $status->name : ''));
        if (!empty($status->for_revision) && $ids) {
            return Registry::error(__('Revision records still use this definition. Use the native revision workflow.', 'publishpress-statuses'), 403);
        }
        if ($wpdb->last_error || count($ids) > 1000) {
            return Registry::error(__('Use native administration for this deletion.', 'publishpress-statuses'), 413);
        }
        foreach ($ids as $id) {
            $post = self::post((int) $id);
            if (!$post || !self::canAssign($post, $target->name)) {
                return Registry::denied();
            }
            // SQL reassignment does not run native post-save visibility filters.
            // Keep locked objects in native administration instead of bypassing those filters.
            $options = \PublishPress_Statuses::instance()->options;
            $locked = !empty($options->force_default_privacy[$post->post_type]);
            if (Registry::pro()) {
                $locked = $locked || !method_exists('PublishPress\\StatusCapabilities', 'postHasVisibilityConditions')
                    || \PublishPress\StatusCapabilities::postHasVisibilityConditions($post) !== false;
            }
            if ($locked) {
                return Registry::error(__('Use native administration to reassign posts with visibility locks.', 'publishpress-statuses'), 403);
            }
        }
        if ($input['dry_run'] ?? false) {
            return ['dry_run' => true, 'affected_posts' => count($ids), 'reassign_to' => $target->name];
        }
        $result = StatusHandler::deleteCustomStatus($status->name, [], $target->name, array_map('intval', $ids));
        if (is_wp_error($result)) {
            return Registry::unconfirmed();
        }
        $complete = (bool) $result;
        foreach ($ids as $id) {
            $complete = (get_post_status($id) === $target->name) && $complete;
        }
        return ['complete' => $complete, 'deleted' => !get_term_by('slug', $status->name, $taxonomy), 'affected_posts' => count($ids)];
    }
}
