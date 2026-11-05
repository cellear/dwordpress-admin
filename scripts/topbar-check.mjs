/**
 * Exercises every link in the WordPress-style admin bar as the admin user.
 *   node scripts/topbar-check.mjs
 */
import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');
const BASE = process.env.DRUPAL_URL || 'http://127.0.0.1:8081';
const b = await chromium.launch();
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await p.goto(`${BASE}/user/login`); await p.fill('#edit-name', 'admin'); await p.fill('#edit-pass', 'admin'); await p.click('#edit-submit');
await p.goto(`${BASE}/wp-admin`, { waitUntil: 'domcontentloaded' });
const links = await p.$$eval('#wp-admin-bar a[href]', (as) => as.map((a) => ({ text: a.innerText.trim().replace(/\s+/g, ' ') || a.getAttribute('aria-label'), href: a.getAttribute('href') })));
let failed = 0;
for (const l of links) {
  if (/logout/.test(l.href)) continue; // checked last
  if (/^https?:\/\/(www\.)?drupal\.org/.test(l.href)) { console.log('skip external', l.href); continue; }
  const r = await p.goto(l.href.startsWith('http') ? l.href : BASE + l.href, { waitUntil: 'domcontentloaded' });
  const ok = r.status() === 200;
  if (!ok) failed++;
  console.log(ok ? 'PASS' : 'FAIL', r.status(), l.text, '->', l.href);
}
const logout = links.find((l) => /logout/.test(l.href));
await p.goto(BASE + logout.href, { waitUntil: 'domcontentloaded' });
const confirm = await p.$('input[value="Log out"], #edit-submit');
if (confirm) await confirm.click();
await p.waitForLoadState('domcontentloaded');
const stillBar = await p.$('#wp-admin-bar');
console.log(stillBar ? 'FAIL' : 'PASS', 'Log Out ends the session (bar gone)');
if (stillBar) failed++;
await b.close();
console.log(`\n${links.length} bar links, ${failed} failures`);
process.exit(failed ? 1 : 0);
