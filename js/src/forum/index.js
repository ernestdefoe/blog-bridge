import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import DiscussionHero from 'flarum/forum/components/DiscussionHero';
import Button from 'flarum/common/components/Button';
import icon from 'flarum/common/helpers/icon';

import promoteToBlog from './promoteToBlog';

app.initializers.add('ernestdefoe-blog-bridge', () => {
  // Backlink under the discussion title whenever the thread has been promoted — points readers
  // to the published blog post. `blogUrl` is refreshed live on the discussion after a promote,
  // so this appears without a reload.
  extend(DiscussionHero.prototype, 'bodyItems', function (items) {
    const url = this.attrs.discussion?.attribute('blogUrl');
    if (!url) return;

    items.add(
      'blogBacklink',
      <a className="BlogBacklink" href={url} target="_blank" rel="noopener noreferrer">
        {icon('fas fa-ghost', { className: 'BlogBacklink-icon' })}
        <span className="BlogBacklink-label">{app.translator.trans('blog-bridge.forum.also_on_blog')}</span>
        {icon('fas fa-arrow-right', { className: 'BlogBacklink-arrow' })}
      </a>,
      90
    );
  });

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
