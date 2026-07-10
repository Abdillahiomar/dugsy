<?php

use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use App\Services\AccessService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    // Sélection
    #[Url]
    public string $classId   = '';
    #[Url]
    public string $subjectId = '';
    #[Url]
    public string $period    = '';

    // Formulaire évaluation
    public bool   $showEvalForm  = false;
    public string $evalType      = 'devoir';
    public string $evalDate      = '';
    public int    $evalMaxScore  = 20;
    public string $evalId        = ''; // évaluation sélectionnée

    // Grille de notes [student_school_year_id => score]
    public array $scores = [];

    public bool  $saved  = false;

    public function mount(): void
    {
        $this->evalDate = now()->format('Y-m-d');
        $this->period   = $this->period ?: 'Trimestre 1';
    }

    #[On('academic-year-changed')]
    public function refresh(): void
    {
        $this->classId   = '';
        $this->subjectId = '';
        $this->evalId    = '';
        $this->scores    = [];
    }

    public function updatedClassId(): void
    {
        $this->subjectId = '';
        $this->evalId    = '';
        $this->scores    = [];
    }

    public function updatedSubjectId(): void
    {
        $this->evalId = '';
        $this->scores = [];
        $this->loadExistingScores();
    }

    public function updatedEvalId(): void
    {
        $this->scores = [];
        $this->loadExistingScores();
    }

    public function updatedPeriod(): void
    {
        $this->evalId = '';
        $this->scores = [];
    }

    private function loadExistingScores(): void
    {
        if (! $this->evalId) return;

        $existing = Grade::where('evaluation_id', $this->evalId)->get();
        foreach ($existing as $g) {
            $this->scores[(string) $g->student_school_year_id] = (string) $g->score;
        }
    }

    public function createEvaluation(): void
    {
        $this->validate([
            'evalType'     => 'required|string',
            'evalDate'     => 'required|date',
            'evalMaxScore' => 'required|integer|min:1|max:100',
            'classId'      => 'required|exists:school_classes,id',
            'subjectId'    => 'required|exists:subjects,id',
        ]);

        $eval = Evaluation::create([
            'school_class_id' => $this->classId,
            'subject_id'      => $this->subjectId,
            'type'            => $this->evalType,
            'period'          => $this->period,
            'date'            => $this->evalDate,
            'max_score'       => $this->evalMaxScore,
        ]);

        $this->evalId       = (string) $eval->id;
        $this->showEvalForm = false;
        $this->scores       = [];
    }

    private function isGradingOpen(): bool
    {
        // Admin et directeur peuvent toujours saisir
        if (auth()->user()->hasAnyRole(['admin', 'directeur'])) {
            return true;
        }

        $year = AcademicYearService::current();

        $gradingPeriod = \App\Models\GradingPeriod::where('school_id', auth()->user()->school_id)
            ->where('academic_year_id', $year?->id)
            ->where('period', $this->period)
            ->first();

        // Pas de config = ouvert par défaut
        if (! $gradingPeriod) return true;

        return $gradingPeriod->is_open
            && now()->between($gradingPeriod->open_from, $gradingPeriod->open_until);
    }

    public function saveGrades(): void
    {
        // Vérifier que l'enseignant a accès à cette classe
        if (! AccessService::canManageClass((int) $this->classId)) {
            abort(403, 'Accès non autorisé à cette classe.');
        }
        
        if (! $this->evalId) return;

        $eval = Evaluation::find($this->evalId);
        if (! $eval) return;

        foreach ($this->scores as $ssyId => $score) {
            if ($score === '' || $score === null) {
                // Supprimer la note si vidée
                Grade::where('student_school_year_id', $ssyId)
                    ->where('evaluation_id', $this->evalId)
                    ->delete();
                continue;
            }

            $scoreFloat = (float) str_replace(',', '.', $score);
            $scoreFloat = max(0, min($eval->max_score, $scoreFloat));

            Grade::updateOrCreate(
                [
                    'student_school_year_id' => $ssyId,
                    'evaluation_id'          => $this->evalId,
                ],
                ['score' => $scoreFloat]
            );
        }

        $this->saved = true;
    }
    // À ajouter dans grades/index.blade.php, absences/index.blade.php, etc.

    /**
     * Retourne les IDs des classes assignées à l'enseignant connecté.
     * Si admin/directeur → toutes les classes.
     */
    private function getMyClassIds(): ?array
    {
        $user = auth()->user();

        // Admin et directeur voient tout
        if ($user->hasAnyRole(['admin', 'directeur', 'comptable', 'surveillant'])) {
            return null; // null = pas de filtre
        }

        // Enseignant → ses classes via ClassSubjectTeacher
        $staff = $user->staff;
        if (! $staff) return []; // aucune classe

        return \App\Models\ClassSubjectTeacher::where('staff_id', $staff->id)
            ->pluck('school_class_id')
            ->unique()
            ->values()
            ->toArray();
    }

    private function getMySubjectIds(): ?array
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'directeur'])) return null;

        $staff = $user->staff;
        if (! $staff) return [];

        return \App\Models\ClassSubjectTeacher::where('staff_id', $staff->id)
            ->pluck('subject_id')
            ->unique()
            ->values()
            ->toArray();
    }

    public function with(): array
    {
       
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;
         $config = \App\Services\GradingConfigService::get($schoolId);
        $myClassIds = $this->getMyClassIds();

        // 2. Types d'évaluation dynamiques (remplace la liste hardcodée)
        $evalTypes = $config->evaluation_types ?? ['devoir','controle','examen'];

        $gradingPeriodOpen = true;
        if (! auth()->user()->hasAnyRole(['admin','directeur'])) {
            $gp = \App\Models\GradingPeriod::where('school_id', $schoolId)
                ->where('academic_year_id', $year?->id)
                ->where('period', $this->period)
                ->first();
            $gradingPeriodOpen = $gp ? $gp->isCurrentlyOpen() : true;
        }

        // Filtrer les classes selon le rôle
        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->when($myClassIds !== null, fn ($q) => $q->whereIn('id', $myClassIds))
            ->with('level')
            ->get();

        // Filtrer les matières selon le rôle
        $subjects = collect();
        if ($this->classId) {
            $mySubjectIds = $this->getMySubjectIds();
            $subjects = Subject::whereHas('classSubjects', fn ($q) =>
                $q->where('school_class_id', $this->classId)
            )
            ->when($mySubjectIds !== null, fn ($q) => $q->whereIn('id', $mySubjectIds))
            ->orderBy('name')
            ->get();
        }

        // Évaluations existantes pour classe + matière + période
        $evaluations = collect();
        if ($this->classId && $this->subjectId && $this->period) {
            $evaluations = Evaluation::where('school_class_id', $this->classId)
                ->where('subject_id', $this->subjectId)
                ->where('period', $this->period)
                ->orderByDesc('date')
                ->get();
        }

        // Évaluation courante
        $currentEval = $this->evalId
            ? Evaluation::find($this->evalId)
            : null;

        // Élèves de la classe
        $students = collect();
        if ($this->classId) {
            $students = StudentSchoolYear::where('school_class_id', $this->classId)
                ->where('academic_year_id', $year?->id)
                ->with('student')
                ->get()
                ->sortBy('student.last_name');
        }

        // Stats de la grille courante
        $filledCount = collect($this->scores)->filter(fn ($s) => $s !== '')->count();
        $avgScore    = $filledCount > 0
            ? round(collect($this->scores)->filter(fn ($s) => $s !== '')->avg(), 2)
            : null;

        $periods = ['Trimestre 1', 'Trimestre 2', 'Trimestre 3'];

        return compact(
            'year', 'classes', 'subjects', 'evaluations',
            'currentEval', 'students', 'filledCount', 'avgScore', 'periods', 'config', 'evalTypes', 'gradingPeriodOpen'
        );
    }
}; ?>

<style>
    .page-toolbar { display:flex; align-items:center; gap:.75rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .select-inp {
        padding:.45rem .75rem; border-radius:8px; border:1px solid var(--line);
        background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif;
        color:var(--ink); outline:none; cursor:pointer;
        transition:border-color .15s;
    }
    .select-inp:focus { border-color:var(--sidebar-soft); }
    .select-inp:disabled { opacity:.4; cursor:default; }
    .toolbar-sep { width:1px; height:24px; background:var(--line); }

    /* Layout grille + sidebar */
    .grades-layout { display:grid; grid-template-columns:1fr 280px; gap:1.25rem; align-items:start; }
    @media(max-width:900px) { .grades-layout { grid-template-columns:1fr; } }

    /* Card */
    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .card-title { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .card-body { padding:1.25rem 1.5rem; }

    /* Éval form */
    .eval-form { background:var(--paper); border-radius:10px; border:1px solid var(--line); padding:1rem; margin-bottom:1rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-4px);} to{opacity:1;transform:translateY(0);} }
    .eval-form-grid { display:grid; grid-template-columns:2fr 1fr 1fr; gap:.75rem; align-items:end; }
    @media(max-width:600px) { .eval-form-grid { grid-template-columns:1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.3rem; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input { padding:.45rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; }
    .form-input:focus { border-color:var(--sidebar-soft); }
    .form-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:.875rem; }

    /* Éval cards */
    .eval-list { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1rem; }
    .eval-card {
        display:flex; align-items:center; gap:.75rem;
        padding:.65rem 1rem; border-radius:8px;
        border:1.5px solid var(--line); background:var(--paper);
        cursor:pointer; transition:all .12s;
    }
    .eval-card.selected { border-color:var(--sidebar); background:rgba(42,63,126,.05); }
    .eval-card-radio { width:14px; height:14px; border-radius:50%; border:2px solid var(--line); flex-shrink:0; transition:all .12s; }
    .eval-card.selected .eval-card-radio { border-color:var(--sidebar); background:var(--sidebar); }
    .eval-card-type { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 7px; border-radius:4px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); text-transform:uppercase; }
    .eval-card-date { font-size:.8rem; color:var(--ink); opacity:.5; margin-left:auto; font-family:'JetBrains Mono',monospace; }
    .eval-card-max { font-size:.8rem; color:var(--ink); opacity:.4; }

    /* Grille de notes */
    table { width:100%; border-collapse:collapse; }
    thead tr { background:var(--paper); border-bottom:1px solid var(--line); }
    thead th { padding:.6rem 1rem; text-align:left; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.45; }
    thead th:last-child { text-align:center; width:120px; }
    tbody tr { border-bottom:1px solid var(--line); transition:background .1s; }
    tbody tr:last-child { border-bottom:none; }
    tbody tr:hover { background:rgba(30,45,90,.02); }
    tbody td { padding:.6rem 1rem; font-size:.875rem; color:var(--ink); vertical-align:middle; }
    tbody td:last-child { text-align:center; }

    .student-cell { display:flex; align-items:center; gap:.65rem; }
    .s-avatar { width:28px; height:28px; border-radius:50%; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .s-name { font-weight:600; font-size:.875rem; }
    .s-matric { font-family:'JetBrains Mono',monospace; font-size:10px; opacity:.4; }

    /* Input note */
    .score-wrap { display:flex; align-items:center; gap:4px; justify-content:center; }
    .score-input {
        width:64px; text-align:center;
        padding:.4rem .5rem; border-radius:7px;
        border:1.5px solid var(--line); background:var(--paper-raised);
        font-family:'JetBrains Mono',monospace; font-size:14px; font-weight:600;
        color:var(--ink); outline:none;
        transition:border-color .15s, box-shadow .15s;
    }
    .score-input:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .score-input.good   { color:#166534; border-color:rgba(30,120,80,.3); }
    .score-input.mid    { color:#8A6010; border-color:rgba(232,168,56,.4); }
    .score-input.bad    { color:var(--accent-red); border-color:rgba(224,92,58,.3); }
    .score-max { font-family:'JetBrains Mono',monospace; font-size:11px; opacity:.35; }

    /* Stats sidebar */
    .stat-row { display:flex; justify-content:space-between; align-items:center; padding:.5rem 0; border-bottom:1px solid var(--line); font-size:.875rem; }
    .stat-row:last-child { border-bottom:none; }
    .stat-label { color:var(--ink); opacity:.55; }
    .stat-value { font-family:'JetBrains Mono',monospace; font-weight:700; color:var(--ink); }

    /* Buttons */
    .btn-primary { display:inline-flex; align-items:center; gap:5px; padding:.45rem 1rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-primary:hover { background:var(--sidebar-soft); }
    .btn-primary svg { width:15px; height:15px; }
    .btn-secondary { display:inline-flex; align-items:center; gap:5px; padding:.45rem .875rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-secondary:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .btn-sm-save { padding:.35rem .875rem; border-radius:6px; background:var(--sidebar); color:#FFFFFF; font-size:.8125rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-sm-cancel { padding:.35rem .75rem; border-radius:6px; background:var(--paper-raised); color:var(--ink); font-size:.8125rem; border:1px solid var(--line); cursor:pointer; }

    .btn-save-grades {
        display:flex; align-items:center; justify-content:center; gap:6px;
        width:100%; padding:.6rem; border-radius:9px;
        background:#166534; color:#FFFFFF;
        font-size:.875rem; font-weight:700; font-family:'Inter',sans-serif;
        border:none; cursor:pointer; margin-top:1rem;
        transition:background .15s;
    }
    .btn-save-grades:hover { background:#14532d; }
    .btn-save-grades svg { width:15px; height:15px; }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }

    /* Empty state */
    .empty { padding:3rem 2rem; text-align:center; }
    .empty svg { width:40px; height:40px; margin:0 auto .875rem; opacity:.2; }
    .empty-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); margin-bottom:.3rem; }
    .empty-sub { font-size:.875rem; color:var(--ink); opacity:.45; }

    /* Période chips */
    .period-chips { display:flex; gap:.4rem; flex-wrap:wrap; }
    .period-chip {
        padding:.35rem .875rem; border-radius:7px;
        border:1.5px solid var(--line); background:var(--paper);
        font-size:.8125rem; font-weight:500; cursor:pointer;
        transition:all .12s; color:var(--ink);
    }
    .period-chip.active { border-color:var(--sidebar); background:rgba(42,63,126,.07); color:var(--sidebar); font-weight:600; }
</style>

<div>

    {{-- Toolbar --}}
    <div class="page-toolbar">
        {{-- Classe --}}
        <select wire:model.live="classId" class="select-inp">
            <option value="">— Sélectionner une classe —</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->level?->name }})</option>
            @endforeach
        </select>

        @if ($classId)
            {{-- Matière --}}
            <select wire:model.live="subjectId" class="select-inp">
                <option value="">— Matière —</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                @endforeach
            </select>
        @endif

        @if ($classId && $subjectId)
            <div class="toolbar-sep"></div>
            {{-- Période --}}
            <div class="period-chips">
                @foreach ($periods as $p)
                    <button type="button"
                            wire:click="$set('period','{{ $p }}')"
                            class="period-chip {{ $period === $p ? 'active' : '' }}">
                        {{ $p }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Remplace la liste hardcodée --}}
        <select wire:model="evalType" class="form-input">
            @foreach ($evalTypes as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>


    @if ($saved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Notes enregistrées.
        </div>
    @endif

    @if (! $classId || ! $subjectId)
        <div class="empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <div class="empty-title">Sélectionne une classe et une matière</div>
            <div class="empty-sub">Puis choisie ou crée une évaluation pour saisir les notes.</div>
        </div>
    @else

        <div class="grades-layout">

            {{-- Colonne principale --}}
            <div>

                {{-- Évaluations --}}
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Évaluation — {{ $period }}</span>
                        <button wire:click="$toggle('showEvalForm')" class="btn-secondary">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Nouvelle évaluation
                        </button>
                    </div>
                    <div class="card-body">

                        {{-- Formulaire nouvelle évaluation --}}
                        @if ($showEvalForm)
                            <div class="eval-form">
                                <div class="eval-form-grid">
                                    <div class="form-field">
                                        <label class="form-label">Type</label>
                                        <select wire:model="evalType" class="form-input">
                                            <option value="devoir">Devoir</option>
                                            <option value="controle">Contrôle</option>
                                            <option value="examen">Examen</option>
                                            <option value="interrogation">Interrogation</option>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Date</label>
                                        <input wire:model="evalDate" type="date" class="form-input">
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Note max</label>
                                        <input wire:model="evalMaxScore" type="number" min="1" max="100" class="form-input">
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button wire:click="$set('showEvalForm',false)" class="btn-sm-cancel">Annuler</button>
                                    <button wire:click="createEvaluation" class="btn-sm-save">Créer</button>
                                </div>
                            </div>
                        @endif

                        {{-- Liste des évaluations --}}
                        @if ($evaluations->isEmpty())
                            <p style="font-size:.875rem;color:var(--ink);opacity:.45;text-align:center;padding:1rem 0;">
                                Aucune évaluation pour ce trimestre. Crée-en une ci-dessus.
                            </p>
                        @else
                            <div class="eval-list">
                                @foreach ($evaluations as $eval)
                                    <div wire:click="$set('evalId','{{ $eval->id }}')"
                                         class="eval-card {{ $evalId == $eval->id ? 'selected' : '' }}">
                                        <div class="eval-card-radio"></div>
                                        <span class="eval-card-type">{{ $eval->type }}</span>
                                        <span style="font-size:.875rem;font-weight:500;">
                                            {{ ucfirst($eval->type) }} du {{ $eval->date?->format('d/m/Y') }}
                                        </span>
                                        <span class="eval-card-max">/ {{ $eval->max_score }}</span>
                                        <span class="eval-card-date">
                                            {{ Grade::where('evaluation_id',$eval->id)->count() }} notes
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>


                @if (! $gradingPeriodOpen)
                    <div style="padding:1.25rem;border-radius:10px;background:rgba(224,92,58,.08);border:1px solid rgba(224,92,58,.2);color:var(--accent-red);font-weight:600;margin-bottom:1rem;">
                        🔒 La saisie des notes pour {{ $period }} est fermée.
                        Contactez le directeur pour plus d'information.
                    </div>
                @endif

                @if ($evalId && $currentEval && $gradingPeriodOpen)
                    {{-- grille de saisie --}}
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">
                                Saisie des notes — {{ ucfirst($currentEval->type) }} / {{ $currentEval->max_score }}
                            </span>
                            <span style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--ink);opacity:.4;">
                                {{ $students->count() }} élèves
                            </span>
                        </div>

                        @if ($students->isEmpty())
                            <div class="empty"><div class="empty-sub">Aucun élève inscrit dans cette classe.</div></div>
                        @else
                            <table>
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th style="text-align:center;">Note / {{ $currentEval->max_score }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $ssy)
                                        @php
                                            $score     = $this->scores[(string) $ssy->id] ?? '';
                                            $scoreFloat = $score !== '' ? (float) $score : null;
                                            $max        = $currentEval->max_score;
                                            $colorClass = '';
                                            if ($scoreFloat !== null) {
                                                $pct = $max > 0 ? ($scoreFloat / $max) * 20 : 0;
                                                $colorClass = $pct >= 14 ? 'good' : ($pct >= 10 ? 'mid' : 'bad');
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="student-cell">
                                                    <div class="s-avatar">
                                                        {{ strtoupper(substr($ssy->student->first_name,0,1).substr($ssy->student->last_name,0,1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="s-name">{{ $ssy->student->fullName() }}</div>
                                                        <div class="s-matric">{{ $ssy->student->matricule }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="score-wrap">
                                                    <input
                                                        wire:model.live="scores.{{ $ssy->id }}"
                                                        type="number"
                                                        min="0"
                                                        max="{{ $currentEval->max_score }}"
                                                        step="0.25"
                                                        placeholder="—"
                                                        class="score-input {{ $colorClass }}"
                                                    >
                                                    <span class="score-max">/ {{ $currentEval->max_score }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endif
                

            </div>

            {{-- Sidebar stats --}}
            <div style="position:sticky;top:1.5rem;">

                @if ($evalId && $currentEval)
                    {{-- Stats de la grille --}}
                    <div class="card" style="margin-bottom:1rem;">
                        <div class="card-header"><span class="card-title">Statistiques</span></div>
                        <div class="card-body" style="padding:.875rem 1.25rem;">
                            <div class="stat-row">
                                <span class="stat-label">Élèves notés</span>
                                <span class="stat-value">{{ $filledCount }} / {{ $students->count() }}</span>
                            </div>
                            @if ($avgScore !== null)
                                <div class="stat-row">
                                    <span class="stat-label">Moyenne classe</span>
                                    <span class="stat-value" style="color:{{ $avgScore >= 10 ? '#166534' : 'var(--accent-red)' }}">
                                        {{ $avgScore }} / {{ $currentEval->max_score }}
                                    </span>
                                </div>
                                @php
                                    $filled = collect($this->scores)->filter(fn($s)=>$s!=='')->map(fn($s)=>(float)$s);
                                @endphp
                                <div class="stat-row">
                                    <span class="stat-label">Note max</span>
                                    <span class="stat-value">{{ $filled->max() ?? '—' }}</span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label">Note min</span>
                                    <span class="stat-value">{{ $filled->min() ?? '—' }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                        @if (! $this->isGradingOpen())
                            <div style="...alerte rouge...">
                                🔒 La saisie des notes pour {{ $period }} est fermée.
                                Contactez le directeur pour l'ouvrir.
                            </div>
                        @else
                            <button wire:click="saveGrades" ...>Enregistrer</button>
                        @endif
                @else
                    <div class="card">
                        <div class="card-body">
                            <p style="font-size:.8125rem;color:var(--ink);opacity:.45;text-align:center;padding:.5rem 0;">
                                Sélectionne une évaluation pour commencer la saisie.
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Lien bulletins --}}
                @if ($classId)
                    <div style="margin-top:.75rem;text-align:center;">
                        <a href="{{ route('bulletins.class', $classId) }}"
                           style="font-size:.8125rem;font-weight:600;color:var(--sidebar-soft);text-decoration:none;">
                            Générer les bulletins →
                        </a>
                    </div>
                @endif

            </div>
        </div>
    @endif
</div>
