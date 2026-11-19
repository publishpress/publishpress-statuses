<?php
namespace PublishPress_Statuses;

\defined('ABSPATH') || exit;

class REST
{
    var $route = '';
    var $endpoint_class = '';
    var $post_type = '';
    var $post_id = 0;
    var $post_status = '';
    var $is_posts_request = false;
    var $params = [];
    var $referer = '';

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new REST();
            \PublishPress_Statuses::instance()->doing_rest = true;
        }
        return self::$instance;
    }

    public static function getPostType()
    {
        return self::instance()->post_type;
    }

    public static function getPostID()
    {
        return self::instance()->post_id;
    }

    // Authorization context comes only from the matched controller and URL captures.
    private $target_type = '';
    private $target_id = 0;
    private $active_request = null;

    function pre_dispatch($rest_response, $rest_server, $request)
    {
        add_filter('rest_request_before_callbacks', [$this, 'bindRequest'], 10, 3);
        add_filter('rest_request_after_callbacks', [$this, 'clearRequest'], 10, 3);
        add_filter('presspermit_rest_post_type', [$this, 'fltRestPostType'], 5);
        add_filter('presspermit_rest_post_id', [$this, 'fltRestPostID'], 5);

        foreach (get_post_types([], 'names') as $post_type) {
            add_filter("rest_pre_insert_{$post_type}", [$this, 'validateSelection'], 10, 2);
        }

        return $rest_response;
    }

    function bindRequest($response, $handler, $request)
    {
        $this->clearRequest($response, $handler, $request);
        $controller = (is_array($handler) && isset($handler['callback']) && is_array($handler['callback']) && isset($handler['callback'][0])) ? $handler['callback'][0] : null;
        if (!is_object($controller)) {
            return $response;
        }

        foreach (get_post_types(['show_in_rest' => true], 'objects') as $type) {
            $autosaves = method_exists($type, 'get_autosave_rest_controller')
                ? $type->get_autosave_rest_controller() : null;
            if (($controller !== $type->get_rest_controller()) && ($controller !== $autosaves)) {
                continue;
            }

            $url = $request->get_url_params();
            $id = ($controller === $autosaves) ? ($url['parent'] ?? 0) : ($url['id'] ?? 0);
            $id = absint($id);
            if ($id && (get_post_type($id) !== $type->name)) {
                return $response;
            }

            $this->target_type = $this->post_type = $type->name;
            $this->target_id = $this->post_id = $id;
            $this->active_request = $request;
            $this->params = $request->get_params();
            $headers = $request->get_headers();
            $this->referer = $headers['referer'] ?? '';
            if (is_array($this->referer)) {
                $this->referer = reset($this->referer);
            }
            $this->is_posts_request = true;
            $this->endpoint_class = get_class($controller);
            $this->route = $request->get_route();
            break;
        }

        return $response;
    }

    function clearRequest($response, $handler, $request)
    {
        $this->target_type = $this->post_type = '';
        $this->target_id = $this->post_id = 0;
        $this->active_request = null;
        $this->params = [];
        $this->referer = '';
        $this->is_posts_request = false;
        return $response;
    }

    function validateSelection($prepared, $request)
    {
        if (is_wp_error($prepared) || ($request !== $this->active_request)
            || empty($this->params['pp_status_selection'])) {
            return $prepared;
        }

        $selection = $this->params['pp_status_selection'];
        $status = ('_pending' === $selection) ? 'pending' : $selection;
        if (!is_string($selection) || !$this->target_type
            || !get_post_status_object($status)
            || (!$this->canRetainStatus($status)
                && !apply_filters('publishpress_statuses_trusted_privacy_rule', false, $status, $this->target_type, $this->target_id, (array) $prepared)
                && !\PublishPress_Statuses::haveStatusPermission('set_status', $this->target_type, $status))) {
            return new \WP_Error('rest_cannot_set_status', __('Sorry, you are not allowed to set this status.', 'publishpress-statuses'), ['status' => 403]);
        }
        return $prepared;
    }

    // Retaining a stored status is a content edit, not a new assignment.
    private function canRetainStatus($status)
    {
        if (!$this->target_id || !current_user_can('edit_post', $this->target_id)
            || !$post = get_post($this->target_id)) {
            return false;
        }
        $stored = apply_filters('publishpress_statuses_post_status', $post->post_status, $post);
        return $status === $stored;
    }

    // Repeat authorization on the actual data being persisted, including creates.
    function authorizePostData($data, $postarr)
    {
        if (!$this->active_request || empty($this->params['pp_status_selection'])
            || empty($data['post_type']) || ($data['post_type'] !== $this->target_type)
            || (absint($postarr['ID'] ?? 0) !== $this->target_id)) {
            return $data;
        }

        $status = ('_pending' === $data['post_status']) ? 'pending' : $data['post_status'];
        if (!get_post_status_object($status)
            || (!\PublishPress_Statuses::haveStatusPermission('set_status', $data['post_type'], $status)
                && !apply_filters('publishpress_statuses_trusted_privacy_rule', false, $status, $data['post_type'], $this->target_id, $postarr))) {
            $data['post_status'] = $this->target_id
                ? get_post_field('post_status', $this->target_id) : 'draft';
        }
        return $data;
    }

    function fltRestPostType($post_type)
    {
        return ($this->target_type) ? $this->target_type : $post_type;
    }

    function fltRestPostID($post_id)
    {
        return $this->active_request ? $this->target_id : $post_id;
    }
}

