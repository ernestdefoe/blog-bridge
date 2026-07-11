import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import Button from 'flarum/common/components/Button';

import promoteToBlog from './promoteToBlog';

app.initializers.add('ernestdefoe-blog-bridge', () => {
  // Staff-only "Promote to blog" (and, once promoted, "View on blog") in the discussion's
  // moderation controls. Visibility is driven by the server-computed `canPromoteToBlog`.
  extend(DiscussionControls, 'moderationControls', (items, discussion) => {
    if (!discussion.attribute('canPromoteToBlog')) return;

    const promoted = !!discussion.attribute('blogUrl');

    items.add(
      'promoteToBlog',
      <Button icon="fas fa-ghost" onclick={() => promoteToBlog(discussion)}>
        {app.translator.trans(promoted ? 'blog-bridge.forum.update_on_blog' : 'blog-bridge.forum.promote')}
      </Button>,
      10
    );

    if (promoted) {
      items.add(
        'viewOnBlog',
        <Button icon="fas fa-external-link-alt" onclick={() => window.open(discussion.attribute('blogUrl'), '_blank')}>
          {app.translator.trans('blog-bridge.forum.view_on_blog')}
        </Button>,
        5
      );
    }
  });
});
