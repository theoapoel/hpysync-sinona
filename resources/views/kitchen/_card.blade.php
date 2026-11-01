@php
    $isToday    = $order->delivery_date->isToday();
    $sinceTs    = match($col) {
        'preparing' => $order->kitchen_started_at?->timestamp,
        'ready'     => $order->kitchen_ready_at?->timestamp,
        default     => $order->updated_at?->timestamp,
    };
@endphp
<div class="order-card" id="kcard-{{ $order->id }}">
    <div class="order-card-head">
        <div>
            <div class="order-no">
                {{ $order->order_no }}
                @if($isToday)
                <span class="badge-today">HARI INI</span>
                @endif
            </div>
            <div class="order-date"><i class="fas fa-calendar-alt"></i> {{ $order->delivery_date->isoFormat('D MMM Y') }}</div>
            <div class="order-customer"><i class="fas fa-user"></i> {{ $order->customer?->name ?? '-' }}</div>
        </div>
        <div class="elapsed" data-since="{{ $sinceTs }}">—</div>
    </div>

    <div class="order-items">
        @foreach($order->items as $item)
        <div class="order-item-row">
            <span>{{ $item->product_name }}</span>
            <span class="qty">× {{ $item->qty }}</span>
        </div>
        @endforeach
    </div>

    @if($order->notes)
    <div style="padding:6px 14px;font-size:12px;color:#5F6368;font-style:italic;border-bottom:1px solid #f0f0f0">
        <i class="fas fa-sticky-note"></i> {{ $order->notes }}
    </div>
    @endif

    <div class="order-card-actions">
        @if($col === 'pending')
            <button class="btn btn-kitchen-start btn-sm" style="flex:1"
                data-url="{{ route('kitchen.update-status', $order) }}"
                data-status="preparing"
                onclick="updateKitchenStatus(this)">
                <i class="fas fa-fire"></i> Mulai Proses
            </button>
        @elseif($col === 'preparing')
            <button class="btn btn-kitchen-done btn-sm" style="flex:1"
                data-url="{{ route('kitchen.update-status', $order) }}"
                data-status="ready"
                onclick="updateKitchenStatus(this)">
                <i class="fas fa-check"></i> Selesai / Siap
            </button>
            <button class="btn btn-kitchen-back btn-sm"
                data-url="{{ route('kitchen.update-status', $order) }}"
                data-status="pending"
                onclick="updateKitchenStatus(this)"
                title="Kembalikan ke Antrian">
                <i class="fas fa-undo"></i>
            </button>
        @elseif($col === 'ready')
            <a href="{{ route('delivery-orders.show', $order) }}" class="btn btn-ghost btn-sm" style="flex:1">
                <i class="fas fa-eye"></i> Lihat Order
            </a>
            <button class="btn btn-kitchen-back btn-sm"
                data-url="{{ route('kitchen.update-status', $order) }}"
                data-status="preparing"
                onclick="updateKitchenStatus(this)"
                title="Kembalikan ke Diproses">
                <i class="fas fa-undo"></i>
            </button>
        @endif
    </div>
</div>
