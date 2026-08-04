<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dugsi — Plateforme de gestion scolaire à Djibouti</title>
    <meta name="description" content="Dugsi simplifie la gestion de votre école : élèves, notes, absences, finances et bulletins en un seul endroit.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <style>
        /* ── Reset & base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy    : #1E2D5A;
            --navy2   : #2A3F7E;
            --gold    : #E8A838;
            --red     : #E05C3A;
            --ink     : #1A1E35;
            --paper   : #F5F3EE;
            --paper2  : #FFFFFF;
            --muted   : #6B7090;
            --line    : #E0DBD0;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--paper);
            color: var(--ink);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ── Nav ── */
        .nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2.5rem;
            height: 64px;
            background: rgba(245,243,238,.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: .625rem;
            text-decoration: none;
        }

        .nav-logo {
            width: 32px;
            height: 32px;
            background: var(--navy);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-logo svg { width: 18px; height: 18px; color: white; }

        .nav-name {
            font-family: 'Fraunces', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy);
            letter-spacing: -.02em;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: .875rem;
        }

        .nav-link {
            font-size: .875rem;
            font-weight: 500;
            color: var(--muted);
            text-decoration: none;
            transition: color .15s;
        }
        .nav-link:hover { color: var(--navy); }

        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: .45rem 1.1rem;
            border-radius: 8px;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all .15s;
        }

        .btn-outline-nav {
            border: 1.5px solid var(--navy);
            color: var(--navy);
            background: transparent;
        }
        .btn-outline-nav:hover { background: var(--navy); color: white; }

        .btn-solid-nav {
            background: var(--navy);
            color: white;
            border: 1.5px solid var(--navy);
        }
        .btn-solid-nav:hover { background: var(--navy2); border-color: var(--navy2); }

        /* ── Hero ── */
        .hero {
            min-height: 100vh;
            padding-top: 64px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
        }

        .hero-left {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 5rem 3.5rem 5rem 5rem;
            position: relative;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 1.5rem;
        }

        .hero-eyebrow::before {
            content: '';
            width: 24px;
            height: 2px;
            background: var(--gold);
            border-radius: 1px;
        }

        .hero-title {
            font-family: 'Fraunces', serif;
            font-size: clamp(2.5rem, 5vw, 3.75rem);
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: -.03em;
            color: var(--ink);
            margin-bottom: 1.5rem;
        }

        .hero-title em {
            font-style: italic;
            color: var(--navy);
        }

        .hero-desc {
            font-size: 1.0625rem;
            color: var(--muted);
            line-height: 1.7;
            max-width: 460px;
            margin-bottom: 2.5rem;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .btn-cta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: .75rem 1.75rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all .15s;
        }

        .btn-primary-cta {
            background: var(--navy);
            color: white;
        }
        .btn-primary-cta:hover {
            background: var(--navy2);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(30,45,90,.25);
        }
        .btn-primary-cta svg { width: 16px; height: 16px; }

        .btn-ghost-cta {
            color: var(--navy);
            font-weight: 500;
            font-size: .9375rem;
        }
        .btn-ghost-cta:hover { text-decoration: underline; }

        .hero-trust {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: .8125rem;
            color: var(--muted);
        }

        .trust-avatars {
            display: flex;
        }

        .trust-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid var(--paper);
            background: var(--navy);
            margin-left: -8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 700;
            color: white;
            font-family: 'JetBrains Mono', monospace;
        }

        .trust-avatar:first-child { margin-left: 0; background: var(--navy2); }
        .trust-avatar:nth-child(2) { background: #3D5A99; }
        .trust-avatar:nth-child(3) { background: #166534; }

        /* Hero right — dashboard mockup */
        .hero-right {
            background: var(--navy);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5rem 2.5rem 5rem 1.5rem;
            clip-path: polygon(4% 0, 100% 0, 100% 100%, 0 100%);
        }

        /* Motif géométrique subtil en fond */
        .hero-pattern {
            position: absolute;
            inset: 0;
            opacity: .06;
            background-image:
                repeating-linear-gradient(45deg, rgba(255,255,255,.3) 0px, rgba(255,255,255,.3) 1px, transparent 1px, transparent 40px),
                repeating-linear-gradient(-45deg, rgba(255,255,255,.3) 0px, rgba(255,255,255,.3) 1px, transparent 1px, transparent 40px);
        }

        /* Dashboard mockup */
        .dash-mockup {
            position: relative;
            width: 100%;
            max-width: 520px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,.4), 0 8px 24px rgba(0,0,0,.2);
            border: 1px solid rgba(255,255,255,.1);
            transform: perspective(1000px) rotateY(-4deg) rotateX(2deg);
        }

        .mock-header {
            background: #111827;
            padding: .6rem 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .mock-dot { width: 10px; height: 10px; border-radius: 50%; }
        .mock-dot-red { background: #FF5F57; }
        .mock-dot-yellow { background: #FFBD2E; }
        .mock-dot-green { background: #28C840; }
        .mock-url { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: rgba(255,255,255,.3); margin-left: .5rem; }

        .mock-body { background: #F5F3EE; }

        .mock-topbar {
            background: white;
            border-bottom: 1px solid #E0DBD0;
            padding: .5rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .mock-date { font-family: 'JetBrains Mono', monospace; font-size: 9px; color: #6B7090; }
        .mock-user { display: flex; align-items: center; gap: .4rem; }
        .mock-avatar { width: 20px; height: 20px; background: #1E2D5A; border-radius: 50%; }
        .mock-uname { font-size: 9px; font-weight: 600; color: #1A1E35; }

        .mock-layout { display: grid; grid-template-columns: 72px 1fr; }

        .mock-sidebar { background: #1E2D5A; padding: .75rem .5rem; min-height: 280px; }
        .mock-nav-item { display: flex; align-items: center; gap: 6px; padding: 4px 6px; border-radius: 4px; margin-bottom: 2px; }
        .mock-nav-item.active { background: rgba(255,255,255,.12); }
        .mock-nav-dot { width: 6px; height: 6px; border-radius: 1px; background: rgba(255,255,255,.3); flex-shrink: 0; }
        .mock-nav-dot.active { background: #E8A838; }
        .mock-nav-label { font-size: 8px; color: rgba(255,255,255,.5); }
        .mock-nav-label.active { color: rgba(255,255,255,.9); font-weight: 600; }

        .mock-content { padding: .75rem; }

        .mock-kpis { display: grid; grid-template-columns: repeat(4,1fr); gap: 6px; margin-bottom: 8px; }
        .mock-kpi { background: white; border: 1px solid #E0DBD0; border-radius: 6px; padding: 6px 8px; }
        .mock-kpi-lbl { font-size: 7px; color: #6B7090; margin-bottom: 2px; font-family: 'JetBrains Mono', monospace; }
        .mock-kpi-val { font-family: 'Fraunces', serif; font-size: 16px; font-weight: 700; color: #1A1E35; line-height: 1; }
        .mock-kpi-sub { font-size: 7px; color: #6B7090; margin-top: 1px; }

        .mock-charts { display: grid; grid-template-columns: 2fr 1fr; gap: 6px; margin-bottom: 8px; }
        .mock-chart-card { background: white; border: 1px solid #E0DBD0; border-radius: 6px; padding: 8px; }
        .mock-chart-title { font-size: 8px; font-weight: 600; color: #1A1E35; margin-bottom: 6px; }
        .mock-bars { display: flex; align-items: flex-end; gap: 4px; height: 48px; }
        .mock-bar { flex: 1; background: rgba(30,45,90,.12); border-radius: 2px 2px 0 0; }
        .mock-bar.hi { background: #1E2D5A; }
        .mock-bar.mid { background: #2A3F7E; }

        .mock-donut-wrap { display: flex; align-items: center; justify-content: center; height: 60px; }
        .mock-donut { width: 50px; height: 50px; border-radius: 50%; background: conic-gradient(#1E2D5A 0% 40%, #2A3F7E 40% 65%, #E8A838 65% 80%, #E0DBD0 80% 100%); position: relative; }
        .mock-donut::after { content: ''; position: absolute; inset: 12px; border-radius: 50%; background: white; }

        .mock-table-card { background: white; border: 1px solid #E0DBD0; border-radius: 6px; overflow: hidden; }
        .mock-table-header { padding: 5px 8px; border-bottom: 1px solid #E0DBD0; font-size: 8px; font-weight: 600; color: #1A1E35; }
        .mock-table-row { display: flex; align-items: center; gap: 6px; padding: 4px 8px; border-bottom: 1px solid #F5F3EE; }
        .mock-table-row:last-child { border-bottom: none; }
        .mock-row-dot { width: 16px; height: 16px; border-radius: 50%; background: rgba(30,45,90,.1); flex-shrink: 0; }
        .mock-row-name { font-size: 8px; font-weight: 500; color: #1A1E35; flex: 1; }
        .mock-row-badge { font-size: 7px; font-weight: 600; padding: 1px 5px; border-radius: 3px; }
        .mock-badge-green { background: rgba(30,120,80,.1); color: #166534; }
        .mock-badge-amber { background: rgba(232,168,56,.15); color: #8A6010; }
        .mock-badge-red { background: rgba(224,92,58,.1); color: #E05C3A; }

        /* ── Section header ── */
        .section {
            padding: 6rem 5rem;
        }

        .section-center { text-align: center; }

        .section-eyebrow {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 1rem;
        }

        .section-title {
            font-family: 'Fraunces', serif;
            font-size: clamp(1.875rem, 3.5vw, 2.75rem);
            font-weight: 700;
            letter-spacing: -.03em;
            color: var(--ink);
            line-height: 1.15;
            margin-bottom: 1.25rem;
        }

        .section-desc {
            font-size: 1rem;
            color: var(--muted);
            line-height: 1.7;
            max-width: 560px;
            margin: 0 auto;
        }

        /* ── Stats band ── */
        .stats-band {
            background: var(--navy);
            padding: 4rem 5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 900px;
            margin: 0 auto;
        }

        .stat-item {
            text-align: center;
            padding: 2rem;
            border-right: 1px solid rgba(255,255,255,.1);
        }
        .stat-item:last-child { border-right: none; }

        .stat-num {
            font-family: 'Fraunces', serif;
            font-size: 3.5rem;
            font-weight: 700;
            color: white;
            letter-spacing: -.04em;
            line-height: 1;
            margin-bottom: .35rem;
        }

        .stat-num span {
            color: var(--gold);
        }

        .stat-label {
            font-size: .9375rem;
            color: rgba(255,255,255,.55);
            font-weight: 500;
        }

        /* ── Features grid ── */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 3.5rem;
        }

        .feature-card {
            background: white;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.75rem;
            transition: border-color .15s, box-shadow .15s, transform .15s;
        }

        .feature-card:hover {
            border-color: rgba(30,45,90,.2);
            box-shadow: 0 8px 32px rgba(30,45,90,.08);
            transform: translateY(-2px);
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: rgba(30,45,90,.07);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .feature-icon svg { width: 22px; height: 22px; color: var(--navy); }

        .feature-title {
            font-family: 'Fraunces', serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: .5rem;
        }

        .feature-desc {
            font-size: .875rem;
            color: var(--muted);
            line-height: 1.65;
        }

        .feature-tag {
            display: inline-block;
            margin-top: 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            background: rgba(30,45,90,.07);
            color: var(--navy2);
        }

        /* ── Roles / pour qui ── */
        .roles-section {
            background: white;
            padding: 6rem 5rem;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-top: 3.5rem;
        }

        .role-card {
            border-radius: 14px;
            padding: 1.75rem 1.5rem;
            text-align: center;
            border: 1.5px solid var(--line);
            transition: border-color .15s, transform .15s;
        }

        .role-card:hover {
            border-color: var(--navy);
            transform: translateY(-2px);
        }

        .role-emoji {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .role-title {
            font-family: 'Fraunces', serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: .5rem;
        }

        .role-desc {
            font-size: .8125rem;
            color: var(--muted);
            line-height: 1.6;
        }

        .role-perms {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }

        .role-perm {
            font-size: .75rem;
            color: var(--navy2);
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .role-perm::before {
            content: '✓';
            font-size: .65rem;
            font-weight: 700;
            color: #166534;
        }

        /* ── CTA finale ── */
        .cta-section {
            padding: 7rem 5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-bg {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--navy) 0%, #1a2a60 50%, #0f1a3d 100%);
        }

        .cta-pattern {
            position: absolute;
            inset: 0;
            opacity: .04;
            background-image:
                radial-gradient(circle at 20% 50%, white 1px, transparent 1px),
                radial-gradient(circle at 80% 20%, white 1px, transparent 1px),
                radial-gradient(circle at 60% 80%, white 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .cta-content { position: relative; z-index: 1; }

        .cta-title {
            font-family: 'Fraunces', serif;
            font-size: clamp(2rem, 4vw, 3.25rem);
            font-weight: 700;
            color: white;
            letter-spacing: -.03em;
            line-height: 1.1;
            margin-bottom: 1.25rem;
        }

        .cta-title em {
            font-style: italic;
            color: var(--gold);
        }

        .cta-desc {
            font-size: 1.0625rem;
            color: rgba(255,255,255,.6);
            margin-bottom: 2.5rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-gold {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: .875rem 2rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            background: var(--gold);
            color: var(--ink);
            text-decoration: none;
            transition: all .15s;
        }
        .btn-gold:hover {
            background: #d4951e;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(232,168,56,.35);
        }
        .btn-gold svg { width: 16px; height: 16px; }

        .btn-ghost-white {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: .875rem 1.75rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            background: rgba(255,255,255,.1);
            color: white;
            text-decoration: none;
            border: 1.5px solid rgba(255,255,255,.2);
            transition: all .15s;
        }
        .btn-ghost-white:hover { background: rgba(255,255,255,.18); }

        /* ── Footer ── */
        .footer {
            background: #0f1628;
            padding: 2.5rem 5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: .625rem;
            text-decoration: none;
        }

        .footer-logo {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,.1);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-logo svg { width: 16px; height: 16px; color: white; }

        .footer-name {
            font-family: 'Fraunces', serif;
            font-size: 1rem;
            font-weight: 700;
            color: rgba(255,255,255,.6);
        }

        .footer-copy {
            font-size: .8125rem;
            color: rgba(255,255,255,.3);
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-link {
            font-size: .8125rem;
            color: rgba(255,255,255,.4);
            text-decoration: none;
            transition: color .15s;
        }
        .footer-link:hover { color: rgba(255,255,255,.75); }

        /* ── Divider ── */
        .divider {
            width: 60px;
            height: 3px;
            background: var(--gold);
            border-radius: 2px;
            margin: 1.5rem auto 0;
        }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .hero { grid-template-columns: 1fr; min-height: auto; }
            .hero-right { clip-path: none; padding: 3rem 2.5rem; min-height: 400px; }
            .hero-left { padding: 3rem 2.5rem; }
            .features-grid { grid-template-columns: 1fr 1fr; }
            .roles-grid { grid-template-columns: 1fr 1fr; }
            .stats-grid { grid-template-columns: 1fr; gap: 0; }
            .stat-item { border-right: none; border-bottom: 1px solid rgba(255,255,255,.1); }
            .stat-item:last-child { border-bottom: none; }
            .section, .roles-section { padding: 4rem 2.5rem; }
            .cta-section { padding: 5rem 2.5rem; }
            .footer { flex-direction: column; gap: 1.5rem; text-align: center; padding: 2rem; }
            .footer-links { justify-content: center; }
        }

        @media (max-width: 640px) {
            .nav { padding: 0 1.25rem; }
            .nav-right .btn-nav:first-child { display: none; }
            .hero-left { padding: 2.5rem 1.5rem; }
            .hero-right { padding: 2rem 1.5rem; }
            .features-grid { grid-template-columns: 1fr; }
            .roles-grid { grid-template-columns: 1fr; }
            .section, .roles-section { padding: 3rem 1.5rem; }
            .stats-band { padding: 3rem 1.5rem; }
            .cta-section { padding: 4rem 1.5rem; }
        }
    </style>
</head>
<body>

<!-- ── Navigation ── -->
<nav class="nav">
    <a href="#" class="nav-brand">
        <div class="nav-logo">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
            </svg>
        </div>
        <span class="nav-name">Dugsi</span>
    </a>
    <div class="nav-right">
        <a href="#fonctionnalites" class="nav-link">Fonctionnalités</a>
        <a href="#pour-qui" class="nav-link">Pour qui</a>
        @if (Route::has('login'))
            @auth
                <a href="{{ route('dashboard') }}" class="btn-nav btn-solid-nav">Tableau de bord</a>
            @else
                <a href="{{ route('login') }}" class="btn-nav btn-outline-nav">Se connecter</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn-nav btn-solid-nav">Commencer</a>
                @endif
            @endauth
        @endif
    </div>
</nav>

<!-- ── Hero ── -->
<section class="hero">
    <div class="hero-left">
        <div class="hero-eyebrow">Djibouti · Gestion scolaire</div>

        <h1 class="hero-title">
            Gérez votre école<br>
            <em>simplement,</em><br>
            efficacement.
        </h1>

        <p class="hero-desc">
            Dugsi réunit en un seul endroit tout ce dont votre école a besoin : 
            inscriptions, notes, bulletins, absences, finances et communication 
            avec les parents.
        </p>

        <div class="hero-actions">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn-cta btn-primary-cta">
                    Démarrer gratuitement
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            @endif
            <a href="#fonctionnalites" class="btn-cta btn-ghost-cta">
                Voir les fonctionnalités →
            </a>
        </div>

        <div class="hero-trust">
            <div class="trust-avatars">
                <div class="trust-avatar">AD</div>
                <div class="trust-avatar">MH</div>
                <div class="trust-avatar">FA</div>
            </div>
            <span>Utilisé par des écoles à travers Djibouti</span>
        </div>
    </div>

    <div class="hero-right">
        <div class="hero-pattern"></div>

        <!-- Dashboard mockup -->
        <div class="dash-mockup">
            <div class="mock-header">
                <div class="mock-dot mock-dot-red"></div>
                <div class="mock-dot mock-dot-yellow"></div>
                <div class="mock-dot mock-dot-green"></div>
                <span class="mock-url">dugsi.dj/dashboard</span>
            </div>
            <div class="mock-body">
                <div class="mock-topbar">
                    <span class="mock-date">Mercredi 9 juillet 2026 · 08:24:11</span>
                    <div class="mock-user">
                        <div class="mock-avatar"></div>
                        <span class="mock-uname">Admin Institut</span>
                    </div>
                </div>
                <div class="mock-layout">
                    <div class="mock-sidebar">
                        <div style="font-size:7px;color:rgba(255,255,255,.3);margin-bottom:8px;font-family:'JetBrains Mono',monospace;letter-spacing:.08em;">PRINCIPAL</div>
                        <div class="mock-nav-item active">
                            <div class="mock-nav-dot active"></div>
                            <span class="mock-nav-label active">Tableau de bord</span>
                        </div>
                        <div style="font-size:7px;color:rgba(255,255,255,.3);margin:8px 0 4px;font-family:'JetBrains Mono',monospace;">ACADÉMIQUE</div>
                        @foreach(['Élèves','Classes','Notes','Bulletins','Absences','Devoirs'] as $item)
                            <div class="mock-nav-item">
                                <div class="mock-nav-dot"></div>
                                <span class="mock-nav-label">{{ $item }}</span>
                            </div>
                        @endforeach
                        <div style="font-size:7px;color:rgba(255,255,255,.3);margin:8px 0 4px;font-family:'JetBrains Mono',monospace;">GESTION</div>
                        @foreach(['Finances','Annonces'] as $item)
                            <div class="mock-nav-item">
                                <div class="mock-nav-dot"></div>
                                <span class="mock-nav-label">{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mock-content">
                        <div style="font-family:'Fraunces',serif;font-size:12px;font-weight:700;color:#1A1E35;margin-bottom:8px;">Tableau de bord</div>
                        <div class="mock-kpis">
                            @foreach([['247','Élèves',''],['12','Classes',''],['4.38M','Encaissé','DJF'],['94%','Présence','']] as $kpi)
                                <div class="mock-kpi">
                                    <div class="mock-kpi-lbl">{{ $kpi[0] === '247' ? 'ÉLÈVES' : ($kpi[0] === '12' ? 'CLASSES' : ($kpi[0] === '4.38M' ? 'ENCAISSÉ' : 'PRÉSENCE')) }}</div>
                                    <div class="mock-kpi-val">{{ $kpi[0] }}</div>
                                    <div class="mock-kpi-sub">{{ $kpi[1] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mock-charts">
                            <div class="mock-chart-card">
                                <div class="mock-chart-title">Inscriptions</div>
                                <div class="mock-bars">
                                    <div class="mock-bar" style="height:30%"></div>
                                    <div class="mock-bar" style="height:55%"></div>
                                    <div class="mock-bar hi" style="height:80%"></div>
                                    <div class="mock-bar mid" style="height:65%"></div>
                                    <div class="mock-bar" style="height:90%"></div>
                                    <div class="mock-bar hi" style="height:100%"></div>
                                </div>
                            </div>
                            <div class="mock-chart-card">
                                <div class="mock-chart-title">Niveaux</div>
                                <div class="mock-donut-wrap">
                                    <div class="mock-donut"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mock-table-card">
                            <div class="mock-table-header">Dernières inscriptions</div>
                            @foreach([['AH','Amina Hassan','Admis'],['MO','Mohamed Omar','Partiel'],['FD','Fatuma Dirieh','Admis']] as $row)
                                <div class="mock-table-row">
                                    <div class="mock-row-dot" style="background:rgba(30,45,90,.15);display:flex;align-items:center;justify-content:center;font-size:6px;font-weight:700;color:#1E2D5A;">{{ substr($row[0],0,1) }}</div>
                                    <div class="mock-row-name">{{ $row[1] }}</div>
                                    <div class="mock-row-badge mock-badge-{{ $row[2]==='Admis' ? 'green' : 'amber' }}">{{ $row[2] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Stats band ── -->
<div class="stats-band">
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-num">5<span>+</span></div>
            <div class="stat-label">Modules intégrés</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">6</div>
            <div class="stat-label">Profils utilisateurs</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">100<span>%</span></div>
            <div class="stat-label">Adapté à Djibouti (DJF)</div>
        </div>
    </div>
</div>

<!-- ── Fonctionnalités ── -->
<section class="section section-center" id="fonctionnalites">
    <span class="section-eyebrow">Fonctionnalités</span>
    <h2 class="section-title">Tout ce dont votre école a besoin</h2>
    <p class="section-desc">De l'inscription jusqu'au bulletin, chaque étape de la vie scolaire est couverte.</p>
    <div class="divider"></div>

    <div class="features-grid" style="text-align:left;">

        <div class="feature-card">
            <div class="feature-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/></svg>
            </div>
            <div class="feature-title">Gestion des élèves</div>
            <div class="feature-desc">Inscription, dossiers complets, documents requis, historique scolaire et réinscription en quelques clics.</div>
            <span class="feature-tag">students</span>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <div class="feature-title">Notes & Bulletins</div>
            <div class="feature-desc">Saisie des notes par matière, calcul automatique des moyennes, génération et téléchargement des bulletins en PDF.</div>
            <span class="feature-tag">grades · bulletins</span>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="feature-title">Présences & Absences</div>
            <div class="feature-desc">Feuille de présence par séance, justificatifs avec documents, statistiques et alertes pour les absences répétées.</div>
            <span class="feature-tag">absences</span>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <div class="feature-title">Finances scolaires</div>
            <div class="feature-desc">Frais de scolarité configurables, facturation automatique, paiements en espèces ou D-Money, et suivi des impayés en temps réel.</div>
            <span class="feature-tag">finance · D-Money</span>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            </div>
            <div class="feature-title">Devoirs à la maison</div>
            <div class="feature-desc">Les enseignants publient les devoirs en PDF, les parents peuvent consulter, télécharger et déposer les rendus en ligne.</div>
            <span class="feature-tag">homeworks</span>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            </div>
            <div class="feature-title">Annonces & Communication</div>
            <div class="feature-desc">Publiez des annonces ciblées pour les parents, enseignants ou tout le personnel, avec pièces jointes et planification.</div>
            <span class="feature-tag">announcements</span>
        </div>

    </div>
</section>

<!-- ── Pour qui ── -->
<section class="roles-section section-center" id="pour-qui">
    <span class="section-eyebrow">Pour qui</span>
    <h2 class="section-title">Un accès adapté à chaque rôle</h2>
    <p class="section-desc">Chaque utilisateur voit exactement ce dont il a besoin, ni plus ni moins.</p>
    <div class="divider"></div>

    <div class="roles-grid" style="text-align:left;">

        <div class="role-card">
            <span class="role-emoji">🏫</span>
            <div class="role-title">Directeur</div>
            <div class="role-desc">Vue complète sur l'école, pilotage pédagogique et financier.</div>
            <div class="role-perms">
                <span class="role-perm">Tableau de bord global</span>
                <span class="role-perm">Gestion des classes</span>
                <span class="role-perm">Rapports financiers</span>
                <span class="role-perm">Configuration école</span>
            </div>
        </div>

        <div class="role-card">
            <span class="role-emoji">📚</span>
            <div class="role-title">Enseignant</div>
            <div class="role-desc">Gère ses classes, saisit les notes et suit les absences.</div>
            <div class="role-perms">
                <span class="role-perm">Saisie des notes</span>
                <span class="role-perm">Absences par séance</span>
                <span class="role-perm">Devoirs à la maison</span>
                <span class="role-perm">Génération de bulletins</span>
            </div>
        </div>

        <div class="role-card">
            <span class="role-emoji">💰</span>
            <div class="role-title">Comptable</div>
            <div class="role-desc">Encaisse les paiements et suit les finances de l'école.</div>
            <div class="role-perms">
                <span class="role-perm">Encaissement D-Money</span>
                <span class="role-perm">Suivi des impayés</span>
                <span class="role-perm">Rapports financiers</span>
                <span class="role-perm">Tableau de bord finances</span>
            </div>
        </div>

        <div class="role-card">
            <span class="role-emoji">👪</span>
            <div class="role-title">Parent</div>
            <div class="role-desc">Suit la scolarité de ses enfants depuis son téléphone.</div>
            <div class="role-perms">
                <span class="role-perm">Notes & bulletins</span>
                <span class="role-perm">Absences en temps réel</span>
                <span class="role-perm">Devoirs à rendre</span>
                <span class="role-perm">Solde des frais</span>
            </div>
        </div>

    </div>
</section>

<!-- ── CTA finale ── -->
<section class="cta-section">
    <div class="cta-bg"></div>
    <div class="cta-pattern"></div>
    <div class="cta-content">
        <h2 class="cta-title">
            Votre école mérite<br>
            <em>un meilleur outil.</em>
        </h2>
        <p class="cta-desc">
            Rejoignez Dugsi et transformez la gestion de votre établissement dès aujourd'hui.
        </p>
        <div class="cta-actions">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn-gold">
                    Créer un compte
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            @endif
            @if (Route::has('login'))
                <a href="{{ route('login') }}" class="btn-ghost-white">
                    Se connecter →
                </a>
            @endif
        </div>
    </div>
</section>

<!-- ── Footer ── -->
<footer class="footer">
    <a href="#" class="footer-brand">
        <div class="footer-logo">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
            </svg>
        </div>
        <span class="footer-name">Dugsi</span>
    </a>
    <span class="footer-copy">
        © {{ date('Y') }} Dugsi · Plateforme de gestion scolaire à Djibouti
    </span>
    <div class="footer-links">
        <a href="{{ route('login') }}" class="footer-link">Connexion</a>
        @if (Route::has('register'))
            <a href="{{ route('register') }}" class="footer-link">Inscription</a>
        @endif
    </div>
</footer>

</body>
</html>