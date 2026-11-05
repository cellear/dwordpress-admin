/**
 * End-to-end checks on the Drupal site after the recipe: settings save,
 * Quick Draft, categories, comments, and the stock admin still reachable.
 *   node scripts/roundtrip-check.mjs
 */
import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');
const BASE = process.env.DRUPAL_URL || 'http://127.0.0.1:8081';
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail ? ' — ' + detail : ''}`); };

const b = await chromium.launch();
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await p.goto(`${BASE}/user/login`); await p.fill('#edit-name', 'admin'); await p.fill('#edit-pass', 'admin'); await p.click('#edit-submit');

// 1. Settings → General writes system.site.
const tagline = 'Tagline ' + Date.now();
await p.goto(`${BASE}/wp-admin/settings/general`);
await p.fill('#edit-slogan', tagline);
await p.click('#edit-submit');
await p.waitForSelector('[data-drupal-messages]');
await p.goto(`${BASE}/admin/config/system/site-information`);
check('General settings saved the tagline into system.site', (await p.inputValue('#edit-site-slogan')) === tagline);

// 2. Quick Draft creates a Draft article visible under Posts with the Draft filter.
const title = 'Quick draft ' + Date.now();
await p.goto(`${BASE}/wp-admin`);
await p.fill('#wp-admin-quick-draft input[name="title"]', title);
await p.fill('#wp-admin-quick-draft textarea[name="body"]', 'Written from the dashboard.');
await p.click('#wp-admin-quick-draft input[type="submit"]');
await p.waitForSelector('[data-drupal-messages]');
await p.goto(`${BASE}/wp-admin/posts?moderation_state=editorial-draft`);
check('Quick Draft appears under Posts filtered to Draft', (await p.textContent('table')).includes(title));
await p.goto(`${BASE}/wp-admin/posts?moderation_state=editorial-published`);
check('Quick Draft is not listed as Published', !(await p.textContent('table')).includes(title));

// 3. Categories: add a term on Posts → Categories, assign it on an article, see it rendered.
const cat = 'Baking ' + Date.now();
await p.goto(`${BASE}/wp-admin/posts/categories`);
await p.fill('#edit-name-0-value', cat);
await p.click('#edit-submit');
await p.waitForLoadState('domcontentloaded');
await p.goto(`${BASE}/wp-admin/posts/categories`);
check('New category listed on the Categories page', (await p.textContent('table')).includes(cat));
await p.goto(`${BASE}/wp-admin/posts`);
const editHref = await p.$eval('table a[href*="/edit"]', (a) => a.getAttribute('href'));
const nodeUrl = BASE + editHref.replace(/\/edit.*$/, '');
await p.goto(BASE + editHref);
const label = await p.$(`label:has-text("${cat}")`);
check('Category checkbox shown on the article edit form', !!label);
if (label) {
  await label.click();
  await p.click('#edit-submit');
  await p.waitForLoadState('domcontentloaded');
  await p.goto(nodeUrl);
  check('Rendered article shows the category', (await p.textContent('main, body')).includes(cat), nodeUrl);
}

// 4. Comments: post one on an article, find it under Comments.
const comment = 'Comment ' + Date.now();
await p.goto(nodeUrl);
const hasForm = await p.$('#comment-form, form.comment-form');
check('Comment form present on the article', !!hasForm);
if (hasForm) {
  await p.fill('form.comment-form textarea, #comment-form textarea', comment).catch(async () => {
    const frame = p.frames().find((f) => f.url().includes('ckeditor')) ; if (frame) await frame.fill('body', comment);
  });
  // CKEditor 5: type into the contenteditable.
  const editable = await p.$('form.comment-form .ck-editor__editable, #comment-form .ck-editor__editable');
  if (editable) { await editable.click(); await p.keyboard.type(comment); }
  await p.click('form.comment-form input[type="submit"][value="Save"], #comment-form input[value="Save"]');
  await p.waitForLoadState('domcontentloaded');
  await p.goto(`${BASE}/admin/content/comment`);
  check('Comment listed under Comments', (await p.textContent('body')).includes(comment));
}

// 5. Stock Drupal admin still reachable from Tools.
await p.goto(`${BASE}/wp-admin/tools/drupal`);
await p.click('.wp-admin-page a[href$="/admin"]');
await p.waitForLoadState('domcontentloaded');
check('Tools → Drupal Administration reaches /admin', /\/admin$/.test(p.url()) && (await p.textContent('h1')).includes('Administration'));

await b.close();
const failed = results.filter((r) => !r.ok).length;
console.log(`\n${results.length - failed}/${results.length} checks passed`);
process.exit(failed ? 1 : 0);
