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

    
    
    {{-- Favicon dynamique selon l'école --}}
    @auth
        @php
            $school    = auth()->user()->school;
            $logoFile  = $school?->logo_path ? public_path('storage/schools/logos/'.basename($school->logo_path)) : null;
            $logoUrl   = ($logoFile && file_exists($logoFile))
                ? asset('storage/schools/logos/'.basename($school->logo_path)).'?v='.filemtime($logoFile)
                : null;
        @endphp

        @if ($logoUrl)
            <link rel="icon" type="image/png" href="{{ $logoUrl }}">
        @else
            <link rel="icon" href="/favicon.ico">
        @endif
    @else
        <link rel="icon" href="/favicon.ico">
    @endauth
   

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
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js"></script>

@stack('scripts')
</body>
</html>
