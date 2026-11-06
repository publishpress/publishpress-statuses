<?php

/**
 * Private Statuses abilities and strict, shared input contracts.
 *
 * @package PublishPress
 */

namespace PublishPress_Statuses\Mcp;

\defined('ABSPATH') || exit;
class Registry
{
    public static function object(array $properties = [], array $required = []): array
    {
        $schema = ['type' => 'object', 'required' => $required, 'additionalProperties' => false];
        if ($properties) {
            $schema['properties'] = $properties;
        }
        return $schema;
    }

    public static function id(): array
    {
        return ['type' => 'integer', 'minimum' => 1];
    }
    public static function text(int $max = 1000): array
    {
        return ['type' => 'string', 'maxLength' => $max];
    }
    public static function revision(): array
    {
        return ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
    }
    public static function listOf(array $items, int $max = 100): array
    {
        return ['type' => 'array', 'items' => $items, 'maxItems' => $max, 'uniqueItems' => true];
    }

    public static function error(string $message = '', int $status = 400): \WP_Error
    {
        return new \WP_Error('publishpress_statuses_mcp', $message ?: __('Use the exact fields, types and choices returned by discovery.', 'publishpress-statuses'), ['status' => $status]);
    }

    public static function denied(): \WP_Error
    {
        return self::error(__('You do not have permission to use this Statuses operation.', 'publishpress-statuses'), 403);
    }

    public static function hash($value): string
    {
        $encoded = wp_json_encode($value);
        if (!is_string($encoded)) {
            throw new \RuntimeException('The native revision state could not be encoded.');
        }
        return hash_hmac('sha256', $encoded, wp_salt('nonce'));
    }

    /** Project known native scalar representations into the declared JSON type. */
    public static function project($value, array $schema)
    {
        $types = (array) $schema['type'];
        if (in_array('boolean', $types, true)) {
            return !in_array($value, ['', '0', 0, false, null], true);
        }
        if (in_array('integer', $types, true) && (!in_array('string', $types, true) || $value !== '')) {
            return (int) $value;
        }
        if (in_array('string', $types, true)) {
            return (string) $value;
        }
        if (in_array('array', $types, true) && is_array($value)) {
            return array_values($value);
        }
        return $value;
    }

    public static function validate($value, array $schema, string $path = 'input')
    {
        $type = $schema['type'] ?? null;
        $types = (array) $type;
        $matches = false;
        foreach ($types as $candidate) {
            $matches = $matches || ($candidate === 'object' && (is_object($value) || (is_array($value) && (!$value || !(array_keys($value) === range(0, count($value) - 1) || $value === [])))))
            || ($candidate === 'array' && is_array($value) && (array_keys($value) === range(0, count($value) - 1) || $value === []))
            || ($candidate === 'string' && is_string($value))
            || ($candidate === 'integer' && is_int($value))
            || ($candidate === 'boolean' && is_bool($value))
            || ($candidate === 'null' && $value === null)
            || ($candidate === 'number' && (is_int($value) || is_float($value)));
        }
        if (!$matches) {
            return self::error(sprintf(__('Invalid JSON type at %s.', 'publishpress-statuses'), $path));
        }
        if (in_array('object', $types, true) && (is_array($value) || is_object($value))) {
            $value = (array) $value;
            foreach ($schema['required'] ?? [] as $key) {
                if (!array_key_exists($key, $value)) {
                    return self::error();
                }
            }
            if (($schema['additionalProperties'] ?? true) === false && array_diff(array_keys($value), array_keys($schema['properties'] ?? []))) {
                return self::error();
            }
            foreach ($value as $key => &$child) {
                if (isset($schema['properties'][$key]) || is_array($schema['additionalProperties'] ?? null)) {
                    $child = self::validate($child, $schema['properties'][$key] ?? $schema['additionalProperties'], $path . '.' . $key);
                    if (is_wp_error($child)) {
                        return $child;
                    }
                }
            }
            unset($child);
        } elseif (in_array('array', $types, true) && is_array($value)) {
            foreach ($value as $key => &$child) {
                $child = self::validate($child, $schema['items'], $path . '[' . $key . ']');
                if (is_wp_error($child)) {
                    return $child;
                }
            }
            unset($child);
        }
        $check = rest_validate_value_from_schema($value, $schema, $path);
        return is_wp_error($check) ? $check : $value;
    }

    public static function add(string $name, string $label, string $description, array $schema, callable $callback, callable $permission, bool $write = false, bool $destructive = false, array $output = []): void
    {
        wp_register_ability('publishpress-statuses/' . $name, [
            'label' => $label, 'description' => $description, 'category' => 'publishpress',
            'input_schema' => $schema, 'output_schema' => $output ?: self::object(['items' => ['type' => 'array', 'items' => ['type' => 'object']], 'has_more' => ['type' => 'boolean']]),
            'permission_callback' => static function ($input) use ($permission, $schema) {

                $input = self::validate($input, $schema);
                wp_get_current_user()->get_role_caps();
                return is_wp_error($input) ? $input : ($permission($input) ? true : self::denied());
            },
            'execute_callback' => static function ($input) use ($callback, $permission, $schema) {

                $input = self::validate($input, $schema);
                if (is_wp_error($input)) {
                    return $input;
                }
                wp_get_current_user()->get_role_caps();
                if (!$permission($input)) {
                    return self::denied();
                }
                return self::executeNative($callback, $input);
            },
            'meta' => ['public' => false, 'show_in_rest' => false, 'mcp' => ['public' => false],
                'annotations' => ['readonly' => !$write, 'destructive' => $destructive, 'idempotent' => !$write]],
        ]);
    }

    private static function executeNative(callable $callback, array $input)
    {
        $saved = [];
        foreach (['post', 'id', 'authordata', 'currentday', 'currentmonth', 'pages', 'page', 'multipage', 'more', 'numpages', '_GET', '_POST', '_REQUEST'] as $key) {
            $saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        // Legacy save hooks must not treat this validated tool call as a browser form.
        // Authentication and the connected WordPress identity remain unchanged.
        foreach (['_GET', '_POST', '_REQUEST'] as $key) {
            $GLOBALS[$key] = [];
        }
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Scoped native post context is restored in finally.
        $GLOBALS['post'] = !empty($input['id']) ? get_post($input['id']) : null;
        try {
            return $callback($input);
        } catch (\Throwable $error) {
            return self::error(__('Statuses could not complete this operation. Inspect the current state before retrying; some changes may have been saved.', 'publishpress-statuses'), 500);
        } finally {
            foreach ($saved as $key => $value) {
                if ($value[0]) {
                    $GLOBALS[$key] = $value[1];
                } else {
                    unset($GLOBALS[$key]);
                }
            }
        }
    }

    /** Native edit capability and edit-lock policy for content changes. */
    public static function editable(int $id): bool
    {
        if (!current_user_can('read_post', $id) || !current_user_can('edit_post', $id)) {
            return false;
        }
        if (!function_exists('wp_check_post_lock')) {
            require_once ABSPATH . 'wp-admin/includes/post.php';
        }
        return !wp_check_post_lock($id);
    }

    public static function pro(): bool
    {
        return defined('PUBLISHPRESS_STATUSES_PRO_VERSION') && class_exists('PublishPress\\StatusCapabilities');
    }

    public static function stale(array $input, $state)
    {
        return isset($input['expected_hash']) && hash_equals(self::hash($state), $input['expected_hash'])
        ? true : self::error(__('This object changed. Read it again before saving.', 'publishpress-statuses'), 409);
    }

    public static function unconfirmed(): \WP_Error
    {
        return self::error(__('The operation could not be fully confirmed. Read the object before retrying; some changes may have been saved.', 'publishpress-statuses'), 500);
    }
}
