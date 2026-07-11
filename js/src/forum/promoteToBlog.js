import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

// Publishes the discussion to the blog. Because promotion goes live immediately, a human
// confirm() gate always stands between the click and the public post.
export default function promoteToBlog(discussion) {
  const promoted = !!discussion.attribute('blogUrl');

  const confirmText = extractText(
    app.translator.trans(promoted ? 'blog-bridge.forum.confirm_update' : 'blog-bridge.forum.confirm_publish', {
      title: discussion.title(),
    })
  );

  if (!confirm(confirmText)) return;

  const loading = app.translator.trans('blog-bridge.forum.working');
  const alert = app.alerts.show({ type: 'info', dismissible: false }, loading);

  return app
    .request({
      method: 'POST',
      url: `${app.forum.attribute('apiUrl')}/discussions/${discussion.id()}/promote-to-blog`,
    })
    .then((res) => {
      app.alerts.dismiss(alert);
      discussion.pushAttributes({ blogUrl: res.url });
      app.alerts.show({ type: 'success', dismissible: true }, app.translator.trans('blog-bridge.forum.success'));
      if (res.url) window.open(res.url, '_blank');
    })
    .catch((e) => {
      app.alerts.dismiss(alert);
      throw e; // let Flarum surface the JSON:API error detail
    });
}
