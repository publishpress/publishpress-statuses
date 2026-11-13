<?php
namespace PublishPress_Statuses;

\defined('ABSPATH') || exit;

/**
 * Keep internal workflow storage out of WordPress's generic term editors.
 */
class StatusTaxonomySecurity
{
    public static function register()
    {
        add_filter('register_taxonomy_args', [__CLASS__, 'filterTaxonomyArgs'], 100, 2);
        add_filter('map_meta_cap', [__CLASS__, 'mapMetaCap'], 10, 4);
        add_action('publishpress_statuses_register_taxonomies', [__CLASS__, 'secureRegisteredTaxonomies'], 100);

        foreach (['wp_ajax_add-tag', 'wp_ajax_inline-save-tax', 'wp_ajax_delete-tag',
            'load-edit-tags.php', 'load-term.php'] as $hook) {
            add_action($hook, [__CLASS__, 'blockCoreTermEditor'], 0);
        }
    }

    private static function isInternalTaxonomy($taxonomy)
    {
        return in_array($taxonomy, [
            \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH,
            \PublishPress_Statuses::TAXONOMY_PRIVACY,
            \PublishPress_Statuses::TAXONOMY_CORE_STATUS,
            \PublishPress_Statuses::TAXONOMY_PSEUDO_STATUS,
            'pp_revision_status',
        ], true);
    }

    private static function termCapabilities()
    {
        return array_fill_keys(
            ['manage_terms', 'edit_terms', 'delete_terms', 'assign_terms'],
            'pp_manage_status_terms'
        );
    }

    public static function filterTaxonomyArgs($args, $taxonomy)
    {
        if (self::isInternalTaxonomy($taxonomy)) {
            $args['capabilities'] = array_merge(
                isset($args['capabilities']) ? (array) $args['capabilities'] : [],
                self::termCapabilities()
            );
        }

        return $args;
    }

    public static function secureRegisteredTaxonomies()
    {
        // Also cover a storage taxonomy registered before this plugin loaded.
        foreach ([
            \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH,
            \PublishPress_Statuses::TAXONOMY_PRIVACY,
            \PublishPress_Statuses::TAXONOMY_CORE_STATUS,
            \PublishPress_Statuses::TAXONOMY_PSEUDO_STATUS,
            'pp_revision_status',
        ] as $taxonomy) {
            if ($object = get_taxonomy($taxonomy)) {
                foreach (self::termCapabilities() as $operation => $capability) {
                    $object->cap->$operation = $capability;
                }
            }
        }
    }

    public static function mapMetaCap($caps, $cap, $user_id, $args)
    {
        if ('pp_manage_status_terms' === $cap) {
            // Match the dedicated status handlers, including administrators
            // without the optional pp_manage_statuses capability.
            return user_can($user_id, 'manage_options') ? ['manage_options'] : ['pp_manage_statuses'];
        }

        return $caps;
    }

    public static function blockCoreTermEditor()
    {
        // These are storage taxonomies. Their supported request handlers have
        // dedicated status nonces; generic category/tag nonces are insufficient.
        // Inspect both sources: core AJAX uses POST, while term screens use
        // REQUEST. Do not depend on PHP's configurable request_order.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
        foreach ([$_GET, $_POST, $_REQUEST] as $request) {
            $taxonomy = isset($request['taxonomy']) && is_string($request['taxonomy'])
                ? sanitize_key(wp_unslash($request['taxonomy'])) : '';

            if (self::isInternalTaxonomy($taxonomy)) {
                wp_die(
                    esc_html__('Please use the PublishPress Statuses screen to manage statuses.', 'publishpress-statuses'),
                    '',
                    ['response' => 403]
                );
            }
        }
    }
}
