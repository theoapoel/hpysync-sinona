import { chromium } from 'playwright';
import fs from 'fs';

const BASE = 'http://127.0.0.1:8123';
const OUT = 'docs/screenshots';
fs.mkdirSync(OUT, { recursive: true });

// Menu yang di-capture: [nomor urut, judul, path]
const MENUS = [
  ['01', 'Dashboard',            '/'],
  ['02', 'Kasir (POS)',          '/pos'],
  ['03', 'Transaksi',            '/transactions'],
  ['04', 'Produk',               '/products'],
  ['05', 'Customer',             '/customers'],
  ['06', 'Stok Barang',          '/stock'],
  ['07', 'Stock Opname',         '/stock-opname'],
  ['08', 'Repack',               '/slices'],
  ['09', 'Transfer Barang',      '/stock-transfer'],
  ['10', 'Delivery Order',       '/delivery-orders'],
  ['11', 'Delivery Notes',       '/delivery-notes'],
  ['12', 'Permintaan FG',        '/stock-requests'],
  ['13', 'Pulling Order',        '/pulling-order'],
  ['14', 'Rekap Order',          '/rekap-order'],
  ['15', 'Kitchen Monitor',      '/kitchen'],
  ['16', 'Sync HPY',             '/sync'],
  ['17', 'Laporan Online',       '/online-report'],
  ['18', 'Laporan Pembayaran',   '/mop-report'],
  ['19', 'Kupon',                '/coupons'],
  ['20', 'Manajemen User',       '/users'],
  ['21', 'Manajemen Role',       '/roles'],
  ['22', 'Hak Akses',            '/permissions'],
  ['23', 'Warehouse',            '/warehouses'],
  ['24', 'Pengaturan Toko',      '/settings'],
];

const results = [];

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();

// ── Login via PIN (admin) ─────────────────────────────
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[name="email"]', 'admin@larapos.com');
await page.click('#toggleModeBtn');
await page.fill('#pinInput', '123456');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
  page.click('#submitBtn'),
]);
console.log('after login URL:', page.url());
if (page.url().includes('/login')) {
  console.error('LOGIN GAGAL — cek kredensial/PIN');
  await browser.close();
  process.exit(1);
}

// ── Capture tiap menu ─────────────────────────────────
for (const [no, title, path] of MENUS) {
  const file = `${OUT}/${no}-${path.replace(/\W+/g, '') || 'root'}.png`;
  try {
    const resp = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle', timeout: 20000 });
    await page.waitForTimeout(1200); // beri waktu render JS/tabel
    await page.screenshot({ path: file, fullPage: true });
    const status = resp ? resp.status() : '?';
    results.push({ no, title, path, file, status, ok: true });
    console.log(`OK  ${no} ${title} (${status})`);
  } catch (e) {
    results.push({ no, title, path, file: null, status: 'ERR', ok: false, err: String(e).slice(0, 120) });
    console.log(`ERR ${no} ${title}: ${String(e).slice(0, 120)}`);
  }
}

fs.writeFileSync(`${OUT}/_index.json`, JSON.stringify(results, null, 2));
await browser.close();
console.log(`\nSelesai. ${results.filter(r => r.ok).length}/${MENUS.length} berhasil.`);
