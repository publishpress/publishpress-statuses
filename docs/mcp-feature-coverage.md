# Statuses MCP feature coverage

| Native area | MCP operations | Authority and boundaries |
| --- | --- | --- |
| Feature inventory | List features, edition/dependency availability and related actions | Read/write capabilities and native-only boundaries. |
| General/editor settings | Discover and update settings | Native normalizer, strict choices, manage_options; omitted fields preserved. |
| Post types and visibility defaults | Keyed setting patches | Supported native post types and private statuses, separate lock values. |
| Moderation statuses | List/read/create/update/delete | Native term services, status management, term/meta permissions and revision checks. |
| Labels, captions, colors, icons | Status property patches | Native label locks and sanitization; unrelated label keys preserved. |
| Main/alternate/disabled workflows | Complete order; parent changes | Native order persistence; marker, duplicate and hierarchy validation. |
| Assignment roles | Read and patch role flags | Editable roles, manage_options and native status-management authority. |
| Pro visibility | Status creation/properties and settings | Active Pro taxonomy and native capability/edition controls. |
| Pro status content permissions | Discover flags; patch capability mode and role capabilities | Exact native capability map; no arbitrary/admin-level grants. |
| Pro revision status definitions | Shared status operations when native taxonomy exists | Compatible Revisions dependency; no direct revision lifecycle bypass. |
| Post workflow | Read current/next/available; set status; bulk set | Native REST, object/status/publication permissions, configuration and post locks. |
| Planner/Permissions integrations | Conditional native setting controls | Active native dependencies; their data remains owned by those integrations. |
| Per-post inherited/descendant visibility conditions | Native admin only | Separate native condition persistence and propagation. |
| Import, backup/restore and defaults/reset maintenance | Native admin only | Destructive maintenance is outside the exposed actions. |
| Notifications, licensing and UI rendering | Native/dependency integration | No recipient/credential export or duplicate UI engine. |
