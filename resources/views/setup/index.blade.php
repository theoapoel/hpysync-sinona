<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Setup Awal — HPYSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #F1F5F9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .setup-wrap {
            width: 100%;
            max-width: 560px;
        }

        /* Steps indicator */
        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: 28px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .step-circle {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700;
        }
        .step.active .step-circle { background: #4285F4; color: #fff; }
        .step.done   .step-circle { background: #34A853; color: #fff; }
        .step.idle   .step-circle { background: #E2E8F0; color: #94A3B8; }
        .step-label { font-size: 11px; font-weight: 600; color: #64748B; white-space: nowrap; }
        .step.active .step-label { color: #4285F4; }
        .step.done   .step-label { color: #34A853; }
        .step-line { flex: 1; height: 2px; background: #E2E8F0; min-width: 40px; margin: 0 4px; margin-bottom: 22px; }
        .step-line.done { background: #34A853; }

        /* Card */
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .card-top {
            background: linear-gradient(135deg, #4285F4, #1967D2);
            padding: 28px 32px 24px;
            text-align: center;
        }
        .card-top img { height: 40px; margin-bottom: 16px; }
        .card-top h1 { font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 6px; }
        .card-top p { font-size: 13px; color: rgba(255,255,255,.8); line-height: 1.5; }

        .card-body { padding: 28px 32px; }

        .info-box {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: #1E40AF;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.5;
        }
        .info-box i { flex-shrink: 0; margin-top: 2px; }

        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-label span { color: #EF4444; }
        .form-hint { font-size: 12px; color: #9CA3AF; margin-top: 4px; }

        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%); color: #9CA3AF; font-size: 14px;
            pointer-events: none;
        }
        .form-input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #111827;
            background: #FAFAFA;
            transition: all .2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #4285F4;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(66,133,244,.12);
        }
        .form-input.is-error { border-color: #EF4444; }
        .error-text { font-size: 12px; color: #EF4444; margin-top: 4px; }

        /* URL row with test button */
        .url-row { display: flex; gap: 8px; }
        .url-row .form-input { flex: 1; }
        .btn-test {
            padding: 11px 14px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            background: #F9FAFB;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            white-space: nowrap;
            transition: all .2s;
            font-family: 'Inter', sans-serif;
            display: flex; align-items: center; gap: 6px;
        }
        .btn-test:hover { border-color: #4285F4; color: #4285F4; background: #EFF6FF; }
        .btn-test:disabled { opacity: .6; cursor: not-allowed; }

        /* Connection status */
        .conn-status {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 8px;
        }
        .conn-status.ok  { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; display: flex; }
        .conn-status.err { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; display: flex; }
        .conn-status.loading { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; display: flex; }

        .divider { border: none; border-top: 1px solid #F1F5F9; margin: 20px 0; }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: #4285F4;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all .2s;
        }
        .btn-submit:hover { background: #1967D2; box-shadow: 0 4px 16px rgba(66,133,244,.35); }

        .alert-success {
            background: #F0FDF4; border: 1px solid #BBF7D0;
            color: #166534; border-radius: 10px;
            padding: 12px 16px; margin-bottom: 20px;
            font-size: 13px; display: flex; align-items: center; gap: 8px;
        }
        .alert-error {
            background: #FEF2F2; border: 1px solid #FECACA;
            color: #991B1B; border-radius: 10px;
            padding: 12px 16px; margin-bottom: 20px;
            font-size: 13px; display: flex; align-items: center; gap: 8px;
        }

        .footer-note {
            text-align: center;
            font-size: 12px;
            color: #9CA3AF;
            margin-top: 20px;
        }

        .spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .6s linear infinite;
            display: inline-block;
        }
        .spinner-blue {
            border-color: rgba(66,133,244,.2);
            border-top-color: #4285F4;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="setup-wrap">

    {{-- Steps --}}
    <div class="steps">
        <div class="step active">
            <div class="step-circle"><i class="fas fa-plug"></i></div>
            <div class="step-label">Konfigurasi</div>
        </div>
        <div class="step-line"></div>
        <div class="step idle">
            <div class="step-circle">2</div>
            <div class="step-label">Login Admin</div>
        </div>
        <div class="step-line"></div>
        <div class="step idle">
            <div class="step-circle">3</div>
            <div class="step-label">POS Profile</div>
        </div>
        <div class="step-line"></div>
        <div class="step idle">
            <div class="step-circle">4</div>
            <div class="step-label">Sync Data</div>
        </div>
    </div>

    <div class="card">
        <div class="card-top">
            <img src="{{ asset('images/happypos.png') }}" alt="HPYSync">
            <h1>Setup Koneksi ERP HPY</h1>
            <p>Langkah pertama: hubungkan aplikasi POS ini<br>ke server ERP HPY Anda.</p>
        </div>

        <div class="card-body">

            @if(session('info'))
            <div class="alert-error">
                <i class="fas fa-info-circle"></i> {{ session('info') }}
            </div>
            @endif

            @if($errors->any())
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
            </div>
            @endif

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    Pastikan Anda memiliki akses ke server ERP HPY dan koneksi internet aktif.
                    Konfigurasi ini hanya perlu dilakukan <strong>sekali</strong> saat pertama kali setup.
                </div>
            </div>

            <form method="POST" action="{{ route('setup.store') }}" id="setupForm">
                @csrf

                {{-- URL --}}
                <div class="form-group">
                    <label class="form-label">
                        URL Server ERP HPY <span>*</span>
                    </label>
                    <div class="url-row">
                        <div class="input-wrap" style="flex:1">
                            <i class="fas fa-globe input-icon"></i>
                            <input type="url" name="erpnext_url" id="urlInput"
                                class="form-input {{ $errors->has('erpnext_url') ? 'is-error' : '' }}"
                                placeholder="http://erp.perusahaan.com"
                                value="{{ old('erpnext_url', $url) }}" required autofocus>
                        </div>
                        <button type="button" class="btn-test" id="testBtn" onclick="testConnection()">
                            <i class="fas fa-wifi" id="testIcon"></i>
                            <span id="testLabel">Test</span>
                        </button>
                    </div>
                    <div class="conn-status" id="connStatus"></div>
                    <p class="form-hint">Contoh: http://192.168.1.100:8000 atau https://erp.namaperusahaan.com</p>
                </div>

                {{-- Company --}}
                <div class="form-group">
                    <label class="form-label">
                        Nama Company <span>*</span>
                    </label>
                    <div class="input-wrap">
                        <i class="fas fa-building input-icon"></i>
                        <input type="text" name="erpnext_company"
                            class="form-input {{ $errors->has('erpnext_company') ? 'is-error' : '' }}"
                            placeholder="PT. Nama Perusahaan"
                            value="{{ old('erpnext_company', $company) }}" required>
                    </div>
                    <p class="form-hint">Harus sama persis dengan nama Company di ERP HPY.</p>
                    @error('erpnext_company')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <hr class="divider">

                <button type="submit" class="btn-submit">
                    <i class="fas fa-arrow-right"></i>
                    Simpan &amp; Lanjutkan ke Login
                </button>
            </form>

        </div>
    </div>

    <p class="footer-note">HPYSync &copy; {{ date('Y') }} — Powered by HPY Solution</p>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

async function testConnection() {
    const url    = document.getElementById('urlInput').value.trim();
    const btn    = document.getElementById('testBtn');
    const icon   = document.getElementById('testIcon');
    const label  = document.getElementById('testLabel');
    const status = document.getElementById('connStatus');

    if (!url) {
        showStatus('err', 'Isi URL terlebih dahulu.');
        return;
    }

    btn.disabled = true;
    icon.className = '';
    label.textContent = 'Menguji...';
    showStatus('loading', '<span class="spinner spinner-blue"></span> Menghubungi server ERP HPY...');

    try {
        const resp = await fetch('{{ route("setup.test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ url }),
        });
        const data = await resp.json();

        if (data.success) {
            showStatus('ok', '<i class="fas fa-check-circle"></i> ' + data.message);
        } else {
            showStatus('err', '<i class="fas fa-times-circle"></i> ' + data.error);
        }
    } catch (e) {
        showStatus('err', '<i class="fas fa-times-circle"></i> Gagal menghubungi server: ' + e.message);
    }

    btn.disabled = false;
    icon.className = 'fas fa-wifi';
    label.textContent = 'Test';
}

function showStatus(type, html) {
    const el = document.getElementById('connStatus');
    el.className = 'conn-status ' + type;
    el.innerHTML = html;
}
</script>
</body>
</html>
