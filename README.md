# Blog Bridge

Promote a Flarum discussion to a [Ghost](https://ghost.org) blog post in one click.

Adds a staff-only **Promote to blog** control to a discussion's menu. Clicking it renders the
opening post to HTML and creates (or, on re-promotion, updates) a Ghost post via the Ghost Admin
API — carrying the discussion's tags, a link back to the forum thread, and the first image as the
feature image.

## Features

- **One-click promotion** from the discussion controls, gated by a `discussion.promoteToBlog`
  permission (grantable to any moderator group; admins always have it).
- **Idempotent** — each promotion is keyed to an internal `#forum-{id}` tag in Ghost, so
  re-promoting the same thread updates its post instead of creating a duplicate.
- **Tag mapping** — the discussion's (non-restricted) tags become Ghost tags.
- **Publish or draft** — choose whether promoting goes live immediately or lands as a draft.
- **Confirmation gate** — because a live promote publishes instantly, a confirm step always
  stands between the click and the public post.

## Setup

1. In Ghost, create a **Custom Integration** (Settings → Advanced → Integrations) and copy its
   Admin API key.
2. In Flarum admin, open **Blog Bridge** and set the Ghost site URL + Admin API key, and pick
   whether promotion publishes or drafts.
3. Grant **Promote discussions to the blog** to the staff groups that should have it.

## License

MIT
