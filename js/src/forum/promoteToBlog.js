import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

// Sends the discussion to the blog behind a confirm() gate. The wording follows the
// "On promote" setting: a live post or a Ghost draft.
export default function promoteToBlog(discussion) {
  const promoted = !!discussion.attribute('blogUrl');
  const draft = !!app.forum.attribute('blogBridgeCreatesDraft');

  const confirmKey = promoted ? 'confirm_update' : draft ? 'confirm_draft' : 'confirm_publish';
  const confirmText = extractText(app.translator.trans('blog-bridge.forum.' + confirmKey, { title: discussion.title() }));

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
      app.alerts.show(
        { type: 'success', dismissible: true },
        app.translator.trans(draft ? 'blog-bridge.forum.success_draft' : 'blog-bridge.forum.success')
      );
      if (res.url) window.open(res.url, '_blank');
    })
    .catch((e) => {
      app.alerts.dismiss(alert);
      throw e; // let Flarum surface the JSON:API error detail
    });
}
