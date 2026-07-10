<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès refusé — Dugsi</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;600&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --sidebar:      #1E2D5A;
            --sidebar-soft: #2A3F7E;
            --accent:       #E8A838;
            --accent-red:   #E05C3A;
            --ink:          #1A1E35;
            --paper:        #F5F3EE;
            --paper-raised: #FFFFFF;
            --line:         #E0DBD0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            max-width: 520px;
            width: 100%;
            text-align: center;
        }

        .illustration {
            margin: 0 auto 2rem;
            width: 200px;
            height: 200px;
        }

        .ill-circle {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(224,92,58,.06);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .ill-number {
            font-family: 'Fraunces', serif;
            font-size: 7rem;
            font-weight: 600;
            color: var(--accent-red);
            opacity: .1;
            position: absolute;
            letter-spacing: -.05em;
            user-select: none;
        }

        .ill-icon {
            width: 80px;
            height: 80px;
            background: var(--paper-raised);
            border-radius: 20px;
            border: 2px solid rgba(224,92,58,.2);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(224,92,58,.1);
            position: relative;
            z-index: 1;
        }

        .ill-icon svg {
            width: 36px;
            height: 36px;
            color: var(--accent-red);
            opacity: .55;
        }

        .error-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: .875rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--accent-red);
            opacity: .6;
            margin-bottom: .75rem;
        }

        .error-title {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: .875rem;
            line-height: 1.2;
        }

        .error-desc {
            font-size: .9375rem;
            color: var(--ink);
            opacity: .55;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        /* Info utilisateur */
        .user-info {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            padding: .65rem 1rem;
            border-radius: 10px;
            background: var(--paper-raised);
            border: 1px solid var(--line);
            margin-bottom: 2rem;
            font-size: .8125rem;
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(42,63,126,.1);
            color: var(--sidebar-soft);
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .user-name  { font-weight: 600; color: var(--ink); }
        .user-role  {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            background: rgba(42,63,126,.08);
            color: var(--sidebar-soft);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: .625rem 1.5rem;
            border-radius: 9px;
            background: var(--sidebar);
            color: #FFFFFF;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-home:hover { background: var(--sidebar-soft); }
        .btn-home svg { width: 16px; height: 16px; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: .625rem 1.25rem;
            border-radius: 9px;
            border: 1.5px solid var(--line);
            background: var(--paper-raised);
            color: var(--ink);
            font-size: .875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: border-color .15s;
        }
        .btn-back:hover { border-color: var(--sidebar-soft); color: var(--sidebar-soft); }
        .btn-back svg { width: 15px; height: 15px; }

        /* Raison de l'erreur */
        .reason-box {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem 1rem;
            border-radius: 8px;
            background: rgba(224,92,58,.06);
            border: 1px solid rgba(224,92,58,.15);
            margin-bottom: 2rem;
            font-size: .8125rem;
            color: var(--accent-red);
        }
        .reason-box svg { width: 14px; height: 14px; flex-shrink: 0; }

        .footer-hint {
            margin-top: 2.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .75rem;
            color: var(--ink);
            opacity: .3;
        }

        .dot {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
        }
        .dot-1 { width: 300px; height: 300px; top: -80px; left: -60px; background: rgba(224,92,58,.04); }
        .dot-2 { width: 200px; height: 200px; bottom: -60px; right: -40px; background: rgba(42,63,126,.04); }
    </style>
</head>
<body>
    <div class="dot dot-1"></div>
    <div class="dot dot-2"></div>

    <div class="container">
        <div class="illustration">
            <div class="ill-circle">
                <span class="ill-number">403</span>
                <div class="ill-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="error-code">Erreur 403</div>
        <h1 class="error-title">Accès non autorisé</h1>
        <p class="error-desc">
            Vous n'avez pas les droits nécessaires pour accéder à cette page.
            Contactez l'administrateur si vous pensez que c'est une erreur.
        </p>

        {{-- Info utilisateur connecté --}}
        @auth
            <div style="display:flex;justify-content:center;margin-bottom:1.5rem;">
                <div class="user-info">
                    <div class="user-avatar">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                    <span class="user-name">{{ auth()->user()->name }}</span>
                    @if (auth()->user()->roles->isNotEmpty())
                        <span class="user-role">
                            {{ auth()->user()->roles->first()->label ?? auth()->user()->roles->first()->name }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Raison si disponible --}}
            @if ($exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.')
                <div style="display:flex;justify-content:center;margin-bottom:1.5rem;">
                    <div class="reason-box">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        {{ $exception->getMessage() }}
                    </div>
                </div>
            @else
                <div style="display:flex;justify-content:center;margin-bottom:1.5rem;">
                    <div class="reason-box">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        Votre rôle ne permet pas d'accéder à cette ressource.
                    </div>
                </div>
            @endif
        @endauth

        <div class="actions">
            <a href="{{ url('/dashboard') }}" class="btn-home">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Tableau de bord
            </a>
            <button onclick="history.back()" class="btn-back">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Retour
            </button>
        </div>

        <div class="footer-hint">DOUGSI</div>
    </div>
</body>
</html>