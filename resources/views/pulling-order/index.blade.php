@extends('layouts.app')
@section('title', 'Pulling Order')

@push('styles')
<style>
.filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:20px; }
.filter-bar .form-group { margin-bottom:0; }
.filter-bar label { font-size:12px; font-weight:600; color:var(--text2); display:block; margin-bottom:4px; }
.filter-bar .form-control { padding:8px 12px; font-size:13px; }

.tag-do { background:#E8F0FE; color:#1967D2; border-radius:4px; padding:1px 6px; font-size:11px; font-weight:600; }
.tag-fg { background:#E6F4EA; color:#137333; border-radius:4px; padding:1px 6px; font-size:11px; font-weight:600; }

/* ── Modal ── */
.po-modal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; background:rgba(0,0,0,.5); align-items:center; justify-content:center; }
.po-modal.open { display:flex; }
.po-modal-box { background:var(--surface); border-radius:16px; width:420px; max-width:calc(100vw - 32px); box-shadow:0 8px 40px rgba(0,0,0,.3); overflow:hidden; }
.po-modal-header { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
.po-modal-header h3 { margin:0; font-size:15px; font-weight:700; color:var(--text); flex:1; }
.po-modal-close { border:none; background:none; cursor:pointer; color:var(--text3); font-size:18px; }
.po-modal-body { padding:18px 22px; }
.po-modal-footer { padding:14px 22px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }
</style>
@endpush

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
    <div>
        <h1 class="page-title"><i class="fas fa-list-check text-blue" style="margin-right:8px"></i>Pulling Order</h1>
        <p class="page-subtitle">Semua Delivery Order & Permintaan FG dalam satu tempat — edit pembayaran dan jadwal produksi tanpa buka halaman detail</p>
    </div>
    <a href="{{ route('kitchen.calendar') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-calendar-alt"></i> Kalender
    </a>
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <div class="form-group" style="flex:1;min-width:200px">
                <label>Cari</label>
                <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="No. dokumen / customer...">
            </div>
            <div class="form-group">
                <label>Tipe</label>
                <select name="type" class="form-control form-select" style="width:170px">
                    <option value="">Semua</option>
                    <option value="delivery" @selected($type==='delivery')>Delivery Order</option>
                    <option value="fg_request" @selected($type==='fg_request')>Permintaan FG</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tgl Dari</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            </div>
            <div class="form-group">
                <label>Sampai</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            @if($search || $type || $dateFrom || $dateTo)
            <a href="{{ route('pulling-order.index') }}" class="btn btn-ghost">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tipe</th>
                    <th>No. Dokumen</th>
                    <th>Customer / Pemohon</th>
                    <th>Tanggal</th>
                    <th>Jadwal Produksi</th>
                    <th>Status</th>
                    <th>Status Bayar</th>
                    <th>Total</th>
                    <th>Sisa Bayar</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $payColor = ['unpaid'=>'badge-red','partial'=>'badge-yellow','paid'=>'badge-green'][$row['payment_status']] ?? 'badge-gray';
                    $payLabel = ['unpaid'=>'Belum Lunas','partial'=>'DP','paid'=>'Lunas'][$row['payment_status']] ?? '-';
                    $statusColor = ['draft'=>'badge-gray','confirmed'=>'badge-blue','delivering'=>'badge-yellow','completed'=>'badge-green','submitted'=>'badge-blue'][$row['status']] ?? 'badge-gray';
                    $sch = $row['kitchen_scheduled_at'];
                    $isDelivery   = $row['type'] === 'delivery';
                    $kConfirmed   = $row['kitchen_confirmed_at'] ?? null;
                    $kStatus      = $row['kitchen_status'] ?? null;
                    $canConfirmKitchen = $isDelivery && !$kConfirmed && in_array($row['status'], ['confirmed','delivering']) && $sch;
                @endphp
                <tr>
                    <td><span class="{{ $row['type']==='delivery' ? 'tag-do' : 'tag-fg' }}">{{ $row['type']==='delivery' ? 'DO' : 'FG' }}</span></td>
                    <td><a href="{{ $row['route_show'] }}" class="text-blue font-medium">{{ $row['doc_no'] }}</a></td>
                    <td>{{ $row['party'] }}</td>
                    <td>{{ $row['date']?->isoFormat('D MMM Y') ?? '-' }}</td>
                    <td>
                        @if($sch)
                            <div style="font-size:12px;font-weight:600;color:{{ $sch->isFuture() ? 'var(--blue)' : 'var(--green)' }}">
                                <i class="fas fa-calendar-check" style="margin-right:4px"></i>{{ $sch->isoFormat('D MMM Y HH:mm') }}
                            </div>
                        @else
                            <span class="text-muted text-xs">—</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $statusColor }}">{{ strtoupper($row['status']) }}</span></td>
                    <td>
                        @if($row['type']==='delivery')
                            <span class="badge {{ $payColor }}">{{ $payLabel }}</span>
                            @if(!empty($row['bill_as_receivable']))
                                <span class="badge badge-blue" title="Tagih sebagai piutang (AR)">AR</span>
                            @endif
                        @else
                            <span class="text-muted text-xs">—</span>
                        @endif
                    </td>
                    <td class="money">{{ $row['total'] !== null ? 'Rp '.number_format($row['total'],0,',','.') : '—' }}</td>
                    <td class="money">
                        @if($row['outstanding'] !== null)
                            <span style="font-weight:700;color:{{ $row['outstanding'] > 0 ? 'var(--red)' : 'var(--green)' }}">
                                Rp {{ number_format($row['outstanding'],0,',','.') }}
                            </span>
                        @else
                            <span class="text-muted text-xs">—</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap">
                        @if($isDelivery)
                            @if($kConfirmed)
                                <span class="badge badge-green" title="Dikonfirmasi ke dapur {{ $kConfirmed->isoFormat('D MMM Y HH:mm') }}">
                                    <i class="fas fa-check-circle"></i> {{ $kStatus ? 'Di Antrian' : 'Terjadwal' }}
                                </span>
                            @elseif($canConfirmKitchen)
                                <button type="button" class="btn btn-primary btn-sm" title="Konfirmasi jadwal → masuk Kitchen Monitor"
                                    onclick="confirmKitchen({{ $row['id'] }}, '{{ $row['doc_no'] }}')">
                                    <i class="fas fa-utensils"></i> Konfirmasi
                                </button>
                            @elseif($row['status'] === 'draft')
                                <span class="text-muted text-xs" title="Konfirmasi order dari menu Delivery Order dulu">Belum dikonfirmasi</span>
                            @elseif(!$sch)
                                <span class="text-muted text-xs" title="Set jadwal produksi dulu">Set jadwal dulu</span>
                            @endif
                        @endif
                        <button type="button" class="btn btn-ghost btn-sm" title="Jadwal Produksi"
                            onclick="openScheduleModal('{{ $row['type'] }}', {{ $row['id'] }}, '{{ $row['doc_no'] }}', {{ $sch ? "'".$sch->format('Y-m-d')."'" : 'null' }}, {{ $sch ? $sch->format('H') : 'null' }}, {{ $sch ? "'".$sch->format('i')."'" : 'null' }})">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                        @if($row['type']==='delivery')
                        <button type="button" class="btn btn-ghost btn-sm" title="Tambah Payment"
                            onclick="openPaymentModal({{ $row['id'] }}, '{{ $row['doc_no'] }}', {{ (float) $row['outstanding'] }})">
                            <i class="fas fa-money-bill-wave"></i>
                        </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text3)">Tidak ada order yang sesuai filter</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal: Jadwal Produksi --}}
<div class="po-modal" id="scheduleModal">
    <div class="po-modal-box">
        <div class="po-modal-header">
            <i class="fas fa-calendar-alt" style="color:var(--blue)"></i>
            <h3>Jadwal Produksi — <span id="scheduleDocNo"></span></h3>
            <button type="button" class="po-modal-close" onclick="closeScheduleModal()">&times;</button>
        </div>
        <form id="scheduleForm">
            <div class="po-modal-body">
                <div class="form-group" style="margin-bottom:8px">
                    <label class="form-label" style="font-size:12px">Tanggal</label>
                    <input type="date" id="scheduleDate" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label" style="font-size:12px">Jam</label>
                    <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:6px;align-items:center">
                        <select id="scheduleHour" class="form-control form-select" required>
                            @for($h = 0; $h < 24; $h++)
                            <option value="{{ str_pad($h,2,'0',STR_PAD_LEFT) }}">{{ str_pad($h,2,'0',STR_PAD_LEFT) }}</option>
                            @endfor
                        </select>
                        <span style="font-weight:700">:</span>
                        <select id="scheduleMinute" class="form-control form-select" required>
                            @foreach(['00','05','10','15','20','25','30','35','40','45','50','55'] as $mm)
                            <option value="{{ $mm }}">{{ $mm }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="po-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeScheduleModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="scheduleSubmitBtn">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal: Tambah Payment (Delivery Order saja) --}}
<div class="po-modal" id="paymentModal">
    <div class="po-modal-box">
        <div class="po-modal-header">
            <i class="fas fa-money-bill-wave" style="color:var(--green)"></i>
            <h3>Tambah Payment — <span id="paymentDocNo"></span></h3>
            <button type="button" class="po-modal-close" onclick="closePaymentModal()">&times;</button>
        </div>
        <form id="paymentForm">
            <div class="po-modal-body">
                <div class="form-group" style="margin-bottom:8px">
                    <label class="form-label" style="font-size:12px">Metode</label>
                    <select id="paymentMethod" class="form-control form-select" required>
                        @foreach($mopList ?? ['Cash','Transfer Bank','QRIS','Kartu'] as $mop)
                        <option value="{{ $mop }}">{{ $mop }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:8px">
                    <label class="form-label" style="font-size:12px">Jumlah (Rp) *</label>
                    <input type="number" id="paymentAmount" class="form-control" required min="1" step="1">
                </div>
                <div class="form-group" style="margin-bottom:8px">
                    <label class="form-label" style="font-size:12px">Tanggal</label>
                    <input type="date" id="paymentDate" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="form-group" style="margin-bottom:8px">
                    <label class="form-label" style="font-size:12px">No. Referensi</label>
                    <input type="text" id="paymentReference" class="form-control" placeholder="Nomor transfer / kode unik">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label" style="font-size:12px">Catatan</label>
                    <input type="text" id="paymentNotes" class="form-control" placeholder="Opsional">
                </div>
            </div>
            <div class="po-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closePaymentModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="paymentSubmitBtn">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
let scheduleType = null;
let scheduleId   = null;

function openScheduleModal(type, id, docNo, date, hour, minute) {
    scheduleType = type;
    scheduleId   = id;
    document.getElementById('scheduleDocNo').textContent = docNo;
    document.getElementById('scheduleDate').value = date || '{{ now()->addDay()->format("Y-m-d") }}';
    document.getElementById('scheduleHour').value = hour !== null ? String(hour).padStart(2,'0') : '08';
    document.getElementById('scheduleMinute').value = minute || '00';
    document.getElementById('scheduleModal').classList.add('open');
}
function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('open');
}

document.getElementById('scheduleForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('scheduleSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Menyimpan...';

    const url = scheduleType === 'delivery'
        ? `{{ url('pulling-order/delivery-orders') }}/${scheduleId}/schedule`
        : `{{ url('pulling-order/stock-requests') }}/${scheduleId}/schedule`;

    try {
        const res = await api.post(url, {
            kitchen_scheduled_date:   document.getElementById('scheduleDate').value,
            kitchen_scheduled_hour:   document.getElementById('scheduleHour').value,
            kitchen_scheduled_minute: document.getElementById('scheduleMinute').value,
        });
        if (res.success) {
            toast('Jadwal produksi disimpan: ' + res.scheduled_at, 'success');
            closeScheduleModal();
            setTimeout(() => location.reload(), 600);
        } else {
            toast(res.error || 'Gagal menyimpan jadwal', 'error');
        }
    } catch (err) {
        toast(err.message, 'error');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Simpan';
});

// Konfirmasi jadwal DO → masuk Kitchen Monitor
async function confirmKitchen(id, docNo) {
    if (!confirm('Konfirmasi jadwal ' + docNo + ' ke Kitchen Monitor?\n\nSetelah dikonfirmasi, order akan masuk antrian dapur sesuai jadwal produksinya.')) return;
    try {
        const res = await api.post(`{{ url('pulling-order/delivery-orders') }}/${id}/confirm-kitchen`, {});
        if (res.success) {
            toast(res.in_queue
                ? 'Dikonfirmasi — masuk antrian dapur sekarang.'
                : 'Jadwal dikonfirmasi. Masuk antrian otomatis saat waktunya tiba (' + res.scheduled_at + ').', 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            toast(res.error || 'Gagal konfirmasi', 'error');
        }
    } catch (err) {
        toast(err.message, 'error');
    }
}

let paymentOrderId = null;

function openPaymentModal(id, docNo, outstanding) {
    paymentOrderId = id;
    document.getElementById('paymentDocNo').textContent = docNo;
    document.getElementById('paymentAmount').value = outstanding > 0 ? Math.round(outstanding) : '';
    document.getElementById('paymentReference').value = '';
    document.getElementById('paymentNotes').value = '';
    document.getElementById('paymentModal').classList.add('open');
}
function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('open');
}

document.getElementById('paymentForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('paymentSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Menyimpan...';

    const url = `{{ url('pulling-order/delivery-orders') }}/${paymentOrderId}/payments`;

    try {
        const res = await api.post(url, {
            payment_method: document.getElementById('paymentMethod').value,
            amount:         document.getElementById('paymentAmount').value,
            payment_date:   document.getElementById('paymentDate').value,
            reference_no:   document.getElementById('paymentReference').value,
            notes:          document.getElementById('paymentNotes').value,
        });
        if (res.success) {
            toast('Payment berhasil ditambahkan' + (res.warning ? ' (' + res.warning + ')' : ''), res.warning ? 'warning' : 'success');
            closePaymentModal();
            setTimeout(() => location.reload(), 600);
        } else {
            toast(res.error || 'Gagal menyimpan payment', 'error');
        }
    } catch (err) {
        toast(err.message, 'error');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Simpan';
});
</script>
@endpush
