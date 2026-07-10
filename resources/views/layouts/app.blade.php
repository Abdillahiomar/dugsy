{{-- ============================================================
     resources/views/components/layouts/app.blade.php
     Layout principal — sidebar + header + footer fixes
     ============================================================ --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Dugsi') }}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,600;0,9..144,700;1,9..144,400&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap"
          rel="stylesheet">

    {{-- Styles compilés --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>

    {{-- Livewire --}}
    @livewireStyles
</head>
<body>

<div class="app-shell">

    {{-- ── Sidebar fixe ── --}}
    @include('layouts.partials.sidebar')

    {{-- ── Header fixe ── --}}
    @include('layouts.partials.header')

    {{-- ── Contenu scrollable ── --}}
    <main class="app-main" id="app-main">
        {{ $slot }}
    </main>

    {{-- ── Footer fixe ── --}}
    @include('layouts.partials.footer')

</div>

{{-- Livewire + Alpine --}}
@livewireScripts
@stack('scripts')
</body>
</html>
