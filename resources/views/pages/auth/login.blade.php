<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Dugsi</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,400&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy    : #1E2D5A;
            --navy2   : #2A3F7E;
            --gold    : #E8A838;
            --ink     : #1A1E35;
            --paper   : #F5F3EE;
            --muted   : #6B7090;
            --line    : #E0DBD0;
            --red     : #E05C3A;
            --white   : #FFFFFF;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: var(--paper);
            color: var(--ink);
        }

        /* ── Layout split ── */
        .auth-shell {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        /* ── Panneau gauche — décoratif ── */
        .auth-left {
            background: var(--navy);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        /* Motif géométrique subtil */
        .auth-left::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: .05;
            background-image:
                repeating-linear-gradient(45deg, rgba(255,255,255,.4) 0px, rgba(255,255,255,.4) 1px, transparent 1px, transparent 48px),
                repeating-linear-gradient(-45deg, rgba(255,255,255,.4) 0px, rgba(255,255,255,.4) 1px, transparent 1px, transparent 48px);
        }

        /* Cercle décoratif */
        .auth-left::after {
            content: '';
            position: absolute;
            bottom: -120px;
            right: -120px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            border: 60px solid rgba(232,168,56,.12);
        }

        .auth-brand {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .auth-brand-logo {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,.12);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-brand-logo svg { width: 22px; height: 22px; color: white; }

        .auth-brand-name {
            font-family: 'Fraunces', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            letter-spacing: -.02em;
        }

        .auth-tagline {
            position: relative;
            z-index: 1;
        }

        .auth-tagline-title {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            font-weight: 600;
            color: white;
            line-height: 1.2;
            letter-spacing: -.03em;
            margin-bottom: 1rem;
        }

        .auth-tagline-title em {
            font-style: italic;
            color: var(--gold);
        }

        .auth-tagline-desc {
            font-size: .9375rem;
            color: rgba(255,255,255,.55);
            line-height: 1.65;
        }

        .auth-features {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        .auth-feature {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: .875rem;
            color: rgba(255,255,255,.6);
        }

        .auth-feature-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--gold);
            flex-shrink: 0;
        }

        /* ── Panneau droit — formulaire ── */
        .auth-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            background: var(--white);
        }

        .auth-form-wrap {
            width: 100%;
            max-width: 400px;
        }

        /* Titre formulaire */
        .form-title {
            font-family: 'Fraunces', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -.03em;
            margin-bottom: .35rem;
        }

        .form-subtitle {
            font-size: .9375rem;
            color: var(--muted);
            margin-bottom: 2.25rem;
        }

        /* Session status */
        .session-status {
            padding: .75rem 1rem;
            border-radius: 8px;
            background: rgba(30,120,80,.08);
            border: 1px solid rgba(30,120,80,.2);
            color: #166534;
            font-size: .875rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
        }

        /* Champs */
        .field { display: flex; flex-direction: column; gap: .4rem; margin-bottom: 1.25rem; }
        .field:last-of-type { margin-bottom: 0; }

        .field-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--ink);
            opacity: .55;
        }

        .field-input {
            padding: .65rem .875rem;
            border-radius: 9px;
            border: 1.5px solid var(--line);
            background: var(--paper);
            font-size: .9375rem;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            outline: none;
            width: 100%;
            transition: border-color .15s, box-shadow .15s;
        }

        .field-input:focus {
            border-color: var(--navy2);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(42,63,126,.1);
        }

        .field-input::placeholder { color: var(--muted); opacity: .6; }

        .field-error {
            font-size: .8125rem;
            color: var(--red);
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .field-error::before {
            content: '!';
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--red);
            color: white;
            font-size: 9px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Password wrap */
        .pw-wrap { position: relative; }
        .pw-toggle {
            position: absolute;
            right: .75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            display: flex;
            align-items: center;
            padding: 0;
            transition: color .12s;
        }
        .pw-toggle:hover { color: var(--navy); }
        .pw-toggle svg { width: 16px; height: 16px; }

        /* Ligne remember + mot de passe oublié */
        .auth-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.25rem 0;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: .5rem;
            cursor: pointer;
        }

        .checkbox-input {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1.5px solid var(--line);
            cursor: pointer;
            accent-color: var(--navy);
        }

        .checkbox-label {
            font-size: .875rem;
            color: var(--muted);
            cursor: pointer;
        }

        .forgot-link {
            font-size: .875rem;
            color: var(--navy2);
            text-decoration: none;
            font-weight: 500;
            transition: color .12s;
        }
        .forgot-link:hover { color: var(--navy); text-decoration: underline; }

        /* Bouton submit */
        .btn-submit {
            width: 100%;
            padding: .75rem 1.5rem;
            border-radius: 10px;
            background: var(--navy);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            border: none;
            cursor: pointer;
            transition: background .15s, transform .1s, box-shadow .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
        }

        .btn-submit:hover {
            background: var(--navy2);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(30,45,90,.2);
        }

        .btn-submit:active { transform: translateY(0); }

        .btn-submit svg { width: 17px; height: 17px; }

        /* Séparateur */
        .auth-divider {
            display: flex;
            align-items: center;
            gap: .875rem;
            margin: 1.5rem 0;
            color: var(--muted);
            font-size: .8125rem;
        }
        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        /* Lien inscription */
        .auth-signup {
            text-align: center;
            font-size: .9rem;
            color: var(--muted);
            margin-top: 1.5rem;
        }

        .auth-signup a {
            color: var(--navy2);
            font-weight: 600;
            text-decoration: none;
        }
        .auth-signup a:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 768px) {
            .auth-shell { grid-template-columns: 1fr; }
            .auth-left  { display: none; }
            .auth-right { padding: 2rem 1.25rem; }
        }
    </style>
</head>
<body>

<div class="auth-shell">

    {{-- ── Panneau gauche ── --}}
    <div class="auth-left">
        <div class="auth-brand">
            <div class="auth-brand-logo">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                </svg>
            </div>
            <span class="auth-brand-name">Dugsi</span>
        </div>

        <div class="auth-tagline">
            <div class="auth-tagline-title">
                Bienvenue sur<br>
                <em>votre espace</em><br>
                scolaire.
            </div>
            <div class="auth-tagline-desc">
                Gérez votre établissement simplement :<br>
                élèves, notes, absences et finances.
            </div>
        </div>

        <div class="auth-features">
            @foreach ([
                'Bulletins générés automatiquement',
                'Suivi des absences en temps réel',
                'Paiements D-Money intégrés',
                'Accès sécurisé par rôle',
            ] as $feature)
                <div class="auth-feature">
                    <div class="auth-feature-dot"></div>
                    {{ $feature }}
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Panneau droit — formulaire ── --}}
    <div class="auth-right">
        <div class="auth-form-wrap">

            <div class="form-title">Connexion</div>
            <div class="form-subtitle">Entrez vos identifiants pour accéder à votre espace.</div>

            {{-- Session status --}}
            @if (session('status'))
                <div class="session-status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" x-data="{ showPw: false }">
                @csrf

                {{-- Email --}}
                <div class="field">
                    <label for="email" class="field-label">Adresse email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        class="field-input"
                        value="{{ old('email') }}"
                        placeholder="votre@email.dj"
                        required
                        autofocus
                        autocomplete="email"
                    >
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Mot de passe --}}
                <div class="field">
                    <label for="password" class="field-label">Mot de passe</label>
                    <div class="pw-wrap">
                        <input
                            id="password"
                            name="password"
                            :type="showPw ? 'text' : 'password'"
                            class="field-input"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                            style="padding-right:2.75rem;"
                        >
                        <button type="button" class="pw-toggle" @click="showPw = !showPw">
                            {{-- Œil ouvert --}}
                            <svg x-show="!showPw" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            {{-- Œil barré --}}
                            <svg x-show="showPw" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="display:none;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Se souvenir + mot de passe oublié --}}
                <div class="auth-row">
                    <label class="checkbox-wrap">
                        <input
                            type="checkbox"
                            name="remember"
                            class="checkbox-input"
                            {{ old('remember') ? 'checked' : '' }}>
                        <span class="checkbox-label">Se souvenir de moi</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="forgot-link" wire:navigate>
                            Mot de passe oublié ?
                        </a>
                    @endif
                </div>

                {{-- Bouton submit --}}
                <button type="submit" class="btn-submit" wire:navigate>
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Se connecter
                </button>
            </form>

            {{-- Lien inscription --}}
            @if (Route::has('register'))
                <div class="auth-signup">
                    Pas encore de compte ?
                    <a href="{{ route('register') }}" wire:navigate>Créer un compte</a>
                </div>
            @endif

        </div>
    </div>
</div>

@livewireScripts
</body>
</html>