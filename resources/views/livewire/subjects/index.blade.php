<?php

use App\Models\Subject;
use App\Models\ClassSubjectTeacher;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $cycleFilter = '';

    // Création
    public bool   $showForm    = false;
    public string $name        = '';
    public string $code        = '';
    public string $coefficient = '1';
    public string $cycles      = ''; // cycles concernés (json ou séparé par virgule)
    public string $color       = '#1E2D5A';

    // Edition
    public ?int   $editingId      = null;
    public string $editName        = '';
    public string $editCode        = '';
    public string $editCoefficient = '1';
    public string $editCycles      = '';
    public string $editColor       = '#1E2D5A';

    // Suppression
    public ?int $confirmDeleteId = null;

    public function saveSubject(): void
    {
        $this->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:10',
            'coefficient' => 'required|integer|min:1|max:10',
            'color'       => 'nullable|string|max:7',
        ]);

        Subject::create([
            'school_id'   => auth()->user()->school_id,
            'name'        => $this->name,
            'code'        => strtoupper($this->code) ?: null,
            'coefficient' => $this->coefficient,
            'color'       => $this->color,
            'cycles'      => $this->cycles ?: null,
        ]);

        $this->reset('name', 'code', 'coefficient', 'cycles', 'color', 'showForm');
    }

    public function startEdit(int $id): void
    {
        $subject = Subject::find($id);
        if (! $subject) return;

        $this->editingId      = $id;
        $this->editName        = $subject->name;
        $this->editCode        = $subject->code ?? '';
        $this->editCoefficient = (string) $subject->coefficient;
        $this->editCycles      = $subject->cycles ?? '';
        $this->editColor       = $subject->color ?? '#1E2D5A';
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editName'        => 'required|string|max:100',
            'editCode'        => 'nullable|string|max:10',
            'editCoefficient' => 'required|integer|min:1|max:10',
            'editColor'       => 'nullable|string|max:7',
        ]);

        Subject::where('id', $this->editingId)->update([
            'name'        => $this->editName,
            'code'        => strtoupper($this->editCode) ?: null,
            'coefficient' => $this->editCoefficient,
            'color'       => $this->editColor,
            'cycles'      => $this->editCycles ?: null,
        ]);

        $this->editingId = null;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function deleteSubject(): void
    {
        if (! $this->confirmDeleteId) return;

        Subject::where('id', $this->confirmDeleteId)
            ->where('school_id', auth()->user()->school_id)
            ->delete();

        $this->confirmDeleteId = null;
    }

    public function with(): array
    {
        $subjects = Subject::query()
            ->where('school_id', auth()->user()->school_id)
            ->when($this->search, fn ($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('code', 'like', "%{$this->search}%")
            )
            ->when($this->cycleFilter, fn ($q) =>
                $q->where('cycles', 'like', "%{$this->cycleFilter}%")
            )
            ->withCount('classSubjects')
            ->orderBy('name')
            ->get();

        return ['subjects' => $subjects];
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
        font-family: 'Inter', sans-serif; color: var(--ink); width: 220px; outline: none;
    }
    .search-input:focus { border-color: var(--sidebar-soft); }

    .filter-select {
        padding: 0.45rem 0.75rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper-raised); font-size: 0.875rem;
        font-family: 'Inter', sans-serif; color: var(--ink); outline: none; cursor: pointer;
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

    /* Formulaire création */
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
        display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 80px; gap: 1rem; align-items: end;
    }
    @media (max-width: 900px) { .create-form-body { grid-template-columns: 1fr 1fr 1fr; } }
    @media (max-width: 550px) { .create-form-body { grid-template-columns: 1fr; } }

    .form-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-label {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.5;
    }
    .form-input, .form-select-inp {
        padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-family: 'Inter', sans-serif;
        color: var(--ink); outline: none; width: 100%;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-input:focus, .form-select-inp:focus {
        border-color: var(--sidebar-soft); box-shadow: 0 0 0 3px rgba(42,63,126,0.08);
    }
    .form-error { font-size: 0.75rem; color: var(--accent-red); margin-top: 0.2rem; }
    .color-input {
        width: 100%; height: 38px; border-radius: 8px;
        border: 1px solid var(--line); cursor: pointer; padding: 3px;
        background: var(--paper);
    }
    .form-actions-row {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 1rem 1.5rem; border-top: 1px solid var(--line); justify-content: flex-end;
    }
    .btn-cancel-sm {
        padding: 0.45rem 1rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-weight: 500;
        font-family: 'Inter', sans-serif; color: var(--ink); cursor: pointer;
    }
    .btn-save-sm {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 0.45rem 1.1rem; border-radius: 8px;
        background: var(--sidebar); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.15s;
    }
    .btn-save-sm:hover { background: var(--sidebar-soft); }

    /* Grille de matières */
    .subjects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1rem;
    }

    .subject-card {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .subject-card:hover { box-shadow: 0 2px 12px rgba(42,63,126,0.08); }

    .subject-card-top {
        height: 5px;
    }
    .subject-card-body {
        padding: 1rem 1.25rem 0.875rem;
    }
    .subject-header {
        display: flex; align-items: flex-start; justify-content: space-between;
        margin-bottom: 0.75rem;
    }
    .subject-code {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.1em;
        color: var(--ink); opacity: 0.4; margin-bottom: 2px;
    }
    .subject-name {
        font-family: 'Fraunces', serif; font-size: 1.15rem; font-weight: 600; color: var(--ink);
    }

    /* Coeff visuel */
    .coeff-wrap {
        display: flex; align-items: center; justify-content: center;
        width: 36px; height: 36px; border-radius: 8px;
        border: 1.5px solid var(--line); flex-shrink: 0;
    }
    .coeff-value {
        font-family: 'JetBrains Mono', monospace; font-size: 14px; font-weight: 700;
    }
    .coeff-label {
        font-family: 'JetBrains Mono', monospace; font-size: 8px;
        opacity: 0.5; letter-spacing: 0.05em; text-align: center; line-height: 1;
    }

    .subject-meta {
        display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
        margin-top: 0.6rem;
    }
    .cycle-chip {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        padding: 2px 8px; border-radius: 4px;
        background: rgba(42,63,126,0.08); color: var(--sidebar-soft);
        text-transform: uppercase;
    }
    .cycle-chip.mat  { background: rgba(251,191,36,0.15); color: #92400E; }
    .cycle-chip.prim { background: rgba(74,222,128,0.12); color: #166534; }
    .cycle-chip.col  { background: rgba(99,102,241,0.12); color: #3730A3; }
    .cycle-chip.lyc  { background: rgba(239,68,68,0.1);   color: #991B1B; }

    .subject-usage {
        font-size: 0.8rem; color: var(--ink); opacity: 0.45; margin-top: 0.5rem;
        font-family: 'JetBrains Mono', monospace;
    }

    .subject-card-footer {
        padding: 0.65rem 1.25rem; border-top: 1px solid var(--line);
        display: flex; align-items: center; gap: 0.4rem; justify-content: flex-end;
    }
    .btn-card {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 0.3rem 0.65rem; border-radius: 6px;
        font-size: 0.8rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.12s;
    }
    .btn-card svg { width: 13px; height: 13px; }
    .btn-edit-card   { background: rgba(42,63,126,0.08);  color: var(--sidebar-soft); }
    .btn-edit-card:hover { background: rgba(42,63,126,0.16); }
    .btn-delete-card { background: rgba(224,92,58,0.08);  color: var(--accent-red); }
    .btn-delete-card:hover { background: rgba(224,92,58,0.16); }

    /* Cycles checkbox group */
    .cycles-group { display: flex; gap: 0.4rem; flex-wrap: wrap; }
    .cycle-check-btn {
        padding: 3px 10px; border-radius: 5px;
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; cursor: pointer;
        border: 1.5px solid var(--line); background: var(--paper); color: var(--ink);
        transition: all 0.12s;
    }
    .cycle-check-btn.sel-mat  { border-color: #92400E; background: rgba(251,191,36,0.15); color: #92400E; }
    .cycle-check-btn.sel-prim { border-color: #166534; background: rgba(74,222,128,0.12); color: #166534; }
    .cycle-check-btn.sel-col  { border-color: #3730A3; background: rgba(99,102,241,0.12); color: #3730A3; }
    .cycle-check-btn.sel-lyc  { border-color: #991B1B; background: rgba(239,68,68,0.1);   color: #991B1B; }

    /* Modal édition */
    .edit-overlay {
        position: fixed; inset: 0; z-index: 50;
        background: rgba(0,0,0,0.35);
        display: flex; align-items: center; justify-content: center; padding: 1rem;
        animation: fadeIn 0.15s ease;
    }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    .edit-panel {
        background: var(--paper-raised); border-radius: 14px; border: 1px solid var(--line);
        padding: 1.75rem; max-width: 500px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        animation: slideUp 0.15s ease;
    }
    @keyframes slideUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    .edit-panel-title {
        font-family: 'Fraunces', serif; font-size: 1.15rem; font-weight: 600;
        margin-bottom: 1.25rem; color: var(--ink);
    }
    .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .edit-grid .full { grid-column: 1 / -1; }
    .edit-actions { display: flex; justify-content: flex-end; gap: 0.65rem; padding-top: 1rem; border-top: 1px solid var(--line); margin-top: 1rem; }

    /* Modal suppression */
    .modal-overlay {
        position: fixed; inset: 0; z-index: 100;
        background: rgba(0,0,0,0.4);
        display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .modal {
        background: var(--paper-raised); border-radius: 14px; border: 1px solid var(--line);
        padding: 1.75rem; max-width: 380px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }
    .modal-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
    .modal-desc  { font-size: 0.875rem; color: var(--ink); opacity: 0.6; margin-bottom: 1.25rem; line-height: 1.5; }
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
    .empty-state { padding: 4rem 2rem; text-align: center; }
    .empty-state svg { width: 44px; height: 44px; margin: 0 auto 1rem; opacity: 0.2; }
    .empty-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; color: var(--ink); margin-bottom: 0.3rem; }
    .empty-sub   { font-size: 0.875rem; color: var(--ink); opacity: 0.45; }
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
                       placeholder="Rechercher une matière..." class="search-input">
            </div>
            <select wire:model.live="cycleFilter" class="filter-select">
                <option value="">Tous les cycles</option>
                <option value="Maternelle">Maternelle</option>
                <option value="Primaire">Primaire</option>
                <option value="Collège">Collège</option>
                <option value="Lycée">Lycée</option>
            </select>
        </div>
        <div class="toolbar-right">
            <button wire:click="$toggle('showForm')" class="btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle matière
            </button>
        </div>
    </div>

    {{-- Formulaire création --}}
    @if ($showForm)
        <div class="create-form">
            <div class="create-form-header">
                <span class="create-form-title">Nouvelle matière</span>
                <button wire:click="$set('showForm', false)"
                        style="background:none; border:none; cursor:pointer; opacity:0.4;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="create-form-body">
                <div class="form-field">
                    <label class="form-label">Nom de la matière</label>
                    <input wire:model="name" type="text" class="form-input" placeholder="Mathématiques">
                    @error('name') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Code (ex: MATH)</label>
                    <input wire:model="code" type="text" class="form-input" placeholder="MATH" maxlength="10">
                    @error('code') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Coefficient</label>
                    <select wire:model="coefficient" class="form-select-inp">
                        @for ($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Cycles concernés</label>
                    <select wire:model="cycles" class="form-select-inp">
                        <option value="">Tous</option>
                        <option value="Maternelle">Maternelle</option>
                        <option value="Primaire">Primaire</option>
                        <option value="Collège">Collège</option>
                        <option value="Lycée">Lycée</option>
                        <option value="Collège,Lycée">Collège + Lycée</option>
                        <option value="Primaire,Collège,Lycée">Primaire + Collège + Lycée</option>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Couleur</label>
                    <input wire:model="color" type="color" class="color-input">
                </div>
            </div>
            <div class="form-actions-row">
                <button wire:click="$set('showForm', false)" class="btn-cancel-sm">Annuler</button>
                <button wire:click="saveSubject" class="btn-save-sm">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Créer
                </button>
            </div>
        </div>
    @endif

    {{-- Grille des matières --}}
    @if ($subjects->isEmpty())
        <div class="empty-state">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <div class="empty-title">Aucune matière</div>
            <div class="empty-sub">Crée tes premières matières pour les assigner aux classes.</div>
        </div>
    @else
        <div class="subjects-grid">
            @foreach ($subjects as $subject)
                @php
                    $color     = $subject->color ?? '#1E2D5A';
                    $cycleList = $subject->cycles ? explode(',', $subject->cycles) : [];
                @endphp
                <div class="subject-card">
                    <div class="subject-card-top" style="background: {{ $color }}"></div>
                    <div class="subject-card-body">
                        <div class="subject-header">
                            <div>
                                <div class="subject-code">{{ $subject->code ?? '—' }}</div>
                                <div class="subject-name">{{ $subject->name }}</div>
                            </div>
                            <div style="text-align:center;">
                                <div class="coeff-wrap" style="border-color: {{ $color }}20;">
                                    <div>
                                        <div class="coeff-value" style="color: {{ $color }}">{{ $subject->coefficient }}</div>
                                        <div class="coeff-label">coeff</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Cycles --}}
                        @if (!empty($cycleList))
                            <div class="subject-meta">
                                @foreach ($cycleList as $c)
                                    @php $css = match(trim($c)) {
                                        'Maternelle' => 'mat',
                                        'Primaire'   => 'prim',
                                        'Collège'    => 'col',
                                        'Lycée'      => 'lyc',
                                        default      => '',
                                    }; @endphp
                                    <span class="cycle-chip {{ $css }}">{{ trim($c) }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="subject-meta">
                                <span class="cycle-chip">Tous cycles</span>
                            </div>
                        @endif

                        <div class="subject-usage">
                            {{ $subject->class_subjects_count }} affectation{{ $subject->class_subjects_count > 1 ? 's' : '' }} en classe
                        </div>
                    </div>

                    <div class="subject-card-footer">
                        <button wire:click="startEdit({{ $subject->id }})" class="btn-card btn-edit-card">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Modifier
                        </button>
                        <button wire:click="confirmDelete({{ $subject->id }})" class="btn-card btn-delete-card">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/>
                            </svg>
                            Supprimer
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Panel édition --}}
    @if ($editingId)
        <div class="edit-overlay">
            <div class="edit-panel">
                <div class="edit-panel-title">Modifier la matière</div>
                <div class="edit-grid">
                    <div class="form-field full">
                        <label class="form-label">Nom</label>
                        <input wire:model="editName" type="text" class="form-input">
                        @error('editName') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Code</label>
                        <input wire:model="editCode" type="text" class="form-input" maxlength="10">
                    </div>
                    <div class="form-field">
                        <label class="form-label">Coefficient</label>
                        <select wire:model="editCoefficient" class="form-select-inp">
                            @for ($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="form-field full">
                        <label class="form-label">Cycles concernés</label>
                        <select wire:model="editCycles" class="form-select-inp">
                            <option value="">Tous</option>
                            <option value="Maternelle">Maternelle</option>
                            <option value="Primaire">Primaire</option>
                            <option value="Collège">Collège</option>
                            <option value="Lycée">Lycée</option>
                            <option value="Collège,Lycée">Collège + Lycée</option>
                            <option value="Primaire,Collège,Lycée">Primaire + Collège + Lycée</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Couleur</label>
                        <input wire:model="editColor" type="color" class="color-input">
                    </div>
                </div>
                <div class="edit-actions">
                    <button wire:click="$set('editingId', null)" class="btn-cancel-sm">Annuler</button>
                    <button wire:click="saveEdit" class="btn-save-sm">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal suppression --}}
    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer cette matière ?</div>
                <div class="modal-desc">
                    La matière sera retirée de toutes les classes auxquelles elle est affectée. Les notes associées seront également supprimées.
                </div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId', null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteSubject" class="btn-modal-confirm">Oui, supprimer</button>
                </div>
            </div>
        </div>
    @endif

</div>