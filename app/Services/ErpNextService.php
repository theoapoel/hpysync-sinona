<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderPayment;
use App\Models\DeliveryShipment;
use App\Models\ErpSyncLog;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Slice;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Models\Transaction;
use App\Models\Warehouse;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

class ErpNextService
{
    private Client $client;

    private string $baseUrl;

    private string $apiKey;

    private string $apiSecret;

    /** Cache hasil deteksi versi ERP; null = belum dicoba, [] = sudah dicoba tapi gagal. */
    private ?array $erpVersion = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(Setting::get('erpnext_url', env('ERPNEXT_URL', '')), '/');
        $this->apiKey = Setting::get('erpnext_api_key', env('ERPNEXT_API_KEY', ''));
        $this->apiSecret = Setting::get('erpnext_api_secret', env('ERPNEXT_API_SECRET', ''));

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'connect_timeout' => 8,   // internet mati → gagal cepat, bukan menggantung
            'headers' => [
                'Authorization' => 'token '.$this->apiKey.':'.$this->apiSecret,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'verify' => false,
        ]);
    }

    // =========================================================
    // DETEKSI VERSI ERP
    // ngabaso = v15, sinona = v13. Beda major version bisa beda field/perilaku.
    // Helper ini TIDAK mengubah alur sync apa pun — hanya menyediakan info versi
    // untuk diagnosa (mis. ditempelkan ke pesan error) & percabangan di masa depan.
    // =========================================================

    /** Deteksi versi ERP (frappe/erpnext). Dipanggil sekali, hasilnya di-cache. */
    public function detectErpVersion(): ?array
    {
        if ($this->erpVersion !== null) {
            return $this->erpVersion ?: null;
        }
        if (empty($this->baseUrl)) {
            return null;
        }
        try {
            $resp = $this->client->get('/api/method/frappe.utils.change_log.get_versions');
            $msg = json_decode($resp->getBody()->getContents(), true)['message'] ?? [];
            $erp = $msg['erpnext']['version'] ?? ($msg['frappe']['version'] ?? null);
            $major = $erp ? (int) explode('.', $erp)[0] : null;

            return $this->erpVersion = ['erpnext' => $erp, 'major' => $major];
        } catch (\Throwable $e) {
            // Tandai sudah dicoba supaya tidak memanggil berulang tiap kali dipakai.
            $this->erpVersion = [];

            return null;
        }
    }

    /** Major version ERP (mis. 13 atau 15), atau null bila tak terdeteksi. */
    public function erpMajorVersion(): ?int
    {
        return $this->detectErpVersion()['major'] ?? null;
    }

    // =========================================================
    // TIMEZONE HELPERS
    // App berjalan di UTC, tetapi ERPNext harus menerima waktu lokal (mis. WIB)
    // supaya posting_date/posting_time transaksi sesuai jam sebenarnya.
    // =========================================================
    private function localTimezone(): string
    {
        return Setting::get('timezone', 'Asia/Jakarta') ?: 'Asia/Jakarta';
    }

    /** Konversi datetime (UTC di DB) ke timezone lokal untuk dikirim ke ERP. */
    private function toLocal($dt): Carbon
    {
        return Carbon::parse($dt)->setTimezone($this->localTimezone());
    }

    /** Waktu "sekarang" dalam timezone lokal. */
    private function localNow(): Carbon
    {
        return Carbon::now($this->localTimezone());
    }

    // =========================================================
    // CONFIGURATION CHECK
    // =========================================================
    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiKey) && ! empty($this->apiSecret);
    }

    public function quickPing(): bool
    {
        if (empty($this->baseUrl)) {
            return false;
        }
        try {
            $client = new Client([
                'base_uri' => $this->baseUrl,
                'timeout' => 3,
                'connect_timeout' => 3,
                'verify' => false,
                'http_errors' => false,
            ]);
            $resp = $client->get('/api/method/frappe.utils.ping');

            return $resp->getStatusCode() < 500;
        } catch (\Exception $e) {
            return false;
        }
    }

    // =========================================================
    // TEST CONNECTION
    // =========================================================
    public function testConnection(): array
    {
        return $this->testConnectionWith($this->baseUrl, $this->apiKey, $this->apiSecret);
    }

    /**
     * Test koneksi dengan parameter eksplisit (dari form, belum tersimpan).
     * - Hanya url → cek reachability saja (tanpa credentials)
     * - url + apiKey + apiSecret → cek reachability + autentikasi API
     */
    public function testConnectionWith(string $url, string $apiKey = '', string $apiSecret = ''): array
    {
        $url = rtrim($url, '/');

        if (empty($url)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum diisi.'];
        }

        try {
            // ── Langkah 1: cek URL bisa dijangkau ───────────────────
            $plain = new Client([
                'base_uri' => $url,
                'timeout' => 10,
                'verify' => false,
                'http_errors' => false,
                'allow_redirects' => true,
            ]);

            $ping = $plain->get('/api/method/frappe.utils.ping');

            if ($ping->getStatusCode() >= 500) {
                return ['success' => false, 'error' => 'Server ERP HPY merespons dengan error '.$ping->getStatusCode().'.'];
            }

            // ── Langkah 2: jika credentials tersedia, test API auth ──
            if ($apiKey && $apiSecret) {
                $auth = new Client([
                    'base_uri' => $url,
                    'timeout' => 10,
                    'verify' => false,
                    'headers' => [
                        'Authorization' => 'token '.$apiKey.':'.$apiSecret,
                        'Accept' => 'application/json',
                    ],
                    'http_errors' => false,
                ]);

                $resp = $auth->get('/api/method/frappe.auth.get_logged_user');
                $data = json_decode($resp->getBody()->getContents(), true);

                if ($resp->getStatusCode() === 403 || $resp->getStatusCode() === 401) {
                    return ['success' => false, 'error' => 'URL terjangkau, tetapi API Key / Secret tidak valid atau tidak memiliki izin.'];
                }

                if (isset($data['message'])) {
                    return ['success' => true, 'user' => $data['message'], 'mode' => 'full'];
                }

                return ['success' => false, 'error' => 'API Key/Secret tidak valid. Response: '.json_encode($data)];
            }

            // Hanya URL yang ditest — sudah lolos ping
            return ['success' => true, 'user' => null, 'mode' => 'url_only',
                'message' => 'Server ERP HPY dapat dijangkau. Isi API Key & Secret lalu test kembali untuk verifikasi lengkap.'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Tidak dapat menjangkau server: '.$e->getMessage()];
        }
    }

    // =========================================================
    // SYNC TRANSACTIONS → ERPNext POS Invoice
    // =========================================================
    public function syncTransaction(Transaction $transaction): array
    {
        // Idempoten — satu transaksi hanya boleh punya satu POS Invoice. Dokumen yang
        // sudah pernah dibuat (termasuk yang tertinggal sebagai draft karena submitnya
        // gagal) tidak dibuat ulang, cukup diselesaikan submit-nya.
        if (! empty($transaction->erp_pos_invoice)) {
            return $this->submitPosInvoice($transaction, $transaction->erp_pos_invoice, null);
        }

        // Jaring pengaman untuk POST yang hasilnya tidak pernah sampai balik ke sini
        // (mis. timeout setelah ERP sebenarnya sudah membuat dokumennya): cari dulu
        // invoice dengan po_no = nomor invoice lokal. Kalau ada, dokumen itu diadopsi
        // alih-alih membuat yang kedua.
        $existing = $this->findPosInvoiceByLocalNo($transaction->invoice_no);
        if ($existing) {
            Log::warning('ERPNext sync: POS Invoice sudah ada di ERP, tidak dibuat ulang', [
                'invoice_no' => $transaction->invoice_no,
                'docname' => $existing,
            ]);

            return $this->submitPosInvoice($transaction, $existing, null);
        }

        // Auto-push customer ke ERPNext jika belum punya erp_customer_name
        if ($transaction->customer && ! ($transaction->customer->erp_customer_name ?: null)) {
            $this->pushCustomer($transaction->customer);
            $transaction->customer->refresh();
        }

        $payload = $this->buildPosInvoicePayload($transaction);

        try {
            $response = $this->client->post('/api/resource/POS Invoice', [
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;

        } catch (ConnectException $e) {
            Log::warning("ERPNext auto-sync: network unreachable for {$transaction->invoice_no}");

            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            $errorBody = $this->extractError($e);
            // Tandai versi ERP di pesan FAILED agar error khas beda-versi langsung terlihat.
            if ($v = $this->erpMajorVersion()) {
                $errorBody = "[ERP v{$v}] {$errorBody}";
            }

            $transaction->update([
                'erp_sync_status' => 'failed',
                'erp_sync_error' => $errorBody,
            ]);

            $this->logSync('transaction', $transaction->id, $transaction->invoice_no,
                'failed', $payload, null, null, $errorBody);

            Log::error("ERPNext sync failed for {$transaction->invoice_no}: {$errorBody}");

            return ['success' => false, 'error' => $errorBody];
        }

        // Submit dipisahkan dari POST. Nomor dokumennya WAJIB tersimpan lebih dulu:
        // kalau submitnya gagal dan docname ikut hilang, sync berikutnya akan membuat
        // POS Invoice kedua sementara yang pertama tertinggal sebagai draft di ERP.
        return $this->submitPosInvoice($transaction, $docname, $payload, $data);
    }

    /**
     * Simpan nomor dokumen lalu selesaikan submit-nya. Dipakai baik oleh POST baru
     * maupun oleh sync ulang atas dokumen yang sudah ada.
     */
    private function submitPosInvoice(Transaction $transaction, ?string $docname, ?array $payload, ?array $data = null): array
    {
        if (! $docname) {
            $error = 'ERP tidak mengembalikan nomor POS Invoice.';
            $transaction->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);
            $this->logSync('transaction', $transaction->id, $transaction->invoice_no,
                'failed', $payload, $data, null, $error);

            return ['success' => false, 'error' => $error];
        }

        // Disimpan sebelum submit — inilah jejak yang mencegah dokumen ganda.
        $transaction->update(['erp_pos_invoice' => $docname]);

        try {
            $this->submitDoc('POS Invoice', $docname);
        } catch (ConnectException $e) {
            $error = 'POS Invoice '.$docname.' dibuat tapi submitnya belum terkirim (jaringan). Sync ulang untuk menyelesaikan.';
            $transaction->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);

            return ['success' => false, 'error' => $error, 'docname' => $docname, 'network_error' => true];
        } catch (RequestException $e) {
            $error = 'POS Invoice '.$docname.' dibuat tapi gagal submit: '.$this->extractError($e);
            $transaction->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);
            $this->logSync('transaction', $transaction->id, $transaction->invoice_no,
                'failed', $payload, $data, $docname, $error);

            Log::error("ERPNext submit failed for {$transaction->invoice_no}: {$error}");

            return ['success' => false, 'error' => $error, 'docname' => $docname];
        }

        $transaction->update([
            'erp_synced_at' => now(),
            'erp_sync_status' => 'synced',
            'erp_sync_error' => null,
        ]);

        $this->logSync('transaction', $transaction->id, $transaction->invoice_no,
            'success', $payload, $data, $docname);

        return ['success' => true, 'docname' => $docname];
    }

    /**
     * Cari POS Invoice di ERP berdasarkan nomor invoice lokal (dikirim sebagai po_no).
     * Kegagalan pencarian tidak boleh menghentikan sync — dianggap "tidak ketemu"
     * supaya perilakunya turun ke keadaan semula, bukan memblokir transaksi.
     */
    private function findPosInvoiceByLocalNo(?string $invoiceNo): ?string
    {
        if (empty($invoiceNo)) {
            return null;
        }

        try {
            $response = $this->client->get('/api/resource/POS Invoice', [
                'query' => [
                    'fields' => json_encode(['name']),
                    'filters' => json_encode([['po_no', '=', $invoiceNo]]),
                    'limit_page_length' => 1,
                ],
                'timeout' => 10,
            ]);

            $rows = json_decode($response->getBody()->getContents(), true)['data'] ?? [];

            return $rows[0]['name'] ?? null;
        } catch (\Exception $e) {
            Log::warning('ERPNext: gagal memeriksa POS Invoice yang sudah ada', [
                'invoice_no' => $invoiceNo,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildPosInvoicePayload(Transaction $transaction): array
    {
        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY'));
        $posProfile = Setting::get('erpnext_pos_profile', env('ERPNEXT_POS_PROFILE'));
        $priceList = Setting::get('erpnext_price_list', '');

        $items = $transaction->items->map(function ($item) {
            // Fold diskon item ke dalam rate — kirim net rate langsung.
            // Ini menghindari konflik dengan ERPNext ketika price_list_rate = 0
            // (tidak ada price list), karena ERPNext akan menghitung diskon dari rate
            // yang kita kirim, bukan dari price list.
            $discAmt = (float) $item->discount_amount;
            $netRate = max(0, (float) $item->price - ($item->quantity > 0 ? $discAmt / $item->quantity : 0));
            $netAmount = $netRate * $item->quantity;

            return [
                'item_code' => $item->product->erp_item_code ?? $item->product_sku,
                'item_name' => $item->product_name,
                'qty' => $item->quantity,
                'rate' => $netRate,
                'amount' => $netAmount,
                'uom' => $item->product->unit ?? 'Nos',
            ];
        })->toArray();

        $total = (float) $transaction->total;
        $paidAmount = (float) ($transaction->paid_amount ?? 0);
        $changeAmount = max(0, (float) ($transaction->change_amount ?? 0));

        // Field `base_*` (nilai dalam mata uang company) harus dikirim eksplisit.
        // ERPNext tidak menghitungnya sendiri untuk invoice yang dibuat lewat REST,
        // sehingga tanpa ini `base_paid_amount` tersimpan 0 — dan report bawaan ERP
        // yang membacanya (mis. kolom "Paid Amount" di POS Register) jadi jauh di
        // bawah omzet sebenarnya. POS ini transaksi dalam mata uang company
        // (conversion_rate = 1), jadi nilai base = nilai aslinya.
        $payments = [];
        if ($transaction->payment_method === 'mixed' && $transaction->payment_details) {
            foreach ($transaction->payment_details as $method => $amount) {
                $payments[] = [
                    'mode_of_payment' => $this->mapPaymentMethod($method),
                    'amount' => (float) $amount,
                    'base_amount' => (float) $amount,
                ];
            }
            // paid_amount = jumlah semua metode; mixed diasumsikan pas (tanpa kembalian)
            $docPaidAmount = array_sum(array_column($payments, 'amount'));
            $docChangeAmount = 0.0;
        } else {
            // Kirim jumlah yang benar-benar diterima (tunai bisa termasuk kembalian),
            // fallback ke total jika paid_amount tidak wajar (< total).
            $tendered = $paidAmount >= $total ? $paidAmount : $total;
            $payments[] = [
                'mode_of_payment' => $this->mapPaymentMethod($transaction->payment_method),
                'amount' => $tendered,
                'base_amount' => $tendered,
            ];
            $docPaidAmount = $tendered;
            $docChangeAmount = $changeAmount;
        }

        $defaultWarehouse = Warehouse::getDefault()?->name;

        $couponDiscount = (float) ($transaction->coupon_discount ?? 0);
        $baseDiscAmt = (float) $transaction->discount_amount;
        $baseDiscPct = (float) $transaction->discount_percent;

        // Potongan kupon HARUS dikirim eksplisit lewat discount_amount. ERP menerapkan
        // Pricing Rule kupon dari form JavaScript (`apply_pricing_rule`), bukan dari
        // validasi server — invoice yang dibuat lewat REST tidak pernah memicunya, jadi
        // `coupon_code` cuma tersimpan sebagai teks dan discount_amount tetap 0. Tanpa ini
        // grand_total tercatat harga penuh sementara pembayaran sudah dipotong: laporan
        // omzet (baca grand_total) jadi lebih besar dari laporan MOP (baca payments),
        // dan invoice menyisakan outstanding_amount palsu.
        $erpDiscPct = $couponDiscount > 0 ? 0 : $baseDiscPct;
        $erpDiscAmt = $baseDiscAmt + $couponDiscount;

        // Tetap kirim kode kuponnya sebagai jejak audit di dokumen ERP (tidak memicu
        // perhitungan apa pun, jadi tidak ada risiko diskon dobel).
        $erpCouponCode = null;
        if ($couponDiscount > 0 && $transaction->coupon_code) {
            $coupon = Coupon::where('code', $transaction->coupon_code)->first();
            $erpCouponCode = $coupon?->erp_coupon_name ?: null;
        }

        $payload = [
            'doctype' => 'POS Invoice',
            'naming_series' => Setting::get('erpnext_naming_series', 'ACC-PSINV-.YYYY.-'),
            'pos_profile' => $posProfile,
            'company' => $company,
            'set_warehouse' => $defaultWarehouse,
            'pos_class' => $transaction->pos_class,
            'posting_date' => $this->toLocal($transaction->created_at)->format('Y-m-d'),
            'posting_time' => $this->toLocal($transaction->created_at)->format('H:i:s'),
            'set_posting_time' => 1,
            'items' => $items,
            'payments' => $payments,
            'paid_amount' => $docPaidAmount,
            'base_paid_amount' => $docPaidAmount,
            'change_amount' => $docChangeAmount,
            'base_change_amount' => $docChangeAmount,
            // update_stock HARUS dikirim eksplisit 1. Kalau tidak dikirim, ERP mengambil
            // nilainya dari centang "Update Stock" di POS Profile — dan bila centang itu
            // mati, invoice terbit tanpa memotong stok sama sekali. Padahal stok lokal
            // sudah dikurangi PosController::checkout(), jadi stok POS dan ERP melenceng
            // makin jauh setiap transaksi. Penjualan POS memang serah-terima barang di
            // tempat (tidak ada Delivery Note menyusul seperti alur Delivery Order), jadi
            // invoice inilah yang berhak memotong stok.
            'update_stock' => 1,
            'apply_discount_on' => 'Net Total',
            'additional_discount_percentage' => $erpDiscPct,
            'discount_amount' => $erpDiscAmt,
            // Nomor invoice lokal dibawa sebagai po_no supaya satu transaksi POS bisa
            // dicocokkan balik ke dokumennya di ERP. Dipakai findPosInvoiceByLocalNo()
            // untuk mengenali invoice yang sudah terlanjur dibuat ketika respons POST-nya
            // tidak sampai (timeout), sehingga tidak dibuat dua kali.
            'po_no' => $transaction->invoice_no,
        ];

        // Customer spesifik (sudah di-push ke ERPNext) diutamakan.
        // Fallback ke walk-in customer dari POS Profile (disimpan via pullPosPaymentMethods).
        $erpCustomer = $transaction->customer?->erp_customer_name ?: null;
        $walkin = Setting::get('erpnext_walkin_customer', '');
        $payload['customer'] = $erpCustomer ?: $walkin;

        // Set `owner` dokumen ke kasir yang login (bukan user API key). Tanpa ini, ERP
        // mencatat semua invoice atas nama user API integrasi. email user lokal = email
        // ERP User (hasil sync dari POS Profile), dan Frappe mempertahankan owner yang
        // dikirim saat insert (`if not self.owner: self.owner = session.user`).
        $cashierEmail = $transaction->user?->email;
        if ($cashierEmail) {
            $payload['owner'] = $cashierEmail;
        }

        if ($priceList) {
            $payload['selling_price_list'] = $priceList;
        }

        if ($erpCouponCode) {
            $payload['coupon_code'] = $erpCouponCode;
        }

        $payload['taxes'] = $this->buildTaxesPayload($transaction, $company);

        return $payload;
    }

    private function buildTaxesPayload(Transaction $transaction, string $company): array
    {
        $taxes = [];
        $rowIndex = 1; // 1-based, used for On Previous Row references

        // ── Row 1: Product-level tax (from item tax_rate) ──────────────────
        $taxAccount = Setting::get('erpnext_tax_account', '');
        if ((float) $transaction->tax_amount > 0 && $taxAccount !== '') {
            $taxes[] = [
                'charge_type' => 'Actual',
                'account_head' => $taxAccount,
                'description' => 'Tax',
                'tax_amount' => (float) $transaction->tax_amount,
                'rate' => 0,
            ];
            $rowIndex++;
        }

        // ── Service Charge (Dine In only) ───────────────────────────────────
        if ((float) $transaction->service_charge_amount > 0) {
            $scType = Setting::get('service_charge_erp_type', 'On Net Total');
            $scAccount = Setting::get('service_charge_erp_account', '');

            if ($scAccount) {
                $row = [
                    'charge_type' => $scType,
                    'account_head' => $scAccount,
                    'description' => 'Service Charge ('.$transaction->service_charge_pct.'%)',
                ];

                if ($scType === 'Actual') {
                    $row['tax_amount'] = (float) $transaction->service_charge_amount;
                    $row['rate'] = 0;
                } elseif (in_array($scType, ['On Previous Row Total', 'On Previous Row Amount'])) {
                    $row['rate'] = (float) $transaction->service_charge_pct;
                    $row['row_id'] = (string) ($rowIndex - 1);
                } else {
                    // On Net Total / On Item Qty
                    $row['rate'] = (float) $transaction->service_charge_pct;
                }

                $taxes[] = $row;
                $rowIndex++;
            }
        }

        // ── PB1 / Pajak Daerah (Dine In only) ──────────────────────────────
        if ((float) $transaction->pb1_amount > 0) {
            $pb1Type = Setting::get('pb1_erp_type', 'On Net Total');
            $pb1Account = Setting::get('pb1_erp_account', '');

            if ($pb1Account) {
                $row = [
                    'charge_type' => $pb1Type,
                    'account_head' => $pb1Account,
                    'description' => 'PB1 ('.$transaction->pb1_pct.'%)',
                ];

                if ($pb1Type === 'Actual') {
                    $row['tax_amount'] = (float) $transaction->pb1_amount;
                    $row['rate'] = 0;
                } elseif (in_array($pb1Type, ['On Previous Row Total', 'On Previous Row Amount'])) {
                    $row['rate'] = (float) $transaction->pb1_pct;
                    $row['row_id'] = (string) ($rowIndex - 1);
                } else {
                    $row['rate'] = (float) $transaction->pb1_pct;
                }

                $taxes[] = $row;
            }
        }

        return $taxes;
    }

    private function mapPaymentMethod(string $method): string
    {
        // Jika payment_method sudah berupa nama ERPNext langsung (dari POS Profile),
        // gunakan apa adanya. Fallback ke mapping lama untuk data historis.
        $legacyMap = [
            'cash' => 'Cash',
            'card' => 'Credit Card',
            'transfer' => 'Bank Transfer',
            'qris' => 'QRIS',
        ];

        return $legacyMap[$method] ?? $method;
    }

    private function submitDoc(string $doctype, string $name): void
    {
        $this->client->put("/api/resource/{$doctype}/{$name}", [
            'json' => ['docstatus' => 1],
        ]);
    }

    /**
     * Resolve [paid_from, paid_to] accounts for a "Receive" Payment Entry.
     * paid_from = company's Default Receivable Account, paid_to = Mode of Payment's
     * default account for the company. Both can be overridden manually via Setting.
     */
    private function resolvePaymentAccounts(string $modeOfPayment, string $company): array
    {
        $paidToAccount = Setting::get('erp_pe_paid_to_account', '') ?: null;
        if (! $paidToAccount) {
            try {
                $resp = $this->client->get('/api/resource/Mode%20of%20Payment/'.rawurlencode($modeOfPayment));
                $data = json_decode($resp->getBody()->getContents(), true);
                foreach (($data['data']['accounts'] ?? []) as $acc) {
                    if (($acc['company'] ?? '') === $company && ! empty($acc['default_account'])) {
                        $paidToAccount = $acc['default_account'];
                        break;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Could not resolve Mode of Payment account', ['mode_of_payment' => $modeOfPayment, 'error' => $e->getMessage()]);
            }
        }

        $paidFromAccount = Setting::get('erp_pe_paid_from_account', '') ?: null;
        if (! $paidFromAccount) {
            try {
                $resp = $this->client->get('/api/resource/Company/'.rawurlencode($company), [
                    'query' => ['fields' => '["default_receivable_account"]'],
                ]);
                $data = json_decode($resp->getBody()->getContents(), true);
                $paidFromAccount = $data['data']['default_receivable_account'] ?? null;
            } catch (\Exception $e) {
                Log::warning('Could not resolve Company receivable account', ['company' => $company, 'error' => $e->getMessage()]);
            }
        }

        return [$paidFromAccount, $paidToAccount];
    }

    // =========================================================
    // PULL PRODUCTS FROM ERPNext
    // =========================================================
    public function pullProducts(int $limit = 100, int $start = 0): array
    {
        try {
            $fields = '["name","item_name","item_code","description","item_group","kategori","standard_rate","valuation_rate","stock_uom","is_sales_item","disabled","has_variants","image"]';
            // Tarik semua item (termasuk yang disabled) supaya item yang dinonaktifkan di
            // ERP ikut dinonaktifkan lokal. Filtering disabled ditangani di ErpSyncController.
            $filters = '[]';

            $response = $this->client->get('/api/resource/Item', [
                'timeout' => 120,
                'query' => [
                    'fields' => $fields,
                    'filters' => $filters,
                    'limit' => $limit,
                    'limit_start' => $start,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $items = $data['data'] ?? $data['message'] ?? [];

            // Overlay harga dari Price List jika dikonfigurasi
            $priceList = Setting::get('erpnext_price_list', '');
            if ($priceList && count($items) > 0) {
                $itemCodes = array_column($items, 'name');
                $priceMap = $this->fetchItemPricesMap($itemCodes, $priceList);

                foreach ($items as &$item) {
                    if (isset($priceMap[$item['name']])) {
                        $item['standard_rate'] = $priceMap[$item['name']];
                    }
                }
                unset($item);
            }

            // Barcode bukan field Item — ia child table 'Item Barcode', jadi tidak ikut
            // terbawa oleh list API di atas dan harus ditarik terpisah lalu ditempelkan.
            // Bila pengambilan barcode gagal, key 'barcode' sengaja tidak diisi sama sekali
            // supaya barcode lokal yang sudah ada tidak ikut terhapus.
            if (count($items) > 0) {
                $barcodeMap = $this->fetchItemBarcodesMap(array_column($items, 'name'));

                if ($barcodeMap !== null) {
                    foreach ($items as &$item) {
                        $item['barcode'] = $barcodeMap[$item['name']] ?? null;
                    }
                    unset($item);
                }
            }

            return ['success' => true, 'data' => $items];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // FETCH BARCODES FROM 'Item Barcode' CHILD TABLE
    // =========================================================
    /**
     * Child doctype tidak bisa di-GET langsung per dokumen, tapi bisa di-query sebagai
     * tabel dengan menyertakan parent=Item. Satu panggilan untuk semua item jauh lebih
     * hemat daripada GET /api/resource/Item/{code} per item.
     *
     * @return array<string,string>|null item_code => barcode, atau null bila gagal
     */
    private function fetchItemBarcodesMap(array $itemCodes): ?array
    {
        try {
            $response = $this->client->get('/api/resource/Item Barcode', [
                'timeout' => 120,
                'query' => [
                    'fields' => json_encode(['parent', 'barcode', 'idx']),
                    'filters' => json_encode([
                        ['parenttype', '=', 'Item'],
                        ['parent', 'in', $itemCodes],
                    ]),
                    'parent' => 'Item',
                    'limit_page_length' => 0,
                    // Satu item bisa punya beberapa barcode; idx terkecil dianggap utama.
                    'order_by' => 'idx asc',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $map = [];
            foreach ($data['data'] ?? [] as $row) {
                $parent = $row['parent'] ?? null;
                $barcode = trim((string) ($row['barcode'] ?? ''));

                if (! $parent || $barcode === '' || isset($map[$parent])) {
                    continue;
                }

                $map[$parent] = $barcode;
            }

            return $map;

        } catch (\Exception $e) {
            // Barcode bersifat pelengkap — kegagalan di sini tidak boleh menggagalkan sync produk.
            Log::warning('Failed to fetch Item Barcodes: '.$e->getMessage());

            return null;
        }
    }

    // =========================================================
    // DOWNLOAD PRODUCT IMAGE FROM ERPNext → local public/images/products/
    // =========================================================
    public function downloadProductImage(string $erpImagePath, string $itemCode): ?string
    {
        try {
            $dir = public_path('images/products');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $ext = strtolower(pathinfo($erpImagePath, PATHINFO_EXTENSION)) ?: 'jpg';
            $filename = Str::slug($itemCode).'.'.$ext;
            $dest = $dir.DIRECTORY_SEPARATOR.$filename;

            $response = $this->client->get($erpImagePath, ['stream' => false]);
            file_put_contents($dest, $response->getBody()->getContents());

            return '/images/products/'.$filename;

        } catch (\Exception $e) {
            Log::warning("Failed to download product image '{$erpImagePath}' for item '{$itemCode}': ".$e->getMessage());

            return null;
        }
    }

    // =========================================================
    // FETCH ITEM PRICES FROM PRICE LIST
    // =========================================================
    private function fetchItemPricesMap(array $itemCodes, string $priceList): array
    {
        try {
            $filters = json_encode([
                ['price_list', '=', $priceList],
                ['item_code', 'in', $itemCodes],
                ['selling', '=', 1],
            ]);

            $response = $this->client->get('/api/resource/Item Price', [
                'query' => [
                    'fields' => json_encode(['item_code', 'price_list_rate', 'valid_from']),
                    'filters' => $filters,
                    // Satu item bisa punya beberapa baris Item Price (per valid_from),
                    // jadi limit harus lebih besar dari jumlah item.
                    'limit_page_length' => 0,
                    // Urutkan terbaru dulu supaya baris valid_from paling baru diproses lebih awal.
                    'order_by' => 'valid_from desc',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $map = [];
            $chosenValidFrom = [];
            $today = date('Y-m-d');
            foreach ($data['data'] ?? [] as $row) {
                $code = $row['item_code'];
                $validFrom = $row['valid_from'] ?? '';

                // Abaikan harga yang valid_from-nya masih di masa depan (belum berlaku).
                if ($validFrom !== '' && $validFrom > $today) {
                    continue;
                }

                // Ambil baris dengan valid_from paling baru per item.
                if (! isset($map[$code]) || $validFrom > $chosenValidFrom[$code]) {
                    $map[$code] = (float) $row['price_list_rate'];
                    $chosenValidFrom[$code] = $validFrom;
                }
            }

            return $map;

        } catch (\Exception $e) {
            Log::warning("Failed to fetch Item Prices from price list '{$priceList}': ".$e->getMessage());

            return [];
        }
    }

    // =========================================================
    // PULL CUSTOMERS FROM ERPNext
    // =========================================================
    public function pullCustomers(int $limit = 100, int $start = 0): array
    {
        try {
            $response = $this->client->get('/api/resource/Customer', [
                'query' => [
                    'fields' => json_encode([
                        'name', 'customer_name', 'customer_type',
                        'email_id', 'mobile_no', 'disabled',
                    ]),
                    'filters' => json_encode([['disabled', '=', 0]]),
                    'limit' => $limit,
                    'limit_start' => $start,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return ['success' => true, 'data' => $data['data'] ?? $data['message'] ?? []];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pastikan mapping customer ke ERP benar, dan betulkan bila perlu. Dipakai
     * tombol "Sinkron ERP" di menu Customer supaya operator bisa memperbaiki
     * sendiri tanpa CLI. Status: synced (sudah benar) | fixed (mapping rusak
     * diarahkan ulang ke Customer ERP yang cocok) | linked (dipetakan ke yang
     * sudah ada) | pushed (dibuat baru) | failed.
     */
    public function reconcileCustomer(Customer $customer): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'status' => 'unconfigured', 'error' => 'ERP HPY belum dikonfigurasi.'];
        }

        // Sudah punya mapping dan masih valid → tidak ada yang perlu dilakukan.
        if (! empty($customer->erp_customer_name) && $this->erpRecordExists('Customer', $customer->erp_customer_name)) {
            return ['success' => true, 'status' => 'synced', 'docname' => $customer->erp_customer_name];
        }

        // Mapping rusak (menunjuk Customer ERP yang sudah tiada) → cari padanan by nama.
        if (! empty($customer->erp_customer_name)) {
            $match = $this->findCustomerByName($customer->name);
            if ($match) {
                $previous = $customer->erp_customer_name;
                $customer->update(['erp_customer_name' => $match, 'erp_last_sync' => now()]);
                $this->logSync('customer', $customer->id, $customer->code, 'success', ['relink' => [$previous, $match]], null, $match);

                return ['success' => true, 'status' => 'fixed', 'docname' => $match, 'previous' => $previous];
            }
            // Tidak ada padanan → Customer memang tak ada di ERP; kosongkan mapping
            // rusak lalu buat/pakai ulang lewat pushCustomer (idempoten).
            $customer->update(['erp_customer_name' => null]);
        }

        // Belum/kembali tanpa mapping → push (reuse bila sudah ada, create bila belum).
        $res = $this->pushCustomer($customer);
        if (empty($res['success'])) {
            $res['status'] = 'failed';
        } else {
            $res['status'] = ! empty($res['reused']) ? 'linked' : 'pushed';
        }

        return $res;
    }

    // =========================================================
    // PUSH CUSTOMER TO ERPNext
    // =========================================================
    public function pushCustomer(Customer $customer): array
    {
        // Idempoten 1: sudah terpetakan ke ERP → jangan sentuh. Mencegah pembuatan
        // ulang yang akan memicu duplikat "<nama> - 1" dan tidak mengubah mapping
        // yang sudah dipakai transaksi berjalan.
        if (! empty($customer->erp_customer_name)) {
            return ['success' => true, 'docname' => $customer->erp_customer_name];
        }

        // Idempoten 2: kalau Customer dengan nama ini SUDAH ada di ERP, pakai yang
        // itu alih-alih POST baru. Tanpa ini ERP tidak menolak melainkan membuat
        // duplikat bersuffix ("- 1", "- 2"), lalu mapping bisa nyangkut saat
        // duplikatnya dirapikan. findCustomerByName mengutamakan record tanpa suffix.
        $existing = $this->findCustomerByName($customer->name);
        if ($existing) {
            $customer->update(['erp_customer_name' => $existing, 'erp_last_sync' => now()]);
            $this->logSync('customer', $customer->id, $customer->code, 'success', ['reuse' => $existing], null, $existing);

            return ['success' => true, 'docname' => $existing, 'reused' => true];
        }

        $posClass = Setting::get('pos_class', '');

        $payload = [
            'doctype' => 'Customer',
            'customer_name' => $customer->name,
            'customer_type' => 'Individual',
            'customer_group' => 'Retail',
            'territory' => 'Indonesia',
            'email_id' => $customer->email,
        ];

        // Only include mobile_no if the value looks like a real phone number
        $phone = $customer->phone;
        if ($phone && preg_match('/^[+\d][\d\s\-().]{5,}$/', $phone)) {
            $payload['mobile_no'] = $phone;
        }

        if ($posClass !== '') {
            $payload['class'] = $posClass;
        }

        try {
            $response = $this->client->post('/api/resource/Customer', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;

            $customer->update([
                'erp_customer_name' => $docname,
                'erp_last_sync' => now(),
            ]);

            $this->logSync('customer', $customer->id, $customer->code, 'success', $payload, $data, $docname);

            return ['success' => true, 'docname' => $docname];

        } catch (RequestException $e) {
            $error = $this->extractError($e);
            $this->logSync('customer', $customer->id, $customer->code, 'failed', $payload, null, null, $error);

            return ['success' => false, 'error' => $error];
        }
    }

    // =========================================================
    // BULK SYNC PENDING TRANSACTIONS
    // =========================================================
    public function syncPendingTransactions(): array
    {
        $pending = Transaction::where('erp_sync_status', 'pending')
            ->where('status', 'completed')
            ->with(['items.product', 'customer'])
            ->latest()->get();

        $results = ['total' => $pending->count(), 'success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($pending as $transaction) {
            $result = $this->syncTransaction($transaction);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'invoice' => $transaction->invoice_no,
                    'error' => $result['error'],
                ];
            }
        }

        return $results;
    }

    // =========================================================
    // PULL STOCK FROM BIN (filtered by warehouse name)
    // =========================================================
    public function pullStockFromBin(string $warehouseName): array
    {
        try {
            $response = $this->client->get('/api/resource/Bin', [
                'timeout' => 120,
                'query' => [
                    'fields' => json_encode(['item_code', 'warehouse', 'actual_qty']),
                    'filters' => json_encode([['warehouse', '=', $warehouseName]]),
                    'limit_page_length' => 0,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return ['success' => true, 'data' => $data['data'] ?? []];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // STOCK OPNAME — Material Issue (actual berlebih dari sistem)
    // =========================================================
    public function createOpnameMaterialIssue(string $warehouseName, string $opnameDate, array $items): array
    {
        return $this->createOpnameStockEntry('Material Issue', $warehouseName, $opnameDate, $items, 's_warehouse');
    }

    // =========================================================
    // STOCK OPNAME — Material Receipt (actual kurang dari sistem)
    // =========================================================
    public function createOpnameMaterialReceipt(string $warehouseName, string $opnameDate, array $items): array
    {
        return $this->createOpnameStockEntry('Material Receipt', $warehouseName, $opnameDate, $items, 't_warehouse');
    }

    private function createOpnameStockEntry(string $type, string $warehouseName, string $opnameDate, array $items, string $warehouseField): array
    {
        try {
            $entryItems = array_map(fn ($item) => [
                'item_code' => $item['item_code'],
                'qty' => abs($item['qty']),
                'basic_rate' => $item['basic_rate'] ?? 0,
                $warehouseField => $warehouseName,
            ], $items);

            $response = $this->client->post('/api/resource/Stock Entry', [
                'json' => [
                    'stock_entry_type' => $type,
                    'purpose' => $type,
                    'posting_date' => $opnameDate,
                    'items' => $entryItems,
                    'remarks' => 'Stock Opname - '.$opnameDate,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $name = $data['data']['name'] ?? null;

            if (! $name) {
                return ['success' => false, 'error' => 'Stock Entry dibuat tapi name tidak ada di response'];
            }

            $this->submitDoc('Stock Entry', $name);

            return ['success' => true, 'name' => $name];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // GET WAREHOUSES FROM ERPNext
    // =========================================================
    public function getWarehouses(): array
    {
        try {
            $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY'));
            $filters = $company
                ? json_encode([['company', '=', $company], ['disabled', '=', 0]])
                : json_encode([['disabled', '=', 0]]);

            $response = $this->client->get('/api/resource/Warehouse', [
                'query' => [
                    'fields' => json_encode(['name', 'warehouse_name', 'warehouse_type', 'is_group', 'company', 'parent_warehouse']),
                    'filters' => $filters,
                    'limit' => 200,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return ['success' => true, 'data' => $data['data'] ?? []];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'data' => []];
        }
    }

    // =========================================================
    // GET PENDING IN-TRANSIT STOCK ENTRIES FROM ERPNext
    // =========================================================
    public function getPendingInTransitEntries(): array
    {
        try {
            $filters = json_encode([
                ['purpose', '=', 'Material Transfer'],
                ['docstatus', '=', 1],
            ]);

            $response = $this->client->get('/api/resource/Stock Entry', [
                'query' => [
                    'fields' => json_encode([
                        'name', 'posting_date', 'posting_time',
                        'from_warehouse', 'to_warehouse', 'remarks',
                    ]),
                    'filters' => $filters,
                    'limit' => 100,
                    'order_by' => 'posting_date desc',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return ['success' => true, 'data' => $data['data'] ?? []];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'data' => []];
        }
    }

    // =========================================================
    // GET STOCK ENTRY DETAIL FROM ERPNext
    // =========================================================
    public function getStockEntryDetail(string $name): array
    {
        try {
            $response = $this->client->get("/api/resource/Stock Entry/{$name}");
            $data = json_decode($response->getBody()->getContents(), true);

            return ['success' => true, 'data' => $data['data'] ?? []];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Penjaga transfer stok yang sudah punya Stock Entry di ERP. Mengembalikan hasil
     * siap-pakai bila POST tidak boleh diulang, atau null bila transfer ini memang
     * belum pernah dikirim.
     *
     * Transfer yang punya docname tapi belum 'synced' berarti dokumennya sudah ada di
     * ERP namun submitnya belum tuntas. POST ulang akan menggandakan mutasi stok, jadi
     * kasus itu dikembalikan sebagai kegagalan yang harus dibereskan di ERP — bukan
     * dilaporkan berhasil, karena stoknya memang belum benar-benar berpindah.
     */
    private function guardExistingStockEntry(StockTransfer $transfer): ?array
    {
        if (empty($transfer->erp_stock_entry)) {
            return null;
        }

        if ($transfer->erp_sync_status === 'synced') {
            return ['success' => true, 'docname' => $transfer->erp_stock_entry];
        }

        return [
            'success' => false,
            'docname' => $transfer->erp_stock_entry,
            'error' => 'Stock Entry '.$transfer->erp_stock_entry.' sudah dibuat di ERP HPY tapi belum tersubmit. '
                .'Selesaikan atau batalkan dokumen itu di ERP HPY — mengirim ulang dari sini akan menggandakan mutasi stok.',
        ];
    }

    // =========================================================
    // CREATE OUTGOING TRANSFER → ERPNext (Material Transfer to In-Transit)
    // =========================================================
    public function createOutgoingTransfer(StockTransfer $transfer): array
    {
        // Idempoten — Stock Entry yang sudah terbuat tidak dikirim ulang. Tanpa ini,
        // tombol Retry pada transfer yang sudah tersync memotong stok dua kali di ERP.
        if ($guard = $this->guardExistingStockEntry($transfer)) {
            return $guard;
        }

        $payload = [
            'doctype' => 'Stock Entry',
            'stock_entry_type' => 'Material Transfer',
            'purpose' => 'Material Transfer',
            'company' => Setting::get('erpnext_company', env('ERPNEXT_COMPANY')),
            'posting_date' => $this->localNow()->format('Y-m-d'),
            'posting_time' => $this->localNow()->format('H:i:s'),
            'set_posting_time' => 1,
            'remarks' => $transfer->notes,
            'items' => $transfer->items->map(function ($item) use ($transfer) {
                return [
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'qty' => $item->quantity,
                    'uom' => $item->unit,
                    's_warehouse' => $transfer->from_warehouse,
                    't_warehouse' => $transfer->to_warehouse,
                ];
            })->toArray(),
        ];

        try {
            $response = $this->client->post('/api/resource/Stock Entry', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;

            // Nomor dokumen disimpan SEBELUM submit. Kalau submitnya gagal dan docname
            // ikut hilang, retry akan membuat Stock Entry kedua dan stok ERP terpotong
            // dua kali sementara yang pertama tertinggal sebagai draft.
            if ($docname) {
                $transfer->update(['erp_stock_entry' => $docname]);
                $this->submitDoc('Stock Entry', $docname);
            }

            $transfer->update([
                'erp_stock_entry' => $docname,
                'erp_sync_status' => 'synced',
                'erp_sync_error' => null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // Kurangi stok dari warehouse pengirim
            $fromWarehouse = Warehouse::where('name', $transfer->from_warehouse)->first();
            if ($fromWarehouse) {
                foreach ($transfer->items as $item) {
                    if ($item->product_id) {
                        ProductStock::forProductWarehouse($item->product_id, $fromWarehouse->id)
                            ->decrementQty($item->quantity);
                        $item->product->decrement('stock', $item->quantity);
                    }
                }
            }

            $this->logSync('stock_transfer', $transfer->id, $transfer->transfer_no,
                'success', $payload, $data, $docname);

            return ['success' => true, 'docname' => $docname];

        } catch (RequestException $e) {
            $error = $this->extractError($e);

            $transfer->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);
            $this->logSync('stock_transfer', $transfer->id, $transfer->transfer_no,
                'failed', $payload, null, null, $error);

            return ['success' => false, 'error' => $error];
        }
    }

    // =========================================================
    // CREATE INCOMING RECEIPT → ERPNext (Material Transfer for Receive)
    // =========================================================
    public function createIncomingReceipt(StockTransfer $transfer): array
    {
        // Idempoten — lihat catatan di createOutgoingTransfer().
        if ($guard = $this->guardExistingStockEntry($transfer)) {
            return $guard;
        }

        $payload = [
            'doctype' => 'Stock Entry',
            'stock_entry_type' => 'Material Transfer',
            'purpose' => 'Material Transfer',
            'company' => Setting::get('erpnext_company', env('ERPNEXT_COMPANY')),
            'posting_date' => $this->localNow()->format('Y-m-d'),
            'posting_time' => $this->localNow()->format('H:i:s'),
            'set_posting_time' => 1,
            'remarks' => $transfer->notes,
            'items' => $transfer->items->map(function ($item) use ($transfer) {
                return [
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'qty' => $item->actual_quantity ?? $item->quantity,
                    'uom' => $item->unit,
                    's_warehouse' => $transfer->from_warehouse,
                    't_warehouse' => $transfer->to_warehouse,
                ];
            })->toArray(),
        ];

        try {
            $response = $this->client->post('/api/resource/Stock Entry', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;

            // Docname disimpan sebelum submit — lihat catatan di createOutgoingTransfer().
            if ($docname) {
                $transfer->update(['erp_stock_entry' => $docname]);
                $this->submitDoc('Stock Entry', $docname);
            }

            $transfer->update([
                'erp_stock_entry' => $docname,
                'erp_sync_status' => 'synced',
                'erp_sync_error' => null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // Update stok lokal per warehouse
            $toWarehouse = Warehouse::where('name', $transfer->to_warehouse)->first();
            foreach ($transfer->items as $item) {
                if ($item->product_id) {
                    $qty = $item->actual_quantity ?? $item->quantity;
                    $item->product->increment('stock', $qty);
                    if ($toWarehouse) {
                        ProductStock::forProductWarehouse($item->product_id, $toWarehouse->id)
                            ->incrementQty($qty);
                    }
                }
            }

            $this->logSync('stock_transfer', $transfer->id, $transfer->transfer_no,
                'success', $payload, $data, $docname);

            return ['success' => true, 'docname' => $docname];

        } catch (RequestException $e) {
            $error = $this->extractError($e);

            $transfer->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);
            $this->logSync('stock_transfer', $transfer->id, $transfer->transfer_no,
                'failed', $payload, null, null, $error);

            return ['success' => false, 'error' => $error];
        }
    }

    // =========================================================
    // GET SINGLE USER INFO FROM ERP HPY
    // =========================================================
    public function getUserInfo(string $email): array
    {
        try {
            $resp = $this->client->get('/api/resource/User/'.rawurlencode($email));
            $data = json_decode($resp->getBody()->getContents(), true);
            $erpUser = $data['data'] ?? null;

            if (! $erpUser) {
                return ['success' => false, 'error' => 'User tidak ditemukan di ERP HPY.'];
            }

            $roles = array_column($erpUser['roles'] ?? [], 'role');

            return [
                'success' => true,
                'full_name' => $erpUser['full_name'] ?? $email,
                'roles' => $roles,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // PULL PAYMENT METHODS FROM POS PROFILE
    // =========================================================
    public function pullPosPaymentMethods(): array
    {
        $posProfile = Setting::get('erpnext_pos_profile', '');
        if (! $posProfile) {
            return ['success' => false, 'error' => 'POS Profile belum dikonfigurasi.'];
        }

        try {
            $response = $this->client->get('/api/resource/POS Profile/'.rawurlencode($posProfile));
            $data = json_decode($response->getBody()->getContents(), true);
            $payments = $data['data']['payments'] ?? [];

            // Baris `payments` di POS Profile tidak membawa `type`; nilai itu ada di
            // master Mode of Payment. Tanpa ini mode tunai bernama bebas (mis.
            // "KAS STORE") jatuh ke 'General' dan kolom Nominal Bayar tidak muncul.
            $typeByName = $this->fetchModeOfPaymentTypes();

            $methods = collect($payments)->map(fn ($p) => [
                'mode_of_payment' => $p['mode_of_payment'],
                'type' => $typeByName[$p['mode_of_payment']] ?? ($p['type'] ?? 'General'),
            ])->values()->toArray();

            Setting::set('pos_payment_methods', json_encode($methods), 'erpnext');

            // Simpan default customer dari POS Profile sebagai walk-in customer
            $defaultCustomer = $data['data']['customer'] ?? '';
            if ($defaultCustomer) {
                Setting::set('erpnext_walkin_customer', $defaultCustomer, 'erpnext');
            }

            return ['success' => true, 'methods' => $methods, 'customer' => $defaultCustomer];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        }
    }

    // =========================================================
    // SYNC USERS FROM POS PROFILE
    // =========================================================
    public function syncUsersFromPosProfile(string $posProfile): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        try {
            // Ambil dokumen POS Profile
            $resp = $this->client->get('/api/resource/POS Profile/'.rawurlencode($posProfile));
            $data = json_decode($resp->getBody()->getContents(), true);
            $profile = $data['data'] ?? null;

            if (! $profile) {
                return ['success' => false, 'error' => "POS Profile '{$posProfile}' tidak ditemukan."];
            }

            $applicableUsers = $profile['applicable_for_users'] ?? [];

            if (empty($applicableUsers)) {
                return ['success' => false, 'error' => 'Tidak ada user yang diset di POS Profile ini. Tambahkan user di tab "Applicable for Users" pada POS Profile di ERP HPY.'];
            }

            $users = [];
            foreach ($applicableUsers as $row) {
                $email = $row['user'] ?? null;
                if (! $email) {
                    continue;
                }

                // Ambil detail user dari ERPNext
                try {
                    $userResp = $this->client->get('/api/resource/User/'.rawurlencode($email));
                    $userData = json_decode($userResp->getBody()->getContents(), true);
                    $erpUser = $userData['data'] ?? [];

                    $roles = $erpUser['roles'] ?? [];
                    $roleNames = array_column($roles, 'role');
                    $isSysAdmin = in_array('System Manager', $roleNames);
                    $isPosManager = in_array('Sales User', $roleNames);

                    $users[] = [
                        'email' => $email,
                        'full_name' => $erpUser['full_name'] ?? $email,
                        'role' => $isSysAdmin ? 'admin' : ($isPosManager ? 'manager' : 'cashier'),
                    ];
                } catch (\Exception $e) {
                    // Skip user yang tidak bisa diambil detailnya
                    $users[] = [
                        'email' => $email,
                        'full_name' => $email,
                        'role' => 'cashier',
                    ];
                }
            }

            return ['success' => true, 'users' => $users];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => 'Gagal mengambil POS Profile: '.$this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // VERIFY SYSTEM MANAGER (untuk Factory Reset)
    // =========================================================
    public function loginUser(string $email, string $password): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'erp_unavailable' => true, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        try {
            $client = new Client([
                'base_uri' => $this->baseUrl,
                'timeout' => 15,
                'verify' => false,
                'http_errors' => false,
            ]);

            $response = $client->post('/api/method/login', [
                'json' => ['usr' => $email, 'pwd' => $password],
            ]);

            $status = $response->getStatusCode();
            $data = json_decode($response->getBody()->getContents(), true);

            if ($status === 200 && ($data['message'] ?? '') === 'Logged In') {
                return [
                    'success' => true,
                    'full_name' => $data['full_name'] ?? $email,
                ];
            }

            return ['success' => false, 'error' => 'Email atau password ERP HPY salah.'];

        } catch (\Exception $e) {
            return ['success' => false, 'erp_unavailable' => true, 'error' => 'Tidak dapat menghubungi server ERP HPY: '.$e->getMessage()];
        }
    }

    public function verifySystemManager(string $username, string $password): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'ERP HPY URL belum dikonfigurasi.'];
        }

        try {
            // Gunakan cookie jar agar session ERPNext terbawa ke request berikutnya
            $jar = new CookieJar;
            $client = new Client([
                'base_uri' => $this->baseUrl,
                'timeout' => 15,
                'verify' => false,
                'cookies' => $jar,
            ]);

            // Step 1: Login ke ERPNext
            $loginResp = $client->post('/api/method/login', [
                'json' => ['usr' => $username, 'pwd' => $password],
            ]);

            $loginData = json_decode($loginResp->getBody()->getContents(), true);

            if (($loginData['message'] ?? '') !== 'Logged In') {
                return ['success' => false, 'error' => 'Login gagal. Username atau password salah.'];
            }

            $fullname = $loginData['full_name'] ?? $username;

            // Step 2: Ambil dokumen User (user selalu bisa baca dokumen dirinya sendiri)
            // Roles ada sebagai child table di dalam dokumen User
            $userResp = $client->get('/api/resource/User/'.rawurlencode($username));
            $userData = json_decode($userResp->getBody()->getContents(), true);
            $roles = $userData['data']['roles'] ?? [];
            $hasRole = collect($roles)->contains('role', 'System Manager');

            if (! $hasRole) {
                return [
                    'success' => false,
                    'error' => "User '{$username}' tidak memiliki role System Manager di ERP HPY.",
                ];
            }

            return ['success' => true, 'username' => $username, 'fullname' => $fullname];

        } catch (RequestException $e) {
            // ERPNext mengembalikan 401 untuk kredensial salah
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 401) {
                return ['success' => false, 'error' => 'Login gagal. Username atau password salah.'];
            }

            return ['success' => false, 'error' => 'Koneksi ERP HPY gagal: '.$e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error: '.$e->getMessage()];
        }
    }

    // =========================================================
    // GET ITEM PRICES FROM A SPECIFIC PRICE LIST
    // =========================================================
    public function getPriceListPrices(string $priceList): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        try {
            $all = [];
            $chosenValidFrom = [];
            $today = date('Y-m-d');
            $page = 0;
            $limit = 500;

            do {
                $response = $this->client->get('/api/resource/Item Price', [
                    'query' => [
                        'fields' => json_encode(['item_code', 'price_list_rate', 'valid_from']),
                        'filters' => json_encode([['price_list', '=', $priceList]]),
                        'order_by' => 'valid_from desc',
                        'limit_page_length' => $limit,
                        'limit_start' => $page * $limit,
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                $batch = $data['data'] ?? [];

                foreach ($batch as $row) {
                    if (empty($row['item_code'])) {
                        continue;
                    }
                    $code = $row['item_code'];
                    $validFrom = $row['valid_from'] ?? '';

                    // Lewati harga yang belum berlaku (valid_from di masa depan).
                    if ($validFrom !== '' && $validFrom > $today) {
                        continue;
                    }

                    // Ambil harga dengan valid_from paling baru per item.
                    if (! isset($all[$code]) || $validFrom > $chosenValidFrom[$code]) {
                        $all[$code] = (float) ($row['price_list_rate'] ?? 0);
                        $chosenValidFrom[$code] = $validFrom;
                    }
                }

                $page++;
            } while (count($batch) >= $limit);

            return ['success' => true, 'prices' => $all, 'count' => count($all)];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // CREATE SALES ORDER → ERPNext
    // =========================================================
    public function createSalesOrder(DeliveryOrder $order): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        $customer = $order->customer->erp_customer_name ?: $order->customer->name;
        if (empty($customer)) {
            return ['success' => false, 'error' => 'Customer belum memiliki nama ERP. Push customer ke ERP HPY terlebih dahulu.'];
        }

        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $currency = Setting::get('erpnext_currency', 'IDR');
        $priceList = Setting::get('erpnext_price_list', 'Standard Selling');
        $namingSeries = Setting::get('erp_so_naming_series', 'SAL-ORD-.YYYY.-');
        $defaultWh = Warehouse::getDefault()?->name;

        $items = $order->items->map(fn ($item) => [
            'item_code' => $item->product?->erp_item_code ?? $item->product_sku ?? $item->product_name,
            'item_name' => $item->product_name,
            'description' => $item->product_name,
            'qty' => (float) $item->qty,
            'rate' => (float) $item->price,
            'uom' => $item->product?->unit ?? 'Nos',
            'delivery_date' => $order->delivery_date->format('Y-m-d'),
            'warehouse' => $defaultWh ?? '',
        ])->toArray();

        $payload = [
            'doctype' => 'Sales Order',
            'naming_series' => $namingSeries,
            'customer' => $customer,
            'transaction_date' => $this->localNow()->format('Y-m-d'),
            'delivery_date' => $order->delivery_date->format('Y-m-d'),
            'order_type' => 'Sales',
            'company' => $company,
            'currency' => $currency,
            'conversion_rate' => 1,
            'selling_price_list' => $priceList,
            'price_list_currency' => $currency,
            'plc_conversion_rate' => 1,
            'set_warehouse' => $defaultWh ?? '',
            'po_no' => $order->order_no,
            'items' => $items,
            'remarks' => implode("\n", array_filter([
                'Order: '.$order->order_no,
                $order->billing_address ? 'Billing: '.$order->billing_address : null,
                $order->notes,
            ])),
        ];

        Log::info('DeliveryOrder SO payload', ['order' => $order->order_no, 'payload' => $payload]);

        try {
            $response = $this->client->post('/api/resource/Sales%20Order', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;
        } catch (ConnectException $e) {
            Log::warning('SO auto-sync: network unreachable', ['order' => $order->order_no]);

            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('SO create failed', ['order' => $order->order_no, 'raw' => $rawBody]);
            $error = $this->extractError($e);
            $order->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);

            return ['success' => false, 'error' => $error];
        }

        if ($docname) {
            try {
                $this->submitDoc('Sales Order', $docname);
            } catch (RequestException $e) {
                $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
                Log::error('SO submit failed', ['order' => $order->order_no, 'docname' => $docname, 'raw' => $rawBody]);
                $error = 'SO '.$docname.' dibuat tapi gagal submit: '.$this->extractError($e);
                $order->update([
                    'erp_sales_order' => $docname,
                    'erp_sync_status' => 'failed',
                    'erp_sync_error' => $error,
                ]);

                return ['success' => false, 'error' => $error, 'docname' => $docname];
            }
        }

        $order->update([
            'erp_sales_order' => $docname,
            'erp_sync_status' => 'synced',
            'erp_sync_error' => null,
        ]);

        return ['success' => true, 'sales_order' => $docname, 'docname' => $docname];
    }

    // =========================================================
    // CREATE SALES INVOICE → ERPNext
    // =========================================================

    /**
     * Terbitkan Sales Invoice dari Sales Order milik sebuah Delivery Order.
     *
     * Dipanggil begitu order punya pembayaran pertama (lihat DeliveryOrderController
     * dan DeliveryOrderPaymentController) — order tanpa pembayaran sengaja tidak
     * ditagihkan supaya tidak menumpuk piutang untuk order yang belum tentu jalan.
     *
     * update_stock sengaja 0: pengurangan stok ditangani Delivery Note saat barang
     * benar-benar dikirim (createDeliveryNote), bukan oleh invoice ini. Kalau
     * keduanya memotong stok, stok berkurang dua kali untuk satu pengiriman.
     */
    public function createSalesInvoice(DeliveryOrder $order): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        // Idempoten — satu DO hanya boleh punya satu invoice.
        if (! empty($order->erp_sales_invoice)) {
            return ['success' => true, 'sales_invoice' => $order->erp_sales_invoice, 'docname' => $order->erp_sales_invoice];
        }

        $soName = $order->erp_sales_order;
        if (empty($soName)) {
            return ['success' => false, 'error' => 'Sales Order ERP belum ada. Sync SO terlebih dahulu.'];
        }

        $customer = $order->customer->erp_customer_name ?: $order->customer->name;
        if (empty($customer)) {
            return ['success' => false, 'error' => 'Customer belum memiliki nama ERP. Push customer ke ERP HPY terlebih dahulu.'];
        }

        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $currency = Setting::get('erpnext_currency', 'IDR');
        $priceList = Setting::get('erpnext_price_list', 'Standard Selling');
        $namingSeries = Setting::get('erp_si_naming_series', 'ACC-SINV-.YYYY.-');
        $defaultWh = Warehouse::getDefault()?->name;

        // Baris SO dibutuhkan untuk mengisi so_detail. Tanpa itu ERP tidak menaikkan
        // per_billed di SO, sehingga order yang sama bisa ditagih dua kali.
        $soRows = $this->fetchSalesOrderItemRows($soName);

        $usedRows = [];
        $items = $order->items->map(function ($item) use ($soName, $soRows, &$usedRows, $defaultWh) {
            $itemCode = $item->product?->erp_item_code ?? $item->product_sku ?? $item->product_name;

            $soDetail = null;
            foreach ($soRows as $row) {
                if (($row['item_code'] ?? null) === $itemCode && ! in_array($row['name'], $usedRows, true)) {
                    $soDetail = $row['name'];
                    $usedRows[] = $row['name'];
                    break;
                }
            }

            return array_filter([
                'item_code' => $itemCode,
                'item_name' => $item->product_name,
                'description' => $item->product_name,
                'qty' => (float) $item->qty,
                'rate' => (float) $item->price,
                'uom' => $item->product?->unit ?? 'Nos',
                'warehouse' => $defaultWh ?? '',
                'sales_order' => $soName,
                'so_detail' => $soDetail,
            ], fn ($v) => $v !== null);
        })->toArray();

        $postingDate = $this->localNow()->format('Y-m-d');
        // Jatuh tempo tidak boleh mendahului tanggal posting.
        $dueDate = max($postingDate, $order->delivery_date->format('Y-m-d'));

        $payload = [
            'doctype' => 'Sales Invoice',
            'naming_series' => $namingSeries,
            'customer' => $customer,
            'posting_date' => $postingDate,
            'set_posting_time' => 1,
            'due_date' => $dueDate,
            'company' => $company,
            'currency' => $currency,
            'conversion_rate' => 1,
            'selling_price_list' => $priceList,
            'price_list_currency' => $currency,
            'plc_conversion_rate' => 1,
            'update_stock' => 0,
            'set_warehouse' => $defaultWh ?? '',
            'po_no' => $order->order_no,
            'items' => $items,
            'remarks' => implode("\n", array_filter([
                'Order: '.$order->order_no,
                $order->billing_address ? 'Billing: '.$order->billing_address : null,
                $order->notes,
            ])),
        ];

        Log::info('DeliveryOrder SI payload', ['order' => $order->order_no, 'payload' => $payload]);

        try {
            $response = $this->client->post('/api/resource/Sales%20Invoice', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;
        } catch (ConnectException $e) {
            Log::warning('SI auto-sync: network unreachable', ['order' => $order->order_no]);

            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('SI create failed', ['order' => $order->order_no, 'raw' => $rawBody]);
            $error = $this->extractError($e);
            $order->update(['erp_si_sync_error' => $error]);

            return ['success' => false, 'error' => $error];
        }

        if ($docname) {
            try {
                $this->submitDoc('Sales Invoice', $docname);
            } catch (RequestException $e) {
                $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
                Log::error('SI submit failed', ['order' => $order->order_no, 'docname' => $docname, 'raw' => $rawBody]);
                $error = 'Sales Invoice '.$docname.' dibuat tapi gagal submit: '.$this->extractError($e);
                $order->update([
                    'erp_sales_invoice' => $docname,
                    'erp_si_sync_error' => $error,
                ]);

                return ['success' => false, 'error' => $error, 'docname' => $docname];
            }
        }

        $order->update([
            'erp_sales_invoice' => $docname,
            'erp_si_sync_error' => null,
        ]);

        return ['success' => true, 'sales_invoice' => $docname, 'docname' => $docname];
    }

    /**
     * Baris item Sales Order (name + item_code) untuk dipetakan ke so_detail.
     * Gagal ambil bukan alasan membatalkan invoice — so_detail hanya dikosongkan.
     */
    private function fetchSalesOrderItemRows(string $soName): array
    {
        try {
            $resp = $this->client->get('/api/resource/Sales%20Order/'.rawurlencode($soName));
            $data = json_decode($resp->getBody()->getContents(), true);

            return array_map(
                fn ($row) => ['name' => $row['name'] ?? null, 'item_code' => $row['item_code'] ?? null],
                $data['data']['items'] ?? []
            );
        } catch (\Exception $e) {
            Log::warning('Gagal ambil baris Sales Order untuk so_detail', ['sales_order' => $soName, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Batalkan Sales Invoice sebuah Delivery Order beserta Payment Entry yang
     * menempel padanya. Urutannya wajib PE dulu — ERP menolak membatalkan invoice
     * yang masih direferensi pembayaran ter-submit.
     *
     * Sales Order-nya sengaja dibiarkan: pembatalan SI sudah mengembalikan
     * per_billed ke 0, dan SO yang tertinggal tidak berdampak ke laporan penjualan.
     */
    public function cancelSalesInvoiceForOrder(DeliveryOrder $order): array
    {
        if (empty($this->baseUrl) || empty($order->erp_sales_invoice)) {
            return ['success' => true, 'cancelled' => []];
        }

        $errors = [];
        $cancelled = [];

        $order->loadMissing('payments');
        foreach ($order->payments as $payment) {
            if (empty($payment->erp_payment_entry) || $payment->erp_sync_status === 'cancelled') {
                continue;
            }
            try {
                // Dokumen yang sudah batal di ERP tidak dibatalkan ulang — pembatalan
                // bisa berhenti separuh jalan (mis. koneksi putus setelah PE batal
                // tapi sebelum SI), dan percobaan berikutnya harus bisa melanjutkan
                // dari titik itu, bukan gagal karena langkah yang sudah selesai.
                if ($this->fetchDocStatus('Payment Entry', $payment->erp_payment_entry) !== 2) {
                    $this->cancelDoc('Payment Entry', $payment->erp_payment_entry);
                    $cancelled[] = $payment->erp_payment_entry;
                }
                $payment->update(['erp_sync_status' => 'cancelled']);
            } catch (RequestException $e) {
                $errors[] = 'Payment Entry '.$payment->erp_payment_entry.': '.$this->extractError($e);
            }
        }

        // Invoice hanya dibatalkan bila seluruh pembayarannya lepas — kalau tidak,
        // ERP pasti menolak dan kita berakhir dengan status lokal yang berbohong.
        if (! empty($errors)) {
            return ['success' => false, 'error' => implode('; ', $errors), 'cancelled' => $cancelled];
        }

        try {
            if ($this->fetchDocStatus('Sales Invoice', $order->erp_sales_invoice) !== 2) {
                $this->cancelDoc('Sales Invoice', $order->erp_sales_invoice);
                $cancelled[] = $order->erp_sales_invoice;
            }
        } catch (RequestException $e) {
            $error = 'Sales Invoice '.$order->erp_sales_invoice.': '.$this->extractError($e);
            $order->update(['erp_si_sync_error' => $error]);

            return ['success' => false, 'error' => $error, 'cancelled' => $cancelled];
        }

        $order->update(['erp_si_sync_error' => null]);

        return ['success' => true, 'cancelled' => $cancelled];
    }

    /** docstatus dokumen ERP: 0 draft, 1 submitted, 2 cancelled. null bila tak terbaca. */
    private function fetchDocStatus(string $doctype, string $name): ?int
    {
        try {
            $resp = $this->client->get("/api/resource/{$doctype}/".rawurlencode($name).'?fields='.urlencode(json_encode(['docstatus'])));
            $data = json_decode($resp->getBody()->getContents(), true);
            $status = $data['data']['docstatus'] ?? null;

            return $status === null ? null : (int) $status;
        } catch (\Exception $e) {
            // Tidak terbaca → biarkan pembatalan dicoba; ERP yang jadi penentu.
            return null;
        }
    }

    /**
     * True selama dokumen masih ada di ERP. Hanya mengembalikan false bila ERP
     * secara eksplisit bilang tidak ada (404 / DoesNotExist). Error lain
     * (jaringan, izin) dianggap "masih ada" supaya tidak memicu pembuatan DN
     * dobel saat ERP sekadar tak terjangkau.
     */
    private function erpDocExists(string $doctype, string $name): bool
    {
        try {
            $this->client->get("/api/resource/{$doctype}/".rawurlencode($name).'?fields='.urlencode(json_encode(['name'])));

            return true;
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 404) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return true;
        }
    }

    /** Wrapper publik erpDocExists untuk dipakai command rekonsiliasi. */
    public function erpRecordExists(string $doctype, string $name): bool
    {
        return $this->erpDocExists($doctype, $name);
    }

    /**
     * Cari Customer di ERP berdasarkan customer_name. Mengembalikan docname yang
     * paling tepat: utamakan yang namanya persis sama (record asli tanpa suffix
     * " - 1" yang ditambahkan ERP saat terjadi duplikat), kalau tidak ada ambil
     * yang terlama. null bila tak ada sama sekali.
     */
    public function findCustomerByName(string $customerName): ?string
    {
        try {
            $response = $this->client->get('/api/resource/Customer', [
                'query' => [
                    'fields' => '["name","customer_name","creation"]',
                    'filters' => json_encode([['customer_name', '=', $customerName]]),
                    'order_by' => 'creation asc',
                    'limit_page_length' => 50,
                ],
            ]);
            $rows = json_decode($response->getBody()->getContents(), true)['data'] ?? [];
            if (empty($rows)) {
                return null;
            }
            foreach ($rows as $r) {
                if (($r['name'] ?? null) === $customerName) {
                    return $r['name'];
                }
            }

            return $rows[0]['name'] ?? null;
        } catch (\Exception $e) {
            Log::warning('findCustomerByName gagal', ['customer' => $customerName, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function cancelDoc(string $doctype, string $name): void
    {
        $this->client->put("/api/resource/{$doctype}/".rawurlencode($name), [
            'json' => ['docstatus' => 2],
        ]);
    }

    // =========================================================
    // CREATE DELIVERY NOTE → ERPNext
    // =========================================================
    public function createDeliveryNote(DeliveryShipment $shipment): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        // Idempoten: DN yang sudah pernah dibuat (mis. tertahan sebagai draft karena
        // stok kurang) tidak dibuat ulang — cukup diselesaikan submit-nya, supaya sync
        // ulang tidak menumpuk Delivery Note baru untuk pengiriman yang sama.
        if (! empty($shipment->erp_delivery_note)) {
            // Tapi DN itu bisa sudah dihapus manual di ERP. Kalau benar hilang,
            // kosongkan referensinya supaya sync membuat DN baru — bukan terus
            // mencoba submit nomor yang sudah tiada (idempotency guard yang
            // kalau tidak begini akan mentok Draft selamanya).
            if ($this->erpDocExists('Delivery Note', $shipment->erp_delivery_note)) {
                return $this->submitOrKeepDraftDeliveryNote($shipment, $shipment->erp_delivery_note);
            }
            Log::warning('DN tersimpan tidak ada lagi di ERP, reset untuk dibuat ulang', [
                'shipment' => $shipment->id, 'dn' => $shipment->erp_delivery_note,
            ]);
            $shipment->update(['erp_delivery_note' => null, 'erp_sync_status' => 'pending', 'erp_sync_error' => null]);
        }

        $order = $shipment->order;
        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $currency = Setting::get('erpnext_currency', 'IDR');
        $priceList = Setting::get('erpnext_price_list', 'Standard Selling');
        $namingSeries = Setting::get('erp_dn_naming_series', 'MAT-DN-.YYYY.-');
        $customer = $order->customer->erp_customer_name ?: $order->customer->name;
        $defaultWh = Warehouse::getDefault()?->name;
        $soName = $order->erp_sales_order ?: null;

        // Fetch SO from ERPNext to get item row-names (so_detail) for proper SO fulfilment linking
        $soItemMap = []; // erp_item_code => so row name
        if ($soName) {
            try {
                $soResp = $this->client->get('/api/resource/Sales%20Order/'.rawurlencode($soName));
                $soData = json_decode($soResp->getBody()->getContents(), true);
                foreach (($soData['data']['items'] ?? []) as $soItem) {
                    $code = $soItem['item_code'] ?? '';
                    if ($code && ! isset($soItemMap[$code])) {
                        $soItemMap[$code] = $soItem['name'];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch SO for DN linking', ['so' => $soName, 'error' => $e->getMessage()]);
            }
        }

        // Item order per nama produk — sumber yang sama dengan payload Sales Order, jadi
        // kode item DN dijamin sama dengan baris SO walau baris pengiriman tak membawa SKU.
        $order->loadMissing('items.product');
        $orderItemByName = $order->items->keyBy('product_name');

        $items = collect($shipment->items ?? [])->map(function ($item) use ($defaultWh, $soName, $soItemMap, $orderItemByName) {
            // Resolve erp_item_code: prefer product lookup so it matches what was sent to the SO.
            // Shipment lama bisa tak menyimpan product_sku (form pengiriman hanya mengirim nama);
            // ambil dari item order bernama sama dulu. Pencarian nama produk hanya jalan terakhir:
            // nama bisa kembar (kode ERP lama yang sudah disabled tetap tersimpan sebagai produk
            // nonaktif), jadi produk aktif diutamakan — bukan sekadar baris pertama.
            $orderItem = $orderItemByName->get($item['product_name'] ?? '');
            $sku = ($item['product_sku'] ?? null) ?: $orderItem?->product_sku;
            $product = ($sku ? Product::where('sku', $sku)->first() : null) ?? $orderItem?->product;
            if (! $product && ! empty($item['product_name'])) {
                $product = Product::where('name', $item['product_name'])
                    ->orderByDesc('is_active')
                    ->orderByDesc('id')
                    ->first();
            }
            $itemCode = $product?->erp_item_code ?: ($product?->sku ?: ($sku ?: $item['product_name']));

            $row = [
                'item_code' => $itemCode,
                'item_name' => $item['product_name'],
                'description' => $item['product_name'],
                'qty' => (float) ($item['qty'] ?? 1),
                'rate' => (float) ($item['price'] ?? 0),
                'uom' => $product?->unit ?? 'Nos',
                'warehouse' => $defaultWh ?? '',
                'against_sales_order' => $soName ?? '',
            ];

            if ($soName && isset($soItemMap[$itemCode])) {
                $row['so_detail'] = $soItemMap[$itemCode];
            }

            return $row;
        })->filter(fn ($i) => $i['qty'] > 0)->values()->toArray();

        // Posting date mengikuti tanggal kirim shipment, bukan saat tombol sync ditekan.
        // Tanpa set_posting_time=1 HPYERP mengabaikan posting_date dan memakai jam
        // simpan/submit — DN yang tertahan draft lalu disubmit belakangan ikut bergeser
        // tanggalnya. Transaksi stok tidak boleh bertanggal depan, jadi dibatasi hari ini.
        $now = $this->localNow();
        $postingAt = $now;
        if ($shipment->delivery_date) {
            $shipAt = $this->toLocal($shipment->delivery_date);
            if ($shipAt->format('H:i') === '00:00') {
                $shipAt = $shipAt->setTime(12, 0); // jam kirim tidak diisi: anggap tengah hari
            }
            $postingAt = $shipAt->min($now);
        }

        $payload = [
            'doctype' => 'Delivery Note',
            'naming_series' => $namingSeries,
            'customer' => $customer,
            'posting_date' => $postingAt->format('Y-m-d'),
            'posting_time' => $postingAt->format('H:i:s'),
            'set_posting_time' => 1,
            'company' => $company,
            'currency' => $currency,
            'conversion_rate' => 1,
            'selling_price_list' => $priceList,
            'price_list_currency' => $currency,
            'plc_conversion_rate' => 1,
            'ignore_pricing_rule' => 1,
            'set_warehouse' => $defaultWh ?? '',
            'shipping_address' => $shipment->shipping_address,
            'items' => $items,
            'remarks' => implode("\n", array_filter([
                'Order: '.$order->order_no,
                'Penerima: '.$shipment->recipient_name,
                $shipment->recipient_phone ? 'Telp: '.$shipment->recipient_phone : null,
                $shipment->notes,
            ])),
        ];

        Log::info('DeliveryNote payload', ['shipment' => $shipment->id, 'payload' => $payload]);

        try {
            $response = $this->client->post('/api/resource/Delivery%20Note', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('DN create failed', ['shipment' => $shipment->id, 'raw' => $rawBody]);
            $error = $this->extractError($e);
            $shipment->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);

            return ['success' => false, 'error' => $error];
        }

        // DN baru masih draft (docstatus 0) — coba submit; kalau stok tak cukup,
        // biarkan sebagai draft dan laporkan statusnya.
        return $this->submitOrKeepDraftDeliveryNote($shipment, $docname);
    }

    /**
     * Selesaikan sebuah Delivery Note: coba submit supaya stok terpotong. Bila submit
     * gagal (paling sering karena stok tidak cukup), DN tetap tersimpan sebagai DRAFT
     * di HPY — nomornya dicatat supaya sync ulang menyelesaikan submit, bukan membuat
     * DN baru. Mengembalikan nomor + status ('Submitted'/'Draft') untuk diinfokan.
     */
    private function submitOrKeepDraftDeliveryNote(DeliveryShipment $shipment, ?string $docname): array
    {
        if (empty($docname)) {
            $shipment->update(['erp_sync_status' => 'failed', 'erp_sync_error' => 'Delivery Note gagal dibuat.']);

            return ['success' => false, 'error' => 'Delivery Note gagal dibuat.'];
        }

        // Sudah ter-submit sebelumnya → jangan submit ulang.
        if ($this->fetchDocStatus('Delivery Note', $docname) === 1) {
            $shipment->update(['erp_delivery_note' => $docname, 'erp_sync_status' => 'synced', 'erp_sync_error' => null]);

            return ['success' => true, 'delivery_note' => $docname, 'docname' => $docname, 'submitted' => true, 'status' => 'Submitted'];
        }

        try {
            $this->submitDoc('Delivery Note', $docname);
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::warning('DN submit ditahan sebagai draft', ['shipment' => $shipment->id, 'dn' => $docname, 'raw' => $rawBody]);
            $error = $this->extractError($e);
            $shipment->update(['erp_delivery_note' => $docname, 'erp_sync_status' => 'draft', 'erp_sync_error' => $error]);

            return [
                'success' => true,
                'delivery_note' => $docname,
                'docname' => $docname,
                'submitted' => false,
                'status' => 'Draft',
                'warning' => 'Delivery Note '.$docname.' dibuat sebagai DRAFT (belum ter-submit). Kemungkinan stok tidak cukup: '.$error,
            ];
        }

        $shipment->update(['erp_delivery_note' => $docname, 'erp_sync_status' => 'synced', 'erp_sync_error' => null]);

        return ['success' => true, 'delivery_note' => $docname, 'docname' => $docname, 'submitted' => true, 'status' => 'Submitted'];
    }

    // =========================================================
    // MODE OF PAYMENT LIST
    // =========================================================
    public function getModeOfPaymentList(): array
    {
        if (empty($this->baseUrl)) {
            return [];
        }
        $response = $this->client->get('/api/resource/Mode%20of%20Payment', [
            'query' => [
                'fields' => '["name","type"]',
                'filters' => '[["enabled","=",1]]',
                'limit' => 100,
            ],
        ]);
        $data = json_decode($response->getBody(), true);

        return collect($data['data'] ?? [])->pluck('name')->filter()->values()->toArray();
    }

    // CREATE PAYMENT ENTRY → ERPNext (linked ke Sales Order)
    // =========================================================
    public function createPaymentEntry(DeliveryOrderPayment $payment): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        // Idempoten — satu pembayaran hanya boleh punya satu Payment Entry. Tanpa ini,
        // pembayaran berstatus 'failed' yang sebetulnya sudah terbuat di ERP akan
        // dikirim ulang oleh DeliveryOrderController::confirm() (filternya != 'synced').
        if (! empty($payment->erp_payment_entry)) {
            return ['success' => true, 'docname' => $payment->erp_payment_entry];
        }

        $order = $payment->order;
        $customer = $order->customer->erp_customer_name ?: $order->customer->name;

        // Utamakan Sales Invoice: pembayaran yang menempel ke invoice tercatat sebagai
        // pelunasan piutang sehingga penjualannya ikut terhitung. Referensi ke Sales
        // Order hanya uang muka dan tidak menutup apa pun — dipakai sebagai cadangan
        // bila invoice belum sempat terbit (mis. pembuatannya gagal).
        $refDoctype = $order->erp_sales_invoice ? 'Sales Invoice' : 'Sales Order';
        $refName = $order->erp_sales_invoice ?: $order->erp_sales_order;

        if (empty($refName)) {
            return ['success' => false, 'error' => 'Sales Order ERP belum ada. Konfirmasi order dan sync SO terlebih dahulu.'];
        }
        if (empty($customer)) {
            return ['success' => false, 'error' => 'Customer belum memiliki nama ERP.'];
        }

        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $currency = Setting::get('erpnext_currency', 'IDR');
        $namingSeries = Setting::get('erp_pe_naming_series', 'ACC-PAY-.YYYY.-');

        $modeOfPayment = $this->mapPaymentMethod($payment->payment_method);

        [$paidFromAccount, $paidToAccount] = $this->resolvePaymentAccounts($modeOfPayment, $company);

        if (empty($paidToAccount)) {
            return ['success' => false, 'error' => "Mode of Payment \"$modeOfPayment\" belum punya akun default untuk company \"$company\" di ERP HPY (Mode of Payment > Accounts), atau set manual via Setting key erp_pe_paid_to_account."];
        }
        if (empty($paidFromAccount)) {
            return ['success' => false, 'error' => "Company \"$company\" belum punya Default Receivable Account di ERP HPY, atau set manual via Setting key erp_pe_paid_from_account."];
        }

        $payload = [
            'doctype' => 'Payment Entry',
            'naming_series' => $namingSeries,
            'payment_type' => 'Receive',
            'party_type' => 'Customer',
            'party' => $customer,
            'party_name' => $order->customer->name,
            'posting_date' => $payment->payment_date->format('Y-m-d'),
            // ERPNext mewajibkan Reference No + Reference Date untuk Payment Entry
            // bertipe Bank (Transfer/QRIS/Card). Selalu dikirim; untuk mode Cash
            // kedua field ini diabaikan ERP, jadi aman diisi tanpa syarat. Fallback
            // ke nomor DO bila user tidak mengisi No. Referensi supaya sync tak gagal.
            'reference_no' => $payment->reference_no ?: ('DO-'.$order->order_no),
            'reference_date' => $payment->payment_date->format('Y-m-d'),
            'mode_of_payment' => $modeOfPayment,
            'paid_amount' => (float) $payment->amount,
            'received_amount' => (float) $payment->amount,
            'company' => $company,
            'paid_from' => $paidFromAccount,
            'paid_to' => $paidToAccount,
            'paid_from_account_currency' => $currency,
            'paid_to_account_currency' => $currency,
            'source_exchange_rate' => 1,
            'target_exchange_rate' => 1,
            'references' => [
                [
                    'reference_doctype' => $refDoctype,
                    'reference_name' => $refName,
                    'allocated_amount' => (float) $payment->amount,
                ],
            ],
            'remarks' => implode("\n", array_filter([
                'Payment untuk DO: '.$order->order_no,
                $payment->reference_no ? 'Ref: '.$payment->reference_no : null,
                $payment->notes,
            ])),
        ];

        Log::info('PaymentEntry payload', ['payment_id' => $payment->id, 'order' => $order->order_no]);

        try {
            $response = $this->client->post('/api/resource/Payment%20Entry', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('PaymentEntry create failed', ['payment_id' => $payment->id, 'raw' => $rawBody]);
            $error = $this->extractError($e);
            $payment->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);

            return ['success' => false, 'error' => $error];
        }

        if ($docname) {
            try {
                $this->submitDoc('Payment Entry', $docname);
            } catch (RequestException $e) {
                $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
                Log::error('PaymentEntry submit failed', ['payment_id' => $payment->id, 'docname' => $docname, 'raw' => $rawBody]);
                $error = 'Payment Entry '.$docname.' dibuat tapi gagal submit: '.$this->extractError($e);
                $payment->update([
                    'erp_payment_entry' => $docname,
                    'erp_sync_status' => 'failed',
                    'erp_sync_error' => $error,
                ]);

                return ['success' => false, 'error' => $error, 'docname' => $docname];
            }
        }

        $payment->update([
            'erp_payment_entry' => $docname,
            'erp_sync_status' => 'synced',
            'erp_sync_error' => null,
        ]);

        return ['success' => true, 'payment_entry' => $docname, 'docname' => $docname];
    }

    // =========================================================
    // FETCH HISTORICAL POS INVOICES FROM ERPNext (Online Report)
    // =========================================================
    public function fetchPosInvoices(
        string $dateFrom,
        string $dateTo,
        string $posProfile = '',
        int $maxRecords = 1000,
        string $owner = ''
    ): array {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        try {
            $fields = json_encode([
                'name', 'posting_date', 'posting_time',
                'customer', 'customer_name',
                'grand_total', 'net_total', 'total_taxes_and_charges',
                'paid_amount', 'status', 'pos_profile', 'owner',
            ]);

            $filters = [
                ['posting_date', '>=', $dateFrom],
                ['posting_date', '<=', $dateTo],
                ['docstatus', '=', 1],
            ];

            if ($posProfile) {
                $filters[] = ['pos_profile', '=', $posProfile];
            }

            // Filter per kasir (owner dokumen = email ERP User kasir yang membuat invoice).
            if ($owner) {
                $filters[] = ['owner', '=', $owner];
            }

            $filtersJson = json_encode($filters);
            $allData = [];
            $start = 0;
            $batchSize = 500;

            do {
                $response = $this->client->get('/api/resource/POS Invoice', [
                    'query' => [
                        'fields' => $fields,
                        'filters' => $filtersJson,
                        'limit_start' => $start,
                        'limit_page_length' => $batchSize,
                        'order_by' => 'posting_date asc, posting_time asc',
                    ],
                ]);

                $body = json_decode($response->getBody()->getContents(), true);
                $batch = $body['data'] ?? [];
                $allData = array_merge($allData, $batch);
                $start += $batchSize;

            } while (count($batch) === $batchSize && count($allData) < $maxRecords);

            $truncated = count($batch) === $batchSize && count($allData) >= $maxRecords;

            return [
                'success' => true,
                'data' => $allData,
                'count' => count($allData),
                'truncated' => $truncated,
            ];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Keadaan terkini sekumpulan POS Invoice di ERP HPY, dipakai untuk mendeteksi
     * invoice yang dibatalkan/dihapus di ERP tetapi masih tercatat selesai di lokal.
     *
     * Nama yang tidak ada di hasil berarti dokumennya sudah dihapus di ERP.
     *
     * @param  array<int,string>  $names  nama POS Invoice
     * @return array{success:bool,data:array<string,array{docstatus:int,status:string}>,error?:string}
     */
    public function fetchPosInvoiceStates(array $names): array
    {
        $names = array_values(array_filter(array_unique($names)));

        if (empty($names)) {
            return ['success' => true, 'data' => []];
        }

        if (! $this->isConfigured()) {
            return ['success' => false, 'data' => [], 'error' => 'ERP HPY belum dikonfigurasi.'];
        }

        $states = [];

        try {
            foreach (array_chunk($names, 100) as $chunk) {
                $response = $this->client->get('/api/resource/POS Invoice', [
                    'query' => [
                        'fields' => json_encode(['name', 'docstatus', 'status']),
                        'filters' => json_encode([['name', 'in', $chunk]]),
                        'limit_page_length' => 0,
                    ],
                    'timeout' => 15,
                ]);

                $rows = json_decode($response->getBody()->getContents(), true)['data'] ?? [];

                foreach ($rows as $row) {
                    $states[$row['name']] = [
                        'docstatus' => (int) ($row['docstatus'] ?? 0),
                        'status' => (string) ($row['status'] ?? ''),
                    ];
                }
            }

            return ['success' => true, 'data' => $states];

        } catch (ConnectException $e) {
            return ['success' => false, 'data' => [], 'error' => 'ERP HPY tidak dapat dihubungi.'];
        } catch (RequestException $e) {
            return ['success' => false, 'data' => [], 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Ambil report "POS Register" dari ERP HPY — satu baris per POS Invoice, lengkap
     * dengan metode bayarnya. Invoice split payment memakai mode gabungan, mis. "BCA QR, CASH".
     *
     * Kolom yang dikembalikan report: posting_date, pos_invoice, customer, pos_profile,
     * owner, grand_total, paid_amount, mode_of_payment, is_return.
     *
     * CATATAN: jangan pakai kolom `paid_amount` untuk penjumlahan. Kolom itu membaca
     * field `base_paid_amount`, yang bernilai 0 pada invoice hasil sync app ini,
     * sehingga totalnya jauh di bawah omzet sebenarnya. Pakai `grand_total`, yang
     * selalu sama persis dengan nilai di doctype POS Invoice.
     *
     * @return array{success:bool,data?:array<int,array>,error?:string}
     */
    public function fetchPosRegister(
        string $dateFrom,
        string $dateTo,
        string $posProfile = '',
        string $owner = ''
    ): array {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        if (! $company) {
            return ['success' => false, 'error' => 'Company ERP HPY belum diset.'];
        }

        $filters = [
            'company' => $company,
            'from_date' => $dateFrom,
            'to_date' => $dateTo,
        ];

        if ($posProfile) {
            $filters['pos_profile'] = $posProfile;
        }

        // Filter per kasir (owner dokumen = email ERP User kasir yang membuat invoice).
        if ($owner) {
            $filters['owner'] = $owner;
        }

        try {
            $response = $this->client->get('/api/method/frappe.desk.query_report.run', [
                'query' => [
                    'report_name' => 'POS Register',
                    'filters' => json_encode($filters),
                    'ignore_prepared_report' => 1,
                ],
                // Script Report tanpa paging: satu rentang = satu respons. Terukur
                // 1 tahun ≈ 2,2 MB / 23 detik, jadi beri ruang lebih dari timeout default.
                'timeout' => 180,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $rows = $body['message']['result'] ?? null;

            if (! is_array($rows)) {
                return ['success' => false, 'error' => 'Report POS Register tidak mengembalikan data.'];
            }

            // Report bisa menyelipkan baris total/kosong — ambil yang punya nomor invoice.
            $rows = array_values(array_filter($rows, fn ($r) => is_array($r) && ! empty($r['pos_invoice'])));

            return ['success' => true, 'data' => $rows];

        } catch (ConnectException $e) {
            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Agregasi pembayaran per mode_of_payment untuk sekumpulan POS Invoice.
     *
     * Query ke PARENT doctype "POS Invoice" sambil mengambil field child
     * "Sales Invoice Payment" lewat join (`tabSales Invoice Payment`.fieldname).
     * Child doctype TIDAK boleh diakses langsung via /api/resource — Frappe
     * menolaknya dengan PermissionError (bahkan untuk Administrator).
     *
     * @param  string[]  $invoiceNames
     * @return array{success:bool,data?:array<int,array{mode_of_payment:string,count:int,total:float}>,error?:string}
     */
    public function fetchPosPaymentSummary(array $invoiceNames): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        $names = array_values(array_filter($invoiceNames));
        if (empty($names)) {
            return ['success' => true, 'data' => []];
        }

        $fields = json_encode([
            '`tabSales Invoice Payment`.mode_of_payment',
            '`tabSales Invoice Payment`.amount',
        ]);
        $summary = []; // mode_of_payment => ['count'=>int, 'total'=>float]

        try {
            foreach (array_chunk($names, 100) as $chunk) {
                $filters = json_encode([
                    ['name', 'in', $chunk],
                    ['docstatus', '=', 1],
                ]);

                $start = 0;
                $batchSize = 500;
                do {
                    $response = $this->client->get('/api/resource/POS Invoice', [
                        'query' => [
                            'fields' => $fields,
                            'filters' => $filters,
                            'limit_start' => $start,
                            'limit_page_length' => $batchSize,
                        ],
                    ]);

                    $body = json_decode($response->getBody()->getContents(), true);
                    $rows = $body['data'] ?? [];

                    foreach ($rows as $row) {
                        // Invoice tanpa pembayaran mengembalikan child kosong lewat join — skip
                        if (empty($row['mode_of_payment'])) {
                            continue;
                        }
                        $mode = $row['mode_of_payment'] ?: 'Lainnya';
                        if (! isset($summary[$mode])) {
                            $summary[$mode] = ['count' => 0, 'total' => 0.0];
                        }
                        $summary[$mode]['count']++;
                        $summary[$mode]['total'] += (float) ($row['amount'] ?? 0);
                    }

                    $start += $batchSize;
                } while (count($rows) === $batchSize);
            }

            $data = [];
            foreach ($summary as $mode => $agg) {
                $data[] = [
                    'mode_of_payment' => $mode,
                    'count' => $agg['count'],
                    'total' => $agg['total'],
                ];
            }
            usort($data, fn ($a, $b) => $b['total'] <=> $a['total']);

            return ['success' => true, 'data' => $data];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // POS SHIFT — Buka/Tutup Kasir (POS Opening/Closing Entry)
    // =========================================================

    /** Nama-nama Mode of Payment bertipe Cash (untuk perlakuan kembalian). */
    /** Peta nama Mode of Payment => type (Cash/Bank/General). Kosong bila gagal. */
    public function fetchModeOfPaymentTypes(): array
    {
        try {
            $resp = $this->client->get('/api/resource/Mode of Payment', [
                'query' => [
                    'fields' => json_encode(['name', 'type']),
                    'limit_page_length' => 0,
                ],
            ]);
            $data = json_decode($resp->getBody()->getContents(), true)['data'] ?? [];

            $map = [];
            foreach ($data as $row) {
                if (! empty($row['name'])) {
                    $map[$row['name']] = $row['type'] ?? 'General';
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('fetchModeOfPaymentTypes gagal: '.$e->getMessage());

            return [];
        }
    }

    public function getCashModeNames(): array
    {
        try {
            $resp = $this->client->get('/api/resource/Mode of Payment', [
                'query' => [
                    'fields' => json_encode(['name']),
                    'filters' => json_encode([['type', '=', 'Cash']]),
                    'limit_page_length' => 0,
                ],
            ]);
            $data = json_decode($resp->getBody()->getContents(), true)['data'] ?? [];
            $names = array_column($data, 'name');

            return $names ?: ['Cash'];
        } catch (\Throwable $e) {
            Log::warning('getCashModeNames gagal: '.$e->getMessage());

            return ['Cash'];
        }
    }

    /** Cari POS Opening Entry berstatus Open milik kasir (email). Null bila tidak ada. */
    public function findOpenPosOpeningEntry(string $userEmail): ?string
    {
        $posProfile = Setting::get('erpnext_pos_profile', '');
        try {
            $resp = $this->client->get('/api/resource/POS Opening Entry', [
                'query' => [
                    'fields' => json_encode(['name']),
                    'filters' => json_encode([
                        ['status', '=', 'Open'],
                        ['docstatus', '=', 1],   // exclude opening entry yang sudah dibatalkan (status bisa tetap "Open")
                        ['user', '=', $userEmail],
                        ['pos_profile', '=', $posProfile],
                    ]),
                    'order_by' => 'creation desc',
                    'limit_page_length' => 1,
                ],
            ]);
            $data = json_decode($resp->getBody()->getContents(), true)['data'] ?? [];

            return $data[0]['name'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Buat + submit POS Opening Entry untuk kasir.
     * $periodStart (Y-m-d H:i:s) dipakai saat menyusulkan shift yang dibuka offline,
     * supaya waktu buka di ERP sama dengan waktu buka sebenarnya.
     */
    public function createPosOpeningEntry(string $userEmail, float $openingCash, ?string $periodStart = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'ERP HPY belum dikonfigurasi.'];
        }
        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $posProfile = Setting::get('erpnext_pos_profile', '');
        if (! $posProfile) {
            return ['success' => false, 'error' => 'POS Profile belum diset.'];
        }

        $cashMode = $this->getCashModeNames()[0] ?? 'Cash';
        $now = $this->localNow();
        $start = $periodStart ?: $now->format('Y-m-d H:i:s');

        $payload = [
            'doctype' => 'POS Opening Entry',
            'period_start_date' => $start,
            'posting_date' => substr($start, 0, 10),
            'set_posting_date' => 1,
            'company' => $company,
            'pos_profile' => $posProfile,
            'user' => $userEmail,
            'balance_details' => [
                ['mode_of_payment' => $cashMode, 'opening_amount' => $openingCash],
            ],
        ];

        try {
            $resp = $this->client->post('/api/resource/POS Opening Entry', ['json' => $payload]);
            $name = json_decode($resp->getBody()->getContents(), true)['data']['name'] ?? null;
            if ($name) {
                $this->submitDoc('POS Opening Entry', $name);
            }

            return ['success' => true, 'docname' => $name];
        } catch (ConnectException $e) {
            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        }
    }

    /**
     * Rekonsiliasi shift: kumpulkan POS Invoice kasir yang BELUM terkonsolidasi
     * pada rentang tanggal, lalu hitung expected per metode.
     * Kas: expected = opening + Σamount_tunai − Σkembalian (amount tunai = uang diterima).
     *
     * @return array{success:bool,invoices?:array,modes?:array,totals?:array,error?:string}
     */
    public function getShiftReconciliation(string $userEmail, string $dateFrom, string $dateTo, float $openingCash): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'ERP HPY belum dikonfigurasi.'];
        }
        $posProfile = Setting::get('erpnext_pos_profile', '');

        try {
            $resp = $this->client->get('/api/resource/POS Invoice', [
                'query' => [
                    'fields' => json_encode([
                        'name', 'posting_date', 'customer', 'grand_total', 'net_total', 'total_qty', 'change_amount',
                    ]),
                    'filters' => json_encode([
                        ['posting_date', '>=', $dateFrom],
                        ['posting_date', '<=', $dateTo],
                        ['docstatus', '=', 1],
                        ['pos_profile', '=', $posProfile],
                        ['owner', '=', $userEmail],
                        ['consolidated_invoice', 'is', 'not set'],
                    ]),
                    'order_by' => 'posting_date asc, posting_time asc',
                    'limit_page_length' => 0,
                ],
            ]);
            $invoices = json_decode($resp->getBody()->getContents(), true)['data'] ?? [];
        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        }

        $names = array_column($invoices, 'name');
        $totalChange = array_sum(array_map(fn ($i) => (float) ($i['change_amount'] ?? 0), $invoices));
        $grandTotal = array_sum(array_map(fn ($i) => (float) ($i['grand_total'] ?? 0), $invoices));
        $netTotal = array_sum(array_map(fn ($i) => (float) ($i['net_total'] ?? 0), $invoices));
        $totalQty = array_sum(array_map(fn ($i) => (float) ($i['total_qty'] ?? 0), $invoices));

        // Total pembayaran (gross) per mode.
        $paySummary = $names ? $this->fetchPosPaymentSummary($names) : ['success' => true, 'data' => []];
        if (! $paySummary['success']) {
            return ['success' => false, 'error' => $paySummary['error']];
        }

        $cashModes = $this->getCashModeNames();
        $modes = [];
        foreach ($paySummary['data'] as $row) {
            $mode = $row['mode_of_payment'];
            $isCash = in_array($mode, $cashModes, true);
            $opening = $isCash ? $openingCash : 0.0;
            // Kas: kurangi kembalian dari uang diterima; tambah modal awal.
            $expected = (float) $row['total'] - ($isCash ? $totalChange : 0.0) + $opening;
            $modes[] = [
                'mode_of_payment' => $mode,
                'is_cash' => $isCash,
                'opening_amount' => $opening,
                'expected_amount' => round($expected, 2),
            ];
        }

        // Pastikan mode kas tetap muncul walau belum ada transaksi tunai (untuk setor modal awal).
        $hasCash = collect($modes)->contains('is_cash', true);
        if (! $hasCash && $openingCash > 0) {
            $modes[] = [
                'mode_of_payment' => $cashModes[0] ?? 'Cash',
                'is_cash' => true,
                'opening_amount' => $openingCash,
                'expected_amount' => round($openingCash, 2),
            ];
        }

        return [
            'success' => true,
            'invoices' => $invoices,
            'modes' => $modes,
            'totals' => [
                'grand_total' => round($grandTotal, 2),
                'net_total' => round($netTotal, 2),
                'total_qty' => $totalQty,
                'invoice_count' => count($invoices),
                'total_change' => round($totalChange, 2),
            ],
        ];
    }

    /**
     * Buat + submit POS Closing Entry dari hasil rekonsiliasi + jumlah hitung kasir.
     *
     * @param  array  $recon  hasil getShiftReconciliation()
     * @param  array<string,float>  $countedByMode  nominal hitung fisik per mode (mode kas dari kasir)
     */
    public function createPosClosingEntry(string $openingName, string $userEmail, string $periodStart, array $recon, array $countedByMode): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'ERP HPY belum dikonfigurasi.'];
        }
        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $posProfile = Setting::get('erpnext_pos_profile', '');
        $now = $this->localNow();

        $posTransactions = array_map(fn ($inv) => [
            'pos_invoice' => $inv['name'],
            'posting_date' => $inv['posting_date'],
            'customer' => $inv['customer'],
            'grand_total' => (float) $inv['grand_total'],
        ], $recon['invoices'] ?? []);

        $reconciliation = [];
        foreach ($recon['modes'] ?? [] as $m) {
            $expected = (float) $m['expected_amount'];
            // Non-kas default = expected; kas = jumlah hitung kasir.
            $closing = $m['is_cash']
                ? (float) ($countedByMode[$m['mode_of_payment']] ?? $expected)
                : (float) ($countedByMode[$m['mode_of_payment']] ?? $expected);
            $reconciliation[] = [
                'mode_of_payment' => $m['mode_of_payment'],
                'opening_amount' => (float) $m['opening_amount'],
                'expected_amount' => $expected,
                'closing_amount' => $closing,
                'difference' => round($closing - $expected, 2),
            ];
        }

        $payload = [
            'doctype' => 'POS Closing Entry',
            'pos_opening_entry' => $openingName,
            'period_start_date' => $periodStart,
            'period_end_date' => $now->format('Y-m-d H:i:s'),
            'posting_date' => $now->format('Y-m-d'),
            'company' => $company,
            'pos_profile' => $posProfile,
            'user' => $userEmail,
            'pos_transactions' => $posTransactions,
            'payment_reconciliation' => $reconciliation,
            'grand_total' => (float) ($recon['totals']['grand_total'] ?? 0),
            'net_total' => (float) ($recon['totals']['net_total'] ?? 0),
            'total_quantity' => (float) ($recon['totals']['total_qty'] ?? 0),
        ];

        Log::info('POS Closing payload', ['opening' => $openingName, 'user' => $userEmail, 'invoices' => count($posTransactions)]);

        try {
            $resp = $this->client->post('/api/resource/POS Closing Entry', ['json' => $payload]);
            $name = json_decode($resp->getBody()->getContents(), true)['data']['name'] ?? null;
            if ($name) {
                $this->submitDoc('POS Closing Entry', $name);
            }

            return ['success' => true, 'docname' => $name];
        } catch (ConnectException $e) {
            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        }
    }

    /**
     * Matriks pembayaran per tanggal × mode_of_payment.
     *
     * Query ke PARENT doctype "POS Invoice" (bukan child "Sales Invoice Payment",
     * yang ditolak Frappe dengan PermissionError), sambil menarik field child
     * lewat join dan posting_date dari parent untuk mengelompokkan per tanggal.
     *
     * @param  array<string,string>  $nameToDate  [nama POS Invoice => posting_date]
     * @return array{success:bool,data?:array{modes:array<int,string>,matrix:array<string,array<string,array{count:int,total:float}>>},error?:string}
     */
    public function fetchPosPaymentMatrix(array $nameToDate): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        $names = array_keys($nameToDate);
        if (empty($names)) {
            return ['success' => true, 'data' => ['modes' => [], 'matrix' => []]];
        }

        $fields = json_encode([
            'posting_date',
            'owner',
            '`tabSales Invoice Payment`.mode_of_payment',
            '`tabSales Invoice Payment`.amount',
        ]);
        $matrix = []; // date => mode => ['count'=>int, 'total'=>float]
        $byCashier = []; // owner => mode => ['count'=>int, 'total'=>float]
        $modeTotals = []; // mode => total (untuk sorting kolom)

        try {
            foreach (array_chunk($names, 100) as $chunk) {
                $filters = json_encode([
                    ['name', 'in', $chunk],
                    ['docstatus', '=', 1],
                ]);

                $start = 0;
                $batchSize = 500;
                do {
                    $response = $this->client->get('/api/resource/POS Invoice', [
                        'query' => [
                            'fields' => $fields,
                            'filters' => $filters,
                            'limit_start' => $start,
                            'limit_page_length' => $batchSize,
                        ],
                    ]);

                    $body = json_decode($response->getBody()->getContents(), true);
                    $rows = $body['data'] ?? [];

                    foreach ($rows as $row) {
                        // Baris tanpa pembayaran (mode kosong) di-skip agar tak mengotori matriks
                        if (empty($row['mode_of_payment'])) {
                            continue;
                        }
                        $date = $row['posting_date'] ?? null;
                        if ($date === null) {
                            continue;
                        }
                        $mode = $row['mode_of_payment'] ?: 'Lainnya';
                        $amount = (float) ($row['amount'] ?? 0);

                        if (! isset($matrix[$date][$mode])) {
                            $matrix[$date][$mode] = ['count' => 0, 'total' => 0.0];
                        }
                        $matrix[$date][$mode]['count']++;
                        $matrix[$date][$mode]['total'] += $amount;

                        $owner = $row['owner'] ?? 'Lainnya';
                        if (! isset($byCashier[$owner][$mode])) {
                            $byCashier[$owner][$mode] = ['count' => 0, 'total' => 0.0];
                        }
                        $byCashier[$owner][$mode]['count']++;
                        $byCashier[$owner][$mode]['total'] += $amount;

                        $modeTotals[$mode] = ($modeTotals[$mode] ?? 0) + $amount;
                    }

                    $start += $batchSize;
                } while (count($rows) === $batchSize);
            }

            arsort($modeTotals);
            $modes = array_keys($modeTotals);
            ksort($matrix);
            ksort($byCashier);

            return ['success' => true, 'data' => ['modes' => $modes, 'matrix' => $matrix, 'by_cashier' => $byCashier]];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function fetchPosInvoiceDetail(string $name): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        try {
            $response = $this->client->get('/api/resource/POS Invoice/'.rawurlencode($name));
            $body = json_decode($response->getBody()->getContents(), true);
            $doc = $body['data'] ?? null;

            if (! $doc) {
                return ['success' => false, 'error' => "POS Invoice '{$name}' tidak ditemukan."];
            }

            return ['success' => true, 'data' => $doc];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Ambil status Sales Invoice + Delivery Note sebuah Delivery Order dari ERP HPY.
     *
     * Dipakai Laporan DO Lokal untuk verifikasi ke HPY: apakah invoice sudah terbit
     * & lunas, dan apakah barang sudah diterbitkan Delivery Note-nya. Sumber referensi
     * lokal: delivery_orders.erp_sales_invoice (satu) dan delivery_shipments.erp_delivery_note
     * (bisa banyak — satu DO bisa dikirim beberapa kali).
     */
    public function fetchDeliveryOrderErpStatus(DeliveryOrder $order): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        $statusLabel = [0 => 'Draft', 1 => 'Submitted', 2 => 'Dibatalkan'];

        try {
            $salesInvoice = null;
            if (! empty($order->erp_sales_invoice)) {
                $fields = ['name', 'status', 'docstatus', 'grand_total', 'outstanding_amount', 'per_billed', 'posting_date'];
                $resp = $this->client->get(
                    '/api/resource/Sales Invoice/'.rawurlencode($order->erp_sales_invoice)
                    .'?fields='.urlencode(json_encode($fields))
                );
                $doc = json_decode($resp->getBody()->getContents(), true)['data'] ?? null;
                if ($doc) {
                    $salesInvoice = [
                        'name' => $doc['name'] ?? $order->erp_sales_invoice,
                        'status' => $doc['status'] ?? null,
                        'docstatus' => (int) ($doc['docstatus'] ?? 0),
                        'docstatus_label' => $statusLabel[(int) ($doc['docstatus'] ?? 0)] ?? '-',
                        'grand_total' => (float) ($doc['grand_total'] ?? 0),
                        'outstanding' => (float) ($doc['outstanding_amount'] ?? 0),
                        'per_billed' => (float) ($doc['per_billed'] ?? 0),
                        'posting_date' => $doc['posting_date'] ?? null,
                    ];
                }
            }

            $deliveryNotes = [];
            $dnNames = $order->shipments
                ->pluck('erp_delivery_note')
                ->filter()
                ->unique()
                ->all();

            foreach ($dnNames as $dnName) {
                $fields = ['name', 'status', 'docstatus', 'grand_total', 'per_billed', 'posting_date'];
                $resp = $this->client->get(
                    '/api/resource/Delivery Note/'.rawurlencode($dnName)
                    .'?fields='.urlencode(json_encode($fields))
                );
                $doc = json_decode($resp->getBody()->getContents(), true)['data'] ?? null;
                if ($doc) {
                    $deliveryNotes[] = [
                        'name' => $doc['name'] ?? $dnName,
                        'status' => $doc['status'] ?? null,
                        'docstatus' => (int) ($doc['docstatus'] ?? 0),
                        'docstatus_label' => $statusLabel[(int) ($doc['docstatus'] ?? 0)] ?? '-',
                        'grand_total' => (float) ($doc['grand_total'] ?? 0),
                        'per_billed' => (float) ($doc['per_billed'] ?? 0),
                        'posting_date' => $doc['posting_date'] ?? null,
                    ];
                }
            }

            // Payment Entry — referensinya per pembayaran DO (delivery_order_payments.erp_payment_entry).
            $paymentEntries = [];
            foreach ($order->payments as $payment) {
                if (empty($payment->erp_payment_entry)) {
                    continue;
                }
                $fields = ['name', 'docstatus', 'paid_amount', 'posting_date'];
                $resp = $this->client->get(
                    '/api/resource/Payment Entry/'.rawurlencode($payment->erp_payment_entry)
                    .'?fields='.urlencode(json_encode($fields))
                );
                $doc = json_decode($resp->getBody()->getContents(), true)['data'] ?? null;
                $paymentEntries[] = [
                    'name' => $payment->erp_payment_entry,
                    'method' => $payment->payment_method,
                    'local_amount' => (float) $payment->amount,
                    'docstatus' => $doc ? (int) ($doc['docstatus'] ?? 0) : null,
                    'docstatus_label' => $doc ? ($statusLabel[(int) ($doc['docstatus'] ?? 0)] ?? '-') : 'Tak ditemukan',
                    'paid_amount' => $doc ? (float) ($doc['paid_amount'] ?? 0) : null,
                    'posting_date' => $doc['posting_date'] ?? null,
                ];
            }

            return [
                'success' => true,
                'sales_invoice' => $salesInvoice,
                'delivery_notes' => $deliveryNotes,
                'payment_entries' => $paymentEntries,
            ];
        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function readResponseBody(ResponseInterface $response): string
    {
        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return (string) $stream;
    }

    /**
     * Periksa apakah User di ERP HPY (dicocokkan lewat email) memegang role tertentu.
     *
     * Role tersimpan di child table `roles` pada doctype User. Child table tidak bisa
     * di-GET langsung, jadi ambil dokumen User-nya lalu baca kolom rolesnya.
     *
     * Sengaja fail-closed: kalau ERP tidak bisa dihubungi atau usernya tidak ada,
     * `has_role` = false disertai `error`. Pemanggilnya harus menolak, bukan
     * menganggap boleh — ini gerbang untuk aksi yang mengubah uang.
     */
    public function userHasErpRole(?string $email, string $role): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'has_role' => false, 'error' => 'ERP HPY belum dikonfigurasi.'];
        }

        if (empty($email)) {
            return ['success' => false, 'has_role' => false, 'error' => 'User ini tidak punya email yang bisa dicocokkan ke ERP HPY.'];
        }

        try {
            $resp = $this->client->get(
                '/api/resource/User/'.rawurlencode($email)
                .'?fields='.urlencode(json_encode(['name', 'enabled', 'roles']))
            );
            $doc = json_decode($resp->getBody()->getContents(), true)['data'] ?? null;

            if (! $doc) {
                return ['success' => false, 'has_role' => false, 'error' => "User {$email} tidak ditemukan di ERP HPY."];
            }

            if (isset($doc['enabled']) && ! $doc['enabled']) {
                return ['success' => true, 'has_role' => false, 'roles' => [], 'error' => "User {$email} nonaktif di ERP HPY."];
            }

            $roles = array_values(array_filter(array_map(
                fn ($r) => is_array($r) ? ($r['role'] ?? null) : $r,
                $doc['roles'] ?? []
            )));

            $hasRole = in_array(mb_strtolower($role), array_map('mb_strtolower', $roles), true);

            return [
                'success' => true,
                'has_role' => $hasRole,
                'roles' => $roles,
                'erp_user' => $doc['name'] ?? $email,
            ];
        } catch (RequestException $e) {
            return ['success' => false, 'has_role' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'has_role' => false, 'error' => $e->getMessage()];
        }
    }

    private function extractError(RequestException $e): string
    {
        if (! $e->hasResponse()) {
            return $e->getMessage();
        }

        $status = $e->getResponse()->getStatusCode();
        $body = $this->readResponseBody($e->getResponse());

        if (str_starts_with(ltrim($body), '<')) {
            return "Server ERP HPY tidak tersedia (HTTP {$status}). Coba beberapa saat lagi.";
        }

        Log::debug('ERPNext raw error', ['status' => $status, 'body' => $body]);

        $decoded = json_decode($body, true);
        if ($decoded) {
            $exception = $decoded['exception'] ?? '';

            // Try to extract human-readable message from _server_messages first
            $raw = $decoded['_server_messages'] ?? null;
            if ($raw) {
                $msgs = json_decode($raw, true);
                if (is_array($msgs) && ! empty($msgs)) {
                    $texts = [];
                    foreach ($msgs as $m) {
                        $parsed = is_string($m) ? json_decode($m, true) : $m;
                        $text = is_array($parsed) ? ($parsed['message'] ?? '') : (string) $m;
                        if ($text) {
                            $texts[] = strip_tags(html_entity_decode($text));
                        }
                    }
                    if ($texts) {
                        $msg = implode(' | ', $texts);
                        if (str_contains($exception, 'PermissionError')) {
                            $msg .= ' (Hint: pastikan API user memiliki role Sales User/Manager di ERP HPY)';
                        }

                        return $msg;
                    }
                }
            }

            if (! empty($decoded['message'])) {
                return $decoded['message'];
            }

            if ($exception) {
                $short = preg_replace('/^frappe\.exceptions\./', '', $exception);

                return "ERP HPY {$short}".(str_contains($exception, 'PermissionError')
                    ? ': API user tidak punya izin buat dokumen ini'
                    : '');
            }
        }

        return $body ?: $e->getMessage();
    }

    private function logSync(
        string $type, int $refId, ?string $refNo,
        string $status, $request, $response, ?string $docname, ?string $error = null
    ): void {
        ErpSyncLog::create([
            'type' => $type,
            'reference_id' => $refId,
            'reference_no' => $refNo,
            'status' => $status,
            'request_payload' => json_encode($request),
            'response_payload' => json_encode($response),
            'erp_docname' => $docname,
            'error_message' => $error,
        ]);
    }

    // =========================================================
    // PULL COUPON CODES + PRICING RULES FROM ERPNext 13
    // =========================================================
    /**
     * Fetch Coupon Codes + linked Pricing Rules from ERPNext 13 and upsert locally.
     *
     * ERPNext 13 Coupon Code confirmed fields (from source coupon_code.json):
     *   name, coupon_code, coupon_name, pricing_rule,
     *   valid_from, valid_upto, maximum_use, used, description
     *   NOTE: NO is_active / disable field on Coupon Code itself.
     *
     * ERPNext 13 Pricing Rule confirmed fields (from source pricing_rule.json):
     *   name, disable, rate_or_discount, discount_percentage,
     *   discount_amount, min_amt, max_amt, apply_discount_on
     *   Active/inactive is controlled by Pricing Rule's `disable` field.
     */
    public function pullCoupons(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'ERP HPY belum dikonfigurasi.'];
        }

        try {
            // 1. Fetch all Coupon Codes — no filter on is_active (field doesn't exist in v13)
            //    Use %20 URL encoding; Guzzle may not encode path segments automatically.
            $response = $this->client->get('/api/resource/Coupon%20Code', [
                'query' => [
                    'fields' => json_encode([
                        'name', 'coupon_code', 'coupon_name',
                        'pricing_rule', 'valid_from', 'valid_upto',
                        'maximum_use', 'used',
                    ]),
                    'limit_page_length' => 500,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $coupons = $data['data'] ?? $data['message'] ?? [];

            if (empty($coupons)) {
                return ['success' => true, 'imported' => 0, 'skipped' => 0,
                    'message' => 'Tidak ada Coupon Code di ERP HPY.'];
            }

            // 2. Collect Pricing Rule names (non-empty, deduplicated)
            $ruleNames = array_values(array_filter(array_unique(array_column($coupons, 'pricing_rule'))));
            $pricingRules = $this->fetchPricingRules($ruleNames);

            $imported = 0;
            $skipped = 0;

            foreach ($coupons as $c) {
                // coupon_code = code customers enter; fall back to name if field is empty
                $code = strtoupper(trim($c['coupon_code'] ?? $c['name'] ?? ''));
                $pricingName = $c['pricing_rule'] ?? null;

                if (! $code) {
                    $skipped++;

                    continue;
                }

                // Skip if no Pricing Rule linked
                if (! $pricingName) {
                    $skipped++;

                    continue;
                }

                // Active status = Pricing Rule not disabled
                $rule = $pricingRules[$pricingName] ?? null;
                $pricingDisabled = $rule ? (bool) ($rule['disable'] ?? 0) : false;

                // Resolve discount type + value from Pricing Rule
                $discountType = 'fixed';
                $discountValue = 0;
                $minPurchase = 0;

                if ($rule) {
                    $rateOrDiscount = $rule['rate_or_discount'] ?? 'Discount Percentage';

                    if ($rateOrDiscount === 'Discount Percentage') {
                        $discountType = 'percent';
                        $discountValue = (float) ($rule['discount_percentage'] ?? 0);
                    } else {
                        // 'Discount Amount' or 'Rate'
                        $discountType = 'fixed';
                        $discountValue = (float) ($rule['discount_amount'] ?? 0);
                    }

                    $minPurchase = (float) ($rule['min_amt'] ?? 0);
                }

                if ($discountValue <= 0) {
                    $skipped++;

                    continue;
                }

                Coupon::updateOrCreate(
                    ['code' => $code],
                    [
                        'erp_coupon_name' => $c['name'],
                        'erp_pricing_rule' => $pricingName,
                        'description' => $c['coupon_name'] ?: null,
                        'discount_type' => $discountType,
                        'discount_value' => $discountValue,
                        'min_purchase' => $minPurchase,
                        'max_uses' => ($c['maximum_use'] ?? 0) > 0 ? (int) $c['maximum_use'] : null,
                        'used_count' => (int) ($c['used'] ?? 0),
                        'valid_from' => $c['valid_from'] ?: null,
                        'valid_until' => $c['valid_upto'] ?: null,
                        'is_active' => ! $pricingDisabled,
                    ]
                );

                $imported++;
            }

            $msg = "Berhasil import {$imported} kupon dari ERP HPY.";
            if ($skipped) {
                $msg .= " {$skipped} dilewati (tidak ada pricing rule atau nilai diskon).";
            }

            return ['success' => true, 'imported' => $imported, 'skipped' => $skipped, 'message' => $msg];

        } catch (RequestException $e) {
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch Pricing Rules by name.
     * Returns associative array keyed by rule name.
     * Uses URL-encoded path (/api/resource/Pricing%20Rule) for ERPNext 13 compatibility.
     */
    private function fetchPricingRules(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        try {
            $response = $this->client->get('/api/resource/Pricing%20Rule', [
                'query' => [
                    'fields' => json_encode([
                        'name', 'title', 'disable', 'rate_or_discount',
                        'discount_percentage', 'discount_amount',
                        'min_amt', 'max_amt', 'apply_discount_on',
                    ]),
                    'filters' => json_encode([['name', 'in', $names]]),
                    'limit_page_length' => count($names),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $map = [];
            foreach ($data['data'] ?? [] as $rule) {
                $map[$rule['name']] = $rule;
            }

            return $map;

        } catch (\Exception $e) {
            Log::warning('pullCoupons: failed to fetch Pricing Rules: '.$e->getMessage());

            return [];
        }
    }

    // =========================================================
    // CREATE + SUBMIT MATERIAL REQUEST (Manufacture) DARI DELIVERY ORDER
    // Dipicu saat DO dikonfirmasi. Tujuannya memicu produksi FG lewat jalur
    // Material Request -> (server script) -> Work Order, sama seperti modul
    // Permintaan FG. Tanpa ini DO tidak pernah memicu produksi, stok FG tetap
    // 0, dan Delivery Note nyangkut Draft.
    // =========================================================
    public function createMaterialRequestForDeliveryOrder(DeliveryOrder $order): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        // Idempoten — satu DO hanya boleh punya satu Material Request.
        if (! empty($order->erp_material_request)) {
            return ['success' => true, 'docname' => $order->erp_material_request];
        }

        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $namingSeries = Setting::get('erp_mr_naming_series', 'MAT-MR-.YYYY.-');
        $defaultWh = Warehouse::getDefault()?->name ?? '';
        $today = $this->localNow()->format('Y-m-d');

        // Tanggal produksi = jadwal dapur (kitchen_scheduled_at) kalau ada, kalau
        // tidak pakai tanggal kirim. Tidak boleh mendahului hari ini (ERP menolak
        // schedule_date < transaction_date) supaya WO draft-nya ikut disubmit
        // Auto Complete Produksi (yang memfilter planned_start_date >= cutoff).
        $wanted = $order->kitchen_scheduled_at
            ? $order->kitchen_scheduled_at->format('Y-m-d')
            : ($order->delivery_date ? $order->delivery_date->format('Y-m-d') : $today);
        $scheduleDate = $wanted < $today ? $today : $wanted;

        $items = $order->items->map(function ($item) use ($scheduleDate, $defaultWh) {
            $itemCode = $item->product?->erp_item_code ?? $item->product_sku ?? $item->product_name;

            return [
                'item_code' => $itemCode,
                'item_name' => $item->product_name,
                'qty' => (float) $item->qty,
                'uom' => $item->product?->unit ?: 'Nos',
                'schedule_date' => $scheduleDate,
                'warehouse' => $defaultWh,
                'description' => $item->product_name,
            ];
        })->toArray();

        if (empty($items)) {
            return ['success' => false, 'error' => 'DO tidak punya item untuk diproduksi.'];
        }

        $payload = [
            'doctype' => 'Material Request',
            'naming_series' => $namingSeries,
            'material_request_type' => 'Manufacture',
            'transaction_date' => $today,
            'schedule_date' => $scheduleDate,
            'company' => $company,
            'status_permintaan' => 'Diajukan',
            'items' => $items,
            'remarks' => implode("\n", array_filter([
                'Auto dari Delivery Order: '.$order->order_no,
                $order->billing_address ? 'Billing: '.$order->billing_address : null,
            ])),
        ];

        Log::info('DeliveryOrder MR payload', ['order' => $order->order_no, 'payload' => $payload]);

        try {
            $response = $this->client->post('/api/resource/Material%20Request', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;
        } catch (ConnectException $e) {
            Log::warning('DO MR auto-sync: network unreachable', ['order' => $order->order_no]);

            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('DO MR create failed', ['order' => $order->order_no, 'raw' => $rawBody]);
            $error = $this->extractError($e);
            $order->update(['erp_mr_sync_error' => $error]);

            return ['success' => false, 'error' => $error];
        }

        // Submit supaya scheduler "Auto Produksi Material Request" mengambilnya
        // (query-nya menyaring docstatus = 1).
        if ($docname) {
            try {
                $this->submitDoc('Material Request', $docname);
            } catch (RequestException $e) {
                $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
                Log::error('DO MR submit failed', ['order' => $order->order_no, 'docname' => $docname, 'raw' => $rawBody]);
                $error = 'Material Request '.$docname.' dibuat tapi gagal submit: '.$this->extractError($e);
                $order->update(['erp_material_request' => $docname, 'erp_mr_sync_error' => $error]);

                return ['success' => false, 'error' => $error, 'docname' => $docname];
            }
        }

        $order->update(['erp_material_request' => $docname, 'erp_mr_sync_error' => null]);

        return ['success' => true, 'docname' => $docname, 'material_request' => $docname];
    }

    // =========================================================
    // CREATE MATERIAL REQUEST → ERPNext (material_request_type: Manufacture)
    // =========================================================
    public function createMaterialRequest(StockRequest $stockRequest): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        // Idempoten — satu permintaan FG hanya boleh punya satu Material Request.
        if (! empty($stockRequest->erp_material_request)) {
            return ['success' => true, 'docname' => $stockRequest->erp_material_request];
        }

        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $namingSeries = Setting::get('erp_mr_naming_series', 'MAT-MR-.YYYY.-');
        $defaultWh = Warehouse::getDefault()?->name ?? '';
        $today = $this->localNow()->format('Y-m-d');
        $neededDate = $stockRequest->needed_date
            ? $stockRequest->needed_date->format('Y-m-d')
            : $today;

        // ERPNext menolak "Reqd by Date cannot be before Transaction Date". Permintaan
        // yang tanggal butuhnya sudah lewat (mis. dibuat pekan lalu, baru disync hari
        // ini) pasti ditolak — jadwalnya dimajukan ke hari ini supaya MR tetap terbit.
        // Tanggal butuh aslinya dicatat di remarks agar informasinya tidak hilang.
        $scheduleDate = $neededDate < $today ? $today : $neededDate;
        $backdated = $scheduleDate !== $neededDate;

        $items = $stockRequest->items->map(fn ($item) => [
            'item_code' => $item->item_code ?? $item->item_name,
            'item_name' => $item->item_name,
            'qty' => (float) $item->qty,
            'uom' => $item->uom ?: 'Nos',
            'schedule_date' => $scheduleDate,
            'warehouse' => $defaultWh,
            'description' => $item->notes ?: $item->item_name,
        ])->toArray();

        $payload = [
            'doctype' => 'Material Request',
            'naming_series' => $namingSeries,
            'material_request_type' => 'Manufacture',
            'transaction_date' => $this->localNow()->format('Y-m-d'),
            'schedule_date' => $scheduleDate,
            'company' => $company,
            'status_permintaan' => 'Diajukan',
            'items' => $items,
            'remarks' => implode("\n", array_filter([
                'Request: '.$stockRequest->request_no,
                $backdated ? 'Tanggal butuh asli: '.$neededDate.' (dimajukan ke '.$scheduleDate.' karena sudah lewat)' : null,
                $stockRequest->notes,
            ])),
        ];

        Log::info('StockRequest MR payload', ['request' => $stockRequest->request_no, 'payload' => $payload]);

        try {
            $response = $this->client->post('/api/resource/Material%20Request', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;
        } catch (ConnectException $e) {
            Log::warning('MR auto-sync: network unreachable', ['request' => $stockRequest->request_no]);

            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('MR create failed', ['request' => $stockRequest->request_no, 'raw' => $rawBody]);
            $error = $this->extractError($e);
            $stockRequest->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);

            return ['success' => false, 'error' => $error];
        }

        $stockRequest->update([
            'erp_material_request' => $docname,
            'erp_sync_status' => 'synced',
            'erp_sync_error' => null,
        ]);

        return ['success' => true, 'docname' => $docname, 'material_request' => $docname];
    }

    // =========================================================
    // SUBMIT MATERIAL REQUEST → docstatus = 1 (Selesai)
    // =========================================================
    public function submitMaterialRequest(StockRequest $stockRequest): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        $docname = $stockRequest->erp_material_request;

        if (! $docname) {
            return ['success' => false, 'error' => 'Material Request ERP belum dibuat untuk permintaan ini.'];
        }

        try {
            $this->submitDoc('Material Request', $docname);
        } catch (ConnectException $e) {
            Log::warning('MR submit: network unreachable', ['docname' => $docname]);

            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('MR submit failed', ['docname' => $docname, 'raw' => $rawBody]);

            return ['success' => false, 'error' => $this->extractError($e)];
        }

        Log::info('MR submitted (docstatus=1)', ['docname' => $docname, 'request' => $stockRequest->request_no]);

        return ['success' => true, 'docname' => $docname];
    }

    // =========================================================
    // REFRESH REPACK STATUS ← ERP HPY (hanya baca docstatus)
    // Menyamakan status lokal slice dengan Stock Entry di ERP tanpa submit
    // atau membuat dokumen baru. Dipakai tombol "Refresh Status" di detail Repack.
    // =========================================================
    public function refreshRepackStatus(Slice $slice): array
    {
        if (empty($slice->erp_stock_entry)) {
            return ['success' => false, 'error' => 'Repack ini belum punya Stock Entry di ERP HPY. Gunakan Submit ke ERP.'];
        }

        $docStatus = $this->fetchDocStatus('Stock Entry', $slice->erp_stock_entry);
        $name = $slice->erp_stock_entry;

        if ($docStatus === 1) {
            $changed = $slice->status !== 'submitted' || $slice->erp_sync_status !== 'synced';
            $slice->update([
                'status' => 'submitted',
                'submitted_at' => $slice->submitted_at ?? now(),
                'erp_sync_status' => 'synced',
                'erp_sync_error' => null,
            ]);

            return [
                'success' => true,
                'docstatus' => 1,
                'changed' => $changed,
                'message' => $changed
                    ? "Stock Entry {$name} sudah tersubmit di ERP HPY. Status repack diperbarui menjadi Submitted."
                    : "Status sudah sesuai: Stock Entry {$name} tersubmit di ERP HPY.",
            ];
        }

        if ($docStatus === 2) {
            $changed = $slice->status !== 'cancelled';
            $slice->update([
                'status' => 'cancelled',
                'erp_sync_status' => 'synced',
                'erp_sync_error' => null,
            ]);

            return [
                'success' => true,
                'docstatus' => 2,
                'changed' => $changed,
                'message' => $changed
                    ? "Stock Entry {$name} sudah dibatalkan di ERP HPY. Status repack diperbarui menjadi Dibatalkan."
                    : "Status sudah sesuai: Stock Entry {$name} dibatalkan di ERP HPY.",
            ];
        }

        if ($docStatus === 0) {
            return [
                'success' => true,
                'docstatus' => 0,
                'changed' => false,
                'message' => "Stock Entry {$name} masih Draft di ERP HPY. Submit dokumen itu di ERP HPY, lalu refresh lagi; atau pakai Submit ke ERP dari sini.",
            ];
        }

        return [
            'success' => false,
            'docstatus' => null,
            'error' => "Stock Entry {$name} tidak terbaca di ERP HPY (mungkin sudah dihapus). Periksa di ERP HPY.",
        ];
    }

    // =========================================================
    // CREATE REPACK ENTRY → ERPNext (Stock Entry, purpose = Repack)
    // Konversi 1 item (mis. Bolu) menjadi item lain (mis. Slice):
    // baris sumber = s_warehouse (diissue), baris hasil = t_warehouse (diterima).
    // Nilai/valuasi hasil dihitung otomatis oleh ERPNext dari sumber.
    // =========================================================
    public function createRepackEntry(Slice $slice): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        // Idempoten — Stock Entry repack yang sudah terbuat tidak dikirim ulang, kalau
        // tidak bahan bakunya terpotong dua kali di ERP. Dokumen yang tertinggal sebagai
        // draft (submit gagal) harus dibereskan di ERP, bukan dibuat ulang dari sini.
        if (! empty($slice->erp_stock_entry)) {
            if ($slice->erp_sync_status === 'synced') {
                return ['success' => true, 'docname' => $slice->erp_stock_entry];
            }

            // Rekonsiliasi docstatus riil di ERP. Stock Entry yang tertinggal draft
            // (submit gagal, mis. stok kurang) bisa saja sudah disubmit manual di ERP
            // HPY. Dicek supaya HPYSync ikut sinkron, bukan terjebak "draft" selamanya —
            // pola yang sama sudah dipakai alur Delivery Note (submitOrKeepDraftDeliveryNote).
            $docStatus = $this->fetchDocStatus('Stock Entry', $slice->erp_stock_entry);

            if ($docStatus === 1) {
                // Sudah tersubmit di ERP → tandai selesai, jangan submit ulang.
                $slice->update(['erp_sync_status' => 'synced', 'erp_sync_error' => null]);

                return ['success' => true, 'docname' => $slice->erp_stock_entry];
            }

            if ($docStatus === 0) {
                // Masih draft di ERP → selesaikan submit-nya (tanpa membuat dokumen baru).
                try {
                    $this->submitDoc('Stock Entry', $slice->erp_stock_entry);
                } catch (RequestException $e) {
                    $error = 'Stock Entry '.$slice->erp_stock_entry.' masih draft dan gagal disubmit: '.$this->extractError($e);
                    $slice->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);

                    return ['success' => false, 'docname' => $slice->erp_stock_entry, 'error' => $error];
                }

                $slice->update(['erp_sync_status' => 'synced', 'erp_sync_error' => null]);

                return ['success' => true, 'docname' => $slice->erp_stock_entry];
            }

            // docstatus 2 (cancelled) atau tidak terbaca (null).
            $msg = $docStatus === 2
                ? 'Stock Entry '.$slice->erp_stock_entry.' sudah dibatalkan di ERP HPY. Batalkan repack ini atau buat ulang.'
                : 'Stock Entry '.$slice->erp_stock_entry.' sudah dibuat di ERP HPY tapi statusnya tidak terbaca. Periksa di ERP HPY.';

            return ['success' => false, 'docname' => $slice->erp_stock_entry, 'error' => $msg];
        }

        $company = Setting::get('erpnext_company', env('ERPNEXT_COMPANY', ''));
        $namingSeries = Setting::get('erp_repack_naming_series', 'MAT-STE-.YYYY.-');
        $defaultWh = Warehouse::getDefault()?->name ?? '';

        if (! $defaultWh) {
            return ['success' => false, 'error' => 'Gudang default belum diset. Set salah satu warehouse sebagai default.'];
        }

        // Model dua-tabel: baris issue (keluar) & receipt (masuk). Digabung per
        // item_code + gudang supaya tidak ada baris duplikat di ERP.
        $issue = [];   // item yang dibuang (tabel 1)
        $receive = []; // item hasil (tabel 2)

        $addLine = function (array &$bucket, string $key, string $code, string $name, float $qty, string $uom) {
            if (isset($bucket[$key])) {
                $bucket[$key]['qty'] += $qty;
            } else {
                $bucket[$key] = ['item_code' => $code, 'item_name' => $name, 'qty' => $qty, 'uom' => $uom];
            }
        };

        // Tabel 1 — issue: keluar dari gudangnya masing-masing (kosong = default).
        foreach ($slice->issues as $line) {
            $wh = $line->warehouse ?: $defaultWh;
            $code = $line->item_code ?: $line->item_name;
            $addLine($issue, $code.'|'.$wh, $code, $line->item_name, (float) $line->qty, $line->uom ?: 'Nos');
            $issue[$code.'|'.$wh]['s_warehouse'] = $wh;
        }

        // Tabel 2 — receipt: diterima di gudang default.
        foreach ($slice->receipts as $line) {
            $code = $line->item_code ?: $line->item_name;
            $addLine($receive, $code.'|'.$defaultWh, $code, $line->item_name, (float) $line->qty, $line->uom ?: 'Nos');
        }

        if (empty($issue) || empty($receive)) {
            return ['success' => false, 'error' => 'Repack harus punya minimal 1 item issue dan 1 item receipt.'];
        }

        $items = [];
        foreach ($issue as $line) {
            $items[] = [
                'item_code' => $line['item_code'],
                'item_name' => $line['item_name'],
                'qty' => $line['qty'],
                'uom' => $line['uom'],
                's_warehouse' => $line['s_warehouse'] ?? $defaultWh,
            ];
        }
        foreach ($receive as $line) {
            $items[] = [
                'item_code' => $line['item_code'],
                'item_name' => $line['item_name'],
                'qty' => $line['qty'],
                'uom' => $line['uom'],
                't_warehouse' => $defaultWh,
            ];
        }

        $payload = [
            'doctype' => 'Stock Entry',
            'naming_series' => $namingSeries,
            'stock_entry_type' => 'Repack',
            'purpose' => 'Repack',
            'company' => $company,
            'posting_date' => $this->localNow()->format('Y-m-d'),
            'posting_time' => $this->localNow()->format('H:i:s'),
            'set_posting_time' => 1,
            'from_warehouse' => $defaultWh,
            'to_warehouse' => $defaultWh,
            'items' => $items,
            'remarks' => implode("\n", array_filter([
                'Slice: '.$slice->slice_no,
                $slice->notes,
            ])),
        ];

        Log::info('Slice Repack payload', ['slice' => $slice->slice_no, 'payload' => $payload]);

        try {
            $response = $this->client->post('/api/resource/Stock%20Entry', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);
            $docname = $data['data']['name'] ?? null;
        } catch (ConnectException $e) {
            Log::warning('Repack auto-sync: network unreachable', ['slice' => $slice->slice_no]);

            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('Repack create failed', ['slice' => $slice->slice_no, 'raw' => $rawBody]);
            $error = $this->extractError($e);
            $slice->update(['erp_sync_status' => 'failed', 'erp_sync_error' => $error]);

            return ['success' => false, 'error' => $error];
        }

        if ($docname) {
            try {
                $this->submitDoc('Stock Entry', $docname);
            } catch (RequestException $e) {
                $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
                Log::error('Repack submit failed', ['slice' => $slice->slice_no, 'docname' => $docname, 'raw' => $rawBody]);
                $error = 'Stock Entry '.$docname.' dibuat tapi gagal submit: '.$this->extractError($e);
                $slice->update([
                    'erp_stock_entry' => $docname,
                    'erp_sync_status' => 'failed',
                    'erp_sync_error' => $error,
                ]);

                return ['success' => false, 'error' => $error, 'docname' => $docname];
            }
        }

        $slice->update([
            'erp_stock_entry' => $docname,
            'erp_sync_status' => 'synced',
            'erp_sync_error' => null,
        ]);

        return ['success' => true, 'docname' => $docname, 'stock_entry' => $docname];
    }

    // =========================================================
    // CANCEL REPACK ENTRY → ERPNext (docstatus = 2)
    // Membalik pergerakan stok: sumber kembali masuk, hasil keluar.
    // =========================================================
    public function cancelRepackEntry(Slice $slice): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'error' => 'URL ERP HPY belum dikonfigurasi.'];
        }

        $docname = $slice->erp_stock_entry;

        if (! $docname) {
            // Tidak ada dokumen ERP — tidak ada yang perlu dibatalkan di ERP.
            return ['success' => true, 'docname' => null];
        }

        // Cek dulu status dokumen di ERP. Jika sudah dibatalkan manual di ERP
        // (docstatus = 2), lanjutkan saja pembatalan lokal.
        try {
            $resp = $this->client->get('/api/resource/Stock%20Entry/'.rawurlencode($docname), [
                'query' => ['fields' => '["docstatus"]'],
            ]);
            $docstatus = (int) (json_decode($resp->getBody()->getContents(), true)['data']['docstatus'] ?? 0);

            if ($docstatus === 2) {
                Log::info('Repack already cancelled in ERP', ['docname' => $docname, 'slice' => $slice->slice_no]);

                return ['success' => true, 'docname' => $docname, 'already_cancelled' => true];
            }
        } catch (RequestException $e) {
            // Dokumen tidak ditemukan di ERP (mis. sudah dihapus) — anggap tidak ada
            // yang perlu dibatalkan, lanjutkan pembatalan lokal.
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 404) {
                Log::info('Repack doc not found in ERP, proceeding local cancel', ['docname' => $docname, 'slice' => $slice->slice_no]);

                return ['success' => true, 'docname' => $docname, 'not_found' => true];
            }

            // Error lain saat cek status: jangan lanjut, biar user tahu.
            return ['success' => false, 'error' => $this->extractError($e)];
        } catch (ConnectException $e) {
            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        }

        try {
            $this->client->post('/api/method/frappe.client.cancel', [
                'json' => ['doctype' => 'Stock Entry', 'name' => $docname],
            ]);
        } catch (ConnectException $e) {
            Log::warning('Repack cancel: network unreachable', ['slice' => $slice->slice_no, 'docname' => $docname]);

            return ['success' => false, 'error' => 'Network unreachable', 'network_error' => true];
        } catch (RequestException $e) {
            $rawBody = $e->hasResponse() ? $this->readResponseBody($e->getResponse()) : '';
            Log::error('Repack cancel failed', ['slice' => $slice->slice_no, 'docname' => $docname, 'raw' => $rawBody]);

            return ['success' => false, 'error' => $this->extractError($e)];
        }

        Log::info('Repack cancelled (docstatus=2)', ['docname' => $docname, 'slice' => $slice->slice_no]);

        return ['success' => true, 'docname' => $docname];
    }
}
