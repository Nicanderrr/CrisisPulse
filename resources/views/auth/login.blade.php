<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Login | {{ config('app.name') }}</title>
        <link rel="icon" href="{{ $brandLogoUrl ?: asset('favicon.ico') }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="cp-auth-page cp-login-page">
        <main class="cp-login-shell">
            <section class="cp-login-splash">
                @if ($loginMediaUrl && $loginMediaType === 'video')
                    <video class="cp-login-media" src="{{ $loginMediaUrl }}" autoplay muted loop playsinline></video>
                @elseif ($loginMediaUrl)
                    <img class="cp-login-media" src="{{ $loginMediaUrl }}" alt="">
                @endif
                <div class="cp-login-media-overlay"></div>
                <div class="cp-login-splash-grid"></div>
                <div class="cp-login-splash-content">
                    <div class="cp-login-badge"><i class="bi bi-heart-pulse-fill"></i></div>
                    <div class="cp-login-brand">
                        @if ($brandLogoUrl)
                            <img src="{{ $brandLogoUrl }}" alt="{{ config('app.name') }} logo">
                        @else
                            <span class="cp-brand-mark">CP</span>
                        @endif
                    </div>
                    <div class="cp-login-overline">Korle Bu Teaching Hospital</div>
                    <h1>CrisisPulse <span>AI</span></h1>
                    <p>Communication intelligence for moments that matter.</p>
                    <div class="cp-login-signal"><i class="bi bi-activity"></i><span>Monitoring public trust signals in Accra</span></div>
                </div>
                <div class="cp-login-splash-foot"><i class="bi bi-shield-check"></i> Secure crisis communication workspace</div>
            </section>

            <section class="cp-login-form-side">
                <div class="cp-login-form-wrap">
                    <div class="cp-login-kicker"><i class="bi bi-shield-lock-fill"></i> Secure Login</div>
                    <h2>Welcome back</h2>
                    <p class="cp-login-copy">Sign in to your crisis communication command desk.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger border-0">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="d-grid gap-3" id="loginForm">
                        @csrf
                        <div>
                            <label for="email" class="form-label">Email address</label>
                            <div class="input-group cp-login-input">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control" placeholder="name@example.com" autocomplete="username" required autofocus>
                            </div>
                        </div>
                        <div>
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group cp-login-input">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input id="password" name="password" type="password" class="form-control" placeholder="Enter your password" autocomplete="current-password" required>
                                <button type="button" class="btn cp-password-toggle" id="togglePassword" aria-label="Show password"><i class="bi bi-eye" id="passwordIcon"></i></button>
                            </div>
                        </div>
                        <label class="form-check cp-remember"><input class="form-check-input" type="checkbox" name="remember"> <span class="form-check-label">Remember this station</span></label>
                        <button type="submit" class="btn cp-login-button" id="loginButton"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</button>
                    </form>
                    <p class="cp-login-footer">Authorized hospital communications personnel only</p>
                </div>
            </section>
        </main>
        <script>
            document.getElementById('togglePassword')?.addEventListener('click', () => {
                const field = document.getElementById('password');
                const icon = document.getElementById('passwordIcon');
                const visible = field.type === 'password';
                field.type = visible ? 'text' : 'password';
                icon.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
            document.getElementById('loginForm')?.addEventListener('submit', () => {
                const button = document.getElementById('loginButton');
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying credentials...';
            });
        </script>
    </body>
</html>
