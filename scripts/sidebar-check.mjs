/**
 * Verifies the sidebar stays expanded on desktop even when the browser has a
 * stored "collapsed" preference, resists a collapse event, and still slides
 * in/out on mobile.   node scripts/sidebar-check.mjs
 */
import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');
const BASE = process.env.DRUPAL_URL || 'http://127.0.0.1:8081';
const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail ? ' — ' + detail : ''}`); };

const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
// Reproduce a browser that previously collapsed the sidebar.
await ctx.addInitScript(() => { try { localStorage.setItem('Drupal.navigation.sidebarExpanded', 'false'); } catch (e) {} });
const p = await ctx.newPage();
await p.goto(`${BASE}/user/login`); await p.fill('#edit-name', 'admin'); await p.fill('#edit-pass', 'admin'); await p.click('#edit-submit');
await p.goto(`${BASE}/wp-admin`, { waitUntil: 'networkidle' }); await p.waitForTimeout(500);
const state = async () => p.evaluate(() => {
  const label = document.querySelector('.admin-toolbar .toolbar-button--collapsible .toolbar-button__label');
  const btn = document.querySelector('.admin-toolbar__expand-button');
  return {
    attr: document.documentElement.getAttribute('data-admin-toolbar'),
    stored: localStorage.getItem('Drupal.navigation.sidebarExpanded'),
    width: Math.round(document.querySelector('.admin-toolbar').getBoundingClientRect().width),
    labelVisible: label.getBoundingClientRect().width > 20 && getComputedStyle(label).opacity === '1',
    collapseBtnVisible: btn ? getComputedStyle(btn).display !== 'none' : false,
    contentLeft: Math.round(document.querySelector('.dialog-off-canvas-main-canvas').getBoundingClientRect().left),
  };
});
let s = await state();
check('Sidebar expanded despite stored "collapsed" preference', s.attr === 'expanded' && s.stored === 'true', JSON.stringify(s));
check('Sidebar is 160px wide with visible text labels', s.width >= 160 && s.width <= 162 && s.labelVisible);
check('Collapse button hidden', !s.collapseBtnVisible);
check('Page content starts right of the sidebar', s.contentLeft >= 160);
await p.screenshot({ path: '/tmp/claude-0/-home-user-dwordpress-admin/703fee20-2a6f-516c-b082-5762ca411950/scratchpad/check/c-expanded.png' }).catch(() => {});
await p.evaluate(() => document.documentElement.dispatchEvent(new CustomEvent('toggle-admin-toolbar', { bubbles: true, detail: { state: false, manual: true } })));
await p.waitForTimeout(300);
s = await state();
check('A collapse event is undone', s.attr === 'expanded' && s.labelVisible, JSON.stringify(s));

const m = await (await b.newContext({ viewport: { width: 768, height: 1024 } })).newPage();
await m.goto(`${BASE}/user/login`); await m.fill('#edit-name', 'admin'); await m.fill('#edit-pass', 'admin'); await m.click('#edit-submit');
await m.goto(`${BASE}/wp-admin`, { waitUntil: 'networkidle' });
await m.click('.admin-toolbar-control-bar__burger'); await m.waitForTimeout(400);
const open = await m.evaluate(() => document.documentElement.getAttribute('data-admin-toolbar'));
await m.click('.admin-toolbar__close-button'); await m.waitForTimeout(400);
const closed = await m.evaluate(() => document.documentElement.getAttribute('data-admin-toolbar'));
check('Mobile: burger opens, close button closes', open === 'expanded' && closed === 'collapsed', `${open} / ${closed}`);
await b.close();
const failed = results.filter((r) => !r).length;
console.log(`\n${results.length - failed}/${results.length} checks passed`);
process.exit(failed ? 1 : 0);
