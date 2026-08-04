{{-- ============================================================
     resources/views/layouts/partials/header.blade.php
     ============================================================ --}}

@php
    $user     = auth()->user();
    $role     = $user->roles->first();
    $initials = strtoupper(substr($user->name, 0, 1))
              . strtoupper(substr(strstr($user->name, ' '), 1, 1) ?: substr($user->name, 1, 1));
@endphp

<header class="top-bar" id="app-header">

    {{-- ── Gauche : date + heure + école ── --}}
    <div class="header-left">
        <div class="header-school-name">
            {{ $user->school?->short_name ?? $user->school?->name ?? '' }}
        </div>
        <div class="header-clock">
            <span class="header-date" id="hdr-date"></span>
            <span class="header-time" id="hdr-time"></span>
        </div>
       <button id="sidebarToggle" class="sidebar-toggle">
    ☰
</button>
    </div>

    {{-- ── Droite : actions + toggle + utilisateur ── --}}
    <div class="header-right">

        {{-- Slot contextuel des pages --}}
        {{ $headerActions ?? '' }}

        {{-- Toggle Dark / Light --}}
        <button type="button"
                id="theme-toggle"
                class="theme-toggle-btn"
                onclick="toggleTheme()"
                title="Changer le thème">
            <svg id="icon-light" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
            <svg id="icon-dark" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="display:none;">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
        </button>

        {{-- Menu utilisateur --}}
        <div class="user-menu" x-data="{ open: false }" @click.outside="open = false">

            <button type="button"
                    class="user-menu-trigger"
                    @click="open = !open"
                    :aria-expanded="open">
                <div class="user-menu-avatar">{{ $initials }}</div>
                <div class="user-menu-info">
                    <span class="user-menu-name">{{ $user->name }}</span>
                    <span class="user-menu-role">
                        {{ $role?->label ?? ucfirst($role?->name ?? 'DOUGSI') }}
                    </span>
                </div>
                <svg class="user-menu-chevron"
                     :class="{ 'rotated': open }"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="user-menu-dropdown"
                 x-show="open"
                 x-transition:enter="dropdown-enter"
                 x-transition:enter-start="dropdown-enter-start"
                 x-transition:enter-end="dropdown-enter-end"
                 x-transition:leave="dropdown-leave"
                 x-transition:leave-start="dropdown-leave-start"
                 x-transition:leave-end="dropdown-leave-end"
                 style="display:none;">

                <div class="dropdown-header">
                    <div class="dropdown-avatar">{{ $initials }}</div>
                    <div>
                        <div class="dropdown-name">{{ $user->name }}</div>
                        <div class="dropdown-email">{{ $user->email }}</div>
                        <div class="dropdown-role-badge">
                            {{ $role?->label ?? ucfirst($role?->name ?? '') }}
                        </div>
                    </div>
                </div>

                <div class="dropdown-divider"></div>

                <a href="{{ route('profile.edit') }}"
                   class="dropdown-item"
                   @click="open = false"
                   wire:navigate>
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Mon profil
                </a>

                <a href="{{ route('dashboard') }}"
                   class="dropdown-item"
                   @click="open = false"
                   wire:navigate>
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Tableau de bord
                </a>

                <div class="dropdown-divider"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item dropdown-item-danger">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Se déconnecter
                    </button>
                </form>
            </div>
        </div>
        {{-- fin user-menu --}}

    </div>
    {{-- fin header-right --}}

</header>

<script>
// ── Horloge live ─────────────────────────────────────────────
(function () {
    const JOURS = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const MOIS  = ['janvier','février','mars','avril','mai','juin','juillet',
                   'août','septembre','octobre','novembre','décembre'];

    function pad(n) { return String(n).padStart(2, '0'); }

    function tick() {
        const now  = new Date();
        const date = JOURS[now.getDay()] + ' ' + now.getDate() + ' '
                   + MOIS[now.getMonth()] + ' ' + now.getFullYear();
        const time = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        const d = document.getElementById('hdr-date');
        const t = document.getElementById('hdr-time');
        if (d) d.textContent = date;
        if (t) t.textContent = time;
    }

    tick();
    setInterval(tick, 1000);
})();

// ── Dark Mode ─────────────────────────────────────────────────
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('dugsi-theme', theme);

    const iconLight = document.getElementById('icon-light');
    const iconDark  = document.getElementById('icon-dark');

    if (theme === 'dark') {
        if (iconLight) iconLight.style.display = 'none';
        if (iconDark)  iconDark.style.display  = '';
    } else {
        if (iconLight) iconLight.style.display = '';
        if (iconDark)  iconDark.style.display  = 'none';
    }
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
}

// Appliquer le thème sauvegardé immédiatement
(function () {
    const saved = localStorage.getItem('dugsi-theme') || 'light';
    applyTheme(saved);
})();
</script>