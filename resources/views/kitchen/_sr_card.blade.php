@php
    $sinceTs = $col === 'preparing' ? $sr->kitchen_started_at?->timestamp : $sr->created_at?->timestamp;
    $colColor  = $col === 'requested' ? '#F9A825' : '#1565C0';
    $colBg     = $col === 'requested' ? '#FFF8E1' : '#E3F2FD';
@endphp
<div class="order-card" id="sr-card-{{ $sr->id }}"
    style="min-width:260px;max-width:300px;flex:0 0 auto;border-top:3px solid {{ $colColor }}">
    <div class="order-card-head">
        <div>
            <div class="order-no" style="color:{{ $colColor }}">
                <i class="fas fa-clipboard-check" style="font-size:12px"></i>
                {{ $sr->request_no }}
            </div>
            <div class="order-date">
                <i class="fas fa-calendar-alt"></i>
                Butuh: {{ $sr->needed_date?->isoFormat('D MMM Y') ?? '—' }}{{ $sr->needed_time ? ', ' . substr($sr->needed_time, 0, 5) : '' }}
            </div>
            <div class="order-customer" style="font-size:11px;color:var(--text3)">
                <i class="fas fa-user"></i> {{ $sr->requester->name ?? '—' }}
            </div>
        </div>
        <div class="elapsed" data-since="{{ $sinceTs }}">—</div>
    </div>

    <div class="order-items">
        @foreach($sr->items as $item)
        <div class="order-item-row">
            <span>{{ $item->item_name }}</span>
            <span class="qty">× {{ number_format($item->qty, 0) }} {{ $item->uom }}</span>
        </div>
        @endforeach
    </div>

    @if($sr->notes)
    <div style="padding:6px 14px;font-size:12px;color:var(--text3);font-style:italic;border-bottom:1px solid #f0f0f0">
        <i class="fas fa-sticky-note"></i> {{ $sr->notes }}
    </div>
    @endif

    <div class="order-card-actions">
        @if($col === 'requested')
        <button class="btn btn-kitchen-start btn-sm" style="flex:1"
            data-url="{{ route('stock-requests.kitchen-status', $sr) }}"
            data-status="preparing"
            onclick="updateSrStatus(this)">
            <i class="fas fa-fire"></i> Mulai Persiapan
        </button>
        @elseif($col === 'preparing')
        <button class="btn btn-kitchen-done btn-sm" style="flex:1"
            data-url="{{ route('stock-requests.kitchen-status', $sr) }}"
            data-status="done"
            onclick="updateSrStatus(this)">
            <i class="fas fa-check"></i> Selesai
        </button>
        <button class="btn btn-kitchen-back btn-sm"
            data-url="{{ route('stock-requests.kitchen-status', $sr) }}"
            data-status="requested"
            onclick="updateSrStatus(this)"
            title="Kembalikan ke Diminta">
            <i class="fas fa-undo"></i>
        </button>
        @endif
    </div>
</div>
