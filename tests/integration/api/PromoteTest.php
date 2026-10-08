<?php

namespace ErnestDefoe\BlogBridge\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\BlogBridge\Ghost\GhostClient;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PromoteTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    /** @var array<int, array{method: string, path: string, body: array|null}> what was sent to "Ghost" */
    private array $sent = [];

    /** The Ghost post id for discussion 1, once it has been created there. */
    private ?string $onBlog = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-blog-bridge');

        $this->setting('blog-bridge.ghost_url', 'https://blog.example.com');
        // Not a real key: the id and a hex secret, so a token can be signed.
        $this->setting('blog-bridge.admin_key', 'test-key-id:'.str_repeat('ab', 32));

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'moderator', 'email' => 'mod@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [['user_id' => 3, 'group_id' => Group::MODERATOR_ID]],
            'group_permission' => [['permission' => 'discussion.promoteToBlog', 'group_id' => Group::MODERATOR_ID]],
            Tag::class => [
                ['id' => 1, 'name' => 'News', 'slug' => 'news', 'position' => 0, 'is_restricted' => 0],
                ['id' => 2, 'name' => 'Staff room', 'slug' => 'staff', 'position' => 1, 'is_restricted' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Big news', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Staff only', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 2],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>We <b>shipped</b> it. <IMG src="https://img.example.com/a.png">pic</IMG></p></t>'],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Secret</p></t>'],
            ],
        ]);
    }

    /** Answer Ghost's admin API locally, recording what was sent. */
    private function ghost(): void
    {
        $handler = HandlerStack::create(function (RequestInterface $request) {
            $body = json_decode((string) $request->getBody(), true);
            $this->sent[] = ['method' => $request->getMethod(), 'path' => $request->getUri()->getPath(), 'body' => $body];

            if ($request->getMethod() === 'GET') {
                $posts = $this->onBlog ? [['id' => $this->onBlog, 'updated_at' => '2026-01-01T00:00:00.000Z', 'url' => 'https://blog.example.com/big-news/']] : [];

                return Create::promiseFor(new Response(200, [], json_encode(['posts' => $posts])));
            }

            $this->onBlog ??= 'ghost-post-1';

            return Create::promiseFor(new Response(200, [], json_encode(['posts' => [['id' => $this->onBlog, 'url' => 'https://blog.example.com/big-news/']]])));
        });

        $container = $this->app()->getContainer();
        $container->instance(GhostClient::class, new class($container->make(SettingsRepositoryInterface::class), new Client(['handler' => $handler, 'base_uri' => 'https://blog.example.com/ghost/api/admin/'])) extends GhostClient {
            public function __construct($settings, private Client $mock)
            {
                parent::__construct($settings);
            }

            protected function client(): Client
            {
                return $this->mock;
            }
        });
    }

    private function promote(?int $actor, int $discussion): ResponseInterface
    {
        $request = $this->request('POST', "/api/discussions/$discussion/promote-to-blog", array_filter(['authenticatedAs' => $actor, 'json' => []]));

        // A guest has no session to carry a CSRF token; skip that check so the
        // controller's own answer is what is tested.
        return $this->send($request->withAttribute('bypassCsrfToken', true));
    }

    #[Test]
    public function only_those_granted_the_permission_promote()
    {
        $this->ghost();

        $this->assertSame(401, $this->promote(null, 1)->getStatusCode());
        $this->assertSame(403, $this->promote(2, 1)->getStatusCode(), 'A member, even the author');
        $this->assertSame([], $this->sent);

        $this->assertSame(200, $this->promote(3, 1)->getStatusCode());
        $this->assertSame(200, $this->promote(1, 1)->getStatusCode(), 'An admin');
    }

    #[Test]
    public function only_what_a_guest_can_read_goes_to_the_public_blog()
    {
        $this->ghost();

        $this->assertSame(403, $this->promote(1, 2)->getStatusCode(), 'A discussion in a restricted tag');
        $this->assertSame([], $this->sent);
    }

    #[Test]
    public function nothing_is_sent_until_ghost_is_configured()
    {
        $this->setting('blog-bridge.admin_key', '');
        $this->ghost();

        $this->assertSame(409, $this->promote(1, 1)->getStatusCode());
        $this->assertSame([], $this->sent);
    }

    #[Test]
    public function the_post_carries_the_content_its_source_and_its_public_tags()
    {
        $this->ghost();

        $response = $this->promote(3, 1);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $post = end($this->sent)['body']['posts'][0];
        $this->assertSame('Big news', $post['title']);
        $this->assertStringContainsString('<b>shipped</b>', $post['html']);
        // The source line ("Originally posted by … on …") is a translation, and
        // flarum/testing enables an extension before applying its extenders, so
        // its locale is never loaded in these tests; only its place is checked.
        $this->assertStringContainsString('<hr><p><em>', $post['html']);
        $this->assertSame('https://img.example.com/a.png', $post['feature_image']);
        $this->assertSame(['#forum-1', 'News'], array_column($post['tags'], 'name'));
    }

    #[Test]
    public function promoting_again_updates_the_same_blog_post()
    {
        $this->ghost();

        $this->promote(3, 1);
        $this->promote(3, 1);

        $writes = array_values(array_filter($this->sent, fn ($r) => $r['method'] !== 'GET'));
        $this->assertSame(['POST', 'PUT'], array_column($writes, 'method'));
        $this->assertStringEndsWith('/posts/ghost-post-1/', $writes[1]['path']);
        $this->assertSame('2026-01-01T00:00:00.000Z', $writes[1]['body']['posts'][0]['updated_at'], 'Ghost\'s edit lock');
    }

    #[Test]
    public function the_discussion_says_where_it_lives_on_the_blog()
    {
        $this->ghost();

        $before = json_decode((string) $this->send($this->request('GET', '/api/discussions/1', ['authenticatedAs' => 3]))->getBody(), true)['data']['attributes'];
        $this->assertTrue($before['canPromoteToBlog']);
        $this->assertNull($before['blogUrl']);

        $this->promote(3, 1);

        $after = json_decode((string) $this->send($this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2]))->getBody(), true)['data']['attributes'];
        $this->assertFalse($after['canPromoteToBlog']);
        $this->assertSame('https://blog.example.com/big-news/', $after['blogUrl']);
    }
}
