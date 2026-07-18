import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

const t = (k) => app.translator.trans('blog-bridge.admin.' + k);

/**
 * Admin settings + permission, declared the Flarum 2 way via `flarum/common/extenders`.
 *
 * The previous `app.extensionData.for(...)` API was removed in Flarum 2 — calling
 * it threw "Cannot read properties of undefined (reading 'for')" at boot, which
 * took the whole admin frontend down with an initialization error. Each `.setting()`
 * / `.permission()` now takes a factory function so translations resolve lazily at
 * render time rather than at module load.
 */
export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'blog-bridge.ghost_url',
      type: 'url',
      label: t('ghost_url_label'),
      help: t('ghost_url_help'),
      placeholder: 'https://shatteredpact.com',
    }))
    .setting(() => ({
      setting: 'blog-bridge.admin_key',
      type: 'text',
      label: t('admin_key_label'),
      help: t('admin_key_help'),
    }))
    .setting(() => ({
      setting: 'blog-bridge.publish_status',
      type: 'select',
      options: {
        published: t('status_published'),
        draft: t('status_draft'),
      },
      default: 'published',
      label: t('status_label'),
      help: t('status_help'),
    }))
    .permission(
      () => ({
        icon: 'fas fa-ghost',
        label: t('permission_label'),
        permission: 'discussion.promoteToBlog',
      }),
      'moderate',
      95
    ),
];
