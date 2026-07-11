import app from 'flarum/admin/app';

app.initializers.add('ernestdefoe-blog-bridge', () => {
  app.extensionData
    .for('ernestdefoe-blog-bridge')
    .registerSetting({
      setting: 'blog-bridge.ghost_url',
      type: 'url',
      label: app.translator.trans('blog-bridge.admin.ghost_url_label'),
      help: app.translator.trans('blog-bridge.admin.ghost_url_help'),
      placeholder: 'https://shatteredpact.com',
    })
    .registerSetting({
      setting: 'blog-bridge.admin_key',
      type: 'text',
      label: app.translator.trans('blog-bridge.admin.admin_key_label'),
      help: app.translator.trans('blog-bridge.admin.admin_key_help'),
    })
    .registerSetting({
      setting: 'blog-bridge.publish_status',
      type: 'select',
      options: {
        published: app.translator.trans('blog-bridge.admin.status_published'),
        draft: app.translator.trans('blog-bridge.admin.status_draft'),
      },
      default: 'published',
      label: app.translator.trans('blog-bridge.admin.status_label'),
      help: app.translator.trans('blog-bridge.admin.status_help'),
    })
    .registerPermission(
      {
        icon: 'fas fa-ghost',
        label: app.translator.trans('blog-bridge.admin.permission_label'),
        permission: 'discussion.promoteToBlog',
      },
      'moderate',
      95
    );
});
