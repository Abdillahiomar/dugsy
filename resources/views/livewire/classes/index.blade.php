<?php

use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Services\AcademicYearService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Services\AccessService;

new class extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $cycleFilter = '';

    // Formulaire création
    public bool   $showForm       = false;
    public string $name           = '';
    public string $level_id       = '';
    public string $main_teacher_id = '';
    public string $capacity       = '';

    // Edition inline
    public ?int   $editingId          = null;
    public string $editName           = '';
    public string $editTeacherId      = '';
    public string $editCapacity       = '';

    // Suppression
    public ?int $confirmDeleteId = null;

    #[On('academic-year-changed')]
    public function refresh(): void { }

    public function saveClass(): void
    {
        $this->validate([
            'name'     => 'required|string|max:50',
            'level_id' => 'required|exists:levels,id',
            'capacity' => 'nullable|integer|min:1|max:200',
        ]);

        $year = AcademicYearService::current();
        if (! $year) return;

        SchoolClass::create([
            'school_id'        => auth()->user()->school_id,
            'academic_year_id' => $year->id,
            'level_id'         => $this->level_id,
            'name'             => strtoupper(trim($this->name)),
            'main_teacher_id'  => $this->main_teacher_id ?: null,
            'capacity'         => $this->capacity ?: null,
        ]);

        $this->reset('name', 'level_id', 'main_teacher_id', 'capacity', 'showForm');
    }

    public function startEdit(int $classId): void
    {
        $class = SchoolClass::find($classId);
        if (! $class) return;

        $this->editingId     = $classId;
        $this->editName      = $class->name;
        $this->editTeacherId = (string) ($class->main_teacher_id ?? '');
        $this->editCapacity  = (string) ($class->capacity ?? '');
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editName'     => 'required|string|max:50',
            'editCapacity' => 'nullable|integer|min:1|max:200',
        ]);

        SchoolClass::where('id', $this->editingId)->update([
            'name'            => strtoupper(trim($this->editName)),
            'main_teacher_id' => $this->editTeacherId ?: null,
            'capacity'        => $this->editCapacity ?: null,
        ]);

        $this->editingId = null;
    }

    public function confirmDelete(int $classId): void
    {
        $this->confirmDeleteId = $classId;
    }

    public function deleteClass(): void
    {
        if (! $this->confirmDeleteId) return;
        SchoolClass::where('id', $this->confirmDeleteId)
            ->where('school_id', auth()->user()->school_id)
            ->delete();
        $this->confirmDeleteId = null;
    }

    // À ajouter dans grades/index.blade.php, absences/index.blade.php, etc.




    public function with(): array
    {
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id; // ← manquait

        $classIds = AccessService::myClassIds();

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->when($classIds !== null, fn ($q) => $q->whereIn('id', $classIds))
            ->when($this->search, fn ($q) =>
                $q->where('name', 'like', "%{$this->search}%")
            )
            ->with(['level', 'mainTeacher.user', 'studentSchoolYears'])
            ->get()
            ->groupBy('level.cycle');

        // Ordre des cycles
        $cycleOrder = ['Maternelle', 'Primaire', 'Collège', 'Lycée'];
        $classes = collect($cycleOrder)
            ->filter(fn ($c) => $classes->has($c))
            ->mapWithKeys(fn ($c) => [$c => $classes[$c]->sortBy('level.order')])
            ->when(
                $classes->has(''),
                fn ($col) => $col->put('Autre', $classes->get('', collect()))
            );

        // Filtre cycle
        if ($this->cycleFilter) {
            $classes = $classes->filter(fn ($_, $k) => $k === $this->cycleFilter);
        }

        $levels = Level::where('school_id', $schoolId)
            ->orderBy('order')->get();

        $teachers = Staff::where('school_id', $schoolId)
            ->with('user')->get();

        $cycles = Level::where('school_id', $schoolId)
            ->whereNotNull('cycle')
            ->distinct()
            ->pluck('cycle');

        return compact('classes', 'levels', 'teachers', 'cycles', 'year');
    }
}; ?>

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
        display: grid; grid-template-columns: 2fr 2fr 2fr 1fr; gap: 1rem; align-items: end;
    }
    @media (max-width: 900px) { .create-form-body { grid-template-columns: 1fr 1fr; } }
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

    /* ── Cycles ── */
    .cycle-section { margin-bottom: 2rem; }
    .cycle-section:last-child { margin-bottom: 0; }

    .cycle-header {
        display: flex; align-items: center; gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
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

    /* ── Grille de classes ── */
    .classes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1rem;
    }

    .class-card {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .class-card:hover { border-color: rgba(42,63,126,0.3); box-shadow: 0 2px 12px rgba(42,63,126,0.08); }

    .class-card-header {
        padding: 1rem 1.25rem 0.75rem;
        border-bottom: 1px solid var(--line);
        display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;
    }
    .class-level {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.4;
        margin-bottom: 2px;
    }
    .class-name {
        font-family: 'Fraunces', serif; font-size: 1.35rem; font-weight: 600; color: var(--ink);
        line-height: 1.1;
    }
    .class-menu { position: relative; }
    .class-menu-btn {
        width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--line);
        background: var(--paper); display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: background 0.12s; flex-shrink: 0;
    }
    .class-menu-btn:hover { background: var(--paper-raised); border-color: var(--sidebar-soft); }
    .class-menu-btn svg { width: 14px; height: 14px; color: var(--ink); opacity: 0.5; }

    .class-card-body { padding: 0.875rem 1.25rem; }

    .class-stat-row {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 0.6rem;
    }
    .class-stat-row:last-child { margin-bottom: 0; }
    .class-stat-label { font-size: 0.8125rem; color: var(--ink); opacity: 0.5; }
    .class-stat-value { font-size: 0.8125rem; font-weight: 600; color: var(--ink); }

    /* Barre remplissage */
    .fill-bar-bg {
        height: 4px; border-radius: 2px; background: var(--line); margin-top: 0.5rem; overflow: hidden;
    }
    .fill-bar-fill { height: 100%; border-radius: 2px; transition: width 0.3s; }
    .fill-ok   { background: rgba(74,222,128,0.8); }
    .fill-warn { background: rgba(232,168,56,0.8); }
    .fill-full { background: rgba(239,68,68,0.7); }

    .class-card-footer {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 0.18rem ; /* ← réduit */
        border-radius: 6px;
        font-size: 0.75rem;       /* ← réduit */
        font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.12s;
        white-space: nowrap;      /* ← ajouté */
    }
   .btn-card {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px;
        border-radius: 7px;
        border: none; cursor: pointer; transition: background 0.12s;
        flex-shrink: 0;
    }
    .btn-card svg { width: 15px; height: 15px; }
    .btn-edit-card   { background: rgba(42,63,126,0.08);  color: var(--sidebar-soft); }
    .btn-edit-card:hover { background: rgba(42,63,126,0.16); }
    .btn-delete-card { background: rgba(224,92,58,0.08);  color: var(--accent-red); }
    .btn-delete-card:hover { background: rgba(224,92,58,0.16); }
    .btn-subjects-card { background: rgba(232,168,56,0.12); color: #8A6010; }
    .btn-subjects-card:hover { background: rgba(232,168,56,0.22); }

    /* ── Edition inline ── */
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
    .edit-actions { display: flex; justify-content: flex-end; gap: 0.65rem; padding-top: 1rem; border-top: 1px solid var(--line); }

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

    /* Empty state */
    .empty-cycle {
        padding: 2.5rem; text-align: center; border-radius: 12px;
        border: 1.5px dashed var(--line);
    }
    .empty-cycle p { font-size: 0.875rem; color: var(--ink); opacity: 0.4; }
    .global-empty { padding: 4rem 2rem; text-align: center; }
    .global-empty svg { width: 44px; height: 44px; margin: 0 auto 1rem; opacity: 0.2; }
    .global-empty-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; color: var(--ink); margin-bottom: 0.35rem; }
    .global-empty-sub   { font-size: 0.875rem; color: var(--ink); opacity: 0.45; }
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
                       placeholder="Rechercher une classe..." class="search-input">
            </div>

            <select wire:model.live="cycleFilter" class="filter-select">
                <option value="">Tous les cycles</option>
                @foreach ($cycles as $cycle)
                    <option value="{{ $cycle }}">{{ $cycle }}</option>
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
                Nouvelle classe
            </button>
        </div>
    </div>

    {{-- Formulaire de création --}}
    @if ($showForm)
        <div class="create-form">
            <div class="create-form-header">
                <span class="create-form-title">Nouvelle classe — {{ $year?->label }}</span>
                <button wire:click="$set('showForm', false)"
                        style="background:none; border:none; cursor:pointer; opacity:0.4;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="create-form-body">
                <div class="form-field">
                    <label class="form-label">Niveau</label>
                    <select wire:model="level_id" class="form-select-inp">
                        <option value="">— Sélectionner —</option>
                        @foreach ($levels->groupBy('cycle') as $cycle => $cycleLevels)
                            <optgroup label="{{ $cycle }}">
                                @foreach ($cycleLevels as $level)
                                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('level_id') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Nom / section (ex: A, B, Sciences)</label>
                    <input wire:model="name" type="text" class="form-input"
                           placeholder="A">
                    @error('name') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Professeur principal</label>
                    <select wire:model="main_teacher_id" class="form-select-inp">
                        <option value="">— Optionnel —</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Capacité max</label>
                    <input wire:model="capacity" type="number" class="form-input"
                           placeholder="35" min="1" max="200">
                    @error('capacity') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="form-actions-row">
                <button wire:click="$set('showForm', false)" class="btn-cancel-sm">Annuler</button>
                <button wire:click="saveClass" class="btn-save-sm">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Créer la classe
                </button>
            </div>
        </div>
    @endif

    {{-- Classes groupées par cycle --}}
    @if ($classes->isEmpty())
        <div class="global-empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <div class="global-empty-title">Aucune classe trouvée</div>
            <div class="global-empty-sub">
                @if ($year)
                    Crée ta première classe pour l'année {{ $year->label }}.
                @else
                    Aucune année académique active. Active une année d'abord.
                @endif
            </div>
        </div>
    @else
        @foreach ($classes as $cycle => $cycleClasses)
            @php
                $cycleCss = match($cycle) {
                    'Maternelle' => 'cycle-maternelle',
                    'Primaire'   => 'cycle-primaire',
                    'Collège'    => 'cycle-college',
                    'Lycée'      => 'cycle-lycee',
                    default      => 'cycle-autre',
                };
            @endphp
            <div class="cycle-section">
                <div class="cycle-header">
                    <span class="cycle-badge {{ $cycleCss }}">{{ $cycle }}</span>
                    <span class="cycle-count">{{ $cycleClasses->count() }} classe{{ $cycleClasses->count() > 1 ? 's' : '' }}</span>
                </div>

                @if ($cycleClasses->isEmpty())
                    <div class="empty-cycle"><p>Aucune classe dans ce cycle.</p></div>
                @else
                    <div class="classes-grid">
                        @foreach ($cycleClasses as $class)
                            @php
                                $enrolled  = $class->studentSchoolYears->count();
                                $capacity  = $class->capacity ?? 0;
                                $fillPct   = $capacity > 0 ? min(100, round(($enrolled / $capacity) * 100)) : 0;
                                $fillClass = $fillPct >= 100 ? 'fill-full' : ($fillPct >= 80 ? 'fill-warn' : 'fill-ok');
                            @endphp
                            <div class="class-card">
                                <div class="class-card-header">
                                    <div>
                                        <div class="class-level">{{ $class->level?->name }}</div>
                                        <div class="class-name">{{ $class->name }}</div>
                                    </div>
                                </div>
                                <div class="class-card-body">
                                    <div class="class-stat-row">
                                        <span class="class-stat-label">Elèves inscrits</span>
                                        <span class="class-stat-value">
                                            {{ $enrolled }}
                                            @if ($capacity > 0)
                                                / {{ $capacity }}
                                            @endif
                                        </span>
                                    </div>
                                    <div class="class-stat-row">
                                        <span class="class-stat-label">Prof. principal</span>
                                        <span class="class-stat-value">
                                            {{ $class->mainTeacher?->user?->name ?? '—' }}
                                        </span>
                                    </div>
                                    @if ($capacity > 0)
                                        <div class="fill-bar-bg">
                                            <div class="fill-bar-fill {{ $fillClass }}" style="width:{{ $fillPct }}%"></div>
                                        </div>
                                    @endif
                                </div>
                                <div class="class-card-footer">
                                    {{-- Matières --}}
                                    <a href="{{ route('classes.subjects', $class) }}"
                                    class="btn-card btn-subjects-card"
                                    title="Matières & Enseignants">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                    </a>

                                    {{-- Modifier --}}
                                    <button wire:click="startEdit({{ $class->id }})"
                                            class="btn-card btn-edit-card"
                                            title="Modifier la classe">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>

                                    {{-- Supprimer --}}
                                    <button wire:click="confirmDelete({{ $class->id }})"
                                            class="btn-card btn-delete-card"
                                            title="Supprimer la classe">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    {{-- Panel édition --}}
    @if ($editingId)
        <div class="edit-overlay">
            <div class="edit-panel">
                <div class="edit-panel-title">Modifier la classe</div>
                <div class="edit-grid">
                    <div class="form-field full">
                        <label class="form-label">Nom / section</label>
                        <input wire:model="editName" type="text" class="form-input">
                        @error('editName') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Professeur principal</label>
                        <select wire:model="editTeacherId" class="form-select-inp">
                            <option value="">— Optionnel —</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Capacité max</label>
                        <input wire:model="editCapacity" type="number" class="form-input" min="1" max="200">
                        @error('editCapacity') <span class="form-error">{{ $message }}</span> @enderror
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
                <div class="modal-title">Supprimer cette classe ?</div>
                <div class="modal-desc">
                    Tous les élèves inscrits dans cette classe seront désinscrits. Cette action est irréversible.
                </div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId', null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteClass" class="btn-modal-confirm">Oui, supprimer</button>
                </div>
            </div>
        </div>
    @endif

</div>