<!DOCTYPE html>
<html lang="fr" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dugsi' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance

    <style>
        :root {
            --ink: #20241F;
            --paper: #F7F4EC;
            --paper-raised: #FFFEFA;
            --chalkboard: #1F3A33;
            --chalkboard-soft: #28453D;
            --chalk: #F2E9D8;
            --chalk-dim: #B9C9C0;
            --accent-red: #C1432B;
            --accent-gold: #D9A441;
            --line: #DDD6C4;
        }
        body { background-color: var(--paper); color: var(--ink); font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Fraunces', serif; }
        .font-mono-data { font-family: 'JetBrains Mono', monospace; }
        .ruled-bg {
            background-image: repeating-linear-gradient(to bottom, transparent, transparent 35px, var(--line) 35px, var(--line) 36px);
        }
        .tab-active::before {
            content: '';
            position: absolute;
            left: -1.25rem;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 22px;
            background: var(--accent-gold);
            border-radius: 2px;
        }
    </style>
</head>
<body class="h-full">
    <div class="flex min-h-screen">

        {{-- Sidebar : reliure de classeur --}}
        <aside class="hidden lg:flex w-64 shrink-0 flex-col justify-between px-6 py-8" style="background-color: var(--chalkboard);">
            <div>
                <div class="flex items-center gap-2 mb-12 px-1">
                    <span class="font-display text-2xl font-semibold tracking-tight" style="color: var(--chalk);">Dugsi</span>
                    <span class="font-mono-data text-[10px] uppercase tracking-widest px-1.5 py-0.5 rounded" style="color: var(--chalkboard); background-color: var(--accent-gold);">
                        {{ auth()->user()->school->name ?? 'Ecole' }}
                    </span>
                </div>

                <nav class="space-y-1 pl-5">
                    @php
                        $navItems = [
                            ['label' => 'Tableau de bord', 'route' => 'dashboard', 'icon' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z'],
                            ['label' => 'Eleves', 'route' => 'students.index', 'icon' => 'M12 4.5L2 9l10 4.5L22 9l-10-4.5zM2 13.5l10 4.5 10-4.5M2 17.5l10 4.5 10-4.5'],
                            ['label' => 'Classes', 'route' => 'classes.index', 'icon' => 'M4 6h16M4 12h16M4 18h7'],
                            ['label' => 'Personnel', 'route' => 'staff.index', 'icon' => 'M16 14a4 4 0 10-8 0M12 11a3 3 0 100-6 3 3 0 000 6zM5 21v-2a4 4 0 014-4h6a4 4 0 014 4v2'],
                            ['label' => 'Finances', 'route' => 'finances.index', 'icon' => 'M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6'],
                            ['label' => 'Annonces', 'route' => 'announcements.index', 'icon' => 'M3 11l18-5v12L3 13v-2zM11 13.5V19a2 2 0 002 2v0'],
                        ];
                    @endphp

                    @foreach ($navItems as $item)
                        @php $isActive = request()->routeIs($item['route']); @endphp
                        <a href="{{ \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#' }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors {{ $isActive ? 'tab-active' : '' }}"
                           style="color: {{ $isActive ? '#FFFFFF' : 'var(--chalk-dim)' }}; background-color: {{ $isActive ? 'var(--chalkboard-soft)' : 'transparent' }};">
                            <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            <span class="font-medium">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="pl-5 pt-6 border-t" style="border-color: rgba(242,233,216,0.12);">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-mono-data text-xs font-semibold shrink-0"
                         style="background-color: var(--accent-gold); color: var(--chalkboard);">
                        {{ auth()->user()->initials() }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate" style="color: var(--chalk);">{{ auth()->user()->name }}</p>
                        <p class="text-xs truncate" style="color: var(--chalk-dim);">{{ auth()->user()->email }}</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Contenu --}}
        <div class="flex-1 flex flex-col min-w-0">
            <header class="flex items-center justify-between px-6 lg:px-10 py-5 border-b" style="border-color: var(--line);">
                <div>
                    <p class="font-mono-data text-[11px] uppercase tracking-widest" style="color: var(--accent-red);">
                        {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </p>
                    <h1 class="font-display text-2xl font-semibold mt-0.5">{{ $header ?? 'Tableau de bord' }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    {{ $headerActions ?? '' }}
                </div>
            </header>

            <main class="flex-1 px-6 lg:px-10 py-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
