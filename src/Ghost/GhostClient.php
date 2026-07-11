<?php

namespace ErnestDefoe\BlogBridge\Ghost;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * A thin Ghost Admin API client: mints the short-lived HS256 JWT the Admin API expects
 * (`Authorization: Ghost <jwt>`, `kid` = the key id, secret hex-decoded, `aud` = "/admin/"),
 * and creates-or-updates a post keyed by an internal `#forum-{id}` tag so re-promoting the
 * same discussion updates the existing post instead of duplicating it.
 */
class GhostClient
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
    ) {
    }

    public function enabled(): bool
    {
        return $this->url() !== '' && str_contains((string) $this->settings->get('blog-bridge.admin_key'), ':');
    }

    protected function url(): string
    {
        return rtrim((string) $this->settings->get('blog-bridge.ghost_url'), '/');
    }

    protected function base(): string
    {
        return $this->url() . '/ghost/api/admin';
    }

    protected function token(): string
    {
        [$id, $secret] = explode(':', (string) $this->settings->get('blog-bridge.admin_key'), 2);

        $b64 = fn ($data) => rtrim(strtr(base64_encode(is_string($data) ? $data : json_encode($data)), '+/', '-_'), '=');

        $now = time();
        $header = $b64(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $id]);
        $payload = $b64(['iat' => $now, 'exp' => $now + 300, 'aud' => '/admin/']);
        $sig = $b64(hash_hmac('sha256', "$header.$payload", hex2bin($secret), true));

        return "$header.$payload.$sig";
    }

    protected function client(): Client
    {
        return new Client([
            'base_uri' => $this->base() . '/',
            'headers' => [
                'Authorization' => 'Ghost ' . $this->token(),
                'Accept-Version' => 'v5.0',
            ],
            'http_errors' => true,
            'timeout' => 20,
        ]);
    }

    /**
     * The existing Ghost post for a discussion, matched on its internal `#forum-{id}` tag,
     * or null. Returns id + updated_at (needed for the collision check on update) + url.
     *
     * @return array{id: string, updated_at: string, url: string}|null
     */
    public function findByForumTag(int $discussionId): ?array
    {
        $res = $this->client()->get('posts/', [
            'query' => [
                'filter' => "tag:hash-forum-$discussionId",
                'limit' => 1,
                'fields' => 'id,updated_at,url',
            ],
        ]);

        $post = json_decode((string) $res->getBody(), true)['posts'][0] ?? null;

        return $post ? [
            'id' => (string) $post['id'],
            'updated_at' => (string) $post['updated_at'],
            'url' => (string) ($post['url'] ?? ''),
        ] : null;
    }

    /**
     * Create (or update, if it was promoted before) the post. `?source=html` tells Ghost to
     * convert the supplied HTML into its own Lexical format.
     *
     * @param  array<string, mixed>  $post
     * @return array{id: string, url: string}
     */
    public function upsert(array $post, int $discussionId): array
    {
        $existing = $this->findByForumTag($discussionId);
        $client = $this->client();

        if ($existing) {
            $post['updated_at'] = $existing['updated_at']; // Ghost's optimistic-lock token
            $res = $client->put("posts/{$existing['id']}/", [
                'query' => ['source' => 'html'],
                'json' => ['posts' => [$post]],
            ]);
        } else {
            $res = $client->post('posts/', [
                'query' => ['source' => 'html'],
                'json' => ['posts' => [$post]],
            ]);
        }

        $created = json_decode((string) $res->getBody(), true)['posts'][0] ?? [];

        return [
            'id' => (string) ($created['id'] ?? ($existing['id'] ?? '')),
            'url' => (string) ($created['url'] ?? ($existing['url'] ?? '')),
        ];
    }

    /**
     * Ghost's own error message when a request fails, for surfacing to the promoting user.
     */
    public static function errorMessage(RequestException $e): string
    {
        if ($e->hasResponse()) {
            $body = json_decode((string) $e->getResponse()->getBody(), true);
            $msg = $body['errors'][0]['message'] ?? null;
            if ($msg) {
                return $msg;
            }
        }

        return $e->getMessage();
    }
}
