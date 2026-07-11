<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// Where a promoted discussion lives on the blog: the Ghost post id (for idempotent re-promotion)
// and its public URL (shown as "View on blog" in the discussion controls).
return Migration::addColumns('discussions', [
    'blog_post_id' => ['string', 'length' => 191, 'nullable' => true],
    'blog_url' => ['string', 'length' => 191, 'nullable' => true],
]);
