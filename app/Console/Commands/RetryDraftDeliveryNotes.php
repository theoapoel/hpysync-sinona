<?php

namespace App\Console\Commands;

use App\Models\DeliveryShipment;
use App\Services\ErpNextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryDraftDeliveryNotes extends Command
{
    protected $signature = 'delivery-notes:retry-draft {--dry-run : Tampilkan kandidat saja tanpa mengubah apa pun}';

    protected $description = 'Coba submit ulang Delivery Note yang tertahan "draft" di ERP HPY (biasanya karena stok FG kurang saat percobaan pertama)';

    public function handle(ErpNextService $erp): int
    {
        $candidates = DeliveryShipment::where('erp_sync_status', 'draft')
            ->whereNotNull('erp_delivery_note')
            ->with('order.customer')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada Delivery Note draft yang perlu di-retry.');

            return self::SUCCESS;
        }

        $this->line("Kandidat: {$candidates->count()} Delivery Note berstatus draft.");

        if ($this->option('dry-run')) {
            foreach ($candidates as $s) {
                $this->line("  {$s->erp_delivery_note} (shipment #{$s->id}) — {$s->erp_sync_error}");
            }
            $this->info('Dry-run — tidak ada perubahan.');

            return self::SUCCESS;
        }

        if (! $erp->isConfigured()) {
            $this->error('ERP HPY belum dikonfigurasi.');

            return self::FAILURE;
        }

        $submitted = 0;
        $stillDraft = 0;

        foreach ($candidates as $shipment) {
            try {
                // createDeliveryNote() idempoten: karena erp_delivery_note sudah terisi,
                // ini akan masuk jalur retry (refreshPostingTime=true) alih-alih bikin DN baru.
                $result = $erp->createDeliveryNote($shipment);

                if (! empty($result['submitted'])) {
                    $submitted++;
                    $this->line("  OK    {$result['delivery_note']} — Submitted");
                } elseif (! empty($result['success'])) {
                    $stillDraft++;
                    $this->warn("  DRAFT {$result['delivery_note']} — masih tertahan: ".($result['error'] ?? $result['warning'] ?? ''));
                } else {
                    $stillDraft++;
                    $this->error("  GAGAL shipment #{$shipment->id}: ".($result['error'] ?? 'unknown'));
                }
            } catch (\Throwable $e) {
                // Satu kandidat error (mis. masalah data lokal) tidak boleh menghentikan
                // batch — kandidat lain tetap harus dicoba.
                $stillDraft++;
                $this->error("  ERROR shipment #{$shipment->id}: ".$e->getMessage());
                Log::error('Retry draft Delivery Note: exception per-kandidat', [
                    'shipment' => $shipment->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Selesai. Submitted: {$submitted} | Masih draft/gagal: {$stillDraft}.");
        Log::info('Retry draft Delivery Note selesai', ['submitted' => $submitted, 'still_draft' => $stillDraft]);

        return self::SUCCESS;
    }
}
