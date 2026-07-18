/**
 * Admin entry. Settings + the promote permission are declared in ./extend the
 * Flarum 2 way (via `flarum/common/extenders`). Flarum picks up the bundle's
 * `extend` export and applies the extenders — no `app.extensionData` needed.
 */
export { default as extend } from './extend';
