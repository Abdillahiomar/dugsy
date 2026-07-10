<?php

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use App\Services\AccessService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    // ── Filtres ───────────────────────────────────────────────────
    #[Url] public string $classId = '';
    #[Url] public string $date    = '';
    #[Url] public string $mode    = 'day';

    // ── Séance ────────────────────────────────────────────────────
    public string $sessionStart = '';
    public string $sessionEnd   = '';
    public string $subjectId    = '';

    public array $predefinedSessions = [
        ['label' => 'Séance 1',  'start' => '07:30', 'end' => '09:30'],
        ['label' => 'Séance 2',  'start' => '09:30', 'end' => '11:30'],
        ['label' => 'Séance 3',  'start' => '11:30', 'end' => '13:00'],
        ['label' => 'Séance 4',  'start' => '14:00', 'end' => '16:00'],
        ['label' => 'Séance 5',  'start' => '16:00', 'end' => '18:00'],
        ['label' => 'Journée',   'start' => '',       'end' => ''],
    ];

    // ── Données de présence ───────────────────────────────────────
    public array $statuses       = [];
    public array $justifications = [];
    public array $lateMinutes    = [];  // [ssy_id => int] durée du retard
    public array $documents      = [];

    public bool   $saved     = false;
    #[Url] public string $weekStart = '';

    public function mount(): void
    {
        $this->date      = $this->date ?: now()->format('Y-m-d');
        $this->weekStart = $this->weekStart ?: now()->startOfWeek()->format('Y-m-d');
    }

    #[On('academic-year-changed')]
    public function refresh(): void
    {
        $this->classId   = '';
        $this->statuses  = [];
        $this->documents = [];
        $this->lateMinutes = [];
    }

    public function updatedClassId(): void
    {
        $this->statuses       = [];
        $this->justifications = [];
        $this->lateMinutes    = [];
        $this->documents      = [];
        $this->subjectId      = '';
        $this->initStatuses();
    }

    public function updatedDate(): void
    {
        $this->saved          = false;
        $this->statuses       = [];
        $this->justifications = [];
        $this->lateMinutes    = [];
        $this->documents      = [];
        $this->initStatuses();
    }

    // ── Helpers de rôle ──────────────────────────────────────────

    private function isTeacher(): bool
    {
        return auth()->user()->hasRole('enseignant')
            && ! auth()->user()->hasAnyRole(['admin','directeur','surveillant']);
    }

    private function canJustify(): bool
    {
        return ! $this->isTeacher();
    }

    // ── Initialisation ────────────────────────────────────────────

    private function initStatuses(): void
    {
        if (! $this->classId) return;

        $year     = AcademicYearService::current();
        $students = StudentSchoolYear::where('school_class_id', $this->classId)
            ->where('academic_year_id', $year?->id)
            ->get();

        foreach ($students as $ssy) {
            $key = (string) $ssy->id;
            if (! isset($this->statuses[$key])) {
                $this->statuses[$key]      = 'present';
                $this->justifications[$key] = '';
                $this->lateMinutes[$key]    = '';
            }
        }
    }

    public function applyPreset(string $start, string $end): void
    {
        $this->sessionStart = $start;
        $this->sessionEnd   = $end;
    }

    public function setAllPresent(): void
    {
        foreach ($this->statuses as $key => $_) {
            $this->statuses[$key]   = 'present';
            $this->lateMinutes[$key] = '';
        }
    }

    // ── Enregistrement ────────────────────────────────────────────

    public function saveAttendance(): void
    {
        if (! $this->classId) return;

        $sessionStart = $this->sessionStart ?: null;

        foreach ($this->statuses as $ssyId => $status) {

            $docPath = null;

            // Upload document (réservé aux non-enseignants)
            if ($this->canJustify() && isset($this->documents[$ssyId]) && $this->documents[$ssyId]) {
                $file = $this->documents[$ssyId];

                $existing = Attendance::where('student_school_year_id', $ssyId)
                    ->where('date', $this->date)
                    ->where(fn ($q) => $sessionStart
                        ? $q->where('session_start', $sessionStart)
                        : $q->whereNull('session_start')
                    )->first();

                if ($existing?->justification_path) {
                    Storage::disk('public')->delete($existing->justification_path);
                }

                $docPath = $file->store('attendances/documents', 'public');
            }

            $data = [
                'status'        => $status,
                'session_end'   => $this->sessionEnd ?: null,
                'subject_id'    => $this->subjectId ?: null,
                'recorded_by'   => auth()->user()->staff?->id,
                // Durée retard
                'late_minutes'  => $status === 'late' && ! empty($this->lateMinutes[$ssyId])
                    ? (int) $this->lateMinutes[$ssyId]
                    : null,
                // Justification : uniquement pour les admins/surveillants
                'justification' => $this->canJustify()
                    ? ($this->justifications[$ssyId] ?? null)
                    : null,
            ];

            if ($docPath) {
                $data['justification_path'] = $docPath;
            }

            Attendance::updateOrCreate(
                [
                    'student_school_year_id' => $ssyId,
                    'date'                   => $this->date,
                    'session_start'          => $sessionStart,
                ],
                $data
            );
        }

        $this->documents = [];
        $this->saved     = true;
    }

    public function deleteAttendance(int $id): void
    {
        $att = Attendance::find($id);
        if (! $att) return;

        if ($att->justification_path) {
            Storage::disk('public')->delete($att->justification_path);
        }

        $att->delete();
    }

    public function prevDay(): void
    {
        $this->date  = \Carbon\Carbon::parse($this->date)->subDay()->format('Y-m-d');
        $this->saved = false;
        $this->initStatuses();
    }

    public function nextDay(): void
    {
        $this->date  = \Carbon\Carbon::parse($this->date)->addDay()->format('Y-m-d');
        $this->saved = false;
        $this->initStatuses();
    }

    public function prevWeek(): void { $this->weekStart = \Carbon\Carbon::parse($this->weekStart)->subWeek()->format('Y-m-d'); }
    public function nextWeek(): void { $this->weekStart = \Carbon\Carbon::parse($this->weekStart)->addWeek()->format('Y-m-d'); }

    // ── With ──────────────────────────────────────────────────────

    public function with(): array
    {
        $year       = AcademicYearService::current();
        $schoolId   = auth()->user()->school_id;
        $isTeacher  = $this->isTeacher();
        $canJustify = $this->canJustify();

        // Classes filtrées selon le rôle
        $myClassIds = AccessService::myClassIds();
        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->when($myClassIds !== null, fn ($q) => $q->whereIn('id', $myClassIds))
            ->with('level')
            ->get();

        $subjects = collect();
        $students = collect();

        if ($this->classId) {
            $students = StudentSchoolYear::where('school_class_id', $this->classId)
                ->where('academic_year_id', $year?->id)
                ->with('student')
                ->get()
                ->sortBy('student.last_name');

            if (empty($this->statuses)) {
                foreach ($students as $ssy) {
                    $key = (string) $ssy->id;
                    $this->statuses[$key]      = 'present';
                    $this->justifications[$key] = '';
                    $this->lateMinutes[$key]    = '';
                }
            }

            // ── Matières filtrées selon le rôle ──────────────────────
            $mySubjectIds = AccessService::mySubjectIds();
            $subjects = Subject::whereHas('classSubjects', fn ($q) =>
                $q->where('school_class_id', $this->classId)
            )
            ->when($mySubjectIds !== null, fn ($q) => $q->whereIn('id', $mySubjectIds))
            ->orderBy('name')
            ->get();
        }

        // Absences du jour
        $dayAttendances = collect();
        if ($this->classId && $this->mode === 'day') {
            $ssyIds = $students->pluck('id');
            $query  = Attendance::whereIn('student_school_year_id', $ssyIds)
                ->where('date', $this->date)
                ->with(['subject'])
                ->orderBy('session_start');

            // ── Enseignant voit uniquement les absences de sa matière ─
            if ($isTeacher) {
                $mySubjectIds = AccessService::mySubjectIds() ?? [];
                if (! empty($mySubjectIds)) {
                    $query->whereIn('subject_id', $mySubjectIds);
                }
            }

            $dayAttendances = $query->get()->groupBy('student_school_year_id');
        }

        // Vue semaine
        $days    = collect();
        $weekData = collect();
        if ($this->mode === 'week' && $this->classId) {
            $start = \Carbon\Carbon::parse($this->weekStart);
            for ($i = 0; $i < 6; $i++) {
                $days->push($start->copy()->addDays($i));
            }

            $mySubjectIds = $isTeacher ? (AccessService::mySubjectIds() ?? []) : null;

            foreach ($students as $ssy) {
                $query = Attendance::where('student_school_year_id', $ssy->id)
                    ->whereBetween('date', [
                        $this->weekStart,
                        $start->copy()->addDays(5)->format('Y-m-d'),
                    ]);

                if ($isTeacher && ! empty($mySubjectIds)) {
                    $query->whereIn('subject_id', $mySubjectIds);
                }

                $weekAtts = $query->get()->groupBy(fn ($a) => $a->date->format('Y-m-d'));
                $weekData->push(['ssy' => $ssy, 'attendances' => $weekAtts]);
            }
        }

        // Stats
        $statsData = collect();
        if ($this->mode === 'stats' && $this->classId) {
            $mySubjectIds = $isTeacher ? (AccessService::mySubjectIds() ?? []) : null;

            $statsData = $students->map(function ($ssy) use ($mySubjectIds) {
                $query = Attendance::where('student_school_year_id', $ssy->id);

                // ── Enseignant : uniquement ses matières ──────────────
                if ($mySubjectIds !== null && ! empty($mySubjectIds)) {
                    $query->whereIn('subject_id', $mySubjectIds);
                }

                $all       = $query->get();
                $absTotal  = $all->where('status', 'absent')->count();

                $absHours = $all->where('status', 'absent')->sum(function ($a) {
                    if (! $a->session_start || ! $a->session_end) return 0;
                    return \Carbon\Carbon::parse($a->session_start)
                        ->diffInMinutes(\Carbon\Carbon::parse($a->session_end)) / 60;
                });

                // Total minutes de retard
                $totalLateMinutes = $all->where('status', 'late')
                    ->sum('late_minutes');

                return [
                    'ssy'               => $ssy,
                    'total'             => $all->count(),
                    'absent'            => $absTotal,
                    'late'              => $all->where('status', 'late')->count(),
                    'excused'           => $all->where('status', 'excused')->count(),
                    'present'           => $all->where('status', 'present')->count(),
                    'abs_hours'         => round($absHours, 1),
                    'total_late_minutes'=> $totalLateMinutes,
                    'absence_rate'      => $all->count() > 0
                        ? round(($absTotal / $all->count()) * 100)
                        : 0,
                ];
            })->sortByDesc('absent');
        }

        $absentCount  = collect($this->statuses)->filter(fn ($s) => $s === 'absent')->count();
        $lateCount    = collect($this->statuses)->filter(fn ($s) => $s === 'late')->count();
        $presentCount = collect($this->statuses)->filter(fn ($s) => $s === 'present')->count();

        return compact(
            'year', 'classes', 'students', 'subjects',
            'dayAttendances', 'days', 'weekData', 'statsData',
            'absentCount', 'lateCount', 'presentCount',
            'isTeacher', 'canJustify'
        );
    }
}; ?>

<style>
    .page-toolbar { display:flex; align-items:center; gap:.75rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .toolbar-left  { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; flex:1; }

    .select-inp { padding:.45rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; }
    .select-inp:focus { border-color:var(--sidebar-soft); }

    .mode-tabs { display:flex; background:var(--paper); border:1px solid var(--line); border-radius:9px; padding:3px; }
    .mode-tab { padding:.35rem .875rem; border-radius:6px; font-size:.8125rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); border:none; cursor:pointer; background:none; opacity:.55; transition:all .12s; }
    .mode-tab.active { background:var(--sidebar); color:#FFFFFF; opacity:1; }

    .date-nav { display:flex; align-items:center; gap:.5rem; }
    .date-nav-btn { width:30px; height:30px; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); display:flex; align-items:center; justify-content:center; cursor:pointer; }
    .date-nav-btn:hover { border-color:var(--sidebar-soft); }
    .date-nav-btn svg { width:14px; height:14px; }
    .date-display { font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:600; color:var(--ink); padding:.35rem .75rem; background:var(--paper); border:1px solid var(--line); border-radius:7px; white-space:nowrap; }
    .date-input { padding:.35rem .75rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'JetBrains Mono',monospace; color:var(--ink); outline:none; }

    .abs-layout { display:grid; grid-template-columns:1fr 260px; gap:1.25rem; align-items:start; }
    @media(max-width:900px) { .abs-layout { grid-template-columns:1fr; } }

    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .card-title { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .card-meta  { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.4; }

    /* Séance */
    .session-config { background:var(--paper); border-radius:10px; border:1px solid var(--line); padding:1rem 1.25rem; margin-bottom:1.25rem; }
    .session-config-title { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.75rem; }
    .preset-chips { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:.75rem; }
    .preset-chip { padding:.35rem .875rem; border-radius:7px; border:1.5px solid var(--line); background:var(--paper-raised); font-size:.8125rem; font-weight:500; cursor:pointer; transition:all .12s; color:var(--ink); font-family:'JetBrains Mono',monospace; white-space:nowrap; }
    .preset-chip:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .preset-chip.active { border-color:var(--sidebar); background:rgba(42,63,126,.07); color:var(--sidebar); font-weight:700; }
    .session-row { display:grid; grid-template-columns:1fr 1fr 2fr; gap:.75rem; align-items:end; }
    @media(max-width:600px) { .session-row { grid-template-columns:1fr 1fr; } }
    .form-field-sm { display:flex; flex-direction:column; gap:.3rem; }
    .form-label-sm { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input-sm { padding:.4rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'JetBrains Mono',monospace; color:var(--ink); outline:none; width:100%; }
    .form-input-sm:focus { border-color:var(--sidebar-soft); }
    .form-select-sm { padding:.4rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; }

    /* Table */
    table { width:100%; border-collapse:collapse; }
    thead tr { background:var(--paper); border-bottom:1px solid var(--line); }
    thead th { padding:.6rem 1rem; text-align:left; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.45; white-space:nowrap; }
    tbody tr { border-bottom:1px solid var(--line); transition:background .1s; }
    tbody tr:last-child { border-bottom:none; }
    tbody tr:hover { background:rgba(30,45,90,.02); }
    tbody td { padding:.75rem 1rem; font-size:.875rem; color:var(--ink); vertical-align:top; }

    .student-cell { display:flex; align-items:center; gap:.65rem; }
    .s-av { width:28px; height:28px; border-radius:50%; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .s-nm { font-weight:600; }
    .s-mt { font-family:'JetBrains Mono',monospace; font-size:10px; opacity:.4; }

    /* Statut buttons */
    .status-group { display:flex; gap:.3rem; flex-wrap:wrap; }
    .status-btn { padding:.3rem .6rem; border-radius:6px; font-size:.75rem; font-weight:600; font-family:'Inter',sans-serif; border:1.5px solid transparent; cursor:pointer; transition:all .12s; opacity:.5; white-space:nowrap; }
    .status-btn:hover { opacity:.8; }
    .status-btn.active { opacity:1; }
    .sb-present { background:rgba(30,120,80,.08); color:#166534; border-color:rgba(30,120,80,.2); }
    .sb-present.active { background:rgba(30,120,80,.15); border-color:#166534; }
    .sb-absent  { background:rgba(224,92,58,.08); color:var(--accent-red); border-color:rgba(224,92,58,.2); }
    .sb-absent.active  { background:rgba(224,92,58,.15); border-color:var(--accent-red); }
    .sb-late    { background:rgba(232,168,56,.1); color:#8A6010; border-color:rgba(232,168,56,.25); }
    .sb-late.active    { background:rgba(232,168,56,.2); border-color:#8A6010; }
    .sb-excused { background:rgba(42,63,126,.08); color:var(--sidebar-soft); border-color:rgba(42,63,126,.2); }
    .sb-excused.active { background:rgba(42,63,126,.15); border-color:var(--sidebar-soft); }

    /* Durée retard */
    .late-duration-wrap { display:flex; align-items:center; gap:.4rem; margin-top:.5rem; animation:fadeIn .12s ease; }
    .late-duration-input { width:70px; padding:.35rem .5rem; border-radius:6px; border:1.5px solid rgba(232,168,56,.4); background:rgba(232,168,56,.06); font-family:'JetBrains Mono',monospace; font-size:13px; font-weight:700; color:#8A6010; outline:none; text-align:center; }
    .late-duration-input:focus { border-color:#8A6010; }
    .late-duration-label { font-size:.8rem; color:#8A6010; font-weight:600; opacity:.7; }
    @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }

    /* Justificatif */
    .justif-block { margin-top:.5rem; display:flex; flex-direction:column; gap:.4rem; animation:fadeIn .12s ease; }
    .justif-input { padding:.35rem .6rem; border-radius:6px; border:1px solid var(--line); background:var(--paper); font-size:.8rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; }
    .justif-input:focus { border-color:var(--sidebar-soft); }
    .upload-row { display:flex; align-items:center; gap:.5rem; }
    .upload-btn { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; border:1px dashed var(--line); background:var(--paper); font-size:.75rem; font-family:'Inter',sans-serif; color:var(--ink); opacity:.6; cursor:pointer; position:relative; overflow:hidden; transition:all .12s; }
    .upload-btn:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); opacity:1; }
    .upload-btn input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .upload-btn svg { width:12px; height:12px; }
    .doc-preview { display:inline-flex; align-items:center; gap:4px; padding:.25rem .6rem; border-radius:5px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); font-size:.75rem; font-weight:600; }
    .doc-preview svg { width:12px; height:12px; }

    /* Bannière enseignant */
    .teacher-banner { display:flex; align-items:center; gap:.65rem; padding:.65rem 1rem; border-radius:8px; background:rgba(42,63,126,.05); border:1px solid rgba(42,63,126,.15); margin-bottom:1rem; font-size:.8125rem; color:var(--sidebar-soft); }
    .teacher-banner svg { width:15px; height:15px; flex-shrink:0; }

    /* Absences existantes */
    .existing-absences { margin-top:.5rem; display:flex; flex-direction:column; gap:.3rem; }
    .abs-record { display:flex; align-items:center; gap:.5rem; padding:.35rem .65rem; border-radius:6px; background:var(--paper); border:1px solid var(--line); flex-wrap:wrap; }
    .abs-record-time { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; color:var(--sidebar-soft); white-space:nowrap; }
    .abs-record-status { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:1px 6px; border-radius:3px; text-transform:uppercase; white-space:nowrap; }
    .abs-status-absent  { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .abs-status-late    { background:rgba(232,168,56,.12); color:#8A6010; }
    .abs-status-excused { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .abs-status-present { background:rgba(30,120,80,.08); color:#166634; }
    .abs-record-subj { font-size:.75rem; color:var(--ink); opacity:.55; }
    .abs-record-late { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700; color:#8A6010; }
    .abs-record-doc  { display:inline-flex; align-items:center; gap:3px; font-size:.75rem; color:var(--sidebar-soft); text-decoration:none; }
    .abs-record-doc:hover { text-decoration:underline; }
    .abs-record-doc svg { width:12px; height:12px; }
    .btn-del-record { margin-left:auto; background:none; border:none; cursor:pointer; color:var(--accent-red); opacity:.5; padding:2px 4px; border-radius:4px; }
    .btn-del-record:hover { opacity:1; background:rgba(224,92,58,.08); }
    .btn-del-record svg { width:12px; height:12px; }

    /* Sidebar */
    .stat-box { padding:.875rem 1rem; border-radius:10px; border:1px solid var(--line); background:var(--paper); margin-bottom:.65rem; text-align:center; }
    .stat-num { font-family:'JetBrains Mono',monospace; font-size:1.5rem; font-weight:700; }
    .stat-lbl { font-size:.75rem; color:var(--ink); opacity:.5; margin-top:2px; }
    .stat-present { color:#166534; } .stat-absent { color:var(--accent-red); } .stat-late { color:#8A6010; }

    .btn-all-present { display:flex; align-items:center; justify-content:center; gap:5px; width:100%; padding:.45rem; border-radius:8px; background:var(--paper-raised); border:1px solid var(--line); font-size:.8125rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; margin-bottom:.5rem; }
    .btn-all-present:hover { border-color:#166534; color:#166534; }
    .btn-save { display:flex; align-items:center; justify-content:center; gap:5px; width:100%; padding:.55rem; border-radius:8px; background:#166534; color:#FFFFFF; font-size:.875rem; font-weight:700; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-save:hover { background:#14532d; }
    .btn-save svg { width:15px; height:15px; }
    .session-badge { font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:700; padding:4px 10px; border-radius:6px; background:rgba(42,63,126,.1); color:var(--sidebar-soft); display:inline-block; width:100%; text-align:center; margin-bottom:.75rem; }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-4px);} to{opacity:1;transform:translateY(0);} }

    /* Empty */
    .empty { padding:3rem 2rem; text-align:center; }
    .empty svg { width:40px; height:40px; margin:0 auto .875rem; opacity:.2; }
    .empty-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .empty-sub   { font-size:.875rem; color:var(--ink); opacity:.45; margin-top:.3rem; }

    /* Semaine */
    .week-cell { width:80px; text-align:center; padding:.4rem; }
    .week-badge { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; font-size:10px; font-weight:700; }
    .wb-present { background:rgba(30,120,80,.12); color:#166534; }
    .wb-absent  { background:rgba(224,92,58,.12); color:var(--accent-red); }
    .wb-late    { background:rgba(232,168,56,.15); color:#8A6010; }
    .wb-excused { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .wb-none    { background:var(--line); color:var(--ink); opacity:.3; font-size:9px; }
    .week-multi { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:700; color:var(--accent-red); }

    /* Stats */
    .bar-bg   { height:6px; border-radius:3px; background:var(--line); overflow:hidden; margin-top:3px; }
    .bar-fill { height:100%; border-radius:3px; }
</style>

<div>

    {{-- Toolbar --}}
    <div class="page-toolbar">
        <div class="toolbar-left">
            <select wire:model.live="classId" class="select-inp">
                <option value="">— Sélectionner une classe —</option>
                @foreach ($classes->groupBy(fn($c) => $c->level?->cycle) as $cycle => $cycleClasses)
                    <optgroup label="{{ $cycle ?? 'Autre' }}">
                        @foreach ($cycleClasses as $class)
                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>

            <div class="mode-tabs">
                <button type="button" wire:click="$set('mode','day')"   class="mode-tab {{ $mode==='day'   ?'active':'' }}">Jour</button>
                <button type="button" wire:click="$set('mode','week')"  class="mode-tab {{ $mode==='week'  ?'active':'' }}">Semaine</button>
                <button type="button" wire:click="$set('mode','stats')" class="mode-tab {{ $mode==='stats' ?'active':'' }}">Statistiques</button>
            </div>
        </div>

        @if ($mode === 'day')
            <div class="date-nav">
                <button wire:click="prevDay" class="date-nav-btn"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
                <input wire:model.live="date" type="date" class="date-input">
                <button wire:click="nextDay" class="date-nav-btn"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
            </div>
        @elseif ($mode === 'week')
            <div class="date-nav">
                <button wire:click="prevWeek" class="date-nav-btn"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
                <span class="date-display">{{ \Carbon\Carbon::parse($weekStart)->locale('fr')->isoFormat('D MMM') }} — {{ \Carbon\Carbon::parse($weekStart)->addDays(5)->locale('fr')->isoFormat('D MMM YYYY') }}</span>
                <button wire:click="nextWeek" class="date-nav-btn"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
            </div>
        @endif
    </div>

    @if ($saved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Présences enregistrées — {{ \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM') }}
            @if ($sessionStart) · {{ substr($sessionStart,0,5) }}{{ $sessionEnd ? ' – '.substr($sessionEnd,0,5) : '' }} @endif
        </div>
    @endif

    @if (! $classId)
        <div class="empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <div class="empty-title">Sélectionne une classe</div>
            <div class="empty-sub">Pour enregistrer ou consulter les présences.</div>
        </div>

    @elseif ($mode === 'day')

    {{-- ══ MODE JOUR ══ --}}
    <div class="abs-layout">
        <div>

            {{-- Bannière enseignant --}}
            @if ($isTeacher)
                <div class="teacher-banner">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Tu peux marquer présent, absent ou retard (avec durée). La justification et les documents sont gérés par l'administration.
                </div>
            @endif

            {{-- Configuration de la séance --}}
            <div class="session-config">
                <div class="session-config-title">Séance concernée</div>
                <div class="preset-chips">
                    @foreach ($predefinedSessions as $preset)
                        <button type="button"
                                wire:click="applyPreset('{{ $preset['start'] }}','{{ $preset['end'] }}')"
                                class="preset-chip {{ $sessionStart === $preset['start'] && $sessionEnd === $preset['end'] ? 'active' : '' }}">
                            {{ $preset['label'] }}
                            @if ($preset['start'])
                                <span style="opacity:.6;font-size:.75em;"> {{ $preset['start'] }}-{{ $preset['end'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                <div class="session-row">
                    <div class="form-field-sm">
                        <label class="form-label-sm">Heure début</label>
                        <input wire:model="sessionStart" type="time" class="form-input-sm">
                    </div>
                    <div class="form-field-sm">
                        <label class="form-label-sm">Heure fin</label>
                        <input wire:model="sessionEnd" type="time" class="form-input-sm">
                    </div>
                    <div class="form-field-sm">
                        <label class="form-label-sm">Matière concernée</label>
                        <select wire:model="subjectId" class="form-select-sm">
                            <option value="">— Optionnel —</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Table des présences --}}
            <div class="card">
                <div class="card-header">
                    <span class="card-title">{{ \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</span>
                    <span class="card-meta">{{ $students->count() }} élèves</span>
                </div>

                @if ($students->isEmpty())
                    <div class="empty"><div class="empty-sub">Aucun élève inscrit dans cette classe.</div></div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th style="width:32%;">Élève</th>
                                <th>Statut</th>
                                <th>{{ $canJustify ? 'Justificatif & Document' : 'Durée retard' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $ssy)
                                @php
                                    $ssyKey  = (string) $ssy->id;
                                    $status  = $this->statuses[$ssyKey]   ?? 'present';
                                    $lateMins = $this->lateMinutes[$ssyKey] ?? '';
                                    $existing = $dayAttendances->get($ssy->id, collect());
                                @endphp
                                <tr>
                                    <td>
                                        <div class="student-cell">
                                            <div class="s-av">{{ strtoupper(substr($ssy->student->first_name,0,1).substr($ssy->student->last_name,0,1)) }}</div>
                                            <div>
                                                <div class="s-nm">{{ $ssy->student->fullName() }}</div>
                                                <div class="s-mt">{{ $ssy->student->matricule }}</div>
                                            </div>
                                        </div>

                                        {{-- Absences existantes du jour --}}
                                        @if ($existing->isNotEmpty())
                                            <div class="existing-absences">
                                                @foreach ($existing as $rec)
                                                    <div class="abs-record">
                                                        <span class="abs-record-time">{{ $rec->sessionLabel() }}</span>
                                                        <span class="abs-record-status abs-status-{{ $rec->status }}">
                                                            {{ match($rec->status) { 'absent'=>'Absent','late'=>'Retard','excused'=>'Justifié','present'=>'Présent',default=>$rec->status } }}
                                                        </span>
                                                        {{-- Durée du retard --}}
                                                        @if ($rec->status === 'late' && $rec->late_minutes)
                                                            <span class="abs-record-late">{{ $rec->late_minutes }}min</span>
                                                        @endif
                                                        @if ($rec->subject)
                                                            <span class="abs-record-subj">{{ $rec->subject->name }}</span>
                                                        @endif
                                                        @if ($rec->justification_path)
                                                            <a href="{{ $rec->justificationUrl() }}" target="_blank" class="abs-record-doc">
                                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                                {{ strtoupper($rec->justificationExtension()) }}
                                                            </a>
                                                        @elseif ($rec->justification)
                                                            <span style="font-size:.75rem;color:var(--ink);opacity:.5;font-style:italic;">{{ Str::limit($rec->justification,25) }}</span>
                                                        @endif
                                                        @if ($canJustify)
                                                            <button wire:click="deleteAttendance({{ $rec->id }})" class="btn-del-record">
                                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="status-group">
                                            {{-- Présent --}}
                                            <button type="button"
                                                    wire:click="$set('statuses.{{ $ssy->id }}','present')"
                                                    class="status-btn sb-present {{ $status==='present' ? 'active' : '' }}">
                                                Présent
                                            </button>
                                            {{-- Absent --}}
                                            <button type="button"
                                                    wire:click="$set('statuses.{{ $ssy->id }}','absent')"
                                                    class="status-btn sb-absent {{ $status==='absent' ? 'active' : '' }}">
                                                Absent
                                            </button>
                                            {{-- Retard --}}
                                            <button type="button"
                                                    wire:click="$set('statuses.{{ $ssy->id }}','late')"
                                                    class="status-btn sb-late {{ $status==='late' ? 'active' : '' }}">
                                                Retard
                                            </button>
                                            {{-- Justifié : uniquement admin/surveillant --}}
                                            @if ($canJustify)
                                                <button type="button"
                                                        wire:click="$set('statuses.{{ $ssy->id }}','excused')"
                                                        class="status-btn sb-excused {{ $status==='excused' ? 'active' : '' }}">
                                                    Justifié
                                                </button>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        {{-- ① DURÉE DU RETARD (visible pour tous si statut = late) --}}
                                        @if ($status === 'late')
                                            <div class="late-duration-wrap">
                                                <input wire:model="lateMinutes.{{ $ssy->id }}"
                                                       type="number"
                                                       min="1"
                                                       max="120"
                                                       class="late-duration-input"
                                                       placeholder="—">
                                                <span class="late-duration-label">min de retard</span>
                                            </div>
                                        @endif

                                        {{-- ② JUSTIFICATIF : uniquement admin/surveillant --}}
                                        @if ($canJustify && in_array($status, ['absent','excused','late']))
                                            <div class="justif-block">
                                                <input wire:model="justifications.{{ $ssy->id }}"
                                                       type="text"
                                                       class="justif-input"
                                                       placeholder="Motif (ex: certificat médical)...">
                                                <div class="upload-row">
                                                    <label class="upload-btn">
                                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                        Joindre un document
                                                        <input wire:model="documents.{{ $ssy->id }}"
                                                               type="file"
                                                               accept=".pdf,.jpg,.jpeg,.png,.webp">
                                                    </label>
                                                    @if (isset($this->documents[$ssyKey]) && $this->documents[$ssyKey])
                                                        <span class="doc-preview">
                                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            Document joint
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div style="position:sticky;top:1.5rem;">
            @if ($sessionStart)
                <div class="session-badge">🕐 {{ substr($sessionStart,0,5) }}@if ($sessionEnd) → {{ substr($sessionEnd,0,5) }} @endif</div>
            @endif

            <div class="stat-box">
                <div class="stat-num stat-present">{{ $presentCount }}</div>
                <div class="stat-lbl">Présents</div>
            </div>
            <div class="stat-box">
                <div class="stat-num stat-absent">{{ $absentCount }}</div>
                <div class="stat-lbl">Absents</div>
            </div>
            <div class="stat-box">
                <div class="stat-num stat-late">{{ $lateCount }}</div>
                <div class="stat-lbl">Retards</div>
            </div>

            @if ($students->isNotEmpty())
                <button wire:click="setAllPresent" class="btn-all-present">✓ Tous présents</button>
                <button wire:click="saveAttendance" class="btn-save">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    Enregistrer
                </button>
            @endif
        </div>
    </div>

    @elseif ($mode === 'week')

    {{-- ══ MODE SEMAINE ══ --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Vue semaine
                @if ($isTeacher) <span style="font-size:.8rem;font-weight:400;opacity:.6;">— vos matières uniquement</span> @endif
            </span>
        </div>
        @if ($students->isEmpty())
            <div class="empty"><div class="empty-sub">Aucun élève dans cette classe.</div></div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Élève</th>
                        @foreach ($days as $day)
                            <th class="week-cell" style="text-align:center;">
                                <div>{{ $day->locale('fr')->isoFormat('ddd') }}</div>
                                <div style="font-size:9px;opacity:.6;">{{ $day->format('d/m') }}</div>
                            </th>
                        @endforeach
                        <th style="text-align:center;">Abs.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weekData as $item)
                        <tr>
                            <td>
                                <div class="student-cell">
                                    <div class="s-av">{{ strtoupper(substr($item['ssy']->student->first_name,0,1).substr($item['ssy']->student->last_name,0,1)) }}</div>
                                    <div><div class="s-nm">{{ $item['ssy']->student->fullName() }}</div></div>
                                </div>
                            </td>
                            @foreach ($days as $day)
                                @php
                                    $dayKey  = $day->format('Y-m-d');
                                    $records = $item['attendances']->get($dayKey, collect());
                                    $absRecs = $records->whereIn('status', ['absent','late','excused']);
                                @endphp
                                <td class="week-cell">
                                    @if ($records->isEmpty())
                                        <span class="week-badge wb-none" style="font-size:9px;">·</span>
                                    @elseif ($absRecs->isEmpty())
                                        <span class="week-badge wb-present">✓</span>
                                    @elseif ($absRecs->count() > 1)
                                        <span class="week-multi" style="font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:700;color:var(--accent-red);">{{ $absRecs->count() }}×</span>
                                    @else
                                        @php $first = $absRecs->first(); @endphp
                                        <span class="week-badge wb-{{ $first->status }}" title="{{ $first->sessionLabel() }}{{ $first->late_minutes ? ' ('.$first->late_minutes.'min)' : '' }}">
                                            {{ match($first->status) { 'absent'=>'A','late'=>'R','excused'=>'J',default=>'?' } }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                            <td style="text-align:center;">
                                @php $totalAbs = $item['attendances']->flatten()->where('status','absent')->count(); @endphp
                                <span style="font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:700;color:{{ $totalAbs>0?'var(--accent-red)':'#166634' }}">{{ $totalAbs }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @elseif ($mode === 'stats')

    {{-- ══ MODE STATISTIQUES ══ --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                Statistiques — {{ $year?->label }}
                @if ($isTeacher) <span style="font-size:.8rem;font-weight:400;opacity:.6;">— vos matières uniquement</span> @endif
            </span>
            <span class="card-meta">{{ $students->count() }} élèves</span>
        </div>
        @if ($students->isEmpty())
            <div class="empty"><div class="empty-sub">Aucun élève.</div></div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th style="text-align:center;">Absences</th>
                        <th style="text-align:center;">Retards</th>
                        @if (! $isTeacher)
                            <th style="text-align:center;">Justifiées</th>
                        @endif
                        <th style="text-align:center;">Heures abs.</th>
                        <th style="text-align:center;">Total retard</th>
                        <th style="text-align:center;">Taux</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statsData as $item)
                        <tr>
                            <td>
                                <div class="student-cell">
                                    <div class="s-av">{{ strtoupper(substr($item['ssy']->student->first_name,0,1).substr($item['ssy']->student->last_name,0,1)) }}</div>
                                    <div>
                                        <div class="s-nm">{{ $item['ssy']->student->fullName() }}</div>
                                        <div class="s-mt">{{ $item['ssy']->student->matricule }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--accent-red);">{{ $item['absent'] }}</td>
                            <td style="text-align:center;font-family:'JetBrains Mono',monospace;font-weight:700;color:#8A6010;">{{ $item['late'] }}</td>
                            @if (! $isTeacher)
                                <td style="text-align:center;font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--sidebar-soft);">{{ $item['excused'] }}</td>
                            @endif
                            <td style="text-align:center;">
                                <span style="font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:700;color:{{ $item['abs_hours']>10?'var(--accent-red)':'var(--ink)' }}">{{ $item['abs_hours'] }}h</span>
                            </td>
                            <td style="text-align:center;">
                                @if ($item['total_late_minutes'] > 0)
                                    <span style="font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:700;color:#8A6010;">{{ $item['total_late_minutes'] }}min</span>
                                @else
                                    <span style="color:var(--ink);opacity:.25;">—</span>
                                @endif
                            </td>
                            <td style="min-width:100px;">
                                <div style="font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;color:{{ $item['absence_rate']>20?'var(--accent-red)':($item['absence_rate']>10?'#8A6010':'#166634') }}">
                                    {{ $item['absence_rate'] }}%
                                </div>
                                <div class="bar-bg">
                                    <div class="bar-fill" style="width:{{ min(100,$item['absence_rate']) }}%;background:{{ $item['absence_rate']>20?'var(--accent-red)':($item['absence_rate']>10?'#8A6010':'#166634') }};"></div>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('students.show', $item['ssy']->student) }}"
                                   style="font-size:.8rem;font-weight:600;color:var(--sidebar-soft);text-decoration:none;">Voir →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @endif
</div>
