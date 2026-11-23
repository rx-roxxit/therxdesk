---
title: Ideas, templates, feeds, repurpose and integrations
routes: [app.create.ideas.index, app.create.templates.index, app.create.feeds.index, app.repurposes.index, app.api-keys.index, app.webhooks.index, app.mcp.index]
---

# Ideas, templates, feeds, repurpose and integrations

## Ideas

The ideas board keeps post ideas before they are written. Put a card in To Do, In Progress or Done, write notes,
and turn a card into a post when it is ready. Idea generation fills the board from a prompt.

## Templates

A template saves a post's shape: text, media and channel, without a date. Create a post from a template whenever you
need it again. The library offers ready-made starting points.

## Feeds

A feed watches an RSS address and lists new items. Turn an item into a post, or import images from it. Feed polling
runs in the background.

## Repurpose

Repurpose takes a long-form source and rewrites it for the channels you pick. It runs on a schedule, and each result
can be edited before it is scheduled.

## API keys, webhooks and AI apps

- **API keys** let the app's REST API create and read posts from another system. A key is shown once; store it
  safely. Regenerate it if it leaks.
- **Webhooks** tell another system when posts publish or fail. Each delivery and its result are logged, and a failed
  delivery can be replayed.
- **AI apps** connect an outside assistant to the workspace through MCP, so it can create posts in the app. The MCP
  page holds the connection details.

These integrations act inside the workspace and follow the same permissions as the person who set them up. They do
not see Billing.
