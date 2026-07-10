<?php

use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    // Formulaire de création
    public bool   $showForm  = false;
    public string $label     = '';
    public string $starts_at = '';
    public string $ends_at   = '';
    public bool   $is_active = false;

    // Confirmation de suppression
    public ?int $confirmDeleteId = null;

    public function updatedSearch(): void { }

    public function saveYear(): void
    {
        $this->validate([
            'label'     => 'required|string|max:20',
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after:starts_at',
        ]);

        // Si on active cette année, désactiver les autres
        if ($this->is_active) {
            AcademicYear::where('school_id', auth()->user()->school_id)
                ->update(['is_active' => false]);
        }

        $year = AcademicYear::create([
            'school_id' => auth()->user()->school_id,
            'label'     => $this->label,
            'starts_at' => $this->starts_at,
            'ends_at'   => $this->ends_at,
            'is_active' => $this->is_active,
        ]);

        // Switcher automatiquement sur la nouvelle année
        AcademicYearService::switchTo($year->id);

        $this->reset('label', 'starts_at', 'ends_at', 'is_active', 'showForm');
        $this->dispatch('academic-year-changed');
    }

    public function activate(int $yearId): void
    {
        AcademicYear::where('school_id', auth()->user()->school_id)
            ->update(['is_active' => false]);

        AcademicYear::where('id', $yearId)->update(['is_active' => true]);

        AcademicYearService::switchTo($yearId);
        $this->dispatch('academic-year-changed');
    }

    public function confirmDelete(int $yearId): void
    {
        $this->confirmDeleteId = $yearId;
    }

    public function deleteYear(): void
    {
        if (! $this->confirmDeleteId) return;

        $year = AcademicYear::find($this->confirmDeleteId);

        if ($year && $year->school_id === auth()->user()->school_id) {
            $year->delete();
        }

        $this->confirmDeleteId = null;
        $this->dispatch('academic-year-changed');
    }

    public function with(): array
    {
        $years = AcademicYear::query()
            ->where('school_id', auth()->user()->school_id)
            ->when($this->search, fn ($q) =>
                $q->where('label', 'like', "%{$this->search}%")
            )
            ->when($this->statusFilter === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('starts_at')
            ->get();

        return ['years' => $years];
    }
}; ?>

<style>
    /* Toolbar */
    .page-toolbar {
        display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap;
    }
    .toolbar-left  { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
    .toolbar-right { display: flex; align-items: center; gap: 0.65rem; }

    .search-wrap { position: relative; }
    .search-wrap svg {
        position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
        width: 15px; height: 15px; color: var(--ink); opacity: 0.35; pointer-events: none;
    }
    .search-input {
        padding: 0.45rem 0.75rem 0.45rem 2.1rem;
        border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper-raised); font-size: 0.875rem;
        font-family: 'Inter', sans-serif; color: var(--ink); width: 220px;
        outline: none; transition: border-color 0.15s;
    }
    .search-input:focus { border-color: var(--sidebar-soft); }

    .filter-select {
        padding: 0.45rem 0.75rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper-raised); font-size: 0.875rem;
        font-family: 'Inter', sans-serif; color: var(--ink);
        outline: none; cursor: pointer;
    }
    .btn-primary {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 0.45rem 1rem; border-radius: 8px;
        background: var(--sidebar); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.15s;
    }
    .btn-primary:hover { background: var(--sidebar-soft); }
    .btn-primary svg { width: 15px; height: 15px; }

    /* Formulaire de création */
    .create-form {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
        margin-bottom: 1.25rem;
        animation: slideDown 0.15s ease;
    }
    @keyframes slideDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

    .create-form-header {
        padding: 0.875rem 1.5rem; border-bottom: 1px solid var(--line);
        display: flex; align-items: center; justify-content: space-between;
    }
    .create-form-title {
        font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink);
    }
    .create-form-body {
        padding: 1.25rem 1.5rem;
        display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem;
        align-items: end;
    }
    @media (max-width: 800px) { .create-form-body { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 500px) { .create-form-body { grid-template-columns: 1fr; } }

    .form-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-label {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.5;
    }
    .form-input, .form-select-f {
        padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem;
        font-family: 'Inter', sans-serif; color: var(--ink); outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-input:focus, .form-select-f:focus {
        border-color: var(--sidebar-soft);
        box-shadow: 0 0 0 3px rgba(42,63,126,0.08);
    }
    .form-error { font-size: 0.75rem; color: var(--accent-red); margin-top: 0.2rem; }

    .toggle-wrap {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.5rem 0;
    }
    .toggle-label { font-size: 0.875rem; color: var(--ink); cursor: pointer; }

    .form-actions-row {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 1rem 1.5rem; border-top: 1px solid var(--line);
        justify-content: flex-end;
    }
    .btn-cancel {
        padding: 0.45rem 1rem; border-radius: 8px;
        border: 1px solid var(--line); background: var(--paper);
        font-size: 0.875rem; font-weight: 500; font-family: 'Inter', sans-serif;
        color: var(--ink); cursor: pointer;
    }
    .btn-save {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 0.45rem 1.1rem; border-radius: 8px;
        background: var(--sidebar); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.15s;
    }
    .btn-save:hover { background: var(--sidebar-soft); }

    /* Table */
    .table-wrap {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
    }
    table { width: 100%; border-collapse: collapse; }
    thead tr { border-bottom: 1px solid var(--line); background: var(--paper); }
    thead th {
        text-align: left; padding: 0.65rem 1.25rem;
        font-family: 'JetBrains Mono', monospace; font-size: 10px;
        font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;
        color: var(--ink); opacity: 0.45; white-space: nowrap;
    }
    thead th:last-child { text-align: right; }
    tbody tr { border-bottom: 1px solid var(--line); transition: background 0.1s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: rgba(30,45,90,0.03); }
    tbody td { padding: 1rem 1.25rem; font-size: 0.875rem; color: var(--ink); vertical-align: middle; }
    tbody td:last-child { text-align: right; }

    /* Year info */
    .year-label-cell { display: flex; align-items: center; gap: 0.75rem; }
    .year-icon {
        width: 34px; height: 34px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(42,63,126,0.08); flex-shrink: 0;
    }
    .year-icon svg { width: 17px; height: 17px; color: var(--sidebar-soft); }
    .year-name { font-weight: 600; }
    .year-dates { font-size: 0.75rem; color: var(--ink); opacity: 0.45; margin-top: 2px; font-family: 'JetBrains Mono', monospace; }

    /* Progress barre durée */
    .progress-wrap { min-width: 120px; }
    .progress-bar-bg {
        height: 5px; border-radius: 3px;
        background: var(--line); overflow: hidden;
    }
    .progress-bar-fill {
        height: 100%; border-radius: 3px;
        background: var(--sidebar-soft);
        transition: width 0.3s;
    }
    .progress-label { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--ink); opacity: 0.45; margin-top: 3px; }

    /* Badges */
    .badge {
        display: inline-flex; align-items: center; gap: 4px;
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        padding: 3px 9px; border-radius: 4px;
        text-transform: uppercase; letter-spacing: 0.05em;
    }
    .badge-active   { background: rgba(74,222,128,0.12); color: #166534; }
    .badge-inactive { background: rgba(0,0,0,0.06); color: var(--ink); opacity: 0.5; }
    .badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

    /* Actions */
    .actions-cell { display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem; }
    .btn-action {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 0.3rem 0.65rem; border-radius: 6px;
        font-size: 0.8rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.12s;
        white-space: nowrap; text-decoration: none;
    }
    .btn-action svg { width: 13px; height: 13px; }
    .btn-activate { background: rgba(74,222,128,0.12); color: #166534; }
    .btn-activate:hover { background: rgba(74,222,128,0.22); }
    .btn-delete   { background: rgba(224,92,58,0.1); color: var(--accent-red); }
    .btn-delete:hover { background: rgba(224,92,58,0.18); }
    .btn-current  { background: rgba(42,63,126,0.08); color: var(--sidebar-soft); }

    /* Modal de confirmation */
    .modal-overlay {
        position: fixed; inset: 0; z-index: 100;
        background: rgba(0,0,0,0.4);
        display: flex; align-items: center; justify-content: center;
        padding: 1rem;
        animation: fadeIn 0.15s ease;
    }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    .modal {
        background: var(--paper-raised); border-radius: 14px;
        border: 1px solid var(--line); padding: 1.75rem;
        max-width: 400px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        animation: slideUp 0.15s ease;
    }
    @keyframes slideUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    .modal-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
    .modal-desc { font-size: 0.875rem; color: var(--ink); opacity: 0.6; margin-bottom: 1.25rem; line-height: 1.5; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 0.65rem; }
    .btn-modal-cancel {
        padding: 0.45rem 1rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-weight: 500;
        font-family: 'Inter', sans-serif; color: var(--ink); cursor: pointer;
    }
    .btn-modal-confirm {
        padding: 0.45rem 1rem; border-radius: 8px; border: none;
        background: var(--accent-red); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer;
    }

    /* Empty state */
    .empty-state { padding: 3.5rem 2rem; text-align: center; }
    .empty-icon { width: 40px; height: 40px; margin: 0 auto 0.875rem; opacity: 0.2; }
    .empty-title { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 0.3rem; }
    .empty-sub { font-size: 0.875rem; color: var(--ink); opacity: 0.45; }
</style>

<div>

    {{-- Toolbar --}}
    <div class="page-toolbar">
        <div class="toolbar-left">
            <div class="search-wrap">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 111 11a6 6 0 0116 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Rechercher une année..." class="search-input">
            </div>
            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">Tous les statuts</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="toolbar-right">
            <button wire:click="$toggle('showForm')" class="btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle année
            </button>
        </div>
    </div>

    {{-- Formulaire de création --}}
    @if ($showForm)
        <div class="create-form">
            <div class="create-form-header">
                <span class="create-form-title">Nouvelle année académique</span>
                <button wire:click="$set('showForm', false)" style="background:none; border:none; cursor:pointer; opacity:0.4;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="create-form-body">
                <div class="form-field">
                    <label class="form-label">Label (ex: 2026-2027)</label>
                    <input wire:model="label" type="text" class="form-input" placeholder="2026-2027">
                    @error('label') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Date de début</label>
                    <input wire:model="starts_at" type="date" class="form-input">
                    @error('starts_at') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Date de fin</label>
                    <input wire:model="ends_at" type="date" class="form-input">
                    @error('ends_at') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Activer immédiatement</label>
                    <div class="toggle-wrap">
                        <input wire:model="is_active" type="checkbox" id="is_active" style="width:16px;height:16px;cursor:pointer;">
                        <label for="is_active" class="toggle-label">Oui</label>
                    </div>
                </div>
            </div>
            <div class="form-actions-row">
                <button wire:click="$set('showForm', false)" class="btn-cancel">Annuler</button>
                <button wire:click="saveYear" class="btn-save">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Créer
                </button>
            </div>
        </div>
    @endif

    {{-- Table --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Année</th>
                    
                    <th>Durée</th>
                    <th>Progression</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($years as $year)
                    @php
                        $start    = $year->starts_at;
                        $end      = $year->ends_at;
                        $total    = $start->diffInDays($end);
                        $elapsed  = min($start->diffInDays(now()), $total);
                        $progress = $total > 0 ? round(($elapsed / $total) * 100) : 0;
                        $progress = max(0, min(100, $progress));
                        $months   = $start->diffInMonths($end);
                    @endphp
                    <tr>
                        <td>
                            <div class="year-label-cell">
                                <div class="year-icon">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="year-name">{{ $year->label }}</div>
                                    <div class="year-dates">
                                        {{ $year->starts_at->format('d/m/Y') }} → {{ $year->ends_at->format('d/m/Y') }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono',monospace; font-size:12px;">
                                {{ $months }} mois
                            </span>
                        </td>
                        <td>
                            <div class="progress-wrap">
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: {{ $progress }}%"></div>
                                </div>
                                <div class="progress-label">{{ $progress }}% écoulé</div>
                            </div>
                        </td>
                        <td>
                            @if ($year->is_active)
                                <span class="badge badge-active">
                                    <span class="badge-dot"></span> Active
                                </span>
                            @else
                                <span class="badge badge-inactive">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions-cell">
                                @if (! $year->is_active)
                                    <button wire:click="activate({{ $year->id }})" class="btn-action btn-activate">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Activer
                                    </button>
                                    <button wire:click="confirmDelete({{ $year->id }})" class="btn-action btn-delete">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M10 3h4a1 1 0 011 1v3H9V4a1 1 0 011-1z"/>
                                        </svg>
                                        Supprimer
                                    </button>
                                @else
                                    <span class="btn-action btn-current" style="cursor:default;">
                                        ✓ Année en cours
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <svg class="empty-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <div class="empty-title">Aucune année académique</div>
                                <div class="empty-sub">Crée ta première année pour commencer.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal confirmation suppression --}}
    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer cette année ?</div>
                <div class="modal-desc">
                    Cette action est irréversible. Toutes les classes, inscriptions et données liées à cette année seront définitivement supprimées.
                </div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId', null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteYear" class="btn-modal-confirm">Oui, supprimer</button>
                </div>
            </div>
        </div>
    @endif

</div>