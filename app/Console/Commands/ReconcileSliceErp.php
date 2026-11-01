<?php

namespace App\Console\Commands;

use App\Models\Slice;
use App\Services\ErpNextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileSliceErp extends Command
{
    protected $signature = 'slices:reconcile-erp {--dry-run : Tampilkan kandidat saja tanpa mengubah apa pun}';

    protected $description = 'Sinkronkan status Repack/Slice yang terjebak "draft" padahal Stock Entry-nya sudah tersubmit/batal di ERP HPY';

    public function handle(ErpNextService $erp): int
    {
        // Kandidat: sudah punya nomor Stock Entry di ERP tapi status lokal masih draft
        // (submit sebelumnya gagal lalu dibereskan manual di ERP, dsb).
        $candidates = Slice::whereNotNull('erp_stock_entry')
            ->where('erp_stock_entry', '!=', '')
            ->where('status', 'draft')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada slice yang perlu direkonsiliasi.');

            return self::SUCCESS;
        }

        $this->line("Kandidat: {$candidates->count()} slice (status draft tapi punya ERP Stock Entry).");

        if ($this->option('dry-run')) {
            foreach ($candidates as $s) {
                $this->line("  {$s->slice_no} -> {$s->erp_stock_entry} (sync: {$s->erp_sync_status})");
            }
            $this->info('Dry-run — tidak ada perubahan.');

            return self::SUCCESS;
        }

        if (! $erp->isConfigured()) {
            $this->error('ERP HPY belum dikonfigurasi.');

            return self::FAILURE;
        }

        $synced = 0;
        $failed = 0;

        foreach ($candidates as $slice) {
            // createRepackEntry() kini idempoten + rekonsiliasi: kalau Stock Entry sudah
            // tersubmit di ERP, ia menandai synced tanpa membuat dokumen baru.
            $result = $erp->createRepackEntry($slice);

            if (! empty($result['success'])) {
                if ($slice->status === 'draft') {
                    $slice->update([
                        'status' => 'submitted',
                        'submitted_at' => $slice->submitted_at ?? now(),
                    ]);
                }
                $synced++;
                $this->line("  OK   {$slice->slice_no} -> {$slice->erp_stock_entry}");
            } else {
                $failed++;
                $this->warn("  SKIP {$slice->slice_no}: ".($result['error'] ?? 'unknown'));
            }
        }

        $this->info("Selesai. Tersinkron: {$synced} | Dilewati: {$failed}.");
        Log::info('Slice reconcile selesai', ['synced' => $synced, 'failed' => $failed]);

        return self::SUCCESS;
    }
}
