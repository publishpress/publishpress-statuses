---
title: "Technical Details for PublishPress Statuses"
order: 16
doc_category: publishpress-statuses
---

# Technical Details for PublishPress Statuses

Table of Contents

- [How Statuses are Created](#0-toc-title)
- [How Statuses are Stored](#1-toc-title)

This guide is a brief introduction to how the [PublishPress Statuses](https://publishpress.com/links/statuses) plugin stores data. [PublishPress Statuses](https://publishpress.com/links/statuses) uses a combination of the WordPress core and taxonomies.

## How Statuses are Created [#](#0-toc-title)

The plugin uses five statuses that provided by the WordPress core. By default, these statuses only exist in the WordPress codebase and the PublishPress_Statuses.php file.

- [draft](https://publishpress.com/knowledge-base/draft/) (Draft)
- [pending](https://publishpress.com/knowledge-base/pending-review/) (Pending Review)
- [future](https://publishpress.com/knowledge-base/future/) (Scheduled)
- [publish](https://publishpress.com/knowledge-base/publish/) (Published)
- [private](https://publishpress.com/knowledge-base/private) (Privately Published)

If you use PublishPress Statuses to edit a status, then they will be added to the _terms table as taxonomy terms. If you’re not familiar with how taxonomy works in WordPress, [read this guide to how taxonomy data is stored](https://taxopress.com/taxonomy-terms-data-wordpress-database/).

In this example, I will add a new icon to the “Draft” statuses, using the settings in PublishPress Statuses:

![Settings area for Statuses](https://docs-media.publishpress.com/publishpress-statuses/icon-draft-338ad7dc7a.webp)

After that change has been saved, you will see “Draft” as an entry in the _terms table, as in this screenshot below:

![_terms table for Statuses](https://docs-media.publishpress.com/publishpress-statuses/status-terms-20dca21d72.webp)

## How Statuses are Stored [#](#1-toc-title)

The PublishPress Statuses plugin uses up to three different taxonomies to store the custom statuses.

- **post_status** is the taxonomy for [pre-publication statuses](https://publishpress.com/knowledge-base/main-workflow/).
- **post_visibility_pp** is the taxonomy for [visibility statuses](https://publishpress.com/knowledge-base/custom-visibility-statuses/).
- **post_status_core_wp_pp** is the taxonomy for core WordPress statuses.

In general, terms are created in those taxonomies only under these two conditions:

1. A new status is defined by the user.
2. A status has one or more of its properties modified.

The statuses which the plugin makes available by default are fundamentally defined by the PublishPress_Statuses.php file, not by an entry in the _options database table. Status ordering does use option table storage, if the order is modified or a new status added.

The data such as icon and color for each status is saved in the _termmeta table:

![_termmeta table for Statuses](https://docs-media.publishpress.com/publishpress-statuses/termmeta-table-ddd8f17665.webp)
