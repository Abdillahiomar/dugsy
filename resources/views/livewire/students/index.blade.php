<?php

use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\AcademicYearService;
use Livewire\Attributes\On;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $classFilter = '';

    public array $selected = [];
    public bool  $selectAll = false;
    public string $massAction = '';

    public function updatedSearch(): void       { $this->resetPage(); $this->selected = []; $this->selectAll = false; }
    public function updatedStatusFilter(): void { $this->resetPage(); $this->selected = []; $this->selectAll = false; }
    public function updatedClassFilter(): void  { $this->resetPage(); $this->selected = []; $this->selectAll = false; }

    #[On('academic-year-changed')]
    public function refresh(): void { }
    
    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selected = $this->getPageStudentIds();
        } else {
            $this->selected = [];
        }
    }

    public function updatedSelected(): void
    {
        $pageIds = $this->getPageStudentIds();
        $this->selectAll = !empty($pageIds) && empty(array_diff($pageIds, $this->selected));
    }

    private function getPageStudentIds(): array
    {
        $year = AcademicYearService::current();

        return Student::query()
            // ── Même filtre année ──
            ->when($year, fn ($q) =>
                $q->whereHas('schoolYears', fn ($q) =>
                    $q->where('academic_year_id', $year->id)
                )
            )
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name',  'like', "%{$this->search}%")
                    ->orWhere('matricule',  'like', "%{$this->search}%")
                )
            )
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->classFilter && $year, fn ($q) =>
                $q->whereHas('schoolYears', fn ($q) =>
                    $q->where('academic_year_id', $year->id)
                    ->where('school_class_id', $this->classFilter)
                )
            )
            ->paginate(15)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function applyMassAction(): void
    {
        if (empty($this->selected) || !$this->massAction) return;

        $status = match ($this->massAction) {
            'activate'   => 'active',
            'transfer'   => 'transferred',
            'drop'       => 'dropped',
            'graduate'   => 'graduated',
            default      => null,
        };

        if ($status) {
            Student::whereIn('id', $this->selected)->update(['status' => $status]);
        }

        $this->selected    = [];
        $this->selectAll   = false;
        $this->massAction  = '';
    }

    public function clearSelection(): void
    {
        $this->selected   = [];
        $this->selectAll  = false;
        $this->massAction = '';
    }

    public function with(): array
    {
        $year = AcademicYearService::current();

        $students = Student::query()
            // ── Filtrer UNIQUEMENT les élèves inscrits cette année ──
            ->when($year, fn ($q) =>
                $q->whereHas('schoolYears', fn ($q) =>
                    $q->where('academic_year_id', $year->id)
                )
            )
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name',  'like', "%{$this->search}%")
                    ->orWhere('matricule',  'like', "%{$this->search}%")
                )
            )
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->classFilter && $year, fn ($q) =>
                $q->whereHas('schoolYears', fn ($q) =>
                    $q->where('academic_year_id', $year->id)
                    ->where('school_class_id', $this->classFilter)
                )
            )
            ->with(['schoolYears' => fn ($q) =>
                $year
                    ? $q->where('academic_year_id', $year->id)->with('schoolClass.level')
                    : $q->latest('enrolled_at')->limit(1)->with('schoolClass.level')
            ])
            ->latest()
            ->paginate(15);

        $classes = $year
            ? SchoolClass::where('academic_year_id', $year->id)->with('level')->get()
            : collect();

        return compact('students', 'classes', 'year');
    }
}; ?>

<style>
    /* ── Toolbar ── */
    .page-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
    }
    .toolbar-left  { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
    .toolbar-right { display: flex; align-items: center; gap: 0.65rem; }

    .search-wrap { position: relative; }
    .search-wrap svg {
        position: absolute; left: 10px; top: 50%;
        transform: translateY(-50%);
        width: 15px; height: 15px;
        color: var(--ink); opacity: 0.35; pointer-events: none;
    }
    .search-input {
        padding: 0.45rem 0.75rem 0.45rem 2.1rem;
        border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper-raised);
        font-size: 0.875rem; font-family: 'Inter', sans-serif;
        color: var(--ink); width: 220px; outline: none;
        transition: border-color 0.15s;
    }
    .search-input:focus { border-color: var(--sidebar-soft); }

    .filter-select {
        padding: 0.45rem 0.75rem;
        border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper-raised);
        font-size: 0.875rem; font-family: 'Inter', sans-serif;
        color: var(--ink); outline: none; cursor: pointer;
        transition: border-color 0.15s;
    }
    .filter-select:focus { border-color: var(--sidebar-soft); }

    .btn-primary {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 0.45rem 1rem; border-radius: 8px;
        background-color: var(--sidebar); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; text-decoration: none;
        transition: background-color 0.15s;
    }
    .btn-primary:hover { background-color: var(--sidebar-soft); }
    .btn-primary svg   { width: 15px; height: 15px; }

    /* ── Barre de sélection masse ── */
    .mass-bar {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.7rem 1.25rem;
        background: var(--sidebar);
        border-radius: 10px;
        margin-bottom: 0.75rem;
        animation: slideDown 0.15s ease;
    }
    @keyframes slideDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

    .mass-bar-count {
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px; font-weight: 600; color: #FFFFFF;
        white-space: nowrap;
    }
    .mass-bar-count span {
        background: var(--accent); color: var(--sidebar);
        padding: 1px 7px; border-radius: 4px;
        margin-right: 4px;
    }
    .mass-select {
        padding: 0.35rem 0.65rem;
        border-radius: 6px; border: 1px solid rgba(255,255,255,0.2);
        background: rgba(255,255,255,0.1);
        font-size: 0.8125rem; font-family: 'Inter', sans-serif;
        color: #FFFFFF; outline: none; cursor: pointer;
        flex: 1; max-width: 200px;
    }
    .mass-select option { background: var(--sidebar); }
    .btn-mass-apply {
        padding: 0.35rem 0.875rem; border-radius: 6px;
        background: var(--accent); color: var(--sidebar);
        font-size: 0.8125rem; font-weight: 700; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer;
        transition: opacity 0.15s;
    }
    .btn-mass-apply:hover { opacity: 0.85; }
    .btn-mass-cancel {
        padding: 0.35rem 0.75rem; border-radius: 6px;
        background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.75);
        font-size: 0.8125rem; font-family: 'Inter', sans-serif;
        border: 1px solid rgba(255,255,255,0.15); cursor: pointer;
        transition: background 0.15s;
    }
    .btn-mass-cancel:hover { background: rgba(255,255,255,0.18); }

    /* ── Table ── */
    .table-wrap {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
    }
    table { width: 100%; border-collapse: collapse; }
    thead tr { border-bottom: 1px solid var(--line); background-color: var(--paper); }
    thead th {
        text-align: left; padding: 0.65rem 1rem;
        font-family: 'JetBrains Mono', monospace;
        font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em;
        color: var(--ink); opacity: 0.45; white-space: nowrap;
    }
    thead th:first-child { width: 40px; padding: 0.65rem 0 0.65rem 1.1rem; }
    thead th:last-child  { text-align: right; padding-right: 1rem; }

    tbody tr { border-bottom: 1px solid var(--line); transition: background-color 0.1s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background-color: rgba(30,45,90,0.03); }
    tbody tr.is-selected { background-color: rgba(30,45,90,0.06); }

    tbody td {
        padding: 0.7rem 1rem;
        font-size: 0.875rem; color: var(--ink); vertical-align: middle;
    }
    tbody td:first-child { padding: 0.7rem 0 0.7rem 1.1rem; width: 40px; }
    tbody td:last-child  { text-align: right; padding-right: 1rem; }

    /* Checkbox custom */
    .custom-check {
        width: 16px; height: 16px;
        border-radius: 4px; border: 1.5px solid var(--line);
        background: var(--paper-raised);
        cursor: pointer; appearance: none;
        transition: border-color 0.15s, background 0.15s;
        position: relative;
    }
    .custom-check:checked {
        background: var(--sidebar);
        border-color: var(--sidebar);
    }
    .custom-check:checked::after {
        content: '';
        position: absolute; top: 2px; left: 4.5px;
        width: 4px; height: 7px;
        border: 2px solid #FFFFFF;
        border-top: none; border-left: none;
        transform: rotate(45deg);
    }
    .custom-check:hover:not(:checked) { border-color: var(--sidebar-soft); }

    /* Avatar */
    .student-avatar {
        width: 30px; height: 30px; border-radius: 50%;
        background-color: rgba(42,63,126,0.1); color: var(--sidebar-soft);
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px; font-weight: 600;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .student-cell { display: flex; align-items: center; gap: 0.65rem; }
    .student-name   { font-weight: 600; line-height: 1.2; }
    .student-matric { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--ink); opacity: 0.4; }

    /* Badges */
    .badge {
        display: inline-block;
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        padding: 2px 8px; border-radius: 4px;
        text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;
    }
    .badge-active      { background: rgba(42,63,126,0.1);  color: var(--sidebar-soft); }
    .badge-transferred { background: rgba(232,168,56,0.15); color: #8A6010; }
    .badge-dropped     { background: rgba(224,92,58,0.12);  color: #C04020; }
    .badge-graduated   { background: rgba(30,120,80,0.12);  color: #1A6040; }

    /* Actions */
    .actions-cell { display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem; }
    .btn-action {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 0.3rem 0.65rem; border-radius: 6px;
        font-size: 0.8rem; font-weight: 600; font-family: 'Inter', sans-serif;
        text-decoration: none; cursor: pointer; border: none;
        transition: background 0.12s, color 0.12s;
        white-space: nowrap;
    }
    .btn-action svg { width: 13px; height: 13px; }
    .btn-see  { background: rgba(42,63,126,0.08);  color: var(--sidebar-soft); }
    .btn-see:hover  { background: rgba(42,63,126,0.16); }
    .btn-edit { background: rgba(232,168,56,0.12);  color: #8A6010; }
    .btn-edit:hover { background: rgba(232,168,56,0.22); }

    /* ── Pagination ── */
    .pagination-bar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.75rem 1.25rem;
        border-top: 1px solid var(--line);
    }
    .pagination-info {
        font-family: 'JetBrains Mono', monospace; font-size: 11px;
        color: var(--ink); opacity: 0.45;
    }
    .pagination-controls { display: flex; align-items: center; gap: 0.4rem; }
    .page-btn {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 0.35rem 0.75rem; border-radius: 7px;
        font-size: 0.8125rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: 1px solid var(--line); background: var(--paper-raised);
        color: var(--ink); cursor: pointer; text-decoration: none;
        transition: background 0.12s, border-color 0.12s;
    }
    .page-btn svg { width: 14px; height: 14px; }
    .page-btn:hover:not(:disabled) { background: var(--paper); border-color: var(--sidebar-soft); color: var(--sidebar-soft); }
    .page-btn:disabled { opacity: 0.35; cursor: default; }
    .page-current {
        padding: 0.35rem 0.75rem; border-radius: 7px;
        font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600;
        background: var(--sidebar); color: #FFFFFF;
        border: 1px solid var(--sidebar);
    }

    /* Empty state */
    .empty-state { padding: 4rem 2rem; text-align: center; }
    .empty-state-icon { width: 44px; height: 44px; margin: 0 auto 1rem; opacity: 0.2; }
    .empty-state-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; color: var(--ink); margin-bottom: 0.35rem; }
    .empty-state-sub { font-size: 0.875rem; color: var(--ink); opacity: 0.5; }
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
                    placeholder="Nom, prénom, matricule..." class="search-input">
            </div>

            <select wire:model.live="statusFilter" class="filter-select">
                <option value="">Tous les statuts</option>
                <option value="active">Inscrit</option>
                <option value="transferred">Transféré</option>
                <option value="graduated">Diplômé</option>
                <option value="dropped">Abandonné</option>
            </select>

            @if ($classes->isNotEmpty())
                <select wire:model.live="classFilter" class="filter-select">
                    <option value="">Toutes les classes</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="toolbar-right">
            <a href="{{ route('students.enroll') }}" class="btn-primary" wire:navigate>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvel élève
            </a>
        </div>
    </div>

    @if ($year)
    <div style="font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--ink);opacity:.5;margin-bottom:.75rem;">
        Année sélectionnée : {{ $year->label }} (ID: {{ $year->id }})
    </div>
@else
    <div style="font-size:11px;color:var(--accent-red);margin-bottom:.75rem;">
        ⚠ Aucune année résolue — vérifie qu'une année est active en base ou sélectionnée dans le switcher.
    </div>
@endif

    {{-- Barre de sélection en masse --}}
    @if (count($selected) > 0)
        <div class="mass-bar">
            <p class="mass-bar-count"><span>{{ count($selected) }}</span> sélectionné{{ count($selected) > 1 ? 's' : '' }}</p>

            <select wire:model="massAction" class="mass-select">
                <option value="">Action groupée...</option>
                <option value="activate">Marquer comme inscrits</option>
                <option value="transfer">Marquer comme transférés</option>
                <option value="graduate">Marquer comme diplômés</option>
                <option value="drop">Marquer comme abandons</option>
            </select>

            <button wire:click="applyMassAction" class="btn-mass-apply"
                    @if (!$massAction) disabled @endif>
                Appliquer
            </button>
            <button wire:click="clearSelection" class="btn-mass-cancel">Annuler</button>
        </div>
    @endif

    {{-- Table --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>
                        <input type="checkbox" class="custom-check"
                            wire:model.live="selectAll">
                    </th>
                    <th>Elève</th>
                    <th>Classe</th>
                    <th>Naissance</th>
                    <th>Genre</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    @php
                        $initials   = strtoupper(substr($student->first_name,0,1).substr($student->last_name,0,1));
                        $schoolYear = $student->schoolYears->first();
                        $className  = $schoolYear?->schoolClass?->name ?? '—';
                        $badgeClass = match($student->status) {
                            'active'      => 'badge-active',
                            'transferred' => 'badge-transferred',
                            'graduated'   => 'badge-graduated',
                            'dropped'     => 'badge-dropped',
                            default       => 'badge-active',
                        };
                        $statusLabel = match($student->status) {
                            'active', 'enrolled'      => 'Inscrit',
                            'transferred' => 'Transféré',
                            'graduated'   => 'Diplômé',
                            'dropped'     => 'Abandonné',
                            default       => $student->status,
                        };
                        $isSelected = in_array((string) $student->id, $this->selected);
                    @endphp
                    <tr class="{{ $isSelected ? 'is-selected' : '' }}">
                        <td>
                            <input type="checkbox" class="custom-check"
                                wire:model.live="selected"
                                value="{{ $student->id }}">
                        </td>
                        <td>
                            <div class="student-cell">
                                <div class="student-avatar">{{ $initials }}</div>
                                <div>
                                    <div class="student-name">{{ $student->fullName() }}</div>
                                    <div class="student-matric">{{ $student->matricule }}</div>
                                </div>
                            </div>
                        </td>
                        <td>@if ($className !== '—')
                                {{ $className }}
                                @if ($schoolYear)
                                    <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--ink);opacity:.4;margin-top:1px;">
                                        {{ $schoolYear->academicYear->label ?? '' }}
                                    </div>
                                @endif
                            @else
                                <span style="opacity:0.35">Non inscrit</span>
                            @endif
                        </td>
                        <td>
                            @if ($student->birth_date)
                                {{ $student->birth_date->format('d/m/Y') }}
                            @else
                                <span style="opacity:0.35">—</span>
                            @endif
                        </td>
                        <td>{{ $student->gender === 'M' ? 'Masculin' : ($student->gender === 'F' ? 'Féminin' : '—') }}</td>
                        <td>@php
                                    // Remplace la ligne $badgeClass et $statusLabel par :
                                    $ssyStatus   = $schoolYear?->status ?? $student->status;
                                    $badgeClass  = match($ssyStatus) {
                                        'active', 'enrolled'  => 'badge-active',
                                        'transferred'         => 'badge-transferred',
                                        'graduated'           => 'badge-graduated',
                                        'dropped', 'withdrawn'=> 'badge-dropped',
                                        default               => 'badge-active',
                                    };
                                    $statusLabel = match($ssyStatus) {
                                        'active', 'enrolled'  => 'Inscrit',
                                        'transferred'         => 'Transféré',
                                        'graduated'           => 'Diplômé',
                                        'dropped', 'withdrawn'=> 'Abandonné',
                                        default               => $ssyStatus,
                                    };
                                @endphp
                                
                             <span class="badge {{ $badgeClass }}">
                                {{ $statusLabel }}
                            </span>
                            </td>
                        <td>
                            <div class="actions-cell">
                                <a href="{{ route('students.show', $student) }}" class="btn-action btn-see" wire:navigate>
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Voir
                                </a>
                                <a href="{{ route('students.edit', $student) }}" class="btn-action btn-edit" wire:navigate>
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Modifier
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <div class="empty-state-title">Aucun élève trouvé</div>
                                <div class="empty-state-sub">Modifie tes filtres ou inscris un nouvel élève.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        <div class="pagination-bar">
            <span class="pagination-info">
                @if ($students->total() > 0)
                    {{ $students->firstItem() }}–{{ $students->lastItem() }} sur {{ $students->total() }} élève{{ $students->total() > 1 ? 's' : '' }}
                @else
                    0 élève
                @endif
            </span>

            <div class="pagination-controls">
                {{-- Précédent --}}
                @if ($students->onFirstPage())
                    <button class="page-btn" disabled>
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Précédent
                    </button>
                @else
                    <button wire:click="previousPage" class="page-btn">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Précédent
                    </button>
                @endif

                <span class="page-current">{{ $students->currentPage() }} / {{ $students->lastPage() }}</span>

                {{-- Suivant --}}
                @if ($students->hasMorePages())
                    <button wire:click="nextPage" class="page-btn">
                        Suivant
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                @else
                    <button class="page-btn" disabled>
                        Suivant
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

    </div>
</div>