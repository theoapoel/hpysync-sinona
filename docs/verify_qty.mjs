import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8123';
const MODES = [
  ['Klasik', '/pos'],
  ['Quick', '/pos/quick'],
  ['Express', '/pos/express'],
];

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1600, height: 900 } });
const page = await ctx.newPage();

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[name="email"]', 'admin@larapos.com');
await page.click('#toggleModeBtn');
await page.fill('#pinInput', '123456');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
  page.click('#submitBtn'),
]);

let allPass = true;
for (const [label, path] of MODES) {
  await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
  // tunggu skrip POS siap (cart & renderCart terdefinisi)
  await page.waitForFunction(
    () => typeof cart !== 'undefined' && typeof renderCart === 'function',
    null, { timeout: 15000 }
  );

  const before = await page.evaluate(() => ({
    qty: document.getElementById('totalQtyDisplay')?.textContent.trim() ?? 'MISSING',
    top: document.getElementById('topbarTotalDisplay')?.textContent.trim() ?? 'MISSING',
  }));

  // 2 item: 2 x 15.000 + 3 x 10.000 = 60.000, qty 5
  const after = await page.evaluate(() => {
    const mk = (id, name, price, qty) => ({
      id, name, price, basePrice: price, erpItemCode: 'X' + id,
      sku: 'SKU' + id, stock: 99, unit: 'Pcs', tax: 0, track: false,
      qty, discount: 0, discountPct: 0, note: '',
    });
    cart.length = 0;
    cart.push(mk(9001, 'Uji A', 15000, 2));
    cart.push(mk(9002, 'Uji B', 10000, 3));
    renderCart();
    return {
      qty: document.getElementById('totalQtyDisplay')?.textContent.trim() ?? 'MISSING',
      top: document.getElementById('topbarTotalDisplay')?.textContent.trim() ?? 'MISSING',
      panel: document.getElementById('totalDisplay')?.textContent.trim() ?? 'MISSING',
    };
  });

  const pass =
    before.qty === '0' && before.top === 'Rp 0' &&
    after.qty === '5' && after.top === after.panel && after.top !== 'Rp 0';
  if (!pass) allPass = false;
  console.log(
    `${pass ? 'PASS' : 'FAIL'}  ${label.padEnd(8)} ` +
    `awal[qty=${before.qty}, topbar=${before.top}] → ` +
    `isi[qty=${after.qty}, topbar=${after.top}, panel=${after.panel}]`
  );

  await page.screenshot({ path: `docs/screenshots/qty-${label.toLowerCase()}.png`, fullPage: false });
}

await browser.close();
console.log(allPass ? '\nSEMUA MODE LULUS' : '\nADA YANG GAGAL');
process.exit(allPass ? 0 : 1);
