<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — HPYSync</title>
    {{-- <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet"> --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Roboto', sans-serif;
            background: #F8F9FA;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-wrap {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #FFFFF0 0%, #FFF8DC 25%, #FFE082 50%, #FFC107 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }
        .login-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        /*.left-brand { text-align: center; margin-bottom: 48px; position: relative; }
        .left-logo {
            width: 340px; height: 120px;
            background: rgba(255,255,255,.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            padding: 16px 24px;
        }*/
        /*.left-brand { text-align: center; margin-bottom: 48px; position: relative; animation: fadeInDown .8s ease; }*/
        .left-brand { text-align: center; margin-bottom: 40px; position: relative; animation: fadeInDown .8s ease; }

        .left-sub {
            color: rgba(255,255,255,.8);
            font-size: 15px;
            margin-top: 10px;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            letter-spacing: .3px;
        }

        .feature-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: rgba(255,255,255,.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; flex-shrink: 0;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /*.left-logo {
            width: 340px; height: 120px;
            background: rgba(255,255,255,.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            padding: 16px 24px;
            box-shadow: 0 0 30px rgba(255,255,255,.2), 0 0 60px rgba(66,133,244,.3);
            animation: floatLogo 3s ease-in-out infinite;
        }*/
        .left-logo {
            width: 380px; height: 140px;
            background: rgba(255,255,255,.18);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.4);
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            padding: 20px 28px;
            box-shadow:
                0 0 0 1px rgba(255,255,255,.1),
                0 8px 32px rgba(0,0,0,.2),
                0 0 60px rgba(255,255,255,.15),
                0 0 100px rgba(66,133,244,.25);
            animation: floatLogo 4s ease-in-out infinite;
        }

        @keyframes floatLogo {
            0%   { transform: translateY(0px);   box-shadow: 0 0 30px rgba(255,255,255,.2), 0 0 60px rgba(66,133,244,.3); }
            50%  { transform: translateY(-8px);  box-shadow: 0 8px 40px rgba(255,255,255,.25), 0 0 80px rgba(66,133,244,.4); }
            100% { transform: translateY(0px);   box-shadow: 0 0 30px rgba(255,255,255,.2), 0 0 60px rgba(66,133,244,.3); }
        }

        .left-logo svg { width: 48px; height: 48px; }
        .left-title { font-family: 'Inter', sans-serif; font-size: 36px; font-weight: 700; color: #4A2E00; }
        .left-sub {
            color: #7A4500;
            font-size: 15px;
            margin-top: 8px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            letter-spacing: .3px;
        }
    .feature-list { list-style: none; position: relative; }
    .feature-list li {
        display: flex; align-items: center; gap: 12px;
        color: #4A2E00; font-size: 15px; margin-bottom: 20px;
        font-weight: 500;
        animation: fadeInLeft .6s ease both;
    }
        .feature-list li:nth-child(1) { animation-delay: .1s; }
        .feature-list li:nth-child(2) { animation-delay: .2s; }
        .feature-list li:nth-child(3) { animation-delay: .3s; }
        .feature-list li:nth-child(4) { animation-delay: .4s; }

        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to   { opacity: 1; transform: translateX(0); }
        }        
        .feature-icon { width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .dots { display: flex; gap: 8px; margin-top: 48px; }
        /* Social Bar */
        .social-bar {
            position: absolute;
            bottom: 28px;
            left: 0; right: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 0 40px;
        }
        .social-item {
            display: flex; align-items: center; gap: 5px;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,.5);
        }
        .social-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .social-item.whatsapp { color: #25D366; }
        .social-item.whatsapp:hover { background: #25D366; color: #fff; }
        .social-item.website { color: #4285F4; }
        .social-item.website:hover { background: #4285F4; color: #fff; }
        .social-item.instagram { color: #E1306C; }
        .social-item.instagram:hover { background: linear-gradient(45deg,#F58529,#DD2A7B,#8134AF); color: #fff; }
        .social-item.tiktok { color: #010101; }
        .social-item.tiktok:hover { background: #010101; color: #fff; }
        .social-dot { color: rgba(120,70,0,.3); font-size: 10px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,.3); }
        .dot.active { background: #FBBC05; width: 24px; border-radius: 4px; }

        /*.login-right {
            width: 480px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #fff;
        }
        .login-card { width: 100%; max-width: 360px; }
        .login-header { text-align: center; margin-bottom: 40px; }
        .login-header h2 { font-family: 'Google Sans', sans-serif; font-size: 28px; font-weight: 700; color: #202124; }
        .login-header p { color: #5F6368; margin-top: 8px; font-size: 15px; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #5F6368; margin-bottom: 8px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #80868B; font-size: 16px; }
        .form-input {
            width: 100%; padding: 12px 14px 12px 42px;
            border: 1px solid #DADCE0; border-radius: 8px;
            font-size: 15px; color: #202124;
            transition: border-color .2s, box-shadow .2s;
            font-family: 'Roboto', sans-serif;
        }
        .form-input:focus { outline: none; border-color: #4285F4; box-shadow: 0 0 0 3px rgba(66,133,244,.15); }

        .btn-login {
            width: 100%; padding: 14px;
            background: #4285F4; color: #fff;
            border: none; border-radius: 24px;
            font-size: 15px; font-weight: 700;
            font-family: 'Google Sans', sans-serif;
            cursor: pointer; transition: all .2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-login:hover { background: #1967D2; box-shadow: 0 4px 12px rgba(66,133,244,.4); }

        .error-msg { background: #FCE8E6; color: #C5221F; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .footer-text { text-align: center; margin-top: 32px; color: #80868B; font-size: 13px; }

        .color-dots { display: flex; gap: 6px; justify-content: center; margin-bottom: 24px; }
        .color-dot { width: 10px; height: 10px; border-radius: 50%; } */

        .login-right {
            width: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px;
            background: #fff;
            animation: fadeInRight .6s ease;
        }
        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .login-card { width: 100%; max-width: 380px; }

        /* Header */
        .login-header { margin-bottom: 36px; }
        .login-header h2 {
            font-family: 'Inter', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: #0F1117;
            line-height: 1.3;
            letter-spacing: -.4px;
            margin-bottom: 8px;
        }
        .login-header p {
            font-family: 'Inter', sans-serif;
            color: #6B7280;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.6;
        }

        /* Form */
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            letter-spacing: .1px;
        }
        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%); color: #9CA3AF; font-size: 15px;
        }
        .form-input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            color: #0F1117;
            background: #FAFAFA;
            transition: all .2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #4285F4;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(66,133,244,.12);
        }
        .form-input::placeholder { color: #9CA3AF; }

        /* Button */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #4285F4;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all .2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            letter-spacing: .2px;
            margin-top: 4px;
        }
        /* .btn-login:hover {
            background: #1967D2;
            box-shadow: 0 4px 16px rgba(66,133,244,.35);
            transform: translateY(-1px);
        } */

        .btn-login:hover {
            background: linear-gradient(135deg, #5B9CF6, #1967D2);
            box-shadow: 0 6px 20px rgba(66,133,244,.4);
            transform: translateY(-2px);
        }
        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(66,133,244,.3);
        }
        .btn-login:active { transform: translateY(0); }

        /* Divider */
        .login-divider {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0;
            color: #D1D5DB; font-size: 12px; font-family: 'Inter', sans-serif;
        }
        .login-divider::before, .login-divider::after {
            content: ''; flex: 1; height: 1px; background: #E5E7EB;
        }

        /* Error */
        .error-msg {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            display: flex; align-items: center; gap: 8px;
        }

        /* Footer */
        .footer-text {
            text-align: center;
            margin-top: 28px;
            color: #9CA3AF;
            font-size: 12px;
            font-family: 'Inter', sans-serif;
        }

        /* Tagline badge */
        .tagline-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #EFF6FF; color: #3B82F6;
            border: 1px solid #BFDBFE;
            border-radius: 20px; padding: 4px 12px;
            font-size: 12px; font-weight: 500;
            font-family: 'Inter', sans-serif;
            margin-bottom: 16px;
        }

        /* Trust line */
        .trust-line {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            color: #9CA3AF;
            flex-wrap: wrap;
        }
        .trust-line i { color: #4285F4; font-size: 10px; }
        .trust-dot { color: #D1D5DB; }

        /* Input elevated */
        .form-input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            color: #0F1117;
            background: #FAFAFA;
            transition: all .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }

        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { width: 100%; }
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-left">
        <div class="left-brand">
            <div class="left-logo">
                <img src="{{ asset('images/happypos.png') }}" alt="HPYSync"
    style="max-width:100%;max-height:100%;object-fit:contain;">
            </div>
            <div class="left-sub">Integrated Hybrid POS & ERP</div>
        </div>
        <ul class="feature-list">
            <li>
                <div class="feature-icon"><i class="fas fa-bolt" style="color:#FBBC05"></i></div>
                Transaksi cepat & mudah
            </li>
            <li>
                <div class="feature-icon"><i class="fas fa-sync-alt" style="color:#34A853"></i></div>
                Sinkronisasi HPY System
            </li>
            <li>
                <div class="feature-icon"><i class="fas fa-chart-line" style="color:#EA4335"></i></div>
                Laporan & analitik real-time
            </li>
            <li>
                <div class="feature-icon"><i class="fas fa-barcode" style="color:rgba(255,255,255,.8)"></i></div>
                Support barcode scanner
            </li>
        </ul>
        {{-- <<div class="dots">
            <div class="dot active"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div> --}}

        <!-- Social Bar -->
        <div class="social-bar">
            <a href="https://wa.me/+628119660855" target="_blank" class="social-item whatsapp">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </a>
            <span class="social-dot">•</span>
            <a href="https://hpysolution.com" target="_blank" class="social-item website">
                <i class="fas fa-globe"></i> hpysolution.com
            </a>
            <span class="social-dot">•</span>
            <a href="https://instagram.com/hpysolution" target="_blank" class="social-item instagram">
                <i class="fab fa-instagram"></i> @hpysolution
            </a>
            <span class="social-dot">•</span>
            <a href="https://tiktok.com/@hpy.solution" target="_blank" class="social-item tiktok">
                <i class="fab fa-tiktok"></i> @hpy.solution
            </a>
        </div>
    </div>

    <div class="login-right">
        <div class="login-card">
            {{-- <div class="login-header">
                <div class="login-header">
                    <img src="/images/happypos.png" alt="HappyPos"
                        style="height:100px;width:auto;object-fit:contain;margin-bottom:16px;border-radius:8px;">
                    <h2>Selamat Datang</h2>
                    <p>Masuk ke akun HappyPos Anda</p>
                </div>
            </div> --}}
            {{-- <div class="login-header">
                <h2>Kasir tetap jalan, walau internet mati</h2>
                <p>Masuk ke akun HappyPos Anda</p>
            </div> --}}
            <div class="login-header">
                <div class="tagline-badge">
                    <i class="fas fa-bolt"></i> Integrated POS & ERP
                </div>
                <h2>Kasir tetap jalan,<br>walau internet mati</h2>
                <p>Gunakan email &amp; password akun <strong>ERP HPY</strong> Anda untuk masuk.</p>
            </div>
            @if(session('setup_done'))
            <div style="background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-family:'Inter',sans-serif;display:flex;align-items:center;gap:8px">
                <i class="fas fa-check-circle"></i> {{ session('setup_done') }}
            </div>
            @endif

            @if($errors->any())
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" id="loginForm">
                @csrf
                <input type="hidden" name="mode" id="loginMode" value="{{ old('mode', 'online') }}">

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-input"
                            placeholder="email@company.com"
                            value="{{ old('email') }}" required autofocus>
                    </div>
                </div>

                {{-- Online: Password --}}
                <div class="form-group" id="passwordGroup" style="{{ old('mode') === 'pin' ? 'display:none' : '' }}">
                    <label class="form-label">Password ERP HPY</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="passwordInput" class="form-input"
                            placeholder="••••••••" {{ old('mode') !== 'pin' ? 'required' : '' }}>
                    </div>
                </div>

                {{-- Offline: PIN --}}
                <div class="form-group" id="pinGroup" style="{{ old('mode') === 'pin' ? '' : 'display:none' }}">
                    <label class="form-label">PIN Kasir <span style="font-weight:400;color:#9CA3AF">(6 digit)</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-hashtag input-icon"></i>
                        <input type="text" name="pin" id="pinInput" class="form-input"
                            placeholder="••••••" maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                            {{ old('mode') === 'pin' ? 'required' : '' }}
                            value="{{ old('pin') }}">
                    </div>
                    @error('pin')
                        <p style="font-size:12px;color:#DC2626;margin-top:4px">{{ $message }}</p>
                    @enderror
                    <p style="font-size:12px;color:#9CA3AF;margin-top:4px">
                        <i class="fas fa-wifi-slash"></i> Mode offline — tidak memerlukan koneksi internet
                    </p>
                </div>

                <button type="submit" class="btn-login" id="submitBtn">
                    <i class="fas fa-sign-in-alt" id="submitIcon"></i>
                    <span id="submitLabel">Masuk</span>
                </button>

                {{-- Toggle mode --}}
                <div style="text-align:center;margin-top:16px">
                    <button type="button" id="toggleModeBtn" onclick="toggleMode()"
                        style="background:none;border:none;cursor:pointer;font-size:13px;font-family:'Inter',sans-serif;color:#6B7280;display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;transition:all .2s">
                        <i class="fas fa-wifi-slash" id="toggleIcon"></i>
                        <span id="toggleLabel">Login offline dengan PIN</span>
                    </button>
                </div>

                <div class="trust-line">
                    <span><i class="fas fa-bolt"></i> Online: password ERP HPY</span>
                    <span class="trust-dot">•</span>
                    <span><i class="fas fa-lock"></i> Offline: PIN 6 digit</span>
                </div>
            </form>

            <script>
            // Inisialisasi dari old('mode') jika ada error validasi
            (function() {
                const mode = document.getElementById('loginMode').value;
                if (mode === 'pin') _applyPinMode();
                else _applyOnlineMode();
            })();

            function toggleMode() {
                const current = document.getElementById('loginMode').value;
                if (current === 'online') _applyPinMode();
                else _applyOnlineMode();
            }

            function _applyPinMode() {
                document.getElementById('loginMode').value = 'pin';
                document.getElementById('passwordGroup').style.display = 'none';
                document.getElementById('pinGroup').style.display = '';
                document.getElementById('passwordInput').removeAttribute('required');
                document.getElementById('pinInput').setAttribute('required', '');
                document.getElementById('submitIcon').className = 'fas fa-key';
                document.getElementById('submitLabel').textContent = 'Masuk Offline';
                document.getElementById('submitBtn').style.background = '#374151';
                document.getElementById('toggleIcon').className = 'fas fa-wifi';
                document.getElementById('toggleLabel').textContent = 'Login online dengan password ERP HPY';
                document.getElementById('toggleModeBtn').style.color = '#4285F4';
            }

            function _applyOnlineMode() {
                document.getElementById('loginMode').value = 'online';
                document.getElementById('passwordGroup').style.display = '';
                document.getElementById('pinGroup').style.display = 'none';
                document.getElementById('pinInput').removeAttribute('required');
                document.getElementById('passwordInput').setAttribute('required', '');
                document.getElementById('submitIcon').className = 'fas fa-sign-in-alt';
                document.getElementById('submitLabel').textContent = 'Masuk';
                document.getElementById('submitBtn').style.background = '';
                document.getElementById('toggleIcon').className = 'fas fa-wifi-slash';
                document.getElementById('toggleLabel').textContent = 'Login offline dengan PIN';
                document.getElementById('toggleModeBtn').style.color = '#6B7280';
            }
            </script>

            <div class="footer-text">
                HPYSync &copy; {{ date('Y') }} — Powered by HPY
            </div>
        </div>
    </div>
</div>
</body>
</html>
