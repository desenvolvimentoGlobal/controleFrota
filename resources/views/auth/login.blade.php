<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Controle de Frota</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_apenas_bola.jpg') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#015498">
    <link rel="apple-touch-icon" href="{{ asset('images/icone-pwa-192.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 420px; border: none; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .login-header { background: linear-gradient(135deg, #16243B 0%, #00549A 100%); border-radius: 12px 12px 0 0; padding: 2rem; text-align: center; color: #fff; }
        .login-header .sistema-nome { font-size: 1.1rem; font-weight: 600; letter-spacing: .5px; margin: 0; }
        .login-header .sistema-sub { font-size: .8rem; opacity: .8; margin: 0; }
        .card-body { padding: 2rem; }
        .form-label { font-weight: 500; font-size: .875rem; }
        .btn-entrar { background: linear-gradient(135deg, #16243B 0%, #00549A 100%); border: none; font-weight: 600; padding: .65rem; }
        .btn-entrar:hover { opacity: .9; }
    </style>
</head>
<body>

<div class="login-card card">
    <div class="login-header">
        <i class="bi bi-car-front-fill fs-2 mb-2 d-block"></i>
        <p class="sistema-nome">Controle de Frota</p>
        <p class="sistema-sub">Faça login para continuar</p>
    </div>

    <div class="card-body">
        @if (session('status'))
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                <i class="bi bi-check-circle-fill"></i><small>{{ session('status') }}</small>
            </div>
        @endif

        {{-- data-enter-envia: única exceção à regra "POST não envia por Enter" (public/js/app.js). --}}
        <form method="POST" action="{{ route('login.post') }}" novalidate data-enter-envia>
            @csrf

            <div class="mb-3">
                <label for="acesso" class="form-label">Login ou e-mail</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" id="acesso" name="acesso"
                           class="form-control @error('acesso') is-invalid @enderror"
                           value="{{ old('acesso') }}" placeholder="seu.login ou seu@email.com"
                           autofocus autocomplete="username" required>
                    @error('acesso') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="senha" class="form-label mb-1">Senha</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="senha" name="senha"
                           class="form-control @error('senha') is-invalid @enderror"
                           placeholder="••••••••" autocomplete="current-password" required>
                    <button class="btn btn-outline-secondary" type="button" title="Mostrar/ocultar senha" onclick="toggleSenha()">
                        <i class="bi bi-eye" id="iconeSenha"></i>
                    </button>
                    @error('senha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="lembrar" id="lembrar" value="1">
                <label class="form-check-label small" for="lembrar">Manter conectado</label>
            </div>

            <button type="submit" class="btn btn-primary btn-entrar w-100 text-white">
                <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSenha() {
        const input = document.getElementById('senha');
        const icone = document.getElementById('iconeSenha');
        const mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';
        icone.className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
</script>
</body>
</html>
