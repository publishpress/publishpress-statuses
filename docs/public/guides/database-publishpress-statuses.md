---
title: "Database Details for PublishPress Statuses"
order: 6
doc_category: publishpress-statuses
---

# Database Details for PublishPress Statuses

The [PublishPress Statuses](https://publishpress.com/links/statuses) plugin uses up to three different taxonomies to store the custom statuses.

- **post_status** is the taxonomy for pre-publication statuses.
- **post_visibility_pp** is the taxonomy for privacy statuses.
- **post_status_core_wp_pp** is the taxonomy for core WordPress statuses.

In general, terms are created in those taxonomies only under these conditions:

- A new status is defined by the user.
- A status has one or more of its properties modified.

The statuses which the plugin makes available by default are fundamentally defined by the plugin code itself, not by an entry in the _options database table. Status ordering does use option table storage, if the order is modified or a new status added.
