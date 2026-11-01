# Statuses MCP integration

PublishPress Statuses registers private `publishpress-statuses/` abilities through the WordPress Abilities API. Select actions in PublishPress Hub and connect with the WordPress MCP Adapter. The plugin remains usable without either dependency.

## Discovery and settings

Start with `list-features`, `get-plugin-settings`, `list-statuses`, `get-status` and `get-status-roles`. Results include exact identifiers, field schemas, defaults, active edition, status sections, assignment flags and revision tokens. `get-post-workflow` reads an explicit editable post's current status, next status and available choices without returning its content.

`update-plugin-settings` accepts a partial `settings` object. Use JSON booleans for switches, declared strings for choices and keyed objects for post-type settings. For example, `post_types: {"post": false}` disables Posts and preserves Pages. Workflow Guidance accepts `"0"` (bypass), `"1"` (sequence) or `"none"`. An empty default-visibility string means Published. Default visibility and its lock are separate controls. Unknown fields, string booleans, numeric-string IDs, invalid choices, lists in place of objects and null values are rejected.

Settings require `manage_options`. Pro visibility controls and dependency-specific integrations appear only when their native dependencies are active. `PPS_CUSTOM_PRIVACY_EDIT_CAPS` locks that control outside MCP. Omitted options and map entries remain unchanged. Empty map and property patches do not save or materialize defaults.

## Statuses and workflow

`save-status` creates a status with `name` and `properties.label`, or patches an existing status. Native slugs and labels are limited to 20 characters; slugs cannot be renamed. Properties include description, color, icon, button labels, supported post types and status parent. `post_type: []` means all enabled types. Caption patches preserve unrelated labels. Native locked labels and protected workflow markers cannot be edited. `get-status` identifies protected captions in `locked_fields`. Empty caption overrides remain empty when another caption is patched, and backslashes are preserved.

`set-workflow-order` takes the complete `names` list returned by `list-statuses`, including every section marker. Keep markers and move statuses into the desired main, alternate or disabled section. Set `status_parent` through `save-status` to configure a branch; an empty string clears the parent. Cycles and a third nesting level are rejected.

`delete-status` deletes a user-created status and explicitly reassigns its posts through the native deletion service. Every affected object must be readable, editable, unlocked and eligible for the target status. Revision objects and unsupported sources block reassignment. Operations above 1,000 affected posts require native administration. Built-in statuses cannot be deleted. Definitions referenced by native visibility conditions or working revisions must be handled in their native workflows.

## Roles and Pro permissions

`set-status-roles` patches role flags controlling assignment of that status. It changes only the native `status_change_*` capability. Inherited permission modes expose no editable content-capability matrix; edit the owning status instead. Role and permission changes require `manage_options` and native status-management authority; editable-role filters apply.

Pro adds `set-capability-status` for Standard, Custom or Same as permission modes. An empty string selects Standard; the status's own name selects Custom; another compatible name selects Same as. `get-status-roles` returns available status-specific `capability_names` and role values. `set-status-capabilities` accepts only those capabilities. New grants require the connected account to hold the capability. Arbitrary and administrator-level capabilities cannot be granted. Native dependency and custom-visibility settings control which capabilities exist.

Active Pro and compatible PublishPress Revisions expose revision status definitions through native taxonomy discovery. Revision creation, approval and scheduling remain with the Revisions workflow; `set-post-status` does not bypass that lifecycle.

## Post status changes and previews

`set-post-status` changes one normal post using native WordPress REST dispatch, controller checks and Statuses filters. The post must use an enabled type with the standard REST controller. It checks read/edit permissions, status-specific capabilities, publication authority and the post lock. Native filters may select a different final status; inspect `complete` and read back.

`bulk-set-post-status` preflights up to 20 explicit posts and revisions before saving. Duplicate targets and mixed invalid batches are rejected before writes. Persistence is not transactional: `applied_ids` lists confirmed successes and `failed_id` identifies the first unconfirmed row.

Read the relevant `expected_hash` immediately before changing existing state. Use `get-status-roles` tokens for role and capability operations, `list-statuses` for ordering, and `get-post-workflow` for post changes. A conflict requires a fresh read. `dry_run: true` previews without writing. Only declared caption fields appear in previews; other native label metadata is preserved internally. Native persistence diagnostics are not returned to clients. Native save hooks can fail after part of an operation; an unconfirmed result requires read-back before retrying.

## Boundaries

Status management follows `pp_manage_statuses` or `manage_options`; settings and role delegation require `manage_options`. Object and metadata permissions apply independently. Administrator roles do not bypass denied capabilities, including application-password restrictions.

MCP does not expose license keys, account credentials, notification recipients or post content. Native import, backup/restore and reset maintenance remain in wp-admin. Per-post inherited and descendant visibility-condition administration also remains native; it is distinct from global visibility defaults and changing a post status. Planner notification workflows and Revisions lifecycle operations belong to their respective integrations. Display widgets, editor panels and translation rendering remain native UI features. MCP provides no arbitrary option writes, SQL, PHP execution or general role-capability editor.

Status deletion cannot reassign posts protected by forced visibility settings or per-post visibility conditions. Use native administration for those objects.
