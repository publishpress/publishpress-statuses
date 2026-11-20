# Statuses MCP prompts

Use a test site and ask the client to discover identifiers, preview, save and verify native read-back.

- Show my editorial statuses, their main or alternate workflow sections, and disabled statuses.
- Create an orange status called Legal Review for Posts only, previewing it first.
- Change Legal Review's Save As button caption without changing its color or post types.
- Put Legal Review before Pending Review in the main workflow, keeping all other statuses and section markers.
- Make Legal Review a child of In Progress; show me the resulting branch.
- Disable Statuses on Pages while preserving the Posts setting.
- Use sequence guidance and hide sub-statuses from unrelated workflow branches.
- Allow Editors to assign Legal Review, preserving every other role and capability.
- Show the next available workflow status for post 123, then preview changing it to Legal Review.
- Preview changing posts 123 and 456 to Pending Review; stop if either permission check fails.
- With Pro active, create a private visibility status and use it as the default for Posts without enabling its lock.
- With Pro active, show the status-specific content permissions available for Legal Review before proposing a role patch.
- With compatible Revisions active, show the revision status taxonomy and available definitions.
- Preview deleting Legal Review and reassigning all its posts to Draft.

Permission checks: repeat with a subscriber, an Administrator denied the action capability, an account denied edit access to one bulk target, and a restricted application password. Each prohibited request should fail without changing the affected settings or object. Ask for an invalid choice, a string boolean, an unknown key and a stale revision; these should be rejected rather than coerced.
