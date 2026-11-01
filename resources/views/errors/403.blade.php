@extends('layouts.app')
@section('title', '403 — Akses Ditolak')

@section('content')
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;text-align:center">
    <div style="width:88px;height:88px;background:#FCE8E6;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:20px">
        <i class="fas fa-lock" style="font-size:36px;color:var(--red)"></i>
    </div>
    <div style="font-family:'Google Sans',sans-serif;font-size:64px;font-weight:700;color:var(--border);line-height:1;margin-bottom:8px">403</div>
    <h2 style="font-family:'Google Sans',sans-serif;font-size:22px;font-weight:700;margin-bottom:8px">Akses Ditolak</h2>
    <p style="color:var(--text3);font-size:14px;max-width:400px;margin-bottom:6px">
        Role Anda (<strong style="color:var(--text2)">{{ auth()->user()?->role ?? '—' }}</strong>)
        tidak memiliki izin untuk mengakses halaman ini.
    </p>
    <p style="color:var(--text3);font-size:13px;margin-bottom:28px">Hubungi Administrator jika Anda memerlukan akses.</p>
    <div style="display:flex;gap:10px">
        <a href="javascript:history.back()" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
        @if(auth()->user()?->role === 'cashier')
            <a href="{{ route('pos.index') }}" class="btn btn-primary"><i class="fas fa-cash-register"></i> Buka Kasir</a>
        @else
            <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="fas fa-th-large"></i> Dashboard</a>
        @endif
    </div>
</div>
@endsection
