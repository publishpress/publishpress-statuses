---
title: "Examples of Capabilities for Custom Statuses (Pro)"
order: 2
doc_category: publishpress-statuses
---

# Examples of Capabilities for Custom Statuses (Pro)

Table of Contents

- [Allow Users to Edit Posts in One Status](#0-toc-title)
- [Prevent Users from Moving Posts Back in a Workflow](#1-toc-title)

[PublishPress Statuses](https://publishpress.com/links/statuses) makes it possible to add permissions to custom statuses.

In this guide, we’ll walk you through some examples of how this works. These examples uses three plugins:

- [PublishPress Capabilities Pro](https://publishpress.com/links/capabilities)
- [PublishPress Permissions Pro](https://publishpress.com/links/permissions)
- [PublishPress Statuses](https://publishpress.com/statuses/)

## Allow Users to Edit Posts in One Status [#](#0-toc-title)

Let’s see an example of using custom capabilities for one user role and one status.

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

## Prevent Users from Moving Posts Back in a Workflow [#](#1-toc-title)

In this example, we’re going to prevent a user from moving a post backwards in a workflow. Our goal is this:

- Someone in the “Author” role is able submit a post as a “Pitch”.
- Once a pitch has been approved, a user in the “Editor” role will mark it as “Assigned”.
- The author should not be allowed to then change it back to a “Pitch”.

Here’s how to set up a situation like this on your website:

- Go to Capabilities > Settings > Capabilities.
- Enable the “Control selection of custom post statuses” option.
- Enable the “Apply status-specific capabilities for post editing” option.



![Control custom statuses](https://docs-media.publishpress.com/publishpress-statuses/control-custom-statuses-0edb09b7a0.webp)

- Go to Capabilities > Capabilities > Custom Statuses.

Please note: If the status you are using does not have checkboxes for “Edit” and “Edit Others” in its row, you will need to [enable “Custom Capabilities” for that statu](https://publishpress.com/knowledge-base/extended-capabilities-statuses/)s.



![Custom Status Capabilities](https://docs-media.publishpress.com/publishpress-statuses/custom-status-caps-6c81030e2f.webp)

- On the Capabilities screen, choose the role you need to adjust access for. In the screenshot below, we gave selected “Author”.
- Assign the “Set” capability, but not the “Edit” or “Edit Others” capabilities:



![Custom Status Capabilities for Author](https://docs-media.publishpress.com/publishpress-statuses/author-custom-caps-77d6b876f2.webp)
