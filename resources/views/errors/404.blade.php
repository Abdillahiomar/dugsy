<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page introuvable — Dugsi</title>
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

        /* Illustration */
        .illustration {
            margin: 0 auto 2rem;
            width: 200px;
            height: 200px;
            position: relative;
        }

        .ill-circle {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(42,63,126,.06);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .ill-number {
            font-family: 'Fraunces', serif;
            font-size: 7rem;
            font-weight: 600;
            color: var(--sidebar);
            opacity: .12;
            position: absolute;
            letter-spacing: -.05em;
            user-select: none;
        }

        .ill-icon {
            width: 80px;
            height: 80px;
            background: var(--paper-raised);
            border-radius: 20px;
            border: 2px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(30,45,90,.08);
            position: relative;
            z-index: 1;
        }

        .ill-icon svg {
            width: 36px;
            height: 36px;
            color: var(--sidebar-soft);
            opacity: .5;
        }

        /* Contenu */
        .error-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: .875rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--sidebar-soft);
            opacity: .5;
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
            margin-bottom: 2rem;
        }

        /* Actions */
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

        /* Footer */
        .footer-hint {
            margin-top: 2.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .75rem;
            color: var(--ink);
            opacity: .3;
        }

        /* Dots décoratifs */
        .dot {
            position: fixed;
            border-radius: 50%;
            background: rgba(42,63,126,.06);
            pointer-events: none;
        }
        .dot-1 { width: 320px; height: 320px; top: -80px; left: -80px; }
        .dot-2 { width: 200px; height: 200px; bottom: -60px; right: -40px; }
    </style>
</head>
<body>
    <div class="dot dot-1"></div>
    <div class="dot dot-2"></div>

    <div class="container">
        <div class="illustration">
            <div class="ill-circle">
                <span class="ill-number">404</span>
                <div class="ill-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="error-code">Erreur 404</div>
        <h1 class="error-title">Page introuvable</h1>
        <p class="error-desc">
            La page que vous cherchez n'existe pas ou a été déplacée.<br>
            Vérifiez l'adresse ou retournez au tableau de bord.
        </p>

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