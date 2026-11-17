---
title: "Post Access for Statuses (Pro)"
order: 8
doc_category: publishpress-statuses
---

# Post Access for Statuses (Pro)

Table of Contents

- [How to Use the Custom Capabilities](#h-how-to-use-the-custom-capabilities)
- [An Example with Custom Capabilities](#h-an-example-with-custom-capabilities)

This feature requires the Pro version of the PublishPress Permissions plugin.

The [PublishPress Statuses](https://publishpress.com/links/statuses) plugin integrates with the Pro version of the [PublishPress Permissions](https://publishpress.com/links/permissions) plugin. This allows you to manage capabilities for each status. You can decide who can assign, edit, and delete content in each status.

## How to Use the Custom Capabilities [#](#h-how-to-use-the-custom-capabilities)

To add manage these capabilities for a status, follow these steps:

- Make sure you have the [PublishPress Statuses Pro](https://publishpress.com/links/statuses) plugin installed.
- Edit a status and look for the “Post Access” tab.
- In the “Capability Requirements” tab, choose the “Custom Capabilities for this “Status” option.

![Assigned statuses](https://docs-media.publishpress.com/publishpress-statuses/custom-caps-status-4ae29ce85e.webp)

You will be able to select specific capabilities for user roles and post types. Here are the different types of capability:

- **Set**: Move a post to this status.
- **Edit**: Edit a post in this status, if the user is the author.
- **Edit others**: Edit a post in this status, no matter who is the author.
- **Delete**: Delete a post in this status, if the user is the author.
- **Delete others**: Edit a post in this status, no matter who is the author.

![Post access for statuses](https://docs-media.publishpress.com/publishpress-statuses/post-status-capabilities-f70668b388.webp)

## An Example with Custom Capabilities [#](#h-an-example-with-custom-capabilities)

Let’s see an example of using custom capabilities for a status.

We’re going to give a user in the “Contributor” role access to posts in the “Assigned” status.

Normally, users in the “Contributor” role have very limited access to posts. In this screenshot, I’m using [the User Testing feature](https://publishpress.com/knowledge-base/user-testing/) to check on a Contributor ser. They can not access any posts in the WordPress admin area.

![Contributor access in WordPress admin area](https://docs-media.publishpress.com/publishpress-statuses/contributor-access-e85d5ec359.webp)

In this scenario, there are 10 posts on the site. 6 of these posts are in the “Assigned” status, and 4 are in the “Published” status. Those can be seen by anyone in the Administrator or Editor roles.

![Published and Assigned posts](https://docs-media.publishpress.com/publishpress-statuses/published-assigned-ee06c4f0d3.webp)

Inside the “Assigned” status and the “Post Access” tab, I will give the “Contributor” role full access to the “Assigned” status. In this screenshot below, you can see all the boxes are checked in the “Contributor” role.

![Contributor status access](https://docs-media.publishpress.com/publishpress-statuses/assigned-contributor-3217832cb6.webp)

Now when a user in the Contributor role logs in to the site, they will be able to see and edit all the posts in the “Assigned” status.

![Posts in the Assigned statuses](https://docs-media.publishpress.com/publishpress-statuses/contributor-post-access-8e3cb978d9.webp)

They will be able to create new posts and assign them either to the “Draft” or “Assigned” status, as in this next screenshot:

![Contributor creating new posts](https://docs-media.publishpress.com/publishpress-statuses/move-to-assigned-a4e9a38dd9.webp)
