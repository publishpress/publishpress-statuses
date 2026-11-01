<?php
/** Released Adapter checks. Run only against disposable statuses_mcp_* databases. */
define('REST_REQUEST', true);
require $argv[1];
if (strpos(DB_NAME, 'statuses_mcp_') !== 0) {
    throw new RuntimeException('An isolated database is required.');
}
require $argv[2] . '/vendor/autoload.php';
wp_set_current_user(1);
$assertions = 0;
function check($condition, $message) {
    global $assertions;
    ++$assertions;
    if (!$condition) { throw new RuntimeException($message); }
}
function callStatuses($name, $input = []) {
    $ability = wp_get_ability('publishpress-statuses/' . $name);
    check($ability !== null, 'Missing ability ' . $name);
    $tool = \WP\MCP\Domain\Tools\McpTool::fromAbility($ability);
    check(!is_wp_error($tool), 'Invalid tool ' . $name);
    $result = $tool->execute($input);
    if (!is_wp_error($result)) {
        $outputCheck = rest_validate_value_from_schema($result, $ability->get_output_schema());
        check(!is_wp_error($outputCheck), 'Output contract mismatch for ' . $name . (is_wp_error($outputCheck) ? ': ' . $outputCheck->get_error_message() : ''));
    }
    return $result;
}
function success($result, $message) {
    check(!is_wp_error($result), $message . (is_wp_error($result) ? ': ' . $result->get_error_message() : ''));
    return $result;
}
$featureInventory = success(callStatuses('list-features'), 'Native feature inventory');
check(count($featureInventory['items']) >= 10, 'Feature inventory is incomplete');
$settings = success(callStatuses('get-plugin-settings'), 'Settings discovery');
check($settings['pro'] === (getenv('STATUSES_MCP_MODE') === 'pro'), 'Edition detection mismatch');
// Sparse native options must retain implicit defaults when a map patch touches one key.
$savedSparseOptions = get_option('publishpress_custom_status_options', false);
update_option('publishpress_custom_status_options', (object) ['unrelated_sparse' => 'keep']);
$sparse = success(callStatuses('get-plugin-settings'), 'Sparse native defaults');
$emptyMapBefore = get_option('publishpress_custom_status_options');
$sparse = success(callStatuses('update-plugin-settings', ['expected_hash' => $sparse['expected_hash'], 'settings' => (object) ['post_types' => (object) []]]), 'Empty native map patch');
check(get_option('publishpress_custom_status_options') == $emptyMapBefore, 'Empty map patch materialized or changed native defaults');
$sparse = success(callStatuses('update-plugin-settings', ['expected_hash' => $sparse['expected_hash'], 'settings' => (object) ['post_types' => (object) ['post' => false]]]), 'Sparse map patch');
check($sparse['settings']->post_types->post === false && $sparse['settings']->post_types->page === true, 'Sparse map patch discarded the omitted native Pages default');
check(get_option('publishpress_custom_status_options')->unrelated_sparse === 'keep', 'Sparse map patch discarded unrelated data');
if ($savedSparseOptions === false) { delete_option('publishpress_custom_status_options'); } else { update_option('publishpress_custom_status_options', $savedSparseOptions); }
$settings = success(callStatuses('get-plugin-settings'), 'Restore sparse settings');
$raw = (object) get_option('publishpress_custom_status_options', new stdClass());
$raw->unrelated_synthetic_setting = 'preserve';
update_option('publishpress_custom_status_options', $raw);
$settings = success(callStatuses('get-plugin-settings'), 'Settings refresh');
foreach ((array) $settings['fields'] as $name => $field) {
    $value = $settings['settings']->$name;
    check(!is_wp_error(\PublishPress_Statuses\Mcp\Registry::validate($value, $field['schema'])), 'Discovery format mismatch ' . $name);
    $before = get_option('publishpress_custom_status_options');
    success(callStatuses('update-plugin-settings', ['settings' => (object) [$name => $value], 'expected_hash' => $settings['expected_hash'], 'dry_run' => true]), 'Setting preview ' . $name);
    check(get_option('publishpress_custom_status_options') == $before, 'Preview saved ' . $name);
    $settings = success(callStatuses('update-plugin-settings', ['settings' => (object) [$name => $value], 'expected_hash' => $settings['expected_hash']]), 'Setting save ' . $name);
    check($settings['complete'], 'Incomplete settings read-back ' . $name);
    check(get_option('publishpress_custom_status_options')->unrelated_synthetic_setting === 'preserve', 'Setting patch lost unrelated data');
    if ($field['schema']['type'] === 'boolean') {
        foreach ([true, false] as $toggle) {
            $settings = success(callStatuses('update-plugin-settings', ['settings' => (object) [$name => $toggle], 'expected_hash' => $settings['expected_hash']]), 'Native switcher toggle ' . $name);
            check($settings['complete'] && $settings['settings']->$name === $toggle, 'Boolean round-trip mismatch ' . $name);
        }
        $settings = success(callStatuses('update-plugin-settings', ['settings' => (object) [$name => $value], 'expected_hash' => $settings['expected_hash']]), 'Restore native toggle ' . $name);
    }

    check(is_wp_error(callStatuses('update-plugin-settings', ['settings' => (object) [$name => null], 'expected_hash' => $settings['expected_hash']])), 'Null setting accepted ' . $name);
}
$before = get_option('publishpress_custom_status_options');
check(is_wp_error(callStatuses('update-plugin-settings', ['settings' => (object) ['hide_manual_status_selectors' => 'false', 'new_statuses_main_workflow' => false], 'expected_hash' => $settings['expected_hash']])), 'String false accepted');
check(get_option('publishpress_custom_status_options') == $before, 'Invalid mixed settings patch wrote data');
check(is_wp_error(callStatuses('update-plugin-settings', ['settings' => (object) ['post_types' => ['post']], 'expected_hash' => $settings['expected_hash']])), 'List accepted for keyed post-type map');
check(is_wp_error(callStatuses('update-plugin-settings', ['settings' => (object) ['force_editor_detection' => 'invalid'], 'expected_hash' => $settings['expected_hash']])), 'Unknown native choice accepted');
check(is_wp_error(callStatuses('update-plugin-settings', ['settings' => (object) ['unknown' => true], 'expected_hash' => $settings['expected_hash']])), 'Unknown setting accepted');
$settings = success(callStatuses('update-plugin-settings', ['settings' => (object) ['post_types' => (object) ['post' => false]], 'expected_hash' => $settings['expected_hash']]), 'Disable one post type');
check($settings['settings']->post_types->post === false && $settings['settings']->post_types->page === true, 'Post-type map patch lost sibling');
$settings = success(callStatuses('update-plugin-settings', ['settings' => (object) ['post_types' => (object) ['post' => true]], 'expected_hash' => $settings['expected_hash']]), 'Enable post type');
$statuses = success(callStatuses('list-statuses'), 'Status discovery');
check(count($statuses['items']) > 5, 'Native default statuses absent');
$before = get_terms(['taxonomy' => 'post_status', 'hide_empty' => false]);
success(callStatuses('save-status', ['name' => 'mcp-review', 'properties' => (object) ['label' => 'MCP Review', 'color' => '#123456'], 'dry_run' => true]), 'Create preview');
check(get_terms(['taxonomy' => 'post_status', 'hide_empty' => false]) == $before, 'Create preview wrote terms');
$status = success(callStatuses('save-status', ['name' => 'mcp-review', 'properties' => (object) ['label' => 'MCP Review', 'color' => '#123456', 'description' => 'Synthetic description', 'icon' => 'dashicons-edit', 'post_type' => ['post']]]), 'Create native status');
check($status['complete'] && $status['status']['name'] === 'mcp-review', 'Created status native read-back mismatch');
$role = get_role('editor');
check($role->has_cap('status_change_mcp_review'), 'Native creation did not initialize assignment capability');
$status = success(callStatuses('save-status', ['name' => 'mcp-review', 'expected_hash' => $status['expected_hash'], 'properties' => (object) ['labels' => (object) ['save_as' => 'Save for review']]]), 'Save button caption');
check($status['complete'] && $status['status']['labels']->save_as === 'Save for review', 'Caption read-back mismatch');
check($status['status']['color'] === '#123456', 'Caption patch lost color');
$status = success(callStatuses('save-status', ['name' => 'mcp-review', 'expected_hash' => $status['expected_hash'], 'properties' => (object) ['color' => '', 'post_type' => [], 'labels' => (object) ['save_as' => '']]]), 'Clear optional status fields');
check($status['complete'], 'Empty native status values failed read-back');
$status = success(callStatuses('save-status', ['name' => 'mcp-review', 'expected_hash' => $status['expected_hash'], 'properties' => (object) ['color' => '#123456', 'post_type' => ['post'], 'labels' => (object) ['save_as' => 'Save for review']]]), 'Restore status fields');

check(is_wp_error(callStatuses('save-status', ['name' => 'mcp-review', 'expected_hash' => str_repeat('0', 64), 'properties' => (object) ['color' => '#fff']])), 'Stale status patch accepted');
check(is_wp_error(callStatuses('save-status', ['name' => 'mcp-review', 'expected_hash' => $status['expected_hash'], 'properties' => (object) ['post_type' => ['missing']]])), 'Unknown post type accepted');
check(is_wp_error(callStatuses('save-status', ['name' => 'mcp-review', 'expected_hash' => $status['expected_hash'], 'properties' => (object) ['public' => true]])), 'Undeclared property accepted');
$publish = success(callStatuses('get-status', ['name' => 'publish']), 'Read core status');
check(is_wp_error(callStatuses('save-status', ['name' => 'publish', 'expected_hash' => $publish['expected_hash'], 'properties' => (object) ['labels' => (object) ['publish' => 'Not allowed']]])), 'Built-in caption lock bypassed');
$roles = success(callStatuses('get-status-roles', ['name' => 'mcp-review']), 'Role discovery');
$otherCaps = get_role('subscriber')->capabilities;
$roles = success(callStatuses('set-status-roles', ['name' => 'mcp-review', 'expected_hash' => $roles['expected_hash'], 'roles' => (object) ['subscriber' => true]]), 'Assignment role grant');
check($roles['complete'] && get_role('subscriber')->has_cap('status_change_mcp_review'), 'Assignment grant missing');
$roles = success(callStatuses('set-status-roles', ['name' => 'mcp-review', 'expected_hash' => $roles['expected_hash'], 'roles' => (object) ['subscriber' => false]]), 'Assignment role removal');
check(get_role('subscriber')->capabilities === $otherCaps, 'Role edit changed unrelated capabilities');
$postId = wp_insert_post(['post_type' => 'post', 'post_title' => 'MCP isolated post', 'post_status' => 'draft'], true);
check(!is_wp_error($postId), 'Post fixture failed');
$workflowPost = success(callStatuses('get-post-workflow', ['id' => $postId]), 'Post workflow');
check(is_wp_error(callStatuses('get-post-workflow', ['id' => (string) $postId])), 'Numeric string ID accepted');
$before = get_post($postId);
success(callStatuses('set-post-status', ['id' => $postId, 'status' => 'mcp-review', 'expected_hash' => $workflowPost['expected_hash'], 'dry_run' => true]), 'Post status preview');
check(get_post($postId) == $before, 'Post status preview wrote data');
$workflowPost = success(callStatuses('set-post-status', ['id' => $postId, 'status' => 'mcp-review', 'expected_hash' => $workflowPost['expected_hash']]), 'Native post status save');
check($workflowPost['complete'] && get_post_status($postId) === 'mcp-review', 'Native filters did not save requested status');
$denyAssignment = static function ($caps, $cap) { return $cap === 'status_change_mcp_review' ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyAssignment, PHP_INT_MAX, 2);
check(is_wp_error(callStatuses('set-post-status', ['id' => $postId, 'status' => 'mcp-review', 'expected_hash' => $workflowPost['expected_hash']])), 'Administrator bypassed denied status capability');
remove_filter('map_meta_cap', $denyAssignment, PHP_INT_MAX);
$denyManage = static function ($caps, $cap) { return in_array($cap, ['pp_manage_statuses', 'manage_options'], true) ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyManage, PHP_INT_MAX, 2);
check(is_wp_error(callStatuses('list-statuses')), 'Administrator bypassed denied management');
check(is_wp_error(callStatuses('get-plugin-settings')), 'Administrator bypassed denied settings access');
remove_filter('map_meta_cap', $denyManage, PHP_INT_MAX);
$term = get_term_by('slug', 'mcp-review', 'post_status');
$denyMeta = static function ($caps, $cap, $userId, $args) use ($term) { return $cap === 'edit_term_meta' && (int) ($args[0] ?? 0) === (int) $term->term_id ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyMeta, PHP_INT_MAX, 4);
$status = success(callStatuses('get-status', ['name' => 'mcp-review']), 'Metadata-denied read');
check(is_wp_error(callStatuses('save-status', ['name' => 'mcp-review', 'expected_hash' => $status['expected_hash'], 'properties' => (object) ['color' => '#fff']])), 'Denied term metadata write bypassed');
remove_filter('map_meta_cap', $denyMeta, PHP_INT_MAX);
$workflow = success(callStatuses('list-statuses'), 'Read workflow order');
$short = $workflow['names']; array_pop($short);
check(is_wp_error(callStatuses('set-workflow-order', ['names' => $short, 'expected_hash' => $workflow['expected_hash']])), 'Incomplete order accepted');
$order = success(callStatuses('set-workflow-order', ['names' => $workflow['names'], 'expected_hash' => $workflow['expected_hash']]), 'Save native workflow order');
check($order['complete'], 'Order read-back failed');
$savedLockOptions = get_option('publishpress_custom_status_options');
$lockedOptions = clone (object) $savedLockOptions;
$lockedOptions->force_default_privacy = ['post' => 1];
update_option('publishpress_custom_status_options', $lockedOptions);
\PublishPress_Statuses::instance()->options = $lockedOptions;
$lockedDelete = success(callStatuses('get-status', ['name' => 'mcp-review']), 'Locked deletion token');
check(is_wp_error(callStatuses('delete-status', ['name' => 'mcp-review', 'reassign_to' => 'publish', 'expected_hash' => $lockedDelete['expected_hash']])), 'SQL deletion bypassed forced visibility');
check(get_post_status($postId) === 'mcp-review', 'Locked deletion reassigned a post');
update_option('publishpress_custom_status_options', $savedLockOptions);
\PublishPress_Statuses::instance()->options = (object) $savedLockOptions;
$status = success(callStatuses('get-status', ['name' => 'mcp-review']), 'Deletion token');
success(callStatuses('delete-status', ['name' => 'mcp-review', 'reassign_to' => 'draft', 'expected_hash' => $status['expected_hash'], 'dry_run' => true]), 'Delete preview');
check(get_post_status($postId) === 'mcp-review', 'Deletion preview reassigned post');
$deleted = success(callStatuses('delete-status', ['name' => 'mcp-review', 'reassign_to' => 'draft', 'expected_hash' => $status['expected_hash']]), 'Native delete/reassignment');
check($deleted['complete'] && get_post_status($postId) === 'draft' && !get_term_by('slug', 'mcp-review', 'post_status'), 'Deletion did not match native scope');
if ($settings['pro']) {
    $visibility = success(callStatuses('save-status', ['name' => 'mcp-private', 'taxonomy' => 'post_visibility_pp', 'properties' => (object) ['label' => 'MCP Private', 'color' => '#abcdef']]), 'Pro visibility creation');
    check($visibility['status']['private'], 'Pro visibility status is not private');
    $roles = success(callStatuses('get-status-roles', ['name' => 'mcp-private']), 'Pro role token');
    $mode = success(callStatuses('set-capability-status', ['name' => 'mcp-private', 'capability_status' => 'mcp-private', 'expected_hash' => $roles['expected_hash']]), 'Pro custom permission mode');
    check($mode['complete'] && get_option('presspermit_status_capability_status')['mcp-private'] === 'mcp-private', 'Pro mode native storage mismatch');
    if (!\PublishPress\Statuses\StatusControl::customPrivacyEditCapsEnabled()) {
        foreach ($mode['capability_names'] as $privateCap) {
            check(strpos($privateCap, 'read_') === 0 || strpos($privateCap, 'list_') === 0, 'Disabled visibility-editing capabilities leaked into discovery');
        }
    }

    check(is_wp_error(callStatuses('set-capability-status', ['name' => 'mcp-private', 'capability_status' => 'draft', 'expected_hash' => $mode['expected_hash']])), 'Incompatible Pro permission inheritance accepted');
    check(is_wp_error(callStatuses('set-status-capabilities', ['name' => 'mcp-private', 'expected_hash' => $mode['expected_hash'], 'roles' => (object) ['subscriber' => (object) ['manage_options' => true]]])), 'Arbitrary administrator grant accepted');
    $settings = success(callStatuses('get-plugin-settings'), 'New visibility setting discovery');
    $settings = success(callStatuses('update-plugin-settings', ['expected_hash' => $settings['expected_hash'], 'settings' => (object) ['default_privacy' => (object) ['post' => 'mcp-private']]]), 'New visibility default');
    check($settings['complete'] && $settings['settings']->default_privacy->post === 'mcp-private', 'Dynamic visibility default mismatch');
    $settings = success(callStatuses('update-plugin-settings', ['expected_hash' => $settings['expected_hash'], 'settings' => (object) ['default_privacy' => (object) ['post' => '']]]), 'Clear visibility default');

} else {
    check(wp_get_ability('publishpress-statuses/set-capability-status') === null, 'Pro permission action registered in Free');
    check(is_wp_error(callStatuses('save-status', ['name' => 'mcp-private', 'taxonomy' => 'post_visibility_pp', 'properties' => (object) ['label' => 'Private']])), 'Free accepted Pro taxonomy');
}
// The native service's scoped deletion must not mutate posts inserted after preflight.
$scopeStatus = success(callStatuses('save-status', ['name' => 'mcp-scope', 'properties' => (object) ['label' => 'Scope Race']]), 'Scoped delete fixture');
$scopePost = wp_insert_post(['post_type' => 'post', 'post_title' => 'Preauthorized scope', 'post_status' => 'mcp-scope']);
$scopeStatus = success(callStatuses('get-status', ['name' => 'mcp-scope']), 'Scoped delete token');
$injectedId = 0;
$injectDuringUpdate = static function ($query) use (&$injectedId) {
    global $wpdb;
    if (!$injectedId && strpos($query, 'UPDATE ' . $wpdb->posts . ' SET post_status') === 0 && strpos($query, "'mcp-scope'") !== false) {
        $injectedId = -1;
        $wpdb->insert($wpdb->posts, ['post_type' => 'post', 'post_title' => 'New concurrent scope', 'post_status' => 'mcp-scope']);
        $injectedId = (int) $wpdb->insert_id;
    }
    return $query;
};
add_filter('query', $injectDuringUpdate);
$scopedResult = callStatuses('delete-status', ['name' => 'mcp-scope', 'reassign_to' => 'draft', 'expected_hash' => $scopeStatus['expected_hash']]);
remove_filter('query', $injectDuringUpdate);
check(is_wp_error($scopedResult) && $injectedId > 0, 'Deletion did not detect a changed native scope');
check(get_post_status($injectedId) === 'mcp-scope' && get_term_by('slug', 'mcp-scope', 'post_status'), 'Deletion changed an unauthorized concurrent post or deleted its status');
check(get_post_status($scopePost) === 'draft', 'Partial native reassignment was not observable');
// New-term metadata authorization is enforced before native meta/capability writes.
$denyCreateMeta = static function ($caps, $cap) { return $cap === 'edit_term_meta' ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyCreateMeta, PHP_INT_MAX, 2);
check(is_wp_error(callStatuses('save-status', ['name' => 'mcp-denied-new', 'properties' => (object) ['label' => 'Denied New', 'color' => '#fff']])), 'New-term metadata authorization bypassed');
remove_filter('map_meta_cap', $denyCreateMeta, PHP_INT_MAX);
check(!get_term_by('slug', 'mcp-denied-new', 'post_status') && !get_role('editor')->has_cap('status_change_mcp_denied_new'), 'Denied creation left a status or capability grant');
// Lazy materialization of a native default status cannot bypass term-meta permissions.
$defaultStatus = success(callStatuses('get-status', ['name' => 'pending']), 'Lazy default metadata token');
add_filter('map_meta_cap', $denyCreateMeta, PHP_INT_MAX, 2);
check(is_wp_error(callStatuses('save-status', ['name' => 'pending', 'expected_hash' => $defaultStatus['expected_hash'], 'properties' => (object) ['color' => '#ff0000']])), 'Lazy default metadata authorization bypassed');
remove_filter('map_meta_cap', $denyCreateMeta, PHP_INT_MAX);
if ($settings['pro']) {
    // Use actual registered native condition mappings; fixtures do not invent capability names.
    $nativeCapabilities = \PublishPress\StatusCapabilities::instance();
    $nativeCapabilities->act_late_registrations();
    $testedNativeCapability = false;
    foreach (success(callStatuses('list-statuses'), 'Native cap candidate statuses')['items'] as $candidate) {
        $capRead = success(callStatuses('get-status-roles', ['name' => $candidate['name']]), 'Native capability discovery');
        if (!$capRead['capability_names']) { continue; }
        $capName = $capRead['capability_names'][0];
        if (strpos($capName, 'status_change_') === 0) { continue; }
        $actor = wp_get_current_user();
        $held = !empty($actor->caps[$capName]);
        $actor->add_cap($capName);
        $capRead = success(callStatuses('get-status-roles', ['name' => $candidate['name']]), 'Grant token');
        $grant = success(callStatuses('set-status-capabilities', ['name' => $candidate['name'], 'expected_hash' => $capRead['expected_hash'], 'roles' => (object) ['subscriber' => (object) [$capName => true]]]), 'Native Pro role grant');
        check($grant['complete'] && get_role('subscriber')->has_cap($capName), 'Native Pro capability grant did not persist');
        $removed = success(callStatuses('set-status-capabilities', ['name' => $candidate['name'], 'expected_hash' => $grant['expected_hash'], 'roles' => (object) ['subscriber' => (object) [$capName => false]]]), 'Native Pro role removal');
        check($removed['complete'] && !get_role('subscriber')->has_cap($capName), 'Native Pro capability removal failed');
        $beforeMode = $removed['capability_status'];
        $disabledMode = success(callStatuses('set-capability-status', ['name' => $candidate['name'], 'capability_status' => '', 'expected_hash' => $removed['expected_hash']]), 'Disable native capability mode');
        check($disabledMode['capability_names'] === [], 'Inactive native mode exposed stale capabilities');
        check(is_wp_error(callStatuses('set-status-capabilities', ['name' => $candidate['name'], 'expected_hash' => $disabledMode['expected_hash'], 'roles' => (object) ['subscriber' => (object) [$capName => true]]])), 'Inactive capability mode allowed a grant');
        success(callStatuses('set-capability-status', ['name' => $candidate['name'], 'capability_status' => $beforeMode, 'expected_hash' => $disabledMode['expected_hash']]), 'Restore native capability mode');
        $contributorHeld = get_role('contributor')->has_cap($capName);
        get_role('contributor')->remove_cap($capName);
        $lateGrant = success(callStatuses('get-status-roles', ['name' => $candidate['name']]), 'Late grant token');
        $denyGrantCap = static function ($caps, $cap) use ($capName) { return $cap === $capName ? ['do_not_allow'] : $caps; };
        $revokeAfterRoleWrite = static function ($name, $old, $new) use ($capName, $denyGrantCap) { global $wpdb; if ($name === $wpdb->prefix . 'user_roles' && !empty($new['subscriber']['capabilities'][$capName])) { add_filter('map_meta_cap', $denyGrantCap, PHP_INT_MAX, 2); } };
        add_action('updated_option', $revokeAfterRoleWrite, 10, 3);
        $lateGrantResult = success(callStatuses('set-status-capabilities', ['name' => $candidate['name'], 'expected_hash' => $lateGrant['expected_hash'], 'roles' => (object) ['subscriber' => (object) [$capName => true], 'contributor' => (object) [$capName => true]]]), 'Late capability grant result');
        remove_action('updated_option', $revokeAfterRoleWrite);
        remove_filter('map_meta_cap', $denyGrantCap, PHP_INT_MAX);
        check(!$lateGrantResult['complete'] && get_role('subscriber')->has_cap($capName) && !get_role('contributor')->has_cap($capName), 'Late role grant bypassed revoked capability or hid partial persistence');
        get_role('subscriber')->remove_cap($capName);
        if ($contributorHeld) { get_role('contributor')->add_cap($capName); }
        if (!$held) { $actor->remove_cap($capName); }
        $testedNativeCapability = true;
        break;
    }
    check($testedNativeCapability, 'No native Pro content capability was exercised');
}
if (taxonomy_exists('pp_revision_status')) {
    $revisionStatus = success(callStatuses('save-status', ['name' => 'mcp-revision', 'taxonomy' => 'pp_revision_status', 'properties' => (object) ['label' => 'MCP Revision']]), 'Native revision status creation');
    check($revisionStatus['status']['for_revision'], 'Native revision status lost its edition/dependency classification');
    $revisionRead = success(callStatuses('get-status', ['name' => 'mcp-revision']), 'Native revision token');
    $revisionRead = success(callStatuses('save-status', ['name' => 'mcp-revision', 'expected_hash' => $revisionRead['expected_hash'], 'properties' => (object) ['color' => '#112233']]), 'Native revision properties');
    check($revisionRead['complete'], 'Revision field save failed native read-back');
    $revisionWorking = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Native revision MIME fixture']);
    $wpdb->update($wpdb->posts, ['post_mime_type' => 'pending-revision'], ['ID' => $revisionWorking]);
    clean_post_cache($revisionWorking);
    check((bool) rvy_in_revision_workflow($revisionWorking), 'Native revision MIME fixture is not recognized');
    check(is_wp_error(callStatuses('get-post-workflow', ['id' => $revisionWorking])), 'MIME-based revision bypassed native workflow boundary');
    $wpdb->update($wpdb->posts, ['post_mime_type' => 'mcp-revision'], ['ID' => $revisionWorking]);
    clean_post_cache($revisionWorking);
    $revisionDelete = success(callStatuses('get-status', ['name' => 'mcp-revision']), 'Referenced revision definition token');
    check(is_wp_error(callStatuses('delete-status', ['name' => 'mcp-revision', 'reassign_to' => 'draft', 'expected_hash' => $revisionDelete['expected_hash'], 'dry_run' => true])), 'Referenced revision definition was deletable');

}
$scopeFile = getenv('STATUSES_MCP_CAPABILITIES_FILE');
if ($scopeFile && is_readable($scopeFile)) {
    require_once $scopeFile;
    update_option('cme_capabilities_application_password_capabilities', true);
    PP_Capabilities_Application_Password_Capabilities::instance();
    $scopeUser = is_multisite() ? wp_insert_user(['user_login' => 'mcp_scope_admin', 'user_pass' => 'synthetic-only', 'role' => 'administrator']) : 1;
    wp_set_current_user($scopeUser);
    $password = WP_Application_Passwords::create_new_application_password($scopeUser, ['name' => 'Statuses isolated scope']);
    check(!is_wp_error($password), 'Scoped password creation failed');
    $uuid = $password[1]['uuid'];
    $key = pp_capabilities_application_password_storage_key($scopeUser, $uuid);
    update_option(pp_capabilities_application_password_option_name(), [$key => ['user_id' => $scopeUser, 'uuid' => $uuid, 'denied' => ['manage_options' => false, 'pp_manage_statuses' => false]]]);
    do_action('application_password_did_authenticate', get_userdata($scopeUser), $password[1]);
    check(is_wp_error(callStatuses('get-plugin-settings')), 'Application-password settings scope bypassed');
    check(is_wp_error(callStatuses('list-statuses')), 'Application-password management scope bypassed');
    update_option(pp_capabilities_application_password_option_name(), []);
    update_option('cme_capabilities_application_password_capabilities', false);
    WP_Application_Passwords::delete_application_password($scopeUser, $uuid);
    unset($password);
    wp_set_current_user(1);
}
$bulkA = wp_insert_post(['post_type' => 'post', 'post_title' => 'Bulk A', 'post_status' => 'draft']);
$bulkB = wp_insert_post(['post_type' => 'post', 'post_title' => 'Bulk B', 'post_status' => 'draft']);
$bulkOne = success(callStatuses('get-post-workflow', ['id' => $bulkA]), 'Bulk A token');
$bulkTwo = success(callStatuses('get-post-workflow', ['id' => $bulkB]), 'Bulk B token');
$rows = [['id' => $bulkA, 'status' => 'pending', 'expected_hash' => $bulkOne['expected_hash']], ['id' => $bulkB, 'status' => 'missing-status', 'expected_hash' => $bulkTwo['expected_hash']]];
check(is_wp_error(callStatuses('bulk-set-post-status', ['items' => $rows])), 'Mixed invalid bulk accepted');
check(get_post_status($bulkA) === 'draft' && get_post_status($bulkB) === 'draft', 'Invalid bulk wrote a valid prefix');
$rows[1]['status'] = 'pending';
$denySecond = static function ($caps, $cap, $userId, $args) use ($bulkB) { return $cap === 'edit_post' && (int) ($args[0] ?? 0) === $bulkB ? ['do_not_allow'] : $caps; };
$revokeSecond = static function ($id) use ($bulkA, $denySecond) { if ((int) $id === $bulkA) { add_filter('map_meta_cap', $denySecond, PHP_INT_MAX, 4); } };
add_action('save_post_post', $revokeSecond, 10, 1);
$partial = success(callStatuses('bulk-set-post-status', ['items' => $rows]), 'Partial bulk result');
remove_action('save_post_post', $revokeSecond);
remove_filter('map_meta_cap', $denySecond, PHP_INT_MAX);
check(!$partial['complete'] && $partial['applied_ids'] === [$bulkA] && $partial['failed_id'] === $bulkB, 'Bulk partial result hid successful prefix');
check(get_post_status($bulkB) === 'draft', 'Bulk bypassed revoked object permission');
// Native slugs may start with digits provided the complete slug is not numeric.
$caption = 'Path \\ Review';
$escaping = success(callStatuses('save-status', ['name' => '2nd-review', 'properties' => (object) ['label' => 'Second Review', 'labels' => (object) ['save_as' => $caption, 'publish' => '']]]), 'Native digit-prefixed slug');
check($escaping['complete'] && $escaping['status']['labels']->save_as === $caption, 'Creation lost caption backslashes');
$escaping = success(callStatuses('save-status', ['name' => '2nd-review', 'expected_hash' => $escaping['expected_hash'], 'properties' => (object) ['labels' => (object) ['save_as' => '']]]), 'Clear caption override');
$escaping = success(callStatuses('save-status', ['name' => '2nd-review', 'expected_hash' => $escaping['expected_hash'], 'properties' => (object) ['labels' => (object) ['publish' => $caption]]]), 'Escaped caption patch');
check($escaping['complete'] && $escaping['status']['labels']->publish === $caption, 'Update lost caption backslashes');
check($escaping['status']['labels']->save_as === '', 'Partial caption patch replaced omitted empty override with display fallback');
$lateTermRead = success(callStatuses('get-status', ['name' => '2nd-review']), 'Late term-edit token');
$lateTerm = get_term_by('slug', '2nd-review', 'post_status');
$lateTermLabel = $lateTerm->name;
$denyLateTerm = static function ($caps, $cap, $userId, $args) use ($lateTerm) { return $cap === 'edit_term' && (int) ($args[0] ?? 0) === (int) $lateTerm->term_id ? ['do_not_allow'] : $caps; };
$revokeAfterMeta = static function ($metaId, $termId, $key) use ($lateTerm, $denyLateTerm) { if ((int) $termId === (int) $lateTerm->term_id && $key === 'color') { add_filter('map_meta_cap', $denyLateTerm, PHP_INT_MAX, 4); } };
add_action('added_term_meta', $revokeAfterMeta, 10, 3);
add_action('updated_term_meta', $revokeAfterMeta, 10, 3);
$lateTermResult = callStatuses('save-status', ['name' => '2nd-review', 'expected_hash' => $lateTermRead['expected_hash'], 'properties' => (object) ['color' => '#aabbcc', 'label' => 'Denied Late Label']]);
remove_action('added_term_meta', $revokeAfterMeta);
remove_action('updated_term_meta', $revokeAfterMeta);
remove_filter('map_meta_cap', $denyLateTerm, PHP_INT_MAX);
check(is_wp_error($lateTermResult), 'Term save bypassed authority revoked by a metadata hook');
check(get_term_by('slug', '2nd-review', 'post_status')->name === $lateTermLabel && get_term_meta($lateTerm->term_id, 'color', true) === '#aabbcc', 'Late term failure did not preserve its honest partial boundary');
$lockedRead = success(callStatuses('get-status', ['name' => 'publish']), 'Native label lock discovery');
check(in_array('label', $lockedRead['locked_fields'], true) && in_array('labels', $lockedRead['locked_fields'], true), 'Native label locks absent from discovery');
if ($settings['pro']) {
    $targetCaps = apply_filters('publishpress_statuses_required_caps', ['status_change_mcp_private'], 'set_status', 'mcp-private', 'post');
    check(count($targetCaps) > 0, 'Native private-status assignment capability absent');
    $privatePost = wp_insert_post(['post_type' => 'post', 'post_title' => 'Private permission fixture', 'post_status' => 'draft']);
    $privateToken = success(callStatuses('get-post-workflow', ['id' => $privatePost]), 'Private assignment token');
    $denyPrivateAssignment = static function ($caps, $cap) use ($targetCaps) { return in_array($cap, $targetCaps, true) ? ['do_not_allow'] : $caps; };
    add_filter('map_meta_cap', $denyPrivateAssignment, PHP_INT_MAX, 2);
    check(is_wp_error(callStatuses('set-post-status', ['id' => $privatePost, 'status' => 'mcp-private', 'expected_hash' => $privateToken['expected_hash'], 'dry_run' => true])), 'Administrator bypassed denied private assignment capability');
    remove_filter('map_meta_cap', $denyPrivateAssignment, PHP_INT_MAX);
    check(get_post_status($privatePost) === 'draft', 'Denied private assignment changed content');
    success(callStatuses('save-status', ['name' => 'mcp-lock-private', 'taxonomy' => 'post_visibility_pp', 'properties' => (object) ['label' => 'Locked Private']]), 'Native condition status');
    check(!in_array('mcp-lock-private', (array) get_option('publishpress_status_positions', []), true), 'Visibility status was inserted into the moderation workflow');
    \PublishPress\Statuses\StatusControl::setItemCondition('force_visibility', 'object', 'post', $privatePost, 'mcp-lock-private', 'item', ['propagate' => false]);
    check(\PublishPress\StatusCapabilities::statusHasVisibilityConditions('mcp-lock-private') === true, 'Native visibility condition reference fixture failed');
    check(\PublishPress\StatusCapabilities::postHasVisibilityConditions(get_post($privatePost)) === true, 'Object visibility lock was not discovered');
    global $wpdb;
    $wpdb->update($wpdb->posts, ['post_status' => '2nd-review'], ['ID' => $privatePost]);
    clean_post_cache($privatePost);
    $conditionDelete = success(callStatuses('get-status', ['name' => '2nd-review']), 'Object-locked reassignment token');
    check(is_wp_error(callStatuses('delete-status', ['name' => '2nd-review', 'reassign_to' => 'publish', 'expected_hash' => $conditionDelete['expected_hash']])), 'SQL deletion bypassed native object visibility conditions');
    check(get_post_status($privatePost) === '2nd-review', 'Object-locked deletion changed content');
    $wpdb->update($wpdb->posts, ['post_status' => 'draft'], ['ID' => $privatePost]);
    clean_post_cache($privatePost);

    $conditionStatus = success(callStatuses('get-status', ['name' => 'mcp-lock-private']), 'Condition status token');
    $conditionsTable = $wpdb->pp_conditions;
    $wpdb->pp_conditions = $wpdb->prefix . 'mcp_missing_conditions';
    $previousShowErrors = $wpdb->show_errors();
    ob_start();
    try {
        $unavailableConditions = callStatuses('delete-status', ['name' => 'mcp-lock-private', 'reassign_to' => 'draft', 'expected_hash' => $conditionStatus['expected_hash'], 'dry_run' => true]);
    } finally {
        $conditionDiagnostics = ob_get_clean();
        $wpdb->show_errors($previousShowErrors);
        $wpdb->pp_conditions = $conditionsTable;
    }
    check(is_wp_error($unavailableConditions) && $conditionDiagnostics === '', 'Condition-query failure leaked database diagnostics or allowed deletion');

    check(is_wp_error(callStatuses('delete-status', ['name' => 'mcp-lock-private', 'reassign_to' => 'draft', 'expected_hash' => $conditionStatus['expected_hash'], 'dry_run' => true])), 'Referenced native visibility definition was deletable');
    \PublishPress\Statuses\StatusControl::clearItemCondition('force_visibility', 'object', 'post', $privatePost, 'item');
    $beforeLateOptions = get_option('publishpress_custom_status_options');
    $beforeLatePrivacy = get_option('presspermit_privacy_statuses_enabled', 1);
    $lateSettings = success(callStatuses('get-plugin-settings'), 'Multi-option permission token');
    $denyLateSettings = static function ($caps, $cap) { return $cap === 'manage_options' ? ['do_not_allow'] : $caps; };
    $revokeAfterFirstOption = static function ($name) use ($denyLateSettings) { if ($name === 'publishpress_custom_status_options') { add_filter('map_meta_cap', $denyLateSettings, PHP_INT_MAX, 2); } };
    add_action('updated_option', $revokeAfterFirstOption);
    $lateResult = success(callStatuses('update-plugin-settings', ['expected_hash' => $lateSettings['expected_hash'], 'settings' => (object) ['new_statuses_main_workflow' => !$lateSettings['settings']->new_statuses_main_workflow, 'privacy_statuses_enabled' => !$lateSettings['settings']->privacy_statuses_enabled]]), 'Late settings permission result');
    remove_action('updated_option', $revokeAfterFirstOption);
    remove_filter('map_meta_cap', $denyLateSettings, PHP_INT_MAX);
    check(!$lateResult['complete'] && get_option('presspermit_privacy_statuses_enabled', 1) === $beforeLatePrivacy, 'Second option write bypassed revoked authority');
    update_option('publishpress_custom_status_options', $beforeLateOptions);
    success(callStatuses('get-plugin-settings'), 'Restore late settings state');
    $parent = success(callStatuses('save-status', ['name' => 'mcp-parent', 'properties' => (object) ['label' => 'MCP Parent']]), 'Inheritance parent');
    $parentRoles = success(callStatuses('get-status-roles', ['name' => 'mcp-parent']), 'Parent permission token');
    success(callStatuses('set-capability-status', ['name' => 'mcp-parent', 'capability_status' => 'mcp-parent', 'expected_hash' => $parentRoles['expected_hash']]), 'Parent custom mode');
    success(callStatuses('save-status', ['name' => 'mcp-child', 'properties' => (object) ['label' => 'MCP Child', 'status_parent' => 'mcp-parent']]), 'Inheritance child');
    $childRoles = success(callStatuses('get-status-roles', ['name' => 'mcp-child']), 'Child permission token');
    $childRoles = success(callStatuses('set-capability-status', ['name' => 'mcp-child', 'capability_status' => 'mcp-parent', 'expected_hash' => $childRoles['expected_hash']]), 'Inherited permission mode');
    check($childRoles['capability_names'] === [], 'Inherited mode exposed its parent permission matrix');
}
if (getenv('STATUSES_MCP_LARGE_SCOPE') === '1') {
    for ($index = 0; $index < 1010; ++$index) {
        $lastScopeTerm = wp_insert_term('ZZ Scope ' . sprintf('%04d', $index), 'post_status', ['slug' => 'zz-scope-' . $index]);
        check(!is_wp_error($lastScopeTerm), 'Large native scope fixture failed');
    }
    $largeToken = success(callStatuses('get-status', ['name' => '2nd-review']), 'Large native revision scope');
    update_term_meta($lastScopeTerm['term_id'], 'color', '#334455');
    check(is_wp_error(callStatuses('save-status', ['name' => '2nd-review', 'expected_hash' => $largeToken['expected_hash'], 'properties' => (object) ['color' => '#556677']])), 'Revision token ignored native status changes beyond 1,000 terms');
}
$nativeFailure = static function ($term, $taxonomy) { return $taxonomy === 'post_status' && $term === 'MCP diagnostic' ? new WP_Error('db_insert_error', 'synthetic-private-diagnostic-secret', ['private_debug' => 'synthetic-private-diagnostic-secret']) : $term; };
add_filter('pre_insert_term', $nativeFailure, 10, 2);
$failedCreation = callStatuses('save-status', ['name' => 'mcp-diagnostic', 'properties' => (object) ['label' => 'MCP diagnostic']]);
remove_filter('pre_insert_term', $nativeFailure);
check(is_wp_error($failedCreation), 'Native insertion failure was not reported');
check(strpos($failedCreation->get_error_message() . wp_json_encode($failedCreation->get_error_data()), 'synthetic-private-diagnostic-secret') === false, 'Native persistence diagnostics leaked through MCP');
check(!get_role('editor')->has_cap('status_change_mcp_diagnostic') && !get_term_by('slug', 'mcp-diagnostic', 'post_status'), 'Failed term creation granted a capability or left an object');
$privateLabelTerm = get_term_by('slug', '2nd-review', 'post_status');
$privateLabelValues = (array) get_term_meta($privateLabelTerm->term_id, 'labels', true);
$privateLabelValues['native_private_note'] = 'synthetic-native-private-label-secret';
update_term_meta($privateLabelTerm->term_id, 'labels', (object) $privateLabelValues);
$privateLabelToken = success(callStatuses('get-status', ['name' => '2nd-review']), 'Native private label token');
$privateLabelPreview = success(callStatuses('save-status', ['name' => '2nd-review', 'expected_hash' => $privateLabelToken['expected_hash'], 'properties' => (object) ['labels' => (object) ['publish' => 'Preview caption']], 'dry_run' => true]), 'Native private label preview');
check(strpos(wp_json_encode($privateLabelPreview), 'synthetic-native-private-label-secret') === false, 'Undeclared native label metadata leaked through preview');
check(get_term_meta($privateLabelTerm->term_id, 'labels', true)->native_private_note === 'synthetic-native-private-label-secret', 'Preview changed unrelated native label metadata');
$encodingPost = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Revision encoding fixture']);
$encodingToken = success(callStatuses('get-post-workflow', ['id' => $encodingPost]), 'Encodable revision token');
$deepNativeMeta = 'synthetic-private-deep-metadata';
for ($depth = 0; $depth < 530; ++$depth) { $deepNativeMeta = ['child' => $deepNativeMeta]; }
$savedEncodingOptions = get_option('publishpress_custom_status_options');
$deepEncodingOptions = clone $savedEncodingOptions;
$deepEncodingOptions->legacy_deep_unexposed = $deepNativeMeta;
update_option('publishpress_custom_status_options', $deepEncodingOptions);
$encodingRead = callStatuses('get-post-workflow', ['id' => $encodingPost]);
check(is_wp_error($encodingRead), 'Unencodable revision state returned a reusable token');
check(strpos($encodingRead->get_error_message(), 'synthetic-private-deep-metadata') === false, 'Revision encoding error disclosed native metadata');
check(is_wp_error(callStatuses('set-post-status', ['id' => $encodingPost, 'status' => 'pending', 'expected_hash' => $encodingToken['expected_hash']])), 'Unencodable native state bypassed revision protection');
check(get_post_status($encodingPost) === 'draft', 'Failed revision encoding permitted a write');
update_option('publishpress_custom_status_options', $savedEncodingOptions);
$emptyStatusToken = success(callStatuses('get-status', ['name' => '2nd-review']), 'Empty status patch token');
$emptyStatusWrites = 0;
$countStatusWrites = static function () use (&$emptyStatusWrites) { ++$emptyStatusWrites; };
add_action('edit_terms', $countStatusWrites);
$emptyStatus = success(callStatuses('save-status', ['name' => '2nd-review', 'expected_hash' => $emptyStatusToken['expected_hash'], 'properties' => (object) ['labels' => (object) []]]), 'Empty status caption patch');
remove_action('edit_terms', $countStatusWrites);
check($emptyStatusWrites === 0 && $emptyStatus['expected_hash'] === $emptyStatusToken['expected_hash'], 'Empty status patch ran native persistence or changed data');
$subscriber = wp_insert_user(['user_login' => 'mcp_subscriber', 'user_pass' => 'synthetic-only', 'role' => 'subscriber']);
wp_set_current_user($subscriber);
check(is_wp_error(callStatuses('list-statuses')), 'Subscriber accessed status administration');
check(is_wp_error(callStatuses('get-post-workflow', ['id' => $postId])), 'Subscriber read unauthorized workflow');
wp_set_current_user(1);
$abilities = wp_get_abilities();
foreach ($abilities as $ability) {
    if (strpos($ability->get_name(), 'publishpress-statuses/') !== 0) { continue; }
    $meta = $ability->get_meta();
    check($meta['public'] === false && $meta['show_in_rest'] === false && $meta['mcp']['public'] === false, 'Ability leaked to public discovery');
}
echo 'Statuses ' . getenv('STATUSES_MCP_MODE') . ': ' . $assertions . " assertions passed.\n";
