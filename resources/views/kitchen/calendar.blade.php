@extends('layouts.app')
@section('title', 'Kalender Produksi')

@push('styles')
<style>
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}
.cal-header-day {
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text3);
    padding: 6px 0;
    letter-spacing: .5px;
}
.cal-day {
    min-height: 80px;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px;
    background: var(--surface);
    cursor: pointer;
    transition: all .15s;
    position: relative;
}
.cal-day:hover { background: var(--surface2); border-color: var(--blue); }
.cal-day.today { border-color: var(--blue); background: #E8F0FE; }
.cal-day.selected { border-color: var(--blue); border-width: 2px; background: #E8F0FE; }
.cal-day.other-month { opacity: .35; cursor: default; }
.cal-day.other-month:hover { background: var(--surface); border-color: var(--border); }
.cal-day-num {
    font-size: 13px;
    font-weight: 700;
    color: var(--text2);
    margin-bottom: 4px;
}
.cal-day.today .cal-day-num { color: var(--blue); }
.cal-day.selected .cal-day-num { color: var(--blue); }
.cal-badge {
    display: inline-block;
    background: var(--blue);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 10px;
    padding: 1px 7px;
    margin-top: 2px;
}
.cal-badge-paid { background: var(--green); }
.cal-chip {
    display: block;
    font-size: 10px;
    font-weight: 600;
    border-radius: 4px;
    padding: 1px 5px;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cal-chip-do { background: #E8F0FE; color: #1967D2; }
.cal-chip-fg { background: #E6F4EA; color: #137333; }
.cal-more { font-size: 10px; color: var(--text3); margin-top: 2px; font-weight: 600; }
.order-list-card {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 10px;
    background: var(--surface);
    transition: box-shadow .15s;
}
.order-list-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.status-dot {
    width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 5px;
}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-calendar-alt text-blue"></i> Kalender Produksi</div>
        <div class="page-subtitle">Jadwal masuk antrian dapur — Delivery Order & Permintaan FG</div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="{{ route('kitchen.index') }}" class="btn btn-ghost">
            <i class="fas fa-columns"></i> Kanban Dapur
        </a>
        <a href="{{ route('delivery-orders.index') }}" class="btn btn-ghost">
            <i class="fas fa-truck"></i> Delivery Orders
        </a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start">

    {{-- Kalender --}}
    <div class="card">
        <div class="card-header">
            {{-- Navigasi bulan --}}
            <div style="display:flex;align-items:center;gap:12px">
                <a href="{{ route('kitchen.calendar', ['month' => $prev->format('Y-m'), 'date' => request('date')]) }}"
                    class="btn btn-ghost btn-sm"><i class="fas fa-chevron-left"></i></a>
                <span style="font-size:16px;font-weight:700;min-width:160px;text-align:center">
                    {{ $month->isoFormat('MMMM YYYY') }}
                </span>
                <a href="{{ route('kitchen.calendar', ['month' => $next->format('Y-m'), 'date' => request('date')]) }}"
                    class="btn btn-ghost btn-sm"><i class="fas fa-chevron-right"></i></a>
            </div>
            <a href="{{ route('kitchen.calendar', ['month' => now()->format('Y-m')]) }}"
                class="btn btn-outline btn-sm">Hari Ini</a>
        </div>
        <div class="card-body">

            {{-- Header hari --}}
            <div class="cal-grid" style="margin-bottom:4px">
                @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $h)
                <div class="cal-header-day">{{ $h }}</div>
                @endforeach
            </div>

            {{-- Grid hari --}}
            @php
                $startOfMonth = $month->copy()->startOfMonth();
                $endOfMonth   = $month->copy()->endOfMonth();
                // Mulai dari Senin sebelum/pada tanggal 1
                $gridStart    = $startOfMonth->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                $gridEnd      = $endOfMonth->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                $today        = \Carbon\Carbon::today();
            @endphp

            <div class="cal-grid">
            @for($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay())
                @php
                    $dateKey    = $day->format('Y-m-d');
                    $isToday    = $day->isSameDay($today);
                    $isSelected = $selectedDate && $day->isSameDay($selectedDate);
                    $isThisMonth = $day->month === $month->month;
                    $dayOrders  = $ordersByDate->get($dateKey, collect());
                    $paidCount  = $dayOrders->where('payment_status', 'paid')->count();
                @endphp
                <div class="cal-day {{ $isToday ? 'today' : '' }} {{ $isSelected ? 'selected' : '' }} {{ !$isThisMonth ? 'other-month' : '' }}"
                    @if($isThisMonth)
                    onclick="window.location='{{ route('kitchen.calendar', ['month' => $month->format('Y-m'), 'date' => $dateKey]) }}'"
                    @endif>
                    <div class="cal-day-num">{{ $day->day }}</div>
                    @if($dayOrders->count() > 0)
                    <div>
                        <span class="cal-badge {{ $paidCount === $dayOrders->count() ? 'cal-badge-paid' : '' }}">
                            {{ $dayOrders->count() }} order
                        </span>
                    </div>
                    {{-- Daftar entri (DO/FG) langsung di kotak tanggal --}}
                    @foreach($dayOrders->take(3) as $do)
                    <span class="cal-chip {{ $do->entry_type === 'delivery' ? 'cal-chip-do' : 'cal-chip-fg' }}"
                        title="{{ $do->doc_no }} — {{ $do->party }}">
                        {{ $do->entry_type === 'delivery' ? 'DO' : 'FG' }} · {{ $do->kitchen_scheduled_at->format('H:i') }} {{ $do->party }}
                    </span>
                    @endforeach
                    @if($dayOrders->count() > 3)
                    <div class="cal-more">+{{ $dayOrders->count() - 3 }} lainnya</div>
                    @endif
                    @if($paidCount < $dayOrders->count())
                    <div style="font-size:10px;color:var(--red);margin-top:2px">
                        {{ $dayOrders->count() - $paidCount }} belum lunas
                    </div>
                    @endif
                    @endif
                </div>
            @endfor
            </div>

            {{-- Legenda --}}
            <div style="display:flex;gap:16px;margin-top:12px;font-size:12px;color:var(--text3)">
                <span><span class="cal-badge">n order</span> Ada jadwal</span>
                <span><span class="cal-badge cal-badge-paid">n order</span> Semua lunas</span>
                <span style="border:2px solid var(--blue);border-radius:6px;padding:1px 8px;display:inline-block">Hari ini</span>
            </div>

        </div>
    </div>

    {{-- Panel detail tanggal terpilih --}}
    <div style="position:sticky;top:80px">
        @if($selectedDate)
        <div class="card">
            <div class="card-header" style="padding:14px 16px;border-bottom:1px solid var(--border)">
                <h4 style="font-size:14px;font-weight:700;margin:0">
                    <i class="fas fa-list text-blue" style="margin-right:6px"></i>
                    {{ $selectedDate->isoFormat('dddd, D MMMM Y') }}
                </h4>
                <span class="badge badge-gray">{{ $selectedOrders->count() }} order</span>
            </div>
            <div class="card-body" style="padding:14px 16px">

                @forelse($selectedOrders as $o)
                @php
                    $isDo = $o->entry_type === 'delivery';
                    $pColor = ['unpaid'=>'var(--red)','partial'=>'#F57F17','paid'=>'var(--green)'][$o->payment_status] ?? 'var(--text3)';
                    $pLabel = ['unpaid'=>'Belum Lunas','partial'=>'DP','paid'=>'Lunas'][$o->payment_status] ?? '-';
                    $kLabel = $isDo
                        ? match($o->kitchen_status) {
                            'pending'   => 'Antrian',
                            'preparing' => 'Diproses',
                            'ready'     => 'Siap Kirim',
                            default     => 'Belum Masuk',
                        }
                        : match($o->kitchen_status) {
                            'requested' => 'Antrian',
                            'preparing' => 'Diproses',
                            'done'      => 'Selesai',
                            default     => 'Belum Masuk',
                        };
                    $kColor = match($o->kitchen_status) {
                        'pending', 'requested' => 'badge-yellow',
                        'preparing' => 'badge-blue',
                        'ready', 'done' => 'badge-green',
                        default     => 'badge-gray',
                    };
                @endphp
                <div class="order-list-card">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px">
                        <div>
                            <span class="{{ $isDo ? 'tag-do' : 'tag-fg' }}" style="font-size:10px;font-weight:700;border-radius:4px;padding:1px 6px;margin-right:6px;{{ $isDo ? 'background:#E8F0FE;color:#1967D2' : 'background:#E6F4EA;color:#137333' }}">{{ $isDo ? 'DO' : 'FG' }}</span>
                            <a href="{{ $o->route_show }}"
                                style="font-weight:700;font-size:14px;color:var(--blue);text-decoration:none">
                                {{ $o->doc_no }}
                            </a>
                            <div style="font-size:12px;color:var(--text2);margin-top:2px">
                                <i class="fas fa-user" style="margin-right:4px"></i>{{ $o->party }}
                            </div>
                        </div>
                        <div style="text-align:right">
                            <span class="badge {{ $kColor }}" style="font-size:10px">{{ $kLabel }}</span>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">
                        <div>
                            <i class="fas fa-clock" style="color:var(--blue);margin-right:4px"></i>
                            {{ $o->kitchen_scheduled_at->format('H:i') }} WIB
                        </div>
                        @if($isDo)
                        <div style="font-weight:600;color:{{ $pColor }}">
                            {{ $pLabel }}
                        </div>
                        @endif
                    </div>
                    <div style="font-size:12px;color:var(--text3);margin-top:6px">
                        <i class="fas fa-box" style="margin-right:4px"></i>
                        {{ $o->items_count }} jenis item
                        @if($o->total !== null)
                        &bull; Rp {{ number_format($o->total, 0, ',', '.') }}
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-muted text-sm" style="text-align:center;padding:20px 0">
                    <i class="fas fa-calendar-times" style="font-size:32px;color:var(--border);display:block;margin-bottom:8px"></i>
                    Tidak ada order dijadwalkan pada tanggal ini.
                </div>
                @endforelse

            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body" style="text-align:center;padding:40px 20px;color:var(--text3)">
                <i class="fas fa-hand-pointer" style="font-size:40px;color:var(--border);display:block;margin-bottom:12px"></i>
                <div style="font-size:14px;font-weight:600;margin-bottom:6px">Klik tanggal</div>
                <div style="font-size:13px">untuk melihat Delivery Order yang dijadwalkan masuk dapur pada hari tersebut.</div>
            </div>
        </div>
        @endif
    </div>

</div>
@endsection
