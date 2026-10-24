<?php

/*
 * Blog Bridge — promote a Flarum discussion to a Ghost blog post.
 */

namespace ErnestDefoe\BlogBridge;

use Flarum\Api\Context;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Settings\SettingsRepositoryInterface;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->post('/discussions/{id}/promote-to-blog', 'blog-bridge.promote', Api\Controller\PromoteController::class),

    // Expose, per discussion, whether the current actor may promote it and where it already lives
    // on the blog — the forum control reads both to decide "Promote" vs "View on blog".
    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canPromoteToBlog')
                ->get(fn (Discussion $discussion, Context $context) => $context->getActor()->hasPermission('discussion.promoteToBlog')),
            Schema\Str::make('blogUrl')
                ->nullable()
                ->get(fn (Discussion $discussion) => $discussion->getAttribute('blog_url')),
        ]),

    // Tells those who can promote whether a promote creates a Ghost draft, so the confirmation
    // says what will actually happen. Read from the cached settings: no query.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('blogBridgeCreatesDraft')
                ->visible(fn ($forum, Context $context) => $context->getActor()->hasPermission('discussion.promoteToBlog'))
                ->get(fn () => resolve(SettingsRepositoryInterface::class)->get('blog-bridge.publish_status') === 'draft'),
        ]),

    // The 'discussion.promoteToBlog' permission row is registered in the admin frontend
    // (js/src/admin) so it shows in the permissions grid; admins bypass it, moderators can be
    // granted it there. The controller enforces it server-side.

    (new Extend\Settings())
        ->default('blog-bridge.publish_status', 'published'),
];
