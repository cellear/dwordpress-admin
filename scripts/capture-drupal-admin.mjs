/**
 * Walks the WordPress-style sidebar on the Drupal site (after the wp_admin
 * recipe is applied), checks every link returns 200 with a heading, and
 * screenshots each page.   node scripts/capture-drupal-admin.mjs
 *
 * Output: docs/drupal-admin/00-overview.md, capture.json, screenshots/*.jpg
 */
import { createRequire } from 'node:module';
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const { chromium } = createRequire(import.meta.url)('playwright');

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = process.env.DRUPAL_URL || 'http://127.0.0.1:8081';
const OUT = join(ROOT, 'docs', 'drupal-admin');
const SHOTS = join(OUT, 'screenshots');
mkdirSync(SHOTS, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();
await page.goto(`${BASE}/user/login`);
await page.fill('#edit-name', process.env.ADMIN_USER || 'admin');
await page.fill('#edit-pass', process.env.ADMIN_PASS || 'admin');
await page.click('#edit-submit');
await page.goto(`${BASE}/wp-admin`);
await page.waitForSelector('.admin-toolbar');

// Read the sidebar tree: top-level items (links or popover buttons) and their children.
const tree = await page.$$eval('.admin-toolbar .toolbar-block__list > li', (items) =>
  items.map((li) => {
    const top = li.querySelector(':scope > a, :scope > button, :scope > .toolbar-button');
    const label = top?.innerText.trim().replace(/^Extend\s+/, '').replace(/\s+/g, ' ') || '';
    const children = [...li.querySelectorAll('.toolbar-popover a, ul a')].map((a) => ({ label: a.innerText.trim().replace(/\s+/g, ' '), href: a.getAttribute('href') }));
    return { label, href: top?.getAttribute('href') || children[0]?.href || null, children: children.filter((c) => c.label) };
  }).filter((m) => m.label)
);

const slug = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const pages = [];
let n = 0;
async function capture(top, label, href) {
  n++;
  const id = `${String(n).padStart(2, '0')}-${slug(top)}${label && label !== top ? '-' + slug(label) : ''}`;
  const rec = { n, top, label: label || top, href, screenshot: `screenshots/${id}.jpg` };
  const r = await page.goto(href.startsWith('http') ? href : BASE + href, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);
  rec.status = r.status();
  rec.finalUrl = page.url().replace(BASE, '');
  rec.h1 = await page.$eval('h1', (h) => h.innerText.trim()).catch(() => '');
  rec.text = await page.$eval('main, .layout-container, body', (el) => el.innerText.replace(/\n{3,}/g, '\n\n').trim().slice(0, 1500)).catch(() => '');
  await page.screenshot({ path: join(SHOTS, `${id}.jpg`), fullPage: true, type: 'jpeg', quality: 80 });
  pages.push(rec);
  console.log(`${rec.status} ${top}${label && label !== top ? ' → ' + label : ''}  [${rec.h1}]  ${rec.finalUrl}`);
}
const seen = new Set();
for (const m of tree) {
  if (!m.href) continue;
  const targets = [{ label: '', href: m.href }, ...m.children];
  for (const t of targets) {
    if (!t.href || t.href.includes('/logout')) continue;
    if (t.label === m.label) continue; // the popover header repeats the parent link
    const key = `${m.label}|${t.label}|${t.href}`;
    if (seen.has(key)) continue;
    seen.add(key);
    await capture(m.label, t.label, t.href);
  }
}

const bad = pages.filter((p) => p.status !== 200 || !p.h1);
let md = `# Drupal wp-admin: every sidebar item\n\nCaptured ${new Date().toISOString().slice(0, 10)} from Drupal with the wp_admin recipe applied on Umami. ${pages.length} pages visited, ${bad.length} problems.\n\n| # | Sidebar item | URL | Status | Heading | Screenshot |\n|---|---|---|---|---|---|\n`;
for (const p of pages) md += `| ${p.n} | ${p.top}${p.label !== p.top ? ' → ' + p.label : ''} | \`${p.finalUrl}\` | ${p.status} | ${p.h1} | [view](${p.screenshot}) |\n`;
if (bad.length) md += `\n## Problems\n\n` + bad.map((p) => `- ${p.top} → ${p.label}: HTTP ${p.status}, heading "${p.h1}"`).join('\n') + '\n';
writeFileSync(join(OUT, '00-overview.md'), md);
writeFileSync(join(OUT, 'capture.json'), JSON.stringify({ base: BASE, tree, pages }, null, 2));
console.log(`\n${pages.length} pages, ${bad.length} problems -> ${OUT}`);
await browser.close();
process.exit(bad.length ? 1 : 0);
