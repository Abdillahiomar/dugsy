<?php

use App\Models\Announcement;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public Announcement $announcement;

    public function mount(Announcement $announcement): void
    {
        // Vérifier que l'annonce est visible pour cet utilisateur
        $user = auth()->user();
        $role = $user->roles->first()?->name ?? '';

        $canManage = $user->hasAnyRole(['admin','directeur'])
            || $user->can('announcements.manage');

        if (! $canManage) {
            // Non admin : doit être publiée et destinée à ce rôle
            if (! $announcement->isPublished()) abort(403);

            $targets = $announcement->target_roles ?? ['all'];
            if (! in_array('all', $targets) && ! in_array($role, $targets)) {
                abort(403);
            }
        }

        $this->announcement = $announcement;
    }

    public function with(): array
    {
        $canManage = auth()->user()->hasAnyRole(['admin','directeur'])
            || auth()->user()->can('announcements.manage');

        return compact('canManage');
    }
}; ?>

<style>
    .bc { display:flex; align-items:center; gap:.5rem; font-size:.8125rem; margin-bottom:1.5rem; color:var(--ink); opacity:.5; }
    .bc a { color:inherit; text-decoration:none; }
    .bc a:hover { color:var(--sidebar-soft); opacity:1; }
    .bc svg { width:14px; height:14px; }
    .bc-cur { opacity:1; font-weight:600; color:var(--ink); }

    .ann-layout { display:grid; grid-template-columns:1fr 280px; gap:1.5rem; align-items:start; }
    @media(max-width:900px) { .ann-layout { grid-template-columns:1fr; } }

    /* Article */
    .article { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; }

    .article-header { padding:1.75rem; background:var(--sidebar); }
    @if ($announcement->is_pinned ?? false)
        .article-header { background:linear-gradient(135deg, var(--sidebar) 80%, rgba(232,168,56,.4)); }
    @endif

    .art-meta-top { display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem; flex-wrap:wrap; }
    .art-badge { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
    .art-badge-pinned  { background:rgba(232,168,56,.25); color:#FFD285; }
    .art-badge-target  { background:rgba(255,255,255,.12); color:rgba(255,255,255,.7); }
    .art-badge-expired { background:rgba(224,92,58,.25); color:#FFB09E; }

    .art-title { font-family:'Fraunces',serif; font-size:1.75rem; font-weight:600; color:#FFFFFF; line-height:1.2; margin-bottom:1rem; }

    .art-author-row { display:flex; align-items:center; gap:.75rem; }
    .art-author-avatar { width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; color:#FFFFFF; flex-shrink:0; }
    .art-author-name { font-size:.875rem; font-weight:600; color:#FFFFFF; }
    .art-date { font-size:.8rem; color:rgba(255,255,255,.55); margin-top:1px; }

    .article-body { padding:2rem 1.75rem; }
    .art-content { font-size:1rem; color:var(--ink); line-height:1.75; white-space:pre-line; opacity:.8; }

    /* Pièce jointe */
    .art-attachment { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.25rem; border-radius:10px; background:rgba(42,63,126,.04); border:1px solid rgba(42,63,126,.1); margin-top:1.75rem; }
    .att-info { display:flex; align-items:center; gap:.75rem; }
    .att-icon { width:36px; height:36px; border-radius:8px; background:var(--sidebar); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .att-icon svg { width:18px; height:18px; color:#FFFFFF; }
    .att-name { font-weight:600; font-size:.875rem; color:var(--ink); }
    .att-size { font-size:.75rem; color:var(--ink); opacity:.4; margin-top:1px; }
    .btn-dl { display:inline-flex; align-items:center; gap:6px; padding:.5rem 1.1rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; text-decoration:none; transition:background .15s; }
    .btn-dl:hover { background:var(--sidebar-soft); }
    .btn-dl svg { width:14px; height:14px; }

    /* Sidebar */
    .side-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1rem; }
    .side-card:last-child { margin-bottom:0; }
    .side-card-header { padding:.75rem 1rem; border-bottom:1px solid var(--line); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; }
    .side-card-body   { padding:.875rem 1rem; }
    .side-row { display:flex; justify-content:space-between; align-items:flex-start; padding:.4rem 0; border-bottom:1px solid var(--line); font-size:.8125rem; gap:.5rem; }
    .side-row:last-child { border-bottom:none; }
    .side-label { color:var(--ink); opacity:.55; flex-shrink:0; }
    .side-value { font-weight:600; color:var(--ink); text-align:right; }

    .badge { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
    .badge-published { background:rgba(30,120,80,.1); color:#166534; }
    .badge-draft     { background:rgba(0,0,0,.06); color:var(--ink); opacity:.55; }
    .badge-expired   { background:rgba(224,92,58,.1); color:var(--accent-red); }

    .btn-back { display:flex; align-items:center; justify-content:center; gap:5px; width:100%; padding:.5rem; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); text-decoration:none; }
    .btn-back:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }

    .btn-edit { display:flex; align-items:center; justify-content:center; gap:5px; width:100%; padding:.5rem; border-radius:8px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; text-decoration:none; margin-bottom:.65rem; }
    .btn-edit:hover { background:rgba(42,63,126,.16); }
    .btn-edit svg { width:15px; height:15px; }
</style>

<div>
    <div class="bc">
        <a href="{{ route('announcements.index') }}">Annonces</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <span class="bc-cur">{{ Str::limit($announcement->title, 50) }}</span>
    </div>

    <div class="ann-layout">

        {{-- Article principal --}}
        <div class="article">

            {{-- En-tête --}}
            <div class="article-header">
                <div class="art-meta-top">
                    @if ($announcement->is_pinned)
                        <span class="art-badge art-badge-pinned">📌 Épinglée</span>
                    @endif
                    <span class="art-badge art-badge-target">
                        {{ $announcement->targetLabel() }}
                    </span>
                    @if ($announcement->isExpired())
                        <span class="art-badge art-badge-expired">Expirée</span>
                    @endif
                </div>

                <h1 class="art-title">{{ $announcement->title }}</h1>

                <div class="art-author-row">
                    <div class="art-author-avatar">
                        {{ strtoupper(substr($announcement->author->name, 0, 2)) }}
                    </div>
                    <div>
                        <div class="art-author-name">{{ $announcement->author->name }}</div>
                        <div class="art-date">
                            @if ($announcement->published_at)
                                Publié le {{ $announcement->published_at->locale('fr')->isoFormat('dddd D MMMM YYYY à HH:mm') }}
                            @else
                                Brouillon — non publié
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Corps --}}
            <div class="article-body">
                <div class="art-content">{{ $announcement->content }}</div>

                {{-- Pièce jointe --}}
                @if ($announcement->file_path)
                    <div class="art-attachment">
                        <div class="att-info">
                            <div class="att-icon">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="att-name">{{ $announcement->file_name }}</div>
                                @if ($announcement->file_size)
                                    <div class="att-size">{{ $announcement->file_size }}</div>
                                @endif
                            </div>
                        </div>
                        <a href="{{ $announcement->fileUrl() }}" target="_blank" class="btn-dl">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Télécharger
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div style="position:sticky;top:1.5rem;">

            {{-- Actions admin --}}
            @if ($canManage)
                <a href="{{ route('announcements.index') }}?editingId={{ $announcement->id }}"
                   class="btn-edit" style="margin-bottom:.65rem;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Modifier cette annonce
                </a>
            @endif

            {{-- Détails --}}
            <div class="side-card">
                <div class="side-card-header">Détails</div>
                <div class="side-card-body">
                    <div class="side-row">
                        <span class="side-label">Statut</span>
                        <span class="badge badge-{{ strtolower($announcement->statusLabel() === 'Publiée' ? 'published' : ($announcement->statusLabel() === 'Expirée' ? 'expired' : 'draft')) }}">
                            {{ $announcement->statusLabel() }}
                        </span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Destinataires</span>
                        <span class="side-value">{{ $announcement->targetLabel() }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Auteur</span>
                        <span class="side-value">{{ $announcement->author->name }}</span>
                    </div>
                    @if ($announcement->published_at)
                        <div class="side-row">
                            <span class="side-label">Publié le</span>
                            <span class="side-value">{{ $announcement->published_at->locale('fr')->isoFormat('D MMM YYYY') }}</span>
                        </div>
                    @endif
                    @if ($announcement->expires_at)
                        <div class="side-row">
                            <span class="side-label">Expire le</span>
                            <span class="side-value" style="color:{{ $announcement->isExpired() ? 'var(--accent-red)' : '#8A6010' }};">
                                {{ $announcement->expires_at->locale('fr')->isoFormat('D MMM YYYY') }}
                            </span>
                        </div>
                    @endif
                    <div class="side-row">
                        <span class="side-label">Épinglée</span>
                        <span class="side-value">{{ $announcement->is_pinned ? 'Oui' : 'Non' }}</span>
                    </div>
                    @if ($announcement->file_path)
                        <div class="side-row">
                            <span class="side-label">Pièce jointe</span>
                            <span class="side-value">{{ $announcement->file_name }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <a href="{{ route('announcements.index') }}" class="btn-back">
                ← Retour aux annonces
            </a>
        </div>
    </div>
</div>
