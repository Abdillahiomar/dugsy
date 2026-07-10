{{-- ============================================================
     resources/views/layouts/partials/sidebar.blade.php
     Sidebar fixe — ne bouge jamais
     ============================================================ --}}
<aside class="sidebar" id="app-sidebar">
    <div class="sidebar-top">

        {{-- Brand --}}
        <div class="sidebar-brand">
            @php $school = auth()->user()->school; @endphp
            @if ($school?->logo_path)
                <img src="{{ asset('storage/'.$school->logo_path) }}"
                     alt="Logo"
                     class="sidebar-logo">
            @else
                <div class="sidebar-logo-placeholder">
                    {{ strtoupper(substr($school?->name ?? 'D', 0, 1)) }}
                </div>
            @endif
            <span class="sidebar-brand-name">
                {{ $school?->short_name ?? $school?->name ?? 'Dugsi' }}
            </span>
        </div>

        {{-- Switcher d'année académique --}}
        <div class="sidebar-year-wrap">
            @livewire('year-switcher')
        </div>

        {{-- Navigation --}}
        <nav class="sidebar-nav">
            @php
                $navGroups = [
                    'Principal' => [
                        [
                            'label'      => 'Tableau de bord',
                            'route'      => 'dashboard',
                            'permission' => null,
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
                        ],
                    ],
                    'Académique' => [
                        [
                            'label'      => 'Élèves',
                            'route'      => 'students.index',
                            'permission' => 'students.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
                        ],
                        [
                            'label'      => 'Classes',
                            'route'      => 'classes.index',
                            'permission' => 'classes.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
                        ],
                        [
                            'label'      => 'Matières',
                            'route'      => 'subjects.index',
                            'permission' => 'subjects.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
                        ],
                        [
                            'label'      => 'Notes',
                            'route'      => 'grades.index',
                            'permission' => 'grades.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>',
                        ],
                        [
                            'label'      => 'Bulletins',
                            'route'      => 'bulletins.class',
                            'permission' => 'bulletins.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
                        ],
                        [
                            'label'      => 'Absences',
                            'route'      => 'absences.index',
                            'permission' => 'absences.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                        ],
                        [
                            'label'      => 'Devoirs',
                            'route'      => 'homeworks.index',
                            'permission' => 'homeworks.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0118 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>',
                        ],
                        [
                            'label'      => 'Personnel',
                            'route'      => 'staff.index',
                            'permission' => 'staff.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
                        ],
                    ],
                    'Gestion' => [
                        [
                            'label'      => 'Finances',
                            'route'      => 'finances.index',
                            'permission' => 'finance.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>',
                        ],
                        [
                            'label'      => 'Annonces',
                            'route'      => 'announcements.index',
                            'permission' => 'announcements.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>',
                        ],
                    ],
                    'Configuration' => [
                        [
                            'label'      => 'Années académiques',
                            'route'      => 'academic-years.index',
                            'permission' => 'academic_years.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                        ],
                        [
                            'label'      => 'Paramètres école',
                            'route'      => 'school-config.general',
                            'permission' => 'school.settings',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                        ],
                        [
                            'label'      => 'Frais & Paiements',
                            'route'      => 'school-config.fees',
                            'permission' => 'fees.manage',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
                        ],
                        [
                            'label'      => "Politique d'admission",
                            'route'      => 'school-config.admission',
                            'permission' => 'school.settings',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
                        ],
                        [
                            'label'      => "Politique d'évaluation",
                            'route'      => 'school-config.grading',
                            'permission' => 'school.settings',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
                        ],
                    ],
                    'Administration' => [
                        [
                            'label'      => 'Utilisateurs & Rôles',
                            'route'      => 'users.index',
                            'permission' => 'users.view',
                            'svg'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
                        ],
                    ],
                ];
            @endphp

            @foreach ($navGroups as $groupLabel => $items)
                @php
                    $visibleItems = collect($items)->filter(function ($item) {
                        return $item['permission'] === null
                            || auth()->user()->can($item['permission']);
                    });
                @endphp

                @if ($visibleItems->isNotEmpty())
                    <p class="nav-group-label">{{ $groupLabel }}</p>

                    @foreach ($visibleItems as $item)
                        @php
                            try {
                                $href     = \Illuminate\Support\Facades\Route::has($item['route'])
                                    ? route($item['route'])
                                    : '#';
                                $isActive = request()->routeIs($item['route'] . '*');
                            } catch (\Exception $e) {
                                $href     = '#';
                                $isActive = false;
                            }
                        @endphp
                        <a href="{{ $href }}"
                           class="nav-item {{ $isActive ? 'active' : '' }}"
                           wire:navigate>
                            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                {!! $item['svg'] !!}
                            </svg>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
    </div>

    {{-- Pied du sidebar : version Dugsi --}}
    <div class="sidebar-footer-brand">
        <span style="font-family:'Fraunces',serif;font-size:.8125rem;font-weight:600;color:rgba(255,255,255,.35);letter-spacing:.04em;">
            Dugsi
        </span>
        <span style="font-family:'JetBrains Mono',monospace;font-size:9px;color:rgba(255,255,255,.2);">
            v1.0 · {{ auth()->user()->roles->first()?->label ?? auth()->user()->roles->first()?->name ?? '' }}
        </span>
    </div>
</aside>
