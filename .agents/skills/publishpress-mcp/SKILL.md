---
name: publishpress-mcp
description: Implement or extend the PublishPress Statuses Free and Pro MCP abilities, native status workflows, settings and permission contracts.
---

# PublishPress Statuses MCP integration

Statuses owns its abilities; Hub owns transport and exposure. Begin with `Mcp/bootstrap.php`, `Mcp/Registry.php`, `Mcp/Settings.php`, `Mcp/Actions.php`, `StatusHandler.php` and `PublishPress_Statuses.php`. Pro loads this shared source through `lib/composer.json`; do not duplicate it or edit vendor copies.

Follow native namespaces, style and minimum PHP compatibility. Native options are an object; post-type controls are keyed maps. Workflow Guidance is a string choice, switches use integer/string native storage, and visibility defaults use an empty string for Published. `StatusHandler::sanitizeSetting()` is shared with the native form. Preserve omitted entries and validate current choices after discovery.

Use `addStatus()`, `updateCustomStatus()`, `deleteCustomStatus()`, `saveStatusPositions()` and `setRoleAssignment()`. Refresh native registrations after status writes. Slugs identify statuses; term IDs are not status names. Assignment capabilities derive from slugs, not labels. Label-storage rules protect built-in captions.

Status deletion uses a native SQL-wide reassignment; authorization must cover its exact affected rows, including otherwise hidden objects. Pro content capabilities come from the native condition maps, with shared post-type/admin capabilities excluded. Role grants require explicit capabilities rather than an Administrator shortcut. Use native REST dispatch for post transitions so visibility/workflow restrictions execute normally; restore scoped request context afterwards.

Keep API categories private and registration independent of the connected user's capabilities. Match native permissions at execution, including metadata permissions, application-password restrictions, editable roles, active editions, dependencies and locks. Preflight every bulk row and report partial failures honestly.

Use `tests/Mcp/smoke.php` through a released Adapter with isolated native persistence. See `docs/mcp-feature-coverage.md` and `docs/mcp-prompt-examples.md` for supported areas and usage. Distinguish source-assembled Pro tests from release distributions and external HTTP sessions. Keep documentation about current usage; do not create audit reports or modify vendor dependencies.
