<?php

namespace ErnestDefoe\BlogBridge\Api\Controller;

use ErnestDefoe\BlogBridge\Ghost\GhostClient;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\Translator;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PromoteController implements RequestHandlerInterface
{
    public function __construct(
        protected GhostClient $ghost,
        protected UrlGenerator $url,
        protected SettingsRepositoryInterface $settings,
        protected Translator $translator,
        protected ExtensionManager $extensions,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        if (! $actor->hasPermission('discussion.promoteToBlog')) {
            return $this->error(403, $this->translator->trans('blog-bridge.api.forbidden'));
        }

        if (! $this->ghost->enabled()) {
            return $this->error(409, $this->translator->trans('blog-bridge.api.not_configured'));
        }

        /** @var Discussion $discussion */
        $discussion = Discussion::whereVisibleTo($actor)->with(['firstPost', 'user'])->findOrFail(Arr::get($request->getQueryParams(), 'id'));
        $post = $discussion->firstPost;

        if (! $post instanceof CommentPost) {
            return $this->error(422, $this->translator->trans('blog-bridge.api.no_content'));
        }

        // The blog is public: only a discussion (and opening post) a guest can already read
        // may go there. Private, restricted-tag, hidden or unapproved content stays on the forum.
        $guest = new Guest();
        if (! Discussion::whereVisibleTo($guest)->whereKey($discussion->id)->exists()
            || ! Post::whereVisibleTo($guest)->whereKey($post->id)->exists()) {
            return $this->error(403, $this->translator->trans('blog-bridge.api.not_public'));
        }

        $forumUrl = $this->url->to('forum')->route('discussion', ['id' => $discussion->id]);
        $authorName = $discussion->user->display_name ?? $this->translator->trans('blog-bridge.api.a_member');

        $html = $post->formatContent($request);
        $html .= sprintf(
            '<hr><p><em>%s</em></p>',
            $this->translator->trans('blog-bridge.api.source_line', [
                'author' => e($authorName),
                'link' => sprintf('<a href="%s">%s</a>', e($forumUrl), e($this->settings->get('forum_title') ?: 'the forum')),
            ])
        );

        $payload = [
            'title' => $discussion->title,
            'html' => $html,
            'status' => (string) $this->settings->get('blog-bridge.publish_status', 'published'),
            'tags' => $this->tags($discussion),
        ];

        if ($feature = $this->firstImage($html)) {
            $payload['feature_image'] = $feature;
        }

        try {
            $result = $this->ghost->upsert($payload, (int) $discussion->id);
        } catch (RequestException $e) {
            return $this->error(502, GhostClient::errorMessage($e));
        }

        // This extension's own columns on discussions, not declared properties.
        $discussion->setAttribute('blog_post_id', $result['id']);
        $discussion->setAttribute('blog_url', $result['url']);
        $discussion->save();

        return new JsonResponse(['url' => $result['url']]);
    }

    /**
     * The discussion's visible tag names plus an internal `#forum-{id}` tag that keys the post
     * for idempotent re-promotion. Internal tags (leading `#`) never show on the blog.
     *
     * @return array<int, array{name: string}>
     */
    protected function tags(Discussion $discussion): array
    {
        $tags = [['name' => '#forum-' . $discussion->id]];

        // Not method_exists($discussion, 'tags'): flarum/tags adds that relation
        // at runtime, so the method never exists and no tag was ever sent.
        if ($this->extensions->isEnabled('flarum-tags')) {
            foreach ($discussion->getAttribute('tags') as $tag) {
                if (! $tag->is_restricted) {
                    $tags[] = ['name' => $tag->name];
                }
            }
        }

        return $tags;
    }

    protected function firstImage(string $html): ?string
    {
        return preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m) ? $m[1] : null;
    }

    protected function error(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['errors' => [['status' => (string) $status, 'detail' => $message]]], $status);
    }
}
