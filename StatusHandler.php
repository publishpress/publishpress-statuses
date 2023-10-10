<?php
namespace PublishPress_Statuses;

// Custom Status management: Handle status add / edit requests
class StatusHandler {
    /**
     * Handles a form's POST request to add a custom status
     *
     */
    public static function handleAddCustomStatus()
    {
        global $current_user;

        check_admin_referer('custom-status-add-nonce');

        if (!current_user_can('manage_options') && !current_user_can('pp_manage_statuses')) {
            wp_die(esc_html__('Sorry, you do not have permission to edit custom statuses.', 'publishpress-statuses'));
        }

        // Validate and sanitize the form data
        $status_label = sanitize_text_field(trim($_POST['status_label']));
        $status_name = sanitize_title($status_label);
        $status_description = stripslashes(wp_filter_nohtml_kses(trim($_POST['description'])));
        $status_color = sanitize_hex_color($_POST['status_color']);
        $status_icon = str_replace('dashicons|', '', $_POST['icon']);

        $taxonomy = (!empty($_POST['taxonomy'])) ? sanitize_key($_POST['taxonomy']) : \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH;

        if ($taxonomy && !in_array($taxonomy, [\PublishPress_Statuses::TAXONOMY_PRE_PUBLISH, \PublishPress_Statuses::TAXONOMY_PRIVACY])) {
            $taxonomy = \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH;
        }

        /**
         * Form validation
         * - Name is required and can't conflict with an existing name or slug
         * - Description is optional
         */
        $form_errors = [];

        // Check if name field was filled in
        if (empty($status_label)) {
            $form_errors['label'] = __('Please enter a name for the status', 'publishpress-statuses');
        }
        // Check that the name isn't numeric
        if (is_numeric($status_label)) {
            $form_errors['label'] = __(
                'Please enter a valid, non-numeric name for the status.',
                'publishpress-statuses'
            );
        }
        // Check that the status name doesn't exceed 20 chars
        $name_is_valid = true;
        if (function_exists('mb_strlen')) {
            if (mb_strlen($status_label) > 20) {
                $name_is_valid = false;
            }
        } else {
            if (strlen($status_label) > 20) {
                $name_is_valid = false;
            }
        }
        if (! $name_is_valid) {
            $form_errors['label'] = __(
                'Status name cannot exceed 20 characters. Please try a shorter name.',
                'publishpress-statuses'
            );

            return;
        }

        // Check to make sure the status doesn't already exist as another term because otherwise we'd get a weird slug
        if (term_exists($status_name, 'post_status')) {
            $form_errors['label'] = __(
                'Name conflicts with existing status. Please choose another.',
                'publishpress-statuses'
            );
        }
        // Check to make sure the name is not restricted
        if (self::is_restricted_status(strtolower($status_name))) {
            $form_errors['label'] = __(
                'Status name is restricted. Please choose another name.',
                'publishpress-statuses'
            );
        }

        // If there were any form errors, kick out and return them
        if (count($form_errors)) {
            \PublishPress_Statuses::instance()->form_errors = $form_errors;
            \PublishPress_Statuses::instance()->last_error = 'form-error';
            return;
        }

        // Try to add the status
        $status_args = [
            'description' => $status_description,
            'name' => $status_name,
            'color' => $status_color,
            'icon' => $status_icon,
        ];
        
        $return = \PublishPress_Statuses::instance()->addStatus($taxonomy, $status_label, $status_args);

        if (is_wp_error($return)) {
            wp_die(esc_html__('Could not add status: ', 'publishpress-statuses') . esc_html($return->get_error_message()));
        }

        $roles = ['administrator', 'editor', 'author', 'contributor'];
        foreach ($roles as $roleSlug) {
            $role = get_role($roleSlug);
            if (! empty($role)) {
                $role->add_cap('status_change_' . str_replace('-', '_', $status_name));
            }
        }

        delete_option('publishpress_statuses_num_roles');

        $redirect_args = ['action' => 'edit-status', 'name' => $status_name, 'message' => 'status-added'];

        if ($status_type = \PublishPress_Functions::REQUEST_key('status_type')) {
            $redirect_args['status_type'] = $status_type;
        }

        // Redirect if successful
        $redirect_url = \PublishPress_Statuses::getLink($redirect_args);

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handles a GET request to delete a specific term
     *
     */
    public static function handleDeleteCustomStatus()
    {
        // Check for proper nonce
        check_admin_referer('delete-status');

        // Only allow users with the proper caps
        if (!current_user_can('manage_options') && !current_user_can('pp_manage_statuses')) {
            wp_die(esc_html__('Sorry, you do not have permission to edit custom statuses.', 'publishpress-statuses'));
        }

        // Check to make sure the status isn't already deleted
        $name = sanitize_key($_GET['name']);
        $term = \PublishPress_Statuses::getStatusBy('id', $name);
        if (! $term) {
            wp_die(esc_html__('Status does not exist.', 'publishpress-statuses'));
        }

        $return = self::deleteCustomStatus($name);
        if (is_wp_error($return)) {
            wp_die(esc_html__('Could not delete the status: ', 'publishpress-statuses') . esc_html($return->get_error_message()));
        }

        $redirect_url = \PublishPress_Statuses::getLink(['message' => 'status-deleted']);
        wp_redirect($redirect_url);

        exit;
    }

    
    /**
     * Handles a POST request to edit a custom status
     *
     */
    public static function handleEditCustomStatus()
    {
        check_admin_referer('edit-status');

        if (!current_user_can('manage_options') && !current_user_can('pp_manage_statuses')) {
            wp_die(esc_html__('You are not permitted to do that.', 'publishpress-statuses'));
        }

        if (!$existing_status = \PublishPress_Statuses::getStatusBy('name', sanitize_key($_GET['name']))) {
            wp_die(esc_html__("Post status doesn't exist.", 'publishpress-statuses'));
        }

        $color = sanitize_hex_color($_POST['status_color']);
        $icon = sanitize_text_field($_POST['icon']);
        $icon = str_replace('dashicons|', '', $icon);

        $name = sanitize_key($_GET['name']);
        $status_obj = $existing_status;

        $name = sanitize_text_field(trim($_POST['name']));

        $form_errors = [];

        if (isset($_REQUEST['description'])) {
            $description = stripslashes(wp_filter_nohtml_kses(trim($_REQUEST['description'])));
        }

        if (isset($_REQUEST['status_label'])) {
            /**
             * Form validation for editing custom status
             *
             * Details
             * - 'name' is a required field and can't conflict with existing name or slug
             * - 'description' is optional
             */

            $label = sanitize_text_field(trim($_POST['status_label']));

            // Check if name field was filled in
            if (empty($label)) {
                $form_errors['status_label'] = __('Please enter a name for the status', 'publishpress-statuses');
            }

            // Check that the name isn't numeric
            if (is_numeric($label)) {
                $form_errors['status_label'] = __(
                    'Please enter a valid, non-numeric name for the status.',
                    'publishpress-statuses'
                );
            }
            // Check that the status name doesn't exceed 20 chars
            $name_is_valid = true;

            if (function_exists('mb_strlen')) {
                if (mb_strlen($label) > 20) {
                    $name_is_valid = false;
                }
            } else {
                if (strlen($label) > 20) {
                    $name_is_valid = false;
                }
            }

            if (! $name_is_valid) {
                $form_errors['status_label'] = __(
                    'Status name cannot exceed 20 characters. Please try a shorter name.',
                    'publishpress-statuses'
                );
            }

            if (!empty($form_errors)) {
                \PublishPress_Statuses::instance()->form_errors = $form_errors;
            }
        }

        // Try to edit the post status
        $args = [
            'name' => $name,
            'color' => $color,
            'icon' => $icon,
        ];

        if (isset($label)) {
            $args['label'] = $label;
        }

        if (isset($description)) {
            $args['description'] = $description;
        }


        $labels = [];
        $labels['save_as'] = sanitize_text_field($_REQUEST['status_save_as_label']);
        $labels['publish'] = sanitize_text_field($_REQUEST['status_publish_label']);
        $args['labels'] = (object) $labels;


        $status_post_types = !empty($status_obj->post_type) ? $status_obj->post_type : [];

        if (!empty($_REQUEST['pp_status_all_types'])) {
            $args['post_type'] = [];

        } else {
            $set_post_types = !empty($_REQUEST['pp_status_post_types']) ? array_map('intval', $_REQUEST['pp_status_post_types']) : false;

            if ($set_post_types) {
                if ($add_types = array_filter($set_post_types)) {
                    $status_post_types = array_unique(array_merge($status_post_types, array_map('sanitize_key', array_keys($add_types))));
                }

                if ($remove_types = array_diff($set_post_types, ['1', true, 1])) {
                    $status_post_types = array_diff($status_post_types, array_keys($remove_types));
                }

                $args['post_type'] = $status_post_types;
            }
        }

        if (isset($_REQUEST['roles_set_status'])) {
            $cap_name = str_replace('-', '_', "status_change_{$status_obj->name}");
            
            $roles_set_status = array_map('intval', $_REQUEST['roles_set_status']);

            foreach ($roles_set_status as $role_name => $set_val) {
                $role_name = sanitize_key($role_name);
                $set_val = boolval($set_val);

                if (!\PublishPress_Functions::isEditableRole($role_name)) {
                    continue;
                }

                if ($role = get_role($role_name)) {
                    if ($set_val && empty($role->capabilities[$cap_name])) {
                        $role->add_cap($cap_name);
                        $changed = true;

                    } elseif (!$set_val && !empty($role->capabilities[$cap_name])) {
                        $role->remove_cap($cap_name);
                        $changed = true;
                    }
                }

            }

            if (!empty($changed)) {
                \PublishPress_Statuses::updateStatusNumRoles($status_obj->name, ['force_refresh' => true]);
            }
        }

        if (isset($_REQUEST['status_caps'])) {
            foreach ($_REQUEST['status_caps'] as $role_name => $set_status_caps) {
                $role_name = sanitize_key($role_name);

                if (!\PublishPress_Functions::isEditableRole($role_name)) {
                    continue;
                }

                $role = get_role($role_name);

                if ($add_caps = array_diff_key(
                    array_filter($set_status_caps),
                    array_filter($role->capabilities)
                )) {
                    foreach (array_keys($add_caps) as $cap_name) {
                        $role->add_cap($cap_name);
                    }
                }

                $set_false_status_caps = array_diff_key($set_status_caps, array_filter($set_status_caps));

                foreach(array_keys($set_false_status_caps) as $cap_name) {
                    if (!empty($role->capabilities[$cap_name])) {
                        $role->remove_cap($cap_name);
                    }
                }
            }
        }

        $status_obj = get_post_status_object($name);

        if (!\PublishPress_Functions::empty_REQUEST('return_module')) {
            $arr = ['message' => 'status-updated'];
            $arr['page'] = 'pp-modules-settings';
            $arr['settings_module'] = \PublishPress_Functions::REQUEST_key('return_module');

            $redirect_url = \PublishPress_Statuses::getLink($arr);
        } else {
            $arr = ['message' => 'status-updated'];
            $arr['page'] = 'publishpress-statuses';
            $arr['action'] = 'edit-status';
            $arr['name'] = $name;

            if (!empty($_REQUEST['pp_tab'])) {
                $arr['pp_tab'] = str_replace('pp-', '', sanitize_key($_REQUEST['pp_tab']));
            }

            $arr = apply_filters('publishpress_status_edit_redirect_args', $arr, $status_obj);

            $redirect_url = \PublishPress_Statuses::getLink($arr);
        }

        do_action('publishpress_statuses_edit_status', $existing_status->name, $args);

        $return = self::updateCustomStatus($existing_status->name, $args);

        if (is_wp_error($return)) {
            wp_die(esc_html__('Error updating post status.', 'publishpress-statuses'));
        }

        // Saving custom settings for native statuses
        if (!empty($status_obj) && !empty($status_obj->_builtin)) {
            $name = sanitize_title($_GET['label']);

            update_option("psppno_status_{$name}_color", $color);
            update_option("psppno_status_{$name}_icon", $icon);
        }

        wp_redirect($redirect_url);
        exit;
    }


    /**
     * Update an existing custom status
     *
     * @param int @status_id ID for the status
     * @param array $args Any arguments to be updated
     *
     * @return object|WP_Error|false $updated_status Newly updated status object
     */
    public static function updateCustomStatus($name, $args = [])
    {
        if (in_array($name, ['_pre-publish-alternate', '_disabled'])) {
            return;
        }

        $status_obj = \PublishPress_Statuses::getStatusBy('slug', $name);

        if (! $status_obj || is_wp_error($status_obj)) {
            return new \WP_Error('invalid', __("Custom status ($name) doesn't exist.", 'publishpress-statuses'));
        }

        // Reset our internal object cache
        \PublishPress_Statuses::instance()->clearStatusCache();

        $updatedStatusId = $name;


        // We're encoding metadata that isn't supported by default in the term's description field
        $args_to_encode = [];

        if (!empty($status_obj->_builtin) && !in_array($status_obj->name, ['pending'])) {
            $taxonomy = \PublishPress_Statuses::TAXONOMY_CORE_STATUS;

        } elseif (in_array($name, ['_pre-publish-alternate', '_disabled'])) {
            $taxonomy = \PublishPress_Statuses::TAXONOMY_PSEUDO_STATUS;

        } elseif (!empty($status_obj->private)) {
            $taxonomy = \PublishPress_Statuses::TAXONOMY_PRIVACY;

        } else {
            $taxonomy = \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH;
        }

        // Also re-encode any existing properties, since the plugin that defined it may be temporarily deactivated.
        if (!$term = get_term_by('slug', $name, $taxonomy)) {
            if ($term_id = \PublishPress_Statuses::instance()->addStatus($taxonomy, $status_obj->label, ['slug' => $name])) {
                $term = get_term_by('slug', $name, $taxonomy);
                $updated_status_array = (array) $term;
            }
        }

        if ($term) {
            $term_meta_fields = apply_filters('publishpress_statuses_meta_fields', ['labels', 'post_type', 'roles', 'status_parent', 'color', 'icon']);

            foreach ($args as $field => $set_value) {
                if (in_array($field, $term_meta_fields)) {
                    if (is_array($args[$field])) {
                        $meta_val = [];

                        foreach ($set_value as $k => $val) {
                            $meta_val[$k] = sanitize_textarea_field($val);
                        }
                    } elseif (is_object($set_value)) {
                        $meta_val = \get_object_vars($set_value);

                        foreach($meta_val as $k => $val) {
                            $meta_val[$k] = sanitize_text_field($val);
                        }

                        $meta_val = (object) $meta_val;
                    } else {
                        $meta_val = sanitize_textarea_field($set_value);
                    }

                    $result = update_term_meta($term->term_id, $field, $meta_val);

                    if (is_wp_error($result)) {
                        return $result;
                    }
                }
            }

            $args = array_intersect_key(
                $args, 
                array_fill_keys(['term_id', 'name', 'slug', 'label', 'term_group', 'term_taxonomy_id', 'taxonomy', 'description', 'parent', 'count'], true)
            );

            $args['description'] = (isset($args['description'])) ? $args['description'] : $term->description;

            if (!empty($args['name'])) {
                $args['slug'] = $args['name'];
            }

            if (!empty($args['label'])) {
                $args['name'] = $args['label'];
            }

            // temp (@todo: test status slug rename
            // if (!empty($status_obj->_builtin)) {
            unset($args['slug']);
            //}

            if (!empty($status_obj->_builtin)) {
                $args['name'] = $status_obj->label;
            }

            $updated_status_array = wp_update_term($term->term_id, $taxonomy, $args);

            if (is_wp_error($updated_status_array)) {
                return $updated_status_array;
            }
        }

        if (!$term || !is_array($updated_status_array) || !isset($updated_status_array['term_id'])) {
            $term_id = (!empty($term)) ? $term->term_id : 0;

            error_log(
                sprintf(
                    '[PUBLISHPRESS] Error updating the status term. $status_id: %s, taxonomy: %s, $args: %s',
                    $term_id,
                    $taxonomy,
                    print_r($args, true)
                )
            );

            return new \WP_Error('custom-status-term_id', esc_html__("Error while updating the status ($name)", 'publishpress-statuses'));
        }

        $updatedStatusId = $updated_status_array['term_id'];

        return \PublishPress_Statuses::getStatusBy('id', $updatedStatusId);
    }

    private function statusDeleted($status_name, $reassign_status = '') {

    }


    /**
     * Deletes a custom status from the wp_terms table.
     *
     * Reassigns posts that currently have the deleted status assigned.
     */
    public static function deleteCustomStatus($old_status, $args = [], $reassign_status = '')
    {
        if ($reassign_status == $old_status) {
            return new \WP_Error('invalid', __('Cannot reassign to the status you want to delete', 'publishpress-statuses'));
        }

        // Reset our internal object cache
        \PublishPress_Statuses::instance()->clearStatusCache();

        if (! self::is_restricted_status($old_status)) {
            $default_status = \PublishPress_Statuses::DEFAULT_STATUS;
            
            // If new status in $reassign, use that for all posts of the old_status
            if (!empty($reassign_status)) {
                if ($_status = \PublishPress_Statuses::getStatusBy('id', $reassign_status)) {
                    $new_status = $_status->name;
                }
            }

            if (empty($new_status)) {
                $new_status = $default_status;
            }
            
            if ($old_status == $default_status) {
                $new_status = 'draft';

                // @todo: If we support a custom default status, set it to draft
            }

            self::reassign_post_status($old_status, $new_status);

            if (!$status_obj = \PublishPress_Statuses::getStatusBy('name', $old_status)) {
                return false;
            }

            if (!empty($status_obj->private)) {
                $taxonomy = \PublishPress_Statuses::TAXONOMY_PRIVACY;
            } else {
                $taxonomy = \PublishPress_Statuses::TAXONOMY_PRE_PUBLISH;
            }

            if ($term = get_term_by('slug', $old_status, $taxonomy)) {
                return wp_delete_term($term->term_id, $taxonomy, $args);
            }

        } else {
            return new \WP_Error(
                'restricted',
                __('Restricted status ', 'publishpress-statuses') . '(' . \PublishPress_Statuses::getStatusBy(
                    'id',
                    $old_status
                )->label . ')'
            );
        }
    }

    public static function handleAjaxDeleteStatus() {
        check_ajax_referer('custom-status-sortable');

        if ($status_name = \PublishPress_Functions::REQUEST_key('delete_status')) {
            if (!current_user_can('manage_options') && !current_user_can('pp_manage_statuses')) {
                self::printAjaxResponse('error', esc_html__('You are not permitted to do that.', 'publishpress-statuses'));
            }

            if ($status = \PublishPress_Statuses::getStatusBy('slug', $status_name)) {
                if (!empty($status->_builtin) || !empty($status->pp_builtin)) {
                    self::printAjaxResponse('error', esc_html__('You are not permitted to do that.', 'publishpress-statuses'));
                    return;
                }
                
                $return = self::deleteCustomStatus($status_name);

                if (is_wp_error($return)) {
                    self::printAjaxResponse('error', __('Could not delete the status: ', 'publishpress-statuses'));
                } else {
                    self::printAjaxResponse('success', __('Status deleted', 'publishpress-statuses'));
                }
            } else {
                self::printAjaxResponse('error', esc_html__('Status does not exist.', 'publishpress-statuses'));
            }
        }
    }

    /**
     * Handle an ajax request to update the order of custom statuses
     *
     * @since 0.7
     */
    public static function handleAjaxUpdateStatusPositions()
    {
        check_ajax_referer('custom-status-sortable');

        if (!current_user_can('manage_options') && !current_user_can('pp_manage_statuses')) {
            self::printAjaxResponse('error', esc_html__('You are not permitted to do that.', 'publishpress-statuses'));
        }

        if (!isset($_POST['status_positions']) || !is_array($_POST['status_positions'])) {
            self::printAjaxResponse('error', __('Status positions were not sent.', 'publishpress-statuses'));
        }

        update_option('publishpress_status_positions', array_map('sanitize_key', array_values(array_filter($_POST['status_positions']))));

        // @todo: update 'publishpress_disabled_statuses' based on ordering relative to '_disabled'

        if (!empty($_REQUEST['status_hierarchy'])) {
            $status_parents = [];

            foreach ($_REQUEST['status_hierarchy'] as $arr) {
                $status_name = str_replace('status_row_', '', sanitize_key($arr['id']));
                
                $status_parents[$status_name] = '';
                
                if (!empty($arr['children']) && !empty($status_name)) {
                    foreach ($arr['children'] as $child_arr) {
                        $child_status_name = str_replace('status_row_', '', sanitize_key($child_arr['id']));

                        $status_obj = get_post_status_object($child_status_name);
                        if (!empty($status_obj) && !empty($status_obj->private)) {
                            continue;
                        }

                        $status_parents[$child_status_name] = $status_name;
                    }
                }
            }

            $statuses = \PublishPress_Statuses::getPostStati(
                [], 
                ['output' => 'object', 'context' => 'load'], 
                ['show_disabled' => true]
            );

            // Update any modified status_parent value as an term meta value
            foreach ($status_parents as $status_name => $edited_status_parent) {
                $current_status_parent = (!empty($statuses[$status_name]) && !empty($statuses[$status_name]->status_parent)) 
                ? $statuses[$status_name] : '';

                if ($edited_status_parent == $current_status_parent) {
                    continue; // no change
                }

                $result = self::updateCustomStatus($status_name, ['status_parent' => $edited_status_parent]);

                if (is_wp_error($result)) {
                    self::printAjaxResponse('error', $result->get_error_message());
                }
            }
        }
        
        self::printAjaxResponse('success', esc_html__('Status order updated', 'publishpress-statuses'));
    }

    /**
     * Take a status and a message, JSON encode and print
     *
     * @param string $status Whether it was a 'success' or an 'error'
     * @param string $message
     * @param array $data
     *
     * @since 0.7
     *
     */
    public static function printAjaxResponse($status, $message = '', $data = null)
    {
        \PublishPress_Functions::printAjaxResponse($status, $message, $data);
    }

    /**
     * Determines whether the slug indicated belongs to a restricted status or not
     *
     * @param string $slug Slug of the status
     *
     * @return bool $restricted True if restricted, false if not
     */
    private static function is_restricted_status($slug)
    {
        switch ($slug) {
            case 'publish':
            case 'private':
            case 'future':
            case 'pending':
            case 'draft':
            case 'new':
            case 'inherit':
            case 'auto-draft':
            case 'trash':
                $restricted = true;
                break;

            default:
                $restricted = false;
                break;
        }

        return $restricted;
    }


    /**
     * Validate input from the end user
     *
     * @since 0.7
     */
    public static function settings_validate($new_options)
    {
        // Whitelist validation for the post type options
        if (! isset($new_options['post_types'])) {
            $new_options['post_types'] = [];
        }

        $new_options['post_types'] = self::clean_post_type_options(
            $new_options['post_types'],
            'pp_custom_statuses'
        );

        return $new_options;
    }

    /**
     * Sanitize submitted post types and apply add_post_type_support() usage.
     * 
     * If add_post_type_support() has been used anywhere (legacy support), inherit the state
     *
     * @param array $module_post_types Current state of post type options for the module
     * @param string $post_type_support_slug What the feature is called for post_type_support (e.g. 'pp_calendar')
     *
     * @return array $normalized_post_type_options The setting for each post type, normalized based on rules
     *
     */
    public static function clean_post_type_options($module_post_types = [], $post_type_support_slug = null)
    {
        $normalized_post_type_options = [];

        foreach (array_keys(\PublishPress_Statuses::instance()->get_supported_post_types()) as $post_type) {
            $normalized_post_type_options[$post_type] = !empty($module_post_types[$post_type]) || post_type_supports($post_type, $post_type_support_slug);
        }

        return $normalized_post_type_options;
    }

    public static function settings_validate_and_save()
    {
        if (!isset($_POST['action'], $_POST['_wpnonce'], $_POST['option_page'], $_POST['_wp_http_referer'], $_POST['publishpress_module_name'], $_POST['submit']) || !is_admin()) {
            return false;
        }
        
        if (($_POST['action'] != 'update') || 
        (!in_array('publishpress_statuses', (array)$_POST['publishpress_module_name']))
        ) {
            return false;
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'edit-publishpress-settings')) {
            wp_die(esc_html__('Cheatin&#8217; uh?'));
        }

        $module = \PublishPress_Statuses::instance();
        $module_name = 'custom_status';

        $new_options = (isset($_POST[\PublishPress_Statuses::SETTINGS_SLUG])) ? $_POST[\PublishPress_Statuses::SETTINGS_SLUG] : [];
        $new_options = self::settings_validate($new_options);

        // New way to validate settings
        $new_options = apply_filters('publishpress_validate_module_settings', $new_options, $module_name);

        // Cast our object and save the data.
        $new_options = (object)array_merge((array)$module->options, $new_options);

        update_option('publishpress_' . $module_name . '_options', $new_options);
        
        // Redirect back to the settings page that was submitted without any previous messages
        $goback = add_query_arg('message', 'settings-updated', remove_query_arg(['message'], wp_get_referer()));
        wp_safe_redirect($goback);

        exit;
    }

    /**
     * Assign new statuses to posts using value provided or the default
     *
     * @param string $old_status Slug for the old status
     * @param string $new_status Slug for the new status
     */
    private static function reassign_post_status($old_status, $new_status = '')
    {
        global $wpdb;

        if (empty($new_status)) {
            $new_status = \PublishPress_Statuses::DEFAULT_STATUS;
        }

        $result = $wpdb->update(
            $wpdb->posts,
            ['post_status' => $new_status],
            ['post_status' => $old_status],
            ['%s']
        );
    }
}
