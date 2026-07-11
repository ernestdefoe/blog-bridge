<?php

/*
 * Blog Bridge — promote a Flarum discussion to a Ghost blog post.
 */

namespace ErnestDefoe\BlogBridge;

use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Discussion\Discussion;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Routes('api'))
        ->post('/discussions/{id}/promote-to-blog', 'blog-bridge.promote', Api\Controller\PromoteController::class),

    // Expose, per discussion, whether the current actor may promote it and where it already lives
    // on the blog — the forum control reads both to decide "Promote" vs "View on blog".
    (new Extend\ApiSerializer(DiscussionSerializer::class))
        ->attributes(function (DiscussionSerializer $serializer, Discussion $discussion, array $attributes): array {
            $attributes['canPromoteToBlog'] = $serializer->getActor()->hasPermission('discussion.promoteToBlog');
            $attributes['blogUrl'] = $discussion->blog_url;

            return $attributes;
        }),

    // The 'discussion.promoteToBlog' permission row is registered in the admin frontend
    // (js/src/admin) so it shows in the permissions grid; admins bypass it, moderators can be
    // granted it there. The controller enforces it server-side.

    (new Extend\Settings())
        ->default('blog-bridge.publish_status', 'published'),
];
