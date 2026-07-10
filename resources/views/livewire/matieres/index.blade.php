<?php

use App\Models\Subject;
use App\Models\Level;
use App\Services\AcademicYearService;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $cycleFilter = '';

    // Formulaire création
    public bool   $showForm    = false;
    public string $name        = '';
    public string $code        = '';
    public string $coefficient = '1';
    public string $color       = '#2A3F7E';
    public string $cycle     = '';

    // Édition
    public ?int   $editingId       = null;
    public string $editName        = '';
    public string $editCode        = '';
    public string $editCoefficient = '';
    public string $editColor       = '';
    public string $editCycle       = '';

    // Suppression
    public ?int $confirmDeleteId = null;

    #[On('academic-year-changed')]
    public function refresh(): void {}

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:20',
            'coefficient' => 'required|numeric|min:0.5|max:20',
            'color'       => 'nullable|string|max:7',
            'cycle'       => 'nullable|string|max:50',
        ];
    }

    public function saveSubject(): void
    {
        $this->validate();

        Subject::create([
            'school_id'   => auth()->user()->school_id,
            'name'        => trim($this->name),
            'code'        => strtoupper(trim($this->code)) ?: null,
            'coefficient' => (float) $this->coefficient,
            'color'       => $this->color ?: null,
            'cycles'       => $this->cycle ?: null,
        ]);

        $this->reset('name', 'code', 'coefficient', 'color', 'cycle', 'showForm');
        $this->coefficient = '1';
        $this->color       = '#2A3F7E';
    }

    public function startEdit(int $subjectId): void
    {
        $subject = Subject::where('school_id', auth()->user()->school_id)
            ->find($subjectId);

        if (! $subject) return;

        $this->editingId       = $subject->id;
        $this->editName        = $subject->name;
        $this->editCode        = (string) ($subject->code ?? '');
        $this->editCoefficient = (string) ($subject->coefficient ?? '1');
        $this->editColor       = (string) ($subject->color ?? '#2A3F7E');
        $this->editCycle       = (string) ($subject->cycle ?? '');
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editName'        => 'required|string|max:100',
            'editCode'        => 'nullable|string|max:20',
            'editCoefficient' => 'required|numeric|min:0.5|max:20',
            'editColor'       => 'nullable|string|max:7',
            'editCycle'       => 'nullable|string|max:50',
        ]);

        Subject::where('id', $this->editingId)
            ->where('school_id', auth()->user()->school_id)
            ->update([
                'name'        => trim($this->editName),
                'code'        => strtoupper(trim($this->editCode)) ?: null,
                'coefficient' => (float) $this->editCoefficient,
                'color'       => $this->editColor ?: null,
                'cycles'       => $this->editCycle ?: null,
            ]);

        $this->editingId = null;
    }

    public function confirmDelete(int $subjectId): void
    {
        $this->confirmDeleteId = $subjectId;
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
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;

        $grouped = Subject::where('school_id', $schoolId)
            ->when($this->search, fn ($q) =>
                $q->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                )
            )
            ->when($this->cycleFilter, fn ($q) =>
                $q->where('cycles', $this->cycleFilter)
            )
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($s) => $s->cycle ?: 'Autre');

        // Ordre des cycles — collection PHP standard, pas Eloquent
        $cycleOrder = ['Maternelle', 'Primaire', 'Collège', 'Lycée', 'Autre'];

        $subjects = collect();

        foreach ($cycleOrder as $c) {
            if ($grouped->has($c)) {
                $subjects->put($c, $grouped->get($c));
            }
        }

        // Cycles hors liste (ajoutés à la fin)
        foreach ($grouped as $key => $items) {
            if (! in_array($key, $cycleOrder, true)) {
                $subjects->put($key, $items);
            }
        }

        $cycles = Level::where('school_id', $schoolId)
            ->whereNotNull('cycle')
            ->distinct()
            ->orderBy('cycle')
            ->pluck('cycle');

        return compact('subjects', 'cycles', 'year');
    }
}; ?>



<div>

<style>
    /* ── Toolbar ── */
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

    /* ── Formulaire création ── */
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
        display: grid; grid-template-columns: 2fr 1fr 1fr 1.5fr 0.7fr; gap: 1rem; align-items: end;
    }
    @media (max-width: 1000px) { .create-form-body { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 550px)  { .create-form-body { grid-template-columns: 1fr; } }
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
    .form-color {
        padding: 0.25rem; height: 38px; border-radius: 8px;
        border: 1px solid var(--line); background: var(--paper);
        width: 100%; cursor: pointer;
    }
    .form-error { font-size: 0.75rem; color: var(--accent-red); margin-top: 0.2rem; }
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
    .btn-save-sm svg { width: 15px; height: 15px; }

    /* ── Cycles ── */
    .cycle-section { margin-bottom: 2rem; }
    .cycle-section:last-child { margin-bottom: 0; }
    .cycle-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
    .cycle-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 12px; border-radius: 20px;
        font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.06em;
    }
    .cycle-maternelle { background: rgba(251,191,36,0.15);  color: #92400E; }
    .cycle-primaire   { background: rgba(74,222,128,0.12);  color: #166534; }
    .cycle-college    { background: rgba(99,102,241,0.12);  color: #3730A3; }
    .cycle-lycee      { background: rgba(239,68,68,0.1);    color: #991B1B; }
    .cycle-autre      { background: rgba(0,0,0,0.06);       color: var(--ink); opacity:0.6; }
    .cycle-count {
        font-family: 'JetBrains Mono', monospace; font-size: 11px;
        color: var(--ink); opacity: 0.4;
    }

    /* ── Grille de matières ── */
    .subjects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 1rem;
    }
    .subject-card {
        position: relative;
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .subject-card:hover { border-color: rgba(42,63,126,0.3); box-shadow: 0 2px 12px rgba(42,63,126,0.08); }

    .subject-color-bar { height: 4px; width: 100%; }

    .subject-card-header {
        padding: 1rem 1.25rem 0.75rem;
        border-bottom: 1px solid var(--line);
    }
    .subject-code {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.4;
        margin-bottom: 2px;
    }
    .subject-name {
        font-family: 'Fraunces', serif; font-size: 1.2rem; font-weight: 600; color: var(--ink);
        line-height: 1.15;
    }

    .subject-card-body { padding: 0.875rem 1.25rem; }
    .subject-stat-row {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 0.6rem;
    }
    .subject-stat-row:last-child { margin-bottom: 0; }
    .subject-stat-label { font-size: 0.8125rem; color: var(--ink); opacity: 0.5; }
    .subject-stat-value { font-size: 0.8125rem; font-weight: 600; color: var(--ink); }

    .coef-pill {
        display: inline-flex; align-items: center;
        padding: 2px 9px; border-radius: 20px;
        background: rgba(42,63,126,0.08); color: var(--sidebar-soft);
        font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600;
    }

    .subject-card-footer {
        display: flex; align-items: center; gap: 0.4rem;
        padding: 0.75rem 1.25rem; border-top: 1px solid var(--line);
    }
    .btn-card {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 7px;
        border: none; cursor: pointer; transition: background 0.12s; flex-shrink: 0;
    }
    .btn-card svg { width: 15px; height: 15px; }
    .btn-edit-card       { background: rgba(42,63,126,0.08);  color: var(--sidebar-soft); }
    .btn-edit-card:hover { background: rgba(42,63,126,0.16); }
    .btn-delete-card       { background: rgba(224,92,58,0.08);  color: var(--accent-red); }
    .btn-delete-card:hover { background: rgba(224,92,58,0.16); }

    /* ── Édition ── */
    .edit-overlay {
        position: fixed; inset: 0; z-index: 50;
        background: rgba(0,0,0,0.35);
        display: flex; align-items: center; justify-content: center; padding: 1rem;
        animation: fadeIn 0.15s ease;
    }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    .edit-panel {
        background: var(--paper-raised); border-radius: 14px; border: 1px solid var(--line);
        padding: 1.75rem; max-width: 480px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        animation: slideUp 0.15s ease;
    }
    @keyframes slideUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    .edit-panel-title {
        font-family: 'Fraunces', serif; font-size: 1.15rem; font-weight: 600;
        margin-bottom: 1.25rem; color: var(--ink);
    }
    .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
    .edit-grid .full { grid-column: 1 / -1; }
    .edit-actions {
        display: flex; justify-content: flex-end; gap: 0.65rem;
        padding-top: 1rem; border-top: 1px solid var(--line);
    }

    /* ── Modal suppression ── */
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

    /* ── Empty state ── */
    .global-empty { padding: 4rem 2rem; text-align: center; }
    .global-empty svg { width: 44px; height: 44px; margin: 0 auto 1rem; opacity: 0.2; }
    .global-empty-title {
        font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600;
        color: var(--ink); margin-bottom: 0.35rem;
    }
    .global-empty-sub { font-size: 0.875rem; color: var(--ink); opacity: 0.45; }
</style>

    {{-- ═══ Toolbar ═══ --}}
    <div class="page-toolbar">
        <div class="toolbar-left">
            <div class="search-wrap">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="text"
                       placeholder="Rechercher une matière..." class="search-input">
            </div>

            <select wire:model.live="cycleFilter" class="filter-select">
                <option value="">Tous les cycles</option>
                @foreach ($cycles as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>

        <div class="toolbar-right">
            @if ($year)
                <span style="font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:0.45;">
                    {{ $year->label }}
                </span>
            @endif
            <button wire:click="$toggle('showForm')" class="btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle matière
            </button>
        </div>
    </div>

    {{-- ═══ Formulaire de création ═══ --}}
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
                    <label class="form-label">Code</label>
                    <input wire:model="code" type="text" class="form-input" placeholder="MATH">
                    @error('code') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-field">
                    <label class="form-label">Coefficient</label>
                    <input wire:model="coefficient" type="number" step="0.5" min="0.5" max="20"
                           class="form-input" placeholder="4">
                    @error('coefficient') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-field">
                    <label class="form-label">Cycle</label>
                    <select wire:model="cycle" class="form-select-inp">
                        <option value="">— Tous les cycles —</option>
                        @foreach ($cycles as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                    @error('cycle') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-field">
                    <label class="form-label">Couleur</label>
                    <input wire:model="color" type="color" class="form-color">
                </div>
            </div>

            <div class="form-actions-row">
                <button wire:click="$set('showForm', false)" class="btn-cancel-sm">Annuler</button>
                <button wire:click="saveSubject" class="btn-save-sm">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Créer la matière
                </button>
            </div>
        </div>
    @endif

    {{-- ═══ Matières groupées par cycle ═══ --}}
    @if ($subjects->isEmpty())
        <div class="global-empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <div class="global-empty-title">Aucune matière trouvée</div>
            <div class="global-empty-sub">
                @if ($search || $cycleFilter)
                    Aucun résultat pour ces critères. Essaie une autre recherche.
                @else
                    Crée ta première matière pour commencer.
                @endif
            </div>
        </div>
    @else
        @foreach ($subjects as $cycleName => $cycleSubjects)
            @php
                $cycleCss = match($cycleName) {
                    'Maternelle' => 'cycle-maternelle',
                    'Primaire'   => 'cycle-primaire',
                    'Collège'    => 'cycle-college',
                    'Lycée'      => 'cycle-lycee',
                    default      => 'cycle-autre',
                };
            @endphp

            <div class="cycle-section">
                <div class="cycle-header">
                    <span class="cycle-badge {{ $cycleCss }}">{{ $cycleName }}</span>
                    <span class="cycle-count">
                        {{ $cycleSubjects->count() }} matière{{ $cycleSubjects->count() > 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="subjects-grid">
                    @foreach ($cycleSubjects as $subject)
                        <div class="subject-card" wire:key="subject-{{ $subject->id }}">
                            <div class="subject-color-bar"
                                 style="background: {{ $subject->color ?? 'var(--line)' }}"></div>

                            <div class="subject-card-header">
                                <div class="subject-code">{{ $subject->code ?? '—' }}</div>
                                <div class="subject-name">{{ $subject->name }}</div>
                            </div>

                            <div class="subject-card-body">
                                <div class="subject-stat-row">
                                    <span class="subject-stat-label">Coefficient</span>
                                    <span class="coef-pill">×{{ rtrim(rtrim(number_format($subject->coefficient, 1, ',', ''), '0'), ',') }}</span>
                                </div>
                                <div class="subject-stat-row">
                                    <span class="subject-stat-label">Cycle</span>
                                    <span class="subject-stat-value">{{ $subject->cycle ?? 'Tous' }}</span>
                                </div>
                            </div>

                            <div class="subject-card-footer">
                                <button wire:click="startEdit({{ $subject->id }})"
                                        class="btn-card btn-edit-card" title="Modifier la matière">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>

                                <button wire:click="confirmDelete({{ $subject->id }})"
                                        class="btn-card btn-delete-card" title="Supprimer la matière">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

    {{-- ═══ Panel édition ═══ --}}
    @if ($editingId)
        <div class="edit-overlay">
            <div class="edit-panel">
                <div class="edit-panel-title">Modifier la matière</div>

                <div class="edit-grid">
                    <div class="form-field full">
                        <label class="form-label">Nom de la matière</label>
                        <input wire:model="editName" type="text" class="form-input">
                        @error('editName') <span class="form-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-label">Code</label>
                        <input wire:model="editCode" type="text" class="form-input">
                        @error('editCode') <span class="form-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-label">Coefficient</label>
                        <input wire:model="editCoefficient" type="number" step="0.5" min="0.5" max="20"
                               class="form-input">
                        @error('editCoefficient') <span class="form-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-label">Cycle</label>
                        <select wire:model="editCycle" class="form-select-inp">
                            <option value="">— Tous les cycles —</option>
                            @foreach ($cycles as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">Couleur</label>
                        <input wire:model="editColor" type="color" class="form-color">
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

    {{-- ═══ Modal suppression ═══ --}}
    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer cette matière ?</div>
                <div class="modal-desc">
                    Toutes les notes et affectations liées à cette matière seront supprimées.
                    Cette action est irréversible.
                </div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId', null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteSubject" class="btn-modal-confirm">Oui, supprimer</button>
                </div>
            </div>
        </div>
    @endif

</div>