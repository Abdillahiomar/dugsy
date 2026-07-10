<?php

use App\Models\Announcement;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Url] public string $filter = 'all'; // all | published | draft | pinned

    // ── Formulaire ────────────────────────────────────────────────
    public bool   $showForm     = false;
    public ?int   $editingId    = null;

    public string $title        = '';
    public string $content      = '';
    public array  $targetRoles  = ['all'];
    public bool   $isPinned     = false;
    public string $publishedAt  = '';
    public string $expiresAt    = '';
    public $annFile             = null;

    // ── Suppression ───────────────────────────────────────────────
    public ?int $confirmDeleteId = null;

    public bool  $saved = false;

    public function mount(): void
    {
        $this->publishedAt = now()->format('Y-m-d\TH:i');
    }

    public function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['admin','directeur'])
            || auth()->user()->can('announcements.manage');
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm  = true;
        $this->editingId = null;
    }

    public function openEdit(int $id): void
    {
        $ann = Announcement::find($id);
        if (! $ann) return;

        $this->editingId   = $id;
        $this->title       = $ann->title;
        $this->content     = $ann->content;
        $this->targetRoles = $ann->target_roles ?? ['all'];
        $this->isPinned    = $ann->is_pinned;
        $this->publishedAt = $ann->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->expiresAt   = $ann->expires_at?->format('Y-m-d\TH:i') ?? '';
        $this->showForm    = true;
    }

    public function saveAnnouncement(): void
    {
        $this->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
            'annFile' => 'nullable|file|max:10240',
        ]);

        $schoolId = auth()->user()->school_id;

        $data = [
            'school_id'    => $schoolId,
            'created_by' => auth()->id(),
            'user_id'    => auth()->id(), // garder les deux pour compatibilité
            'title'        => $this->title,
            'content'      => $this->content,
            'target_roles' => empty($this->targetRoles) ? ['all'] : $this->targetRoles,
            'is_pinned'    => $this->isPinned,
            'published_at' => $this->publishedAt ?: null,
            'expires_at'   => $this->expiresAt   ?: null,
        ];

        if ($this->annFile) {
            // Supprimer l'ancien fichier si édition
            if ($this->editingId) {
                $old = Announcement::find($this->editingId);
                if ($old?->file_path) Storage::disk('public')->delete($old->file_path);
            }
            $data['file_path'] = $this->annFile->store('announcements', 'public');
            $data['file_name'] = $this->annFile->getClientOriginalName();
            $data['file_size'] = $this->formatSize($this->annFile->getSize());
        }

        if ($this->editingId) {
            Announcement::where('id', $this->editingId)->update($data);
        } else {
            Announcement::create($data);
        }

        $this->resetForm();
        $this->saved = true;
    }

    public function publishNow(int $id): void
    {
        Announcement::where('id', $id)->update(['published_at' => now()]);
    }

    public function togglePin(int $id): void
    {
        $ann = Announcement::find($id);
        $ann?->update(['is_pinned' => ! $ann->is_pinned]);
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function deleteAnnouncement(): void
    {
        $ann = Announcement::find($this->confirmDeleteId);
        if ($ann) {
            if ($ann->file_path) Storage::disk('public')->delete($ann->file_path);
            $ann->delete();
        }
        $this->confirmDeleteId = null;
    }

    

    private function resetForm(): void
    {
        $this->title       = '';
        $this->content     = '';
        $this->targetRoles = ['all'];
        $this->isPinned    = false;
        $this->publishedAt = now()->format('Y-m-d\TH:i');
        $this->expiresAt   = '';
        $this->annFile     = null;
        $this->showForm    = false;
        $this->editingId   = null;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
        if ($bytes >= 1024)    return round($bytes / 1024) . ' Ko';
        return $bytes . ' o';
    }

    public function with(): array
    {
        $schoolId   = auth()->user()->school_id;
        $userRole   = auth()->user()->roles->first()?->name ?? '';
        $canManage  = $this->canManage();

        $query = Announcement::where('school_id', $schoolId)
            ->with('author')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        // Non-admins : uniquement les publiées qui les concernent
        if (! $canManage) {
            $query->published()->forRole($userRole);
        }

        // Filtres admins
        if ($canManage) {
            match($this->filter) {
                'published' => $query->published(),
                'draft'     => $query->whereNull('published_at'),
                'pinned'    => $query->where('is_pinned', true),
                default     => null,
            };
        }

        $announcements = $query->get();

        $totalPublished = Announcement::where('school_id', $schoolId)->published()->count();
        $totalDraft     = Announcement::where('school_id', $schoolId)->whereNull('published_at')->count();
        $totalPinned    = Announcement::where('school_id', $schoolId)->where('is_pinned', true)->count();

        $roles = [
            'all'         => 'Tout le monde',
            'parent'      => 'Parents',
            'enseignant'  => 'Enseignants',
            'surveillant' => 'Surveillants',
            'comptable'   => 'Comptables',
        ];

        return compact(
            'announcements', 'canManage', 'roles',
            'totalPublished', 'totalDraft', 'totalPinned'
        );
    }
}; ?>

<style>
    /* ── Toolbar ── */
    .page-toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .toolbar-left { display:flex; align-items:center; gap:.65rem; flex-wrap:wrap; }

    .filter-chips { display:flex; gap:.4rem; flex-wrap:wrap; }
    .filter-chip { padding:.4rem .875rem; border-radius:7px; border:1.5px solid var(--line); background:var(--paper); font-size:.8125rem; font-weight:500; cursor:pointer; transition:all .12s; color:var(--ink); }
    .filter-chip.active { border-color:var(--sidebar); background:rgba(42,63,126,.07); color:var(--sidebar); font-weight:600; }

    .btn-primary { display:inline-flex; align-items:center; gap:5px; padding:.45rem 1rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-primary:hover { background:var(--sidebar-soft); }
    .btn-primary svg { width:14px; height:14px; }

    /* ── KPIs ── */
    .kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:.875rem; margin-bottom:1.5rem; }
    @media(max-width:600px) { .kpi-row { grid-template-columns:1fr 1fr; } }
    .kpi-box { padding:.875rem 1.25rem; border-radius:10px; border:1px solid var(--line); background:var(--paper-raised); }
    .kpi-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.25rem; }
    .kpi-value { font-family:'Fraunces',serif; font-size:2rem; font-weight:600; color:var(--ink); }

    /* ── Formulaire ── */
    .form-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.5rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .form-card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .form-card-title  { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .form-card-body   { padding:1.5rem; }
    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media(max-width:700px) { .form-grid-2 { grid-template-columns:1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-field.full { grid-column:1/-1; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input,.form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus,.form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-textarea { padding:.6rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.9375rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; resize:vertical; min-height:140px; line-height:1.65; }
    .form-textarea:focus { border-color:var(--sidebar-soft); }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .form-actions { display:flex; justify-content:flex-end; gap:.65rem; padding-top:1.25rem; border-top:1px solid var(--line); margin-top:1.25rem; }
    .btn-save   { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-cancel { padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }

    /* Destinataires checkboxes */
    .target-checks { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.35rem; }
    .target-check-row { display:flex; align-items:center; gap:.4rem; padding:.4rem .75rem; border-radius:7px; border:1.5px solid var(--line); background:var(--paper); cursor:pointer; transition:all .12s; }
    .target-check-row.selected { border-color:var(--sidebar); background:rgba(42,63,126,.06); }
    .target-checkbox { width:14px; height:14px; border-radius:3px; border:1.5px solid var(--line); appearance:none; cursor:pointer; position:relative; flex-shrink:0; transition:all .12s; }
    .target-checkbox:checked { background:var(--sidebar); border-color:var(--sidebar); }
    .target-checkbox:checked::after { content:''; position:absolute; top:1px; left:3.5px; width:4px; height:7px; border:2px solid #FFF; border-top:none; border-left:none; transform:rotate(45deg); }
    .target-label { font-size:.8125rem; font-weight:500; color:var(--ink); cursor:pointer; }

    /* Upload */
    .upload-zone { display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; border-radius:8px; border:1.5px dashed var(--line); background:var(--paper); cursor:pointer; position:relative; transition:all .12s; }
    .upload-zone:hover { border-color:var(--sidebar-soft); }
    .upload-zone input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .upload-zone svg { width:18px; height:18px; color:var(--sidebar-soft); opacity:.5; flex-shrink:0; }
    .file-attached { display:inline-flex; align-items:center; gap:.4rem; padding:.3rem .65rem; border-radius:5px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); font-size:.8rem; font-weight:600; margin-top:.4rem; }

    /* Toggle */
    .toggle-row { display:flex; align-items:center; justify-content:space-between; padding:.65rem 0; }
    .toggle-label { font-size:.875rem; font-weight:500; color:var(--ink); }
    .toggle-desc  { font-size:.8rem; color:var(--ink); opacity:.5; }
    .toggle-switch { position:relative; width:40px; height:22px; cursor:pointer; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider { position:absolute; inset:0; border-radius:22px; background:var(--line); transition:background .2s; }
    .toggle-slider::before { content:''; position:absolute; width:16px; height:16px; border-radius:50%; background:white; top:3px; left:3px; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .toggle-switch input:checked + .toggle-slider { background:var(--accent); }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(18px); }

    /* ── Liste des annonces ── */
    .ann-list { display:flex; flex-direction:column; gap:1rem; }

    .ann-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; transition:border-color .15s,box-shadow .15s; }
    .ann-card:hover { border-color:rgba(42,63,126,.2); box-shadow:0 4px 16px rgba(42,63,126,.06); }
    .ann-card.pinned { border-color:rgba(232,168,56,.4); }
    .ann-card.pinned .ann-card-side { background:rgba(232,168,56,.06); }

    .ann-card-inner { display:flex; }
    .ann-card-side { width:4px; flex-shrink:0; background:var(--sidebar); }
    .ann-card-body { flex:1; padding:1.25rem 1.5rem; }
    .ann-top { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:.75rem; flex-wrap:wrap; }
    .ann-title-wrap { flex:1; min-width:0; }

    .ann-pin { display:inline-flex; align-items:center; gap:.3rem; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:1px 7px; border-radius:4px; background:rgba(232,168,56,.15); color:#8A6010; margin-bottom:.35rem; }
    .ann-title { font-family:'Fraunces',serif; font-size:1.125rem; font-weight:600; color:var(--ink); line-height:1.25; margin-bottom:.35rem; }
    .ann-title a { color:inherit; text-decoration:none; }
    .ann-title a:hover { color:var(--sidebar-soft); }

    .ann-meta { display:flex; align-items:center; gap:.65rem; flex-wrap:wrap; }
    .ann-author { font-size:.8rem; color:var(--ink); opacity:.5; }
    .ann-date   { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.4; }

    .ann-badges { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
    .badge { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; white-space:nowrap; }
    .badge-published { background:rgba(30,120,80,.1); color:#166534; }
    .badge-draft     { background:rgba(0,0,0,.06); color:var(--ink); opacity:.55; }
    .badge-expired   { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .badge-target    { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }

    .ann-content { font-size:.9375rem; color:var(--ink); opacity:.65; line-height:1.6; margin:.75rem 0; white-space:pre-line; }

    .ann-file { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .75rem; border-radius:6px; background:var(--paper); border:1px solid var(--line); font-size:.8125rem; color:var(--ink); text-decoration:none; margin-bottom:.75rem; transition:border-color .12s; }
    .ann-file:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .ann-file svg { width:14px; height:14px; }

    .ann-actions { display:flex; align-items:center; gap:.4rem; padding-top:.875rem; border-top:1px solid var(--line); flex-wrap:wrap; }
    .btn-action { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .12s; }
    .btn-action svg { width:13px; height:13px; }
    .btn-edit    { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .btn-edit:hover { background:rgba(42,63,126,.16); }
    .btn-publish { background:rgba(30,120,80,.08); color:#166534; }
    .btn-publish:hover { background:rgba(30,120,80,.16); }
    .btn-pin     { background:rgba(232,168,56,.1); color:#8A6010; }
    .btn-pin:hover { background:rgba(232,168,56,.2); }
    .btn-del     { background:rgba(224,92,58,.08); color:var(--accent-red); }
    .btn-del:hover { background:rgba(224,92,58,.16); }
    .btn-see     { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; background:var(--paper); border:1px solid var(--line); color:var(--ink); text-decoration:none; }
    .btn-see:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }

    /* Empty */
    .empty { padding:4rem 2rem; text-align:center; }
    .empty svg { width:44px; height:44px; margin:0 auto 1rem; opacity:.2; }
    .empty-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink); }
    .empty-sub   { font-size:.875rem; color:var(--ink); opacity:.45; margin-top:.35rem; }

    /* Modal */
    .modal-overlay { position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .modal { background:var(--paper-raised); border-radius:14px; border:1px solid var(--line); padding:1.75rem; max-width:380px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; margin-bottom:.5rem; }
    .modal-desc  { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.25rem; line-height:1.5; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.65rem; }
    .btn-modal-cancel  { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; cursor:pointer; font-family:'Inter',sans-serif; color:var(--ink); }
    .btn-modal-confirm { padding:.45rem 1rem; border-radius:8px; background:var(--accent-red); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
</style>

<div>

    @if ($canManage)
        {{-- KPIs --}}
        <div class="kpi-row">
            <div class="kpi-box">
                <div class="kpi-label">Publiées</div>
                <div class="kpi-value" style="color:#166534;">{{ $totalPublished }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Brouillons</div>
                <div class="kpi-value" style="color:var(--ink);opacity:.4;">{{ $totalDraft }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Épinglées</div>
                <div class="kpi-value" style="color:#8A6010;">{{ $totalPinned }}</div>
            </div>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="page-toolbar">
        <div class="toolbar-left">
            @if ($canManage)
                <div class="filter-chips">
                    @foreach ([['v'=>'all','l'=>'Toutes'],['v'=>'published','l'=>'Publiées'],['v'=>'draft','l'=>'Brouillons'],['v'=>'pinned','l'=>'Épinglées']] as $f)
                        <button type="button"
                                wire:click="$set('filter','{{ $f['v'] }}')"
                                class="filter-chip {{ $filter === $f['v'] ? 'active' : '' }}">
                            {{ $f['l'] }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($canManage)
            <button wire:click="openCreate" class="btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nouvelle annonce
            </button>
        @endif
    </div>

    @if ($saved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Annonce enregistrée.
        </div>
    @endif

    {{-- ── Formulaire création / édition ── --}}
    @if ($showForm && $canManage)
        <div class="form-card">
            <div class="form-card-header">
                <span class="form-card-title">{{ $editingId ? 'Modifier l\'annonce' : 'Nouvelle annonce' }}</span>
                <button wire:click="$set('showForm',false)" style="background:none;border:none;cursor:pointer;opacity:.4;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="form-card-body">
                {{-- Titre --}}
                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="form-label">Titre de l'annonce *</label>
                    <input wire:model="title" type="text" class="form-input"
                           placeholder="Ex: Réunion parents-professeurs du 15 juillet">
                    @error('title') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                {{-- Contenu --}}
                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="form-label">Contenu *</label>
                    <textarea wire:model="content" class="form-textarea"
                              placeholder="Texte de l'annonce..."></textarea>
                    @error('content') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-grid-2">
                    {{-- Destinataires --}}
                    <div class="form-field">
                        <label class="form-label">Destinataires</label>
                        <div class="target-checks">
                            @foreach ($roles as $key => $label)
                                <label class="target-check-row {{ in_array($key, $targetRoles) ? 'selected' : '' }}">
                                    <input type="checkbox"
                                           wire:model.live="targetRoles"
                                           value="{{ $key }}"
                                           class="target-checkbox">
                                    <span class="target-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Options --}}
                    <div class="form-field">
                        <label class="form-label">Options</label>

                        <div class="toggle-row">
                            <div>
                                <div class="toggle-label">Épingler l'annonce</div>
                                <div class="toggle-desc">Apparaît toujours en premier.</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" wire:model="isPinned">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="form-field" style="margin-top:.75rem;">
                            <label class="form-label">Date de publication</label>
                            <input wire:model="publishedAt" type="datetime-local" class="form-input">
                            <span style="font-size:.75rem;color:var(--ink);opacity:.4;margin-top:2px;">Vide = brouillon</span>
                        </div>

                        <div class="form-field" style="margin-top:.75rem;">
                            <label class="form-label">Expiration (optionnel)</label>
                            <input wire:model="expiresAt" type="datetime-local" class="form-input">
                        </div>
                    </div>
                </div>

                {{-- Pièce jointe --}}
                <div class="form-field" style="margin-top:.75rem;">
                    <label class="form-label">Pièce jointe (optionnel)</label>
                    <label class="upload-zone">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                        <div>
                            @if ($annFile)
                                <div class="file-attached">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                    {{ $annFile->getClientOriginalName() }}
                                </div>
                            @else
                                <div style="font-size:.875rem;color:var(--ink);opacity:.6;">Cliquer pour joindre un fichier</div>
                                <div style="font-size:.75rem;color:var(--ink);opacity:.4;">PDF, Word, Image — max 10 Mo</div>
                            @endif
                        </div>
                        <input wire:model="annFile" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    </label>
                </div>

                <div class="form-actions">
                    <button wire:click="$set('showForm',false)" class="btn-cancel">Annuler</button>
                    <button wire:click="saveAnnouncement" class="btn-save">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        {{ $editingId ? 'Enregistrer' : 'Publier l\'annonce' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Liste des annonces ── --}}
    @if ($announcements->isEmpty())
        <div class="empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/></svg>
            <div class="empty-title">Aucune annonce</div>
            <div class="empty-sub">
                @if ($canManage) Créez votre première annonce.
                @else Aucune annonce pour le moment. @endif
            </div>
        </div>
    @else
        <div class="ann-list">
            @foreach ($announcements as $ann)
                @php
                    $statusLabel = $ann->statusLabel();
                    $statusCss   = match($statusLabel) {
                        'Publiée'   => 'badge-published',
                        'Brouillon' => 'badge-draft',
                        'Expirée'   => 'badge-expired',
                        default     => 'badge-draft',
                    };
                @endphp
                <div class="ann-card {{ $ann->is_pinned ? 'pinned' : '' }}">
                    <div class="ann-card-inner">
                        <div class="ann-card-side"
                             style="background:{{ $ann->is_pinned ? '#E8A838' : 'var(--sidebar)' }};"></div>
                        <div class="ann-card-body">

                            <div class="ann-top">
                                <div class="ann-title-wrap">
                                    @if ($ann->is_pinned)
                                        <div class="ann-pin">
                                            📌 Épinglée
                                        </div>
                                    @endif
                                    <div class="ann-title">
                                        <a href="{{ route('announcements.show', $ann) }}">
                                            {{ $ann->title }}
                                        </a>
                                    </div>
                                    <div class="ann-meta">
                                        <span class="ann-author">
                                            Par {{ $ann->author->name }}
                                        </span>
                                        <span class="ann-date">
                                            {{ $ann->published_at
                                                ? $ann->published_at->locale('fr')->diffForHumans()
                                                : 'Non publié' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="ann-badges">
                                    @if ($canManage)
                                        <span class="badge {{ $statusCss }}">{{ $statusLabel }}</span>
                                    @endif
                                    <span class="badge badge-target">{{ $ann->targetLabel() }}</span>
                                </div>
                            </div>

                            {{-- Aperçu du contenu --}}
                            <div class="ann-content">{{ Str::limit($ann->content, 200) }}</div>

                            {{-- Pièce jointe --}}
                            @if ($ann->file_path)
                                <a href="{{ $ann->fileUrl() }}" target="_blank" class="ann-file">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    {{ $ann->file_name }}
                                    @if ($ann->file_size) <span style="opacity:.4;">· {{ $ann->file_size }}</span> @endif
                                </a>
                            @endif

                            {{-- Actions --}}
                            <div class="ann-actions">
                                <a href="{{ route('announcements.show', $ann) }}" class="btn-see">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Lire
                                </a>

                                @if ($canManage)
                                    @if ($ann->isDraft())
                                        <button wire:click="publishNow({{ $ann->id }})" class="btn-action btn-publish">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                            Publier maintenant
                                        </button>
                                    @endif

                                    <button wire:click="togglePin({{ $ann->id }})" class="btn-action btn-pin">
                                        {{ $ann->is_pinned ? '📌 Désépingler' : '📌 Épingler' }}
                                    </button>

                                    <button wire:click="openEdit({{ $ann->id }})" class="btn-action btn-edit">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Modifier
                                    </button>

                                    <button wire:click="confirmDelete({{ $ann->id }})" class="btn-action btn-del">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
                                        Supprimer
                                    </button>

                                    @if ($ann->expires_at)
                                        <span style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--ink);opacity:.35;margin-left:auto;">
                                            Expire {{ $ann->expires_at->locale('fr')->diffForHumans() }}
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal suppression --}}
    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer cette annonce ?</div>
                <div class="modal-desc">L'annonce et sa pièce jointe seront définitivement supprimées.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteAnnouncement" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif
</div>
