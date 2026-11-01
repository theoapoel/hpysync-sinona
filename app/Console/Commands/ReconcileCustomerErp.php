<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\ErpNextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileCustomerErp extends Command
{
    protected $signature = 'customers:reconcile-erp {--dry-run : Tampilkan temuan saja tanpa mengubah apa pun}';

    protected $description = 'Audit mapping erp_customer_name yang menunjuk Customer HPY yang sudah tidak ada (mis. suffix "- 1" yang telah dihapus/digabung), penyebab SO/DN/Payment FAILED. Dengan --dry-run hanya melapor; tanpa itu, memperbaiki mapping bila ada Customer yang cocok by nama.';

    public function handle(ErpNextService $erp): int
    {
        if (! $erp->isConfigured()) {
            $this->error('ERP HPY belum dikonfigurasi.');

            return self::FAILURE;
        }

        // Hanya customer yang sudah pernah dipetakan ke ERP. Yang belum (null/kosong)
        // bukan kandidat "nyangkut" — mereka akan di-push saat transaksi pertama.
        $customers = Customer::whereNotNull('erp_customer_name')
            ->where('erp_customer_name', '!=', '')
            ->orderBy('name')
            ->get();

        $this->line("Memeriksa {$customers->count()} customer yang punya mapping ERP ...");

        $orphanFixable = [];   // mapping hilang, tapi ada Customer ERP yang cocok by nama
        $orphanManual = [];    // mapping hilang, tidak ada padanan di ERP
        $okCount = 0;

        foreach ($customers as $c) {
            // Mapping masih valid → lewati (hemat: cek keberadaan dokumen apa adanya).
            if ($erp->erpRecordExists('Customer', $c->erp_customer_name)) {
                $okCount++;

                continue;
            }

            // Mapping menunjuk record yang tidak ada lagi. Cari padanan by nama lokal.
            $match = $erp->findCustomerByName($c->name);
            if ($match) {
                $orphanFixable[] = [$c, $match];
            } else {
                $orphanManual[] = $c;
            }
        }

        $this->newLine();
        $this->info("Mapping valid: {$okCount}");
        $this->warn('Mapping rusak + ADA padanan ERP (bisa dibetulkan): '.count($orphanFixable));
        foreach ($orphanFixable as [$c, $match]) {
            $this->line("  {$c->name}: '{$c->erp_customer_name}' (hilang) -> '{$match}'");
        }
        $this->error('Mapping rusak + TANPA padanan (perlu tangani manual): '.count($orphanManual));
        foreach ($orphanManual as $c) {
            $this->line("  {$c->name}: '{$c->erp_customer_name}' (hilang, tak ada Customer ERP bernama '{$c->name}')");
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('Dry-run — tidak ada perubahan.');

            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($orphanFixable as [$c, $match]) {
            $c->update(['erp_customer_name' => $match, 'erp_last_sync' => now()]);
            $fixed++;
            $this->line("  FIX  {$c->name}: erp_customer_name -> '{$match}'");
        }

        $this->newLine();
        $this->info("Selesai. Diperbaiki: {$fixed} | Perlu manual: ".count($orphanManual));
        Log::info('Customer reconcile selesai', [
            'fixed' => $fixed,
            'manual' => count($orphanManual),
            'ok' => $okCount,
        ]);

        return self::SUCCESS;
    }
}
