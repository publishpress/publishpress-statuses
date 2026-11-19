# Statuses MCP verification

Run `php tests/Mcp/smoke.php /absolute/path/to/wp-load.php /absolute/path/to/mcp-adapter` only in a disposable database named `statuses_mcp_*`. Set `STATUSES_MCP_MODE=free` or `pro`. The Adapter path must contain the released package's `vendor/autoload.php`.

Install actual native plugin sources and WordPress, initialize a synthetic Administrator, and disable outgoing mail and HTTP. Pro must load the current shared Free source through its ordinary bundled dependency. Never edit the repository's vendor files to assemble a test fixture.

The suite exercises Adapter `McpTool::fromAbility()` and execution, native setting round trips, strict malformed-input rejection, status properties/captions, role permissions, native REST transitions, deletion/reassignment, optimistic revisions, partial bulk results, edition gates and private registration. Optional `STATUSES_MCP_CAPABILITIES_FILE` points to Capabilities' native application-password restriction component; this tests that component, not the entire Capabilities distribution.

Source-assembled Pro fixtures do not establish release ZIP correctness. External client HTTP sessions, live notification delivery and every third-party plugin combination require separate verification. Missing-API bootstrap can be checked in a separate PHP process with a stub `add_action`; no abilities should register.

Set `STATUSES_MCP_LARGE_SCOPE=1` to exercise revision coverage across more than 1,000 native status terms. This adds disposable records and is intended for a dedicated isolated run.

All successful Adapter results are checked against their registered output envelopes. Security regressions cover failure-path diagnostic redaction, no grants after failed creation, hidden native label data in previews, and fail-closed revision serialization for legacy option objects.
