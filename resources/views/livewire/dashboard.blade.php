<?php

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Bulletin;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use App\Services\AccessService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{
    #[On('academic-year-changed')]
    public function refreshDashboard(): void {}

    public function with(): array
    {
        $user     = auth()->user();
        $role     = $user->roles->first()?->name ?? 'guest';
        $schoolId = $user->school_id;
        $year     = AcademicYearService::current();

        return match($role) {
            'admin', 'directeur' => $this->adminData($schoolId, $year),
            'enseignant'         => $this->teacherData($user, $schoolId, $year),
            'comptable'          => $this->accountantData($schoolId, $year),
            'surveillant'        => $this->surveillantData($schoolId, $year),
            'parent'             => $this->parentData($user, $year),
            default              => ['role' => $role, 'year' => $year],
        };
    }

    // ── Admin / Directeur ────────────────────────────────────────

    private function adminData(int $schoolId, $year): array
    {
        $role = 'admin';

        // KPIs
        $totalStudents  = StudentSchoolYear::where('academic_year_id', $year?->id)
            ->whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->count();

        $totalClasses   = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)->count();

        $fillRate = 0;
        if ($totalClasses > 0) {
            $totalCapacity = SchoolClass::where('school_id', $schoolId)
                ->where('academic_year_id', $year?->id)
                ->sum('capacity');
            $fillRate = $totalCapacity > 0
                ? round(($totalStudents / $totalCapacity) * 100)
                : 0;
        }

        // Finances
        $totalDue  = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->sum('amount_due');

        $totalPaid = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->sum('amount_paid');

        $unpaidInvoices = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('status', 'unpaid')
         ->where('due_at', '<', now())
         ->with(['studentSchoolYear.student','studentSchoolYear.schoolClass'])
         ->orderBy('due_at')
         ->limit(5)
         ->get();

        // Taux de présence (7 derniers jours)
        $recentAttendances = Attendance::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->whereBetween('date', [now()->subDays(7), now()])->get();

        $presenceRate = $recentAttendances->count() > 0
            ? round(($recentAttendances->where('status','present')->count() / $recentAttendances->count()) * 100)
            : 0;

        // Inscriptions par mois (6 derniers mois)
        $enrollmentsByMonth = StudentSchoolYear::whereHas('student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('academic_year_id', $year?->id)
         ->selectRaw('MONTH(enrolled_at) as month, COUNT(*) as total')
         ->groupBy('month')
         ->orderBy('month')
         ->pluck('total', 'month')
         ->toArray();

        // Répartition par niveau
        $byLevel = StudentSchoolYear::whereHas('student',
                fn ($q) => $q->where('school_id', $schoolId)
            )->where('student_school_years.academic_year_id', $year?->id)  // ← qualifié
            ->join('school_classes', 'student_school_years.school_class_id', '=', 'school_classes.id')
            ->join('levels', 'school_classes.level_id', '=', 'levels.id')
            ->selectRaw('levels.name as level_name, COUNT(*) as total')
            ->groupBy('levels.name')
            ->pluck('total', 'level_name')
            ->toArray();

        // Classes avec plus d'absences
        $absentClasses = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->withCount(['studentSchoolYears as absences_count' => fn ($q) =>
                $q->whereHas('attendances', fn ($q) =>
                    $q->where('status','absent')->where('date', '>=', now()->subDays(7))
                )
            ])
            ->with('level')
            ->orderByDesc('absences_count')
            ->limit(5)
            ->get();

        // Annonces récentes
        $recentAnnouncements = Announcement::where('school_id', $schoolId)
            ->published()
            ->with('author')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        // Alertes
        $alerts = collect();

        // Devoirs sans correction
        $uncorrectedHw = Homework::where('school_id', $schoolId)
            ->where('due_date', '<', now())
            ->withCount(['submissions as uncorrected' => fn ($q) =>
                $q->where('status','submitted')
            ])
            ->having('uncorrected', '>', 0)
            ->count();
        if ($uncorrectedHw > 0) {
            $alerts->push(['type'=>'warning', 'msg' => "{$uncorrectedHw} devoir(s) avec rendus non corrigés."]);
        }

        // Factures en retard
        $overdueCount = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('status','unpaid')->where('due_at','<',now())->count();
        if ($overdueCount > 0) {
            $alerts->push(['type'=>'error', 'msg' => "{$overdueCount} facture(s) en retard de paiement."]);
        }

        // Activité récente
        $recentEnrollments = StudentSchoolYear::whereHas('student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->with(['student','schoolClass'])
         ->orderByDesc('enrolled_at')
         ->limit(5)
         ->get();

        return compact(
            'role','year','totalStudents','totalClasses','fillRate',
            'totalDue','totalPaid','presenceRate',
            'unpaidInvoices','enrollmentsByMonth','byLevel',
            'absentClasses','recentAnnouncements','alerts','recentEnrollments'
        );
    }

    // ── Enseignant ───────────────────────────────────────────────

    private function teacherData($user, int $schoolId, $year): array
    {
        $role  = 'enseignant';
        $staff = $user->staff;

        $myClassIds   = AccessService::myClassIds() ?? [];
        $mySubjectIds = AccessService::mySubjectIds() ?? [];

        $totalClasses  = count($myClassIds);
        $totalStudents = StudentSchoolYear::whereIn('school_class_id', $myClassIds)
            ->where('academic_year_id', $year?->id)->count();

        // Devoirs en attente de correction
        $pendingCorrections = HomeworkSubmission::whereHas('homework', fn ($q) =>
            $q->where('staff_id', $staff?->id)
        )->where('status','submitted')->count();

        // Taux de présence aujourd'hui dans mes classes
        $todayAttendances = Attendance::whereHas('studentSchoolYear', fn ($q) =>
            $q->whereIn('school_class_id', $myClassIds)
              ->where('academic_year_id', $year?->id)
        )->whereDate('date', today())->get();

        $todayPresenceRate = $todayAttendances->count() > 0
            ? round(($todayAttendances->where('status','present')->count() / $todayAttendances->count()) * 100)
            : null;

        // Mes classes avec stats
        $myClasses = SchoolClass::whereIn('id', $myClassIds)
            ->with(['level','studentSchoolYears'])
            ->withCount('studentSchoolYears as student_count')
            ->get()
            ->map(function ($class) use ($year) {
                $absToday = Attendance::whereHas('studentSchoolYear', fn ($q) =>
                    $q->where('school_class_id', $class->id)
                      ->where('academic_year_id', $year?->id)
                )->whereDate('date', today())->where('status','absent')->count();

                return [
                    'class'      => $class,
                    'abs_today'  => $absToday,
                ];
            });

        // Mes devoirs récents
        $myHomeworks = Homework::where('staff_id', $staff?->id)
            ->where('academic_year_id', $year?->id)
            ->with(['schoolClass','subject'])
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Annonces
        $announcements = Announcement::where('school_id', $schoolId)
            ->published()
            ->forRole('enseignant')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        // Absences non justifiées dans mes classes (cette semaine)
        $unjustifiedAbsences = Attendance::whereHas('studentSchoolYear', fn ($q) =>
            $q->whereIn('school_class_id', $myClassIds)
              ->where('academic_year_id', $year?->id)
        )->where('status','absent')
         ->whereNull('justification')
         ->whereNull('justification_path')
         ->whereBetween('date', [now()->startOfWeek(), now()])
         ->with(['studentSchoolYear.student','studentSchoolYear.schoolClass'])
         ->orderByDesc('date')
         ->limit(5)
         ->get();

        return compact(
            'role','year','staff',
            'totalClasses','totalStudents','pendingCorrections','todayPresenceRate',
            'myClasses','myHomeworks','announcements','unjustifiedAbsences'
        );
    }

    // ── Comptable ────────────────────────────────────────────────

    private function accountantData(int $schoolId, $year): array
    {
        $role = 'comptable';

        $totalDue  = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->sum('amount_due');

        $totalPaid = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->sum('amount_paid');

        $overdueTotal = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('status','unpaid')->where('due_at','<',now())->sum('amount_due');

        $todayCollected = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->whereDate('updated_at', today())->where('status','paid')->sum('amount_paid');

        // Encaissements par mois
        $monthlyCollections = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('status','paid')
         ->whereYear('updated_at', now()->year)
         ->selectRaw('MONTH(updated_at) as month, SUM(amount_paid) as total')
         ->groupBy('month')
         ->orderBy('month')
         ->pluck('total', 'month')
         ->toArray();

        // Factures en retard urgentes
        $overdueInvoices = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('status','unpaid')
         ->where('due_at','<',now())
         ->with(['studentSchoolYear.student','studentSchoolYear.schoolClass'])
         ->orderBy('due_at')
         ->limit(8)
         ->get();

        // Répartition par niveau
        $byLevelFinance = StudentSchoolYear::whereHas('student',
                fn ($q) => $q->where('school_id', $schoolId)
            )->where('student_school_years.academic_year_id', $year?->id)  // ← qualifié
            ->join('school_classes', 'student_school_years.school_class_id', '=', 'school_classes.id')
            ->join('levels', 'school_classes.level_id', '=', 'levels.id')
            ->selectRaw('levels.name as level_name,
                SUM(student_invoices.amount_due) as due,
                SUM(student_invoices.amount_paid) as paid')
            ->leftJoin('student_invoices', 'student_invoices.student_school_year_id', '=', 'student_school_years.id')
            ->groupBy('levels.name')
            ->get()
            ->keyBy('level_name');

        // Derniers paiements
        $recentPayments = StudentInvoice::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('status','paid')
         ->with(['studentSchoolYear.student','studentSchoolYear.schoolClass'])
         ->orderByDesc('updated_at')
         ->limit(5)
         ->get();

        return compact(
            'role','year',
            'totalDue','totalPaid','overdueTotal','todayCollected',
            'monthlyCollections','overdueInvoices','byLevelFinance','recentPayments'
        );
    }

    // ── Surveillant ──────────────────────────────────────────────

    private function surveillantData(int $schoolId, $year): array
    {
        $role = 'surveillant';

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->with(['level','studentSchoolYears'])
            ->get();

        $todayAll = Attendance::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->whereDate('date', today())->get();

        $presentToday = $todayAll->where('status','present')->count();
        $absentToday  = $todayAll->where('status','absent')->count();
        $lateToday    = $todayAll->where('status','late')->count();
        $totalToday   = $todayAll->count();
        $presenceRate = $totalToday > 0 ? round(($presentToday / $totalToday) * 100) : 0;

        // Absences par classe aujourd'hui
        $classesByAbsence = $classes->map(function ($class) use ($year) {
            $total   = $class->studentSchoolYears->count();
            $absents = Attendance::whereHas('studentSchoolYear', fn ($q) =>
                $q->where('school_class_id', $class->id)
                  ->where('academic_year_id', $year?->id)
            )->whereDate('date', today())->where('status','absent')->count();

            return [
                'class'   => $class,
                'total'   => $total,
                'absents' => $absents,
                'rate'    => $total > 0 ? round(($absents / $total) * 100) : 0,
            ];
        })->sortByDesc('absents');

        // Élèves avec trop d'absences (>10 cette année)
        $chronicAbsentees = StudentSchoolYear::whereHas('student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('academic_year_id', $year?->id)
         ->withCount(['attendances as absence_count' => fn ($q) =>
             $q->where('status','absent')
         ])
         ->having('absence_count', '>=', 10)
         ->with(['student','schoolClass.level'])
         ->orderByDesc('absence_count')
         ->limit(8)
         ->get();

        // Absences non justifiées cette semaine
        $unjustified = Attendance::whereHas('studentSchoolYear.student',
            fn ($q) => $q->where('school_id', $schoolId)
        )->where('status','absent')
         ->whereNull('justification_path')
         ->whereBetween('date', [now()->startOfWeek(), now()])
         ->with(['studentSchoolYear.student','studentSchoolYear.schoolClass'])
         ->count();

        // Évolution semaine (présences par jour)
        $weekData = collect();
        for ($i = 6; $i >= 0; $i--) {
            $day  = now()->subDays($i);
            $recs = Attendance::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $schoolId)
            )->whereDate('date', $day)->get();

            $weekData->push([
                'day'      => $day->locale('fr')->isoFormat('ddd'),
                'present'  => $recs->where('status','present')->count(),
                'absent'   => $recs->where('status','absent')->count(),
            ]);
        }

        return compact(
            'role','year',
            'presentToday','absentToday','lateToday','totalToday','presenceRate',
            'classesByAbsence','chronicAbsentees','unjustified','weekData'
        );
    }

    // ── Parent ───────────────────────────────────────────────────

    private function parentData($user, $year): array
    {
        $role     = 'parent';
        $guardian = Guardian::where('user_id', $user->id)->first();

        if (! $guardian) {
            return ['role' => $role, 'year' => $year, 'children' => collect()];
        }

        $schoolId = $user->school_id;

        $childrenIds = Student::whereHas('guardians', fn ($q) =>
            $q->where('guardian_id', $guardian->id)
        )->pluck('id');

        $children = StudentSchoolYear::whereIn('student_id', $childrenIds)
            ->where('academic_year_id', $year?->id)
            ->with(['student','schoolClass.level','academicYear'])
            ->get()
            ->map(function ($ssy) use ($year) {
                // Derniers bulletins
                $bulletins = Bulletin::where('student_school_year_id', $ssy->id)
                    ->orderByDesc('generated_at')
                    ->limit(3)
                    ->get();

                // Absences de l'année
                $absences = Attendance::where('student_school_year_id', $ssy->id)
                    ->where('status', 'absent')->count();

                // Retards
                $lates = Attendance::where('student_school_year_id', $ssy->id)
                    ->where('status', 'late')->count();

                // Factures
                $invoiceDue  = StudentInvoice::where('student_school_year_id', $ssy->id)->sum('amount_due');
                $invoicePaid = StudentInvoice::where('student_school_year_id', $ssy->id)->sum('amount_paid');

                // Devoirs en attente
                $pendingHomeworks = Homework::where('school_class_id', $ssy->school_class_id)
                    ->where('academic_year_id', $year?->id)
                    ->where('due_date', '>=', today())
                    ->whereDoesntHave('submissions', fn ($q) =>
                        $q->where('student_school_year_id', $ssy->id)
                    )
                    ->orderBy('due_date')
                    ->limit(3)
                    ->get();

                return [
                    'ssy'              => $ssy,
                    'bulletins'        => $bulletins,
                    'absences'         => $absences,
                    'lates'            => $lates,
                    'invoice_due'      => $invoiceDue,
                    'invoice_paid'     => $invoicePaid,
                    'invoice_balance'  => $invoiceDue - $invoicePaid,
                    'pending_homeworks'=> $pendingHomeworks,
                    'latest_avg'       => $bulletins->first()?->average,
                    'latest_period'    => $bulletins->first()?->period,
                ];
            });

        // Annonces pour les parents
        $announcements = Announcement::where('school_id', $schoolId)
            ->published()
            ->forRole('parent')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return compact('role', 'year', 'guardian', 'children', 'announcements');
    }
}; ?>

<style>
    /* ── Variables communes ── */
    .dash-title { font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; color:var(--ink); margin-bottom:.35rem; }
    .dash-sub   { font-size:.875rem; color:var(--ink); opacity:.5; margin-bottom:1.75rem; }

    /* ── KPIs ── */
    .kpi-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
    .kpi-grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
    .kpi-grid-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; margin-bottom:1.5rem; }
    @media(max-width:900px) { .kpi-grid-4 { grid-template-columns:1fr 1fr; } .kpi-grid-3 { grid-template-columns:1fr 1fr; } }
    @media(max-width:500px) { .kpi-grid-4,.kpi-grid-3,.kpi-grid-2 { grid-template-columns:1fr; } }

    .kpi-card { padding:1.25rem; border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); position:relative; overflow:hidden; }
    .kpi-card::before { content:''; position:absolute; top:0; left:0; width:3px; height:100%; }
    .kpi-blue::before   { background:var(--sidebar); }
    .kpi-green::before  { background:#22c55e; }
    .kpi-amber::before  { background:#E8A838; }
    .kpi-red::before    { background:var(--accent-red); }
    .kpi-purple::before { background:#8B5CF6; }
    .kpi-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.5rem; }
    .kpi-value { font-family:'Fraunces',serif; font-size:2.25rem; font-weight:600; color:var(--ink); line-height:1; margin-bottom:.25rem; }
    .kpi-sub   { font-size:.8rem; color:var(--ink); opacity:.5; }
    .kpi-icon  { position:absolute; top:1rem; right:1rem; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
    .kpi-icon svg { width:16px; height:16px; }
    .ki-blue   { background:rgba(42,63,126,.1); color:var(--sidebar-soft); }
    .ki-green  { background:rgba(34,197,94,.1); color:#166534; }
    .ki-amber  { background:rgba(232,168,56,.15); color:#8A6010; }
    .ki-red    { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .ki-purple { background:rgba(139,92,246,.1); color:#7C3AED; }

    /* ── Layout grilles ── */
    .dash-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem; }
    .dash-grid-3-2 { display:grid; grid-template-columns:2fr 1fr; gap:1.25rem; margin-bottom:1.25rem; }
    .dash-grid-2-1 { display:grid; grid-template-columns:1fr 2fr; gap:1.25rem; margin-bottom:1.25rem; }
    @media(max-width:900px) { .dash-grid-2,.dash-grid-3-2,.dash-grid-2-1 { grid-template-columns:1fr; } }

    /* ── Cards ── */
    .w-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; }
    .w-card-header { padding:.875rem 1.25rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .w-card-title  { font-family:'Fraunces',serif; font-size:.9375rem; font-weight:600; color:var(--ink); }
    .w-card-meta   { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.4; }
    .w-card-body   { padding:.875rem 1.25rem; }

    /* ── Listes ── */
    .list-row { display:flex; align-items:center; gap:.75rem; padding:.6rem 0; border-bottom:1px solid var(--line); }
    .list-row:last-child { border-bottom:none; }
    .lr-avatar { width:30px; height:30px; border-radius:50%; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .lr-name   { font-size:.875rem; font-weight:600; color:var(--ink); }
    .lr-sub    { font-size:.75rem; color:var(--ink); opacity:.5; }
    .lr-amount { font-family:'JetBrains Mono',monospace; font-size:.875rem; font-weight:700; margin-left:auto; flex-shrink:0; }
    .lr-badge  { font-family:'JetBrains Mono',monospace; font-size:9.5px; font-weight:600; padding:2px 7px; border-radius:4px; margin-left:auto; flex-shrink:0; }

    .badge-red    { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .badge-green  { background:rgba(30,120,80,.1); color:#166534; }
    .badge-amber  { background:rgba(232,168,56,.12); color:#8A6010; }
    .badge-blue   { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }

    /* ── Alertes ── */
    .alert-row { display:flex; align-items:center; gap:.6rem; padding:.6rem .875rem; border-radius:8px; margin-bottom:.45rem; font-size:.8125rem; font-weight:500; }
    .alert-row:last-child { margin-bottom:0; }
    .alert-row svg { width:14px; height:14px; flex-shrink:0; }
    .alert-warning { background:rgba(232,168,56,.1); border:1px solid rgba(232,168,56,.2); color:#8A6010; }
    .alert-error   { background:rgba(224,92,58,.08); border:1px solid rgba(224,92,58,.2); color:var(--accent-red); }
    .alert-info    { background:rgba(42,63,126,.06); border:1px solid rgba(42,63,126,.15); color:var(--sidebar-soft); }

    /* ── Progress bar ── */
    .prog-wrap  { display:flex; align-items:center; gap:.65rem; }
    .prog-bar   { flex:1; height:6px; border-radius:3px; background:var(--line); overflow:hidden; }
    .prog-fill  { height:100%; border-radius:3px; transition:width .4s ease; }
    .prog-label { font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; min-width:38px; text-align:right; }

    /* ── Annonces ── */
    .ann-item { padding:.75rem 0; border-bottom:1px solid var(--line); }
    .ann-item:last-child { border-bottom:none; }
    .ann-item-title { font-size:.875rem; font-weight:600; color:var(--ink); margin-bottom:.25rem; }
    .ann-item-title a { color:inherit; text-decoration:none; }
    .ann-item-title a:hover { color:var(--sidebar-soft); }
    .ann-item-meta { font-size:.75rem; color:var(--ink); opacity:.45; }
    .ann-pin { display:inline-flex; align-items:center; gap:3px; font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:1px 5px; border-radius:3px; background:rgba(232,168,56,.15); color:#8A6010; margin-right:.35rem; }

    /* ── Enfant card (parent) ── */
    .child-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .child-card:last-child { margin-bottom:0; }
    .child-header { padding:1rem 1.25rem; background:var(--sidebar); display:flex; align-items:center; justify-content:space-between; }
    .child-avatar { width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; font-family:'JetBrains Mono',monospace; font-size:13px; font-weight:700; color:#FFFFFF; flex-shrink:0; }
    .child-name   { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:#FFFFFF; }
    .child-class  { font-size:.8rem; color:rgba(255,255,255,.6); margin-top:1px; }
    .child-avg    { font-family:'JetBrains Mono',monospace; font-size:1.5rem; font-weight:700; color:#FFFFFF; }
    .child-avg-label { font-size:.75rem; color:rgba(255,255,255,.55); margin-top:2px; text-align:right; }
    .child-body   { display:grid; grid-template-columns:repeat(4,1fr); }
    @media(max-width:700px) { .child-body { grid-template-columns:1fr 1fr; } }
    .child-stat   { padding:.875rem 1rem; border-right:1px solid var(--line); border-bottom:1px solid var(--line); }
    .child-stat:nth-child(4n) { border-right:none; }
    @media(max-width:700px) { .child-stat:nth-child(2n) { border-right:none; } }
    .child-stat-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; color:var(--ink); opacity:.4; margin-bottom:.25rem; }
    .child-stat-value { font-family:'JetBrains Mono',monospace; font-size:1.25rem; font-weight:700; color:var(--ink); }

    .child-hw { padding:.75rem 1.25rem; border-top:1px solid var(--line); }
    .child-hw-title { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.5rem; }
    .hw-item { display:flex; align-items:center; gap:.65rem; padding:.4rem 0; border-bottom:1px solid var(--line); font-size:.8125rem; }
    .hw-item:last-child { border-bottom:none; }
    .hw-due { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; margin-left:auto; white-space:nowrap; }
    .hw-urgent  { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .hw-normal  { background:rgba(30,120,80,.08); color:#166534; }

    /* ── Canvas ── */
    .chart-wrap { padding:.875rem 1.25rem; }
    canvas { max-height:200px; }

    /* ── Lien voir plus ── */
    .see-more { display:block; text-align:center; font-size:.8rem; font-weight:600; color:var(--sidebar-soft); text-decoration:none; padding:.65rem; border-top:1px solid var(--line); margin-top:.25rem; }
    .see-more:hover { background:rgba(42,63,126,.04); }

    /* ── Taux cercle ── */
    .rate-circle { width:80px; height:80px; border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; border:3px solid; }
    .rate-value { font-family:'JetBrains Mono',monospace; font-size:1.1rem; font-weight:700; }
    .rate-label { font-size:.65rem; opacity:.6; margin-top:1px; }
</style>

<div>

{{-- ════════════════════════════════════════════════════════════ --}}
{{-- DASHBOARD ADMIN / DIRECTEUR --}}
{{-- ════════════════════════════════════════════════════════════ --}}
@if (in_array($role, ['admin','directeur']))

    <div class="dash-title">Tableau de bord</div>
    <div class="dash-sub">{{ auth()->user()->school?->name }} · {{ $year?->label }}</div>

    {{-- KPIs --}}
    <div class="kpi-grid-4">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon ki-blue"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/></svg></div>
            <div class="kpi-label">Élèves inscrits</div>
            <div class="kpi-value">{{ number_format($totalStudents) }}</div>
            <div class="kpi-sub">{{ $totalClasses }} classes · {{ $year?->label }}</div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-icon ki-green"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
            <div class="kpi-label">Taux de remplissage</div>
            <div class="kpi-value">{{ $fillRate }}<span style="font-size:1.25rem;">%</span></div>
            <div class="kpi-sub">Capacité des classes</div>
        </div>
        <div class="kpi-card kpi-amber">
            <div class="kpi-icon ki-amber"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
            <div class="kpi-label">Encaissé</div>
            <div class="kpi-value" style="font-size:1.5rem;">{{ number_format($totalPaid,0,',',' ') }}</div>
            <div class="kpi-sub">/ {{ number_format($totalDue,0,',',' ') }} DJF prévu</div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon ki-purple"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
            <div class="kpi-label">Taux de présence</div>
            <div class="kpi-value">{{ $presenceRate }}<span style="font-size:1.25rem;">%</span></div>
            <div class="kpi-sub">7 derniers jours</div>
        </div>
    </div>

    {{-- Alertes --}}
    @if ($alerts->isNotEmpty())
        <div style="margin-bottom:1.25rem;">
            @foreach ($alerts as $alert)
                <div class="alert-row alert-{{ $alert['type'] }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    {{ $alert['msg'] }}
                </div>
            @endforeach
        </div>
    @endif

    <div class="dash-grid-3-2">

        {{-- Inscriptions par mois --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Inscriptions</span>
                <span class="w-card-meta">{{ $year?->label }}</span>
            </div>
            <div class="chart-wrap">
                <canvas id="enrollChart"></canvas>
            </div>
        </div>

        {{-- Répartition par niveau --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Par niveau</span>
            </div>
            <div class="chart-wrap">
                <canvas id="levelChart"></canvas>
            </div>
        </div>
    </div>

    <div class="dash-grid-2">

        {{-- Factures en retard --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Factures en retard</span>
                <span class="w-card-meta" style="color:var(--accent-red);">{{ $unpaidInvoices->count() }}</span>
            </div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @forelse ($unpaidInvoices as $inv)
                    <div class="list-row">
                        <div class="lr-avatar">{{ strtoupper(substr($inv->studentSchoolYear->student->first_name,0,1).substr($inv->studentSchoolYear->student->last_name,0,1)) }}</div>
                        <div>
                            <div class="lr-name">{{ $inv->studentSchoolYear->student->fullName() }}</div>
                            <div class="lr-sub">{{ $inv->studentSchoolYear->schoolClass?->name }} · {{ $inv->label }}</div>
                        </div>
                        <div style="text-align:right;margin-left:auto;">
                            <div class="lr-amount" style="color:var(--accent-red);">{{ number_format($inv->amount_due - $inv->amount_paid,0,',',' ') }} DJF</div>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--ink);opacity:.4;">Échu {{ $inv->due_at->locale('fr')->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div style="text-align:center;padding:1.5rem;font-size:.875rem;color:var(--ink);opacity:.4;">Aucune facture en retard ✓</div>
                @endforelse
            </div>
            @if ($unpaidInvoices->isNotEmpty())
                <a href="{{ route('finances.index') }}" class="see-more">Voir toutes →</a>
            @endif
        </div>

        {{-- Classes avec absences --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Absences cette semaine</span>
                <span class="w-card-meta">Par classe</span>
            </div>
            <div class="w-card-body" style="padding-top:.75rem;padding-bottom:.75rem;">
                @foreach ($absentClasses as $cls)
                    <div style="margin-bottom:.75rem;">
                        <div style="display:flex;justify-content:space-between;font-size:.8125rem;margin-bottom:.25rem;">
                            <span style="font-weight:500;">{{ $cls->name }}</span>
                            <span style="font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;color:{{ $cls->absences_count > 5 ? 'var(--accent-red)' : 'var(--ink)' }};">{{ $cls->absences_count }}</span>
                        </div>
                        <div class="prog-wrap">
                            <div class="prog-bar">
                                <div class="prog-fill" style="width:{{ min(100, $cls->absences_count * 5) }}%;background:{{ $cls->absences_count > 5 ? 'var(--accent-red)' : 'var(--sidebar-soft)' }};"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="dash-grid-3-2">

        {{-- Nouvelles inscriptions --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Dernières inscriptions</span>
            </div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @foreach ($recentEnrollments as $ssy)
                    <div class="list-row">
                        <div class="lr-avatar">{{ strtoupper(substr($ssy->student->first_name,0,1).substr($ssy->student->last_name,0,1)) }}</div>
                        <div>
                            <div class="lr-name">{{ $ssy->student->fullName() }}</div>
                            <div class="lr-sub">{{ $ssy->schoolClass?->name }}</div>
                        </div>
                        <span style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--ink);opacity:.4;margin-left:auto;">{{ $ssy->enrolled_at?->locale('fr')->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('students.index') }}" class="see-more">Voir tous les élèves →</a>
        </div>

        {{-- Annonces --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Annonces</span>
            </div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @forelse ($recentAnnouncements as $ann)
                    <div class="ann-item">
                        <div class="ann-item-title">
                            @if($ann->is_pinned)<span class="ann-pin">📌</span>@endif
                            <a href="{{ route('announcements.show', $ann) }}">{{ Str::limit($ann->title,45) }}</a>
                        </div>
                        <div class="ann-item-meta">{{ $ann->published_at?->locale('fr')->diffForHumans() }}</div>
                    </div>
                @empty
                    <div style="text-align:center;padding:1.5rem;font-size:.875rem;color:var(--ink);opacity:.4;">Aucune annonce publiée.</div>
                @endforelse
            </div>
            <a href="{{ route('announcements.index') }}" class="see-more">Toutes les annonces →</a>
        </div>
    </div>

    {{-- Charts JS --}}
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // ── Inscriptions ────────────────────────────────────────
        const enrollData = @json($enrollmentsByMonth);
        const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        const enrollLabels = Object.keys(enrollData).map(m => months[parseInt(m)-1]);
        const enrollValues = Object.values(enrollData);

        new Chart(document.getElementById('enrollChart'), {
            type: 'line',
            data: {
                labels: enrollLabels,
                datasets: [{
                    label: 'Inscriptions',
                    data: enrollValues,
                    borderColor: '#1E2D5A',
                    backgroundColor: 'rgba(30,45,90,.08)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#1E2D5A',
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { family: 'JetBrains Mono', size: 10 } } },
                    y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { family: 'JetBrains Mono', size: 10 }, stepSize: 1 }, beginAtZero: true }
                }
            }
        });

        // ── Niveaux ─────────────────────────────────────────────
        const levelData = @json($byLevel);
        const colors = ['#1E2D5A','#2A3F7E','#3D5A99','#E8A838','#C04020','#166534','#8B5CF6'];

        new Chart(document.getElementById('levelChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(levelData),
                datasets: [{
                    data: Object.values(levelData),
                    backgroundColor: colors.slice(0, Object.keys(levelData).length),
                    borderWidth: 2,
                    borderColor: '#FFFFFF',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 }, padding: 12 } }
                },
                cutout: '60%',
            }
        });
    });
    </script>
    @endpush

@endif

{{-- ════════════════════════════════════════════════════════════ --}}
{{-- DASHBOARD ENSEIGNANT --}}
{{-- ════════════════════════════════════════════════════════════ --}}
@if ($role === 'enseignant')

    <div class="dash-title">Bonjour, {{ auth()->user()->name }}</div>
    <div class="dash-sub">{{ $year?->label }} · Vos classes et matières</div>

    {{-- KPIs --}}
    <div class="kpi-grid-4">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon ki-blue"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg></div>
            <div class="kpi-label">Mes classes</div>
            <div class="kpi-value">{{ $totalClasses }}</div>
            <div class="kpi-sub">{{ $totalStudents }} élèves au total</div>
        </div>
        <div class="kpi-card kpi-amber">
            <div class="kpi-icon ki-amber"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg></div>
            <div class="kpi-label">Devoirs à corriger</div>
            <div class="kpi-value">{{ $pendingCorrections }}</div>
            <div class="kpi-sub">Rendus en attente</div>
        </div>
        <div class="kpi-card {{ $todayPresenceRate !== null && $todayPresenceRate < 80 ? 'kpi-red' : 'kpi-green' }}">
            <div class="kpi-icon {{ $todayPresenceRate !== null && $todayPresenceRate < 80 ? 'ki-red' : 'ki-green' }}"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
            <div class="kpi-label">Présence aujourd'hui</div>
            <div class="kpi-value">{{ $todayPresenceRate !== null ? $todayPresenceRate.'%' : '—' }}</div>
            <div class="kpi-sub">Dans mes classes</div>
        </div>
        <div class="kpi-card kpi-red">
            <div class="kpi-icon ki-red"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg></div>
            <div class="kpi-label">Absences injustifiées</div>
            <div class="kpi-value">{{ $unjustifiedAbsences->count() }}</div>
            <div class="kpi-sub">Cette semaine</div>
        </div>
    </div>

    <div class="dash-grid-2">

        {{-- Mes classes --}}
        <div class="w-card">
            <div class="w-card-header"><span class="w-card-title">Mes classes</span></div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @foreach ($myClasses as $item)
                    <div class="list-row">
                        <div style="width:36px;height:36px;border-radius:8px;background:rgba(42,63,126,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="16" height="16" fill="none" stroke="var(--sidebar-soft)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2"/></svg>
                        </div>
                        <div style="flex:1;">
                            <div class="lr-name">{{ $item['class']->name }}</div>
                            <div class="lr-sub">{{ $item['class']->level?->name }} · {{ $item['class']->student_count }} élèves</div>
                        </div>
                        @if ($item['abs_today'] > 0)
                            <span class="lr-badge badge-red">{{ $item['abs_today'] }} absent{{ $item['abs_today'] > 1 ? 's' : '' }}</span>
                        @else
                            <span class="lr-badge badge-green">Tous présents</span>
                        @endif
                    </div>
                @endforeach
            </div>
            <a href="{{ route('absences.index') }}" class="see-more">Gérer les absences →</a>
        </div>

        {{-- Mes devoirs --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Mes devoirs</span>
                <a href="{{ route('homeworks.index') }}" style="font-size:.8rem;font-weight:600;color:var(--sidebar-soft);text-decoration:none;">+ Nouveau</a>
            </div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @forelse ($myHomeworks as $hw)
                    <div class="list-row">
                        <div>
                            <div class="lr-name">{{ Str::limit($hw->title, 35) }}</div>
                            <div class="lr-sub">{{ $hw->schoolClass?->name }} · {{ $hw->subject?->name }}</div>
                        </div>
                        <div style="text-align:right;margin-left:auto;flex-shrink:0;">
                            <div style="font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600;color:{{ $hw->due_date->isPast() ? 'var(--ink)' : ($hw->due_date->diffInDays() <= 2 ? 'var(--accent-red)' : '#166634') }};">
                                {{ $hw->due_date->locale('fr')->isoFormat('D MMM') }}
                            </div>
                            <div style="font-size:.75rem;color:var(--ink);opacity:.45;">{{ $hw->submissions_count }} rendu(s)</div>
                        </div>
                    </div>
                @empty
                    <div style="text-align:center;padding:1.5rem;font-size:.875rem;color:var(--ink);opacity:.4;">Aucun devoir publié.</div>
                @endforelse
            </div>
            <a href="{{ route('homeworks.index') }}" class="see-more">Voir tous les devoirs →</a>
        </div>
    </div>

    <div class="dash-grid-3-2">

        {{-- Absences injustifiées --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Absences injustifiées — cette semaine</span>
            </div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @forelse ($unjustifiedAbsences as $att)
                    <div class="list-row">
                        <div class="lr-avatar">{{ strtoupper(substr($att->studentSchoolYear->student->first_name,0,1).substr($att->studentSchoolYear->student->last_name,0,1)) }}</div>
                        <div>
                            <div class="lr-name">{{ $att->studentSchoolYear->student->fullName() }}</div>
                            <div class="lr-sub">{{ $att->studentSchoolYear->schoolClass?->name }} · {{ $att->sessionLabel() }}</div>
                        </div>
                        <span class="lr-badge badge-red" style="margin-left:auto;">{{ $att->date->locale('fr')->isoFormat('ddd D') }}</span>
                    </div>
                @empty
                    <div style="text-align:center;padding:1.5rem;font-size:.875rem;color:var(--ink);opacity:.4;">Aucune absence injustifiée ✓</div>
                @endforelse
            </div>
            <a href="{{ route('absences.index') }}" class="see-more">Gérer les absences →</a>
        </div>

        {{-- Annonces --}}
        <div class="w-card">
            <div class="w-card-header"><span class="w-card-title">Annonces</span></div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @forelse ($announcements as $ann)
                    <div class="ann-item">
                        <div class="ann-item-title">
                            @if($ann->is_pinned)<span class="ann-pin">📌</span>@endif
                            <a href="{{ route('announcements.show', $ann) }}">{{ Str::limit($ann->title,45) }}</a>
                        </div>
                        <div class="ann-item-meta">{{ $ann->published_at?->locale('fr')->diffForHumans() }}</div>
                    </div>
                @empty
                    <div style="text-align:center;padding:1rem;font-size:.875rem;color:var(--ink);opacity:.4;">Aucune annonce.</div>
                @endforelse
            </div>
            <a href="{{ route('announcements.index') }}" class="see-more">Toutes les annonces →</a>
        </div>
    </div>

@endif

{{-- ════════════════════════════════════════════════════════════ --}}
{{-- DASHBOARD COMPTABLE --}}
{{-- ════════════════════════════════════════════════════════════ --}}
@if ($role === 'comptable')

    <div class="dash-title">Finances</div>
    <div class="dash-sub">{{ auth()->user()->school?->name }} · {{ $year?->label }}</div>

    <div class="kpi-grid-4">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon ki-green"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <div class="kpi-label">Total encaissé</div>
            <div class="kpi-value" style="font-size:1.5rem;">{{ number_format($totalPaid,0,',',' ') }}</div>
            <div class="kpi-sub">DJF · {{ $year?->label }}</div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon ki-blue"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
            <div class="kpi-label">Prévisionnel</div>
            <div class="kpi-value" style="font-size:1.5rem;">{{ number_format($totalDue,0,',',' ') }}</div>
            <div class="kpi-sub">DJF · Total dû</div>
        </div>
        <div class="kpi-card kpi-red">
            <div class="kpi-icon ki-red"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <div class="kpi-label">Retards de paiement</div>
            <div class="kpi-value" style="font-size:1.5rem;">{{ number_format($overdueTotal,0,',',' ') }}</div>
            <div class="kpi-sub">DJF · Échus non payés</div>
        </div>
        <div class="kpi-card kpi-amber">
            <div class="kpi-icon ki-amber"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
            <div class="kpi-label">Encaissé aujourd'hui</div>
            <div class="kpi-value" style="font-size:1.5rem;">{{ number_format($todayCollected,0,',',' ') }}</div>
            <div class="kpi-sub">DJF · {{ now()->locale('fr')->isoFormat('D MMMM') }}</div>
        </div>
    </div>

    {{-- Barre de progression globale --}}
    @php $globalRate = $totalDue > 0 ? round(($totalPaid / $totalDue) * 100) : 0; @endphp
    <div class="w-card" style="margin-bottom:1.25rem;">
        <div class="w-card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                <span style="font-size:.875rem;font-weight:600;color:var(--ink);">Taux de recouvrement global</span>
                <span style="font-family:'JetBrains Mono',monospace;font-size:1.25rem;font-weight:700;color:{{ $globalRate >= 80 ? '#166534' : ($globalRate >= 60 ? '#8A6010' : 'var(--accent-red)') }};">{{ $globalRate }}%</span>
            </div>
            <div style="height:10px;border-radius:5px;background:var(--line);overflow:hidden;">
                <div style="height:100%;border-radius:5px;width:{{ $globalRate }}%;background:{{ $globalRate >= 80 ? '#22c55e' : ($globalRate >= 60 ? '#E8A838' : 'var(--accent-red)') }};transition:width .5s;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--ink);opacity:.4;margin-top:.35rem;">
                <span>0</span>
                <span>{{ number_format($totalPaid,0,',',' ') }} / {{ number_format($totalDue,0,',',' ') }} DJF</span>
            </div>
        </div>
    </div>

    <div class="dash-grid-2">

        {{-- Graphique mensuel --}}
        <div class="w-card">
            <div class="w-card-header"><span class="w-card-title">Encaissements par mois</span></div>
            <div class="chart-wrap">
                <canvas id="financeChart"></canvas>
            </div>
        </div>

        {{-- Factures urgentes --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Factures échues</span>
                <span class="w-card-meta" style="color:var(--accent-red);">À encaisser</span>
            </div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @forelse ($overdueInvoices->take(6) as $inv)
                    <div class="list-row">
                        <div class="lr-avatar">{{ strtoupper(substr($inv->studentSchoolYear->student->first_name,0,1).substr($inv->studentSchoolYear->student->last_name,0,1)) }}</div>
                        <div>
                            <div class="lr-name">{{ $inv->studentSchoolYear->student->fullName() }}</div>
                            <div class="lr-sub">{{ $inv->label }}</div>
                        </div>
                        <div class="lr-amount" style="color:var(--accent-red);">{{ number_format($inv->amount_due - $inv->amount_paid,0,',',' ') }}</div>
                    </div>
                @empty
                    <div style="text-align:center;padding:1.5rem;font-size:.875rem;color:var(--ink);opacity:.4;">Aucun retard ✓</div>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const data = @json($monthlyCollections);
        const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        const labels = Object.keys(data).map(m => months[parseInt(m)-1]);
        const values = Object.values(data);

        new Chart(document.getElementById('financeChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Encaissements (DJF)',
                    data: values,
                    backgroundColor: 'rgba(30,45,90,.15)',
                    borderColor: '#1E2D5A',
                    borderWidth: 1.5,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { family: 'JetBrains Mono', size: 10 } } },
                    y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { family: 'JetBrains Mono', size: 10 } }, beginAtZero: true }
                }
            }
        });
    });
    </script>
    @endpush

@endif

{{-- ════════════════════════════════════════════════════════════ --}}
{{-- DASHBOARD SURVEILLANT --}}
{{-- ════════════════════════════════════════════════════════════ --}}
@if ($role === 'surveillant')

    <div class="dash-title">Présences</div>
    <div class="dash-sub">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</div>

    <div class="kpi-grid-4">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon ki-green"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <div class="kpi-label">Présents</div>
            <div class="kpi-value">{{ $presentToday }}</div>
            <div class="kpi-sub">Aujourd'hui</div>
        </div>
        <div class="kpi-card kpi-red">
            <div class="kpi-icon ki-red"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></div>
            <div class="kpi-label">Absents</div>
            <div class="kpi-value">{{ $absentToday }}</div>
            <div class="kpi-sub">Aujourd'hui</div>
        </div>
        <div class="kpi-card kpi-amber">
            <div class="kpi-icon ki-amber"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <div class="kpi-label">Retards</div>
            <div class="kpi-value">{{ $lateToday }}</div>
            <div class="kpi-sub">Aujourd'hui</div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon ki-blue"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg></div>
            <div class="kpi-label">Injustifiées</div>
            <div class="kpi-value">{{ $unjustified }}</div>
            <div class="kpi-sub">Cette semaine</div>
        </div>
    </div>

    <div class="dash-grid-2">

        {{-- Absences par classe --}}
        <div class="w-card">
            <div class="w-card-header"><span class="w-card-title">Absences par classe — aujourd'hui</span></div>
            <div class="w-card-body">
                @foreach ($classesByAbsence->take(8) as $item)
                    <div style="margin-bottom:.75rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.25rem;">
                            <span style="font-size:.8125rem;font-weight:500;">{{ $item['class']->name }}</span>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <span style="font-size:.75rem;color:var(--ink);opacity:.4;">{{ $item['absents'] }}/{{ $item['total'] }}</span>
                                @if ($item['absents'] > 0)
                                    <span style="font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:600;color:{{ $item['rate'] > 20 ? 'var(--accent-red)' : '#8A6010' }};">{{ $item['rate'] }}%</span>
                                @endif
                            </div>
                        </div>
                        <div class="prog-bar">
                            <div class="prog-fill" style="width:{{ $item['rate'] }}%;background:{{ $item['rate'] > 20 ? 'var(--accent-red)' : ($item['rate'] > 10 ? '#E8A838' : '#22c55e') }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('absences.index') }}" class="see-more">Gérer les absences →</a>
        </div>

        {{-- Élèves chroniques --}}
        <div class="w-card">
            <div class="w-card-header">
                <span class="w-card-title">Absences répétées</span>
                <span class="w-card-meta">≥ 10 cette année</span>
            </div>
            <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                @forelse ($chronicAbsentees as $ssy)
                    <div class="list-row">
                        <div class="lr-avatar">{{ strtoupper(substr($ssy->student->first_name,0,1).substr($ssy->student->last_name,0,1)) }}</div>
                        <div>
                            <div class="lr-name">{{ $ssy->student->fullName() }}</div>
                            <div class="lr-sub">{{ $ssy->schoolClass?->name }}</div>
                        </div>
                        <span class="lr-badge badge-red">{{ $ssy->absence_count }} abs.</span>
                    </div>
                @empty
                    <div style="text-align:center;padding:1.5rem;font-size:.875rem;color:var(--ink);opacity:.4;">Aucun élève avec absences répétées.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Évolution semaine --}}
    <div class="w-card">
        <div class="w-card-header"><span class="w-card-title">Présences — 7 derniers jours</span></div>
        <div class="chart-wrap">
            <canvas id="weekChart" style="max-height:160px;"></canvas>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const weekData = @json($weekData);
        new Chart(document.getElementById('weekChart'), {
            type: 'bar',
            data: {
                labels: weekData.map(d => d.day),
                datasets: [
                    { label: 'Présents', data: weekData.map(d => d.present), backgroundColor: 'rgba(34,197,94,.2)', borderColor: '#22c55e', borderWidth: 1.5, borderRadius: 3 },
                    { label: 'Absents',  data: weekData.map(d => d.absent),  backgroundColor: 'rgba(224,92,58,.15)', borderColor: 'var(--accent-red)', borderWidth: 1.5, borderRadius: 3 },
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { family: 'JetBrains Mono', size: 10 } } },
                    y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { family: 'JetBrains Mono', size: 10 } }, beginAtZero: true }
                }
            }
        });
    });
    </script>
    @endpush

@endif

{{-- ════════════════════════════════════════════════════════════ --}}
{{-- DASHBOARD PARENT --}}
{{-- ════════════════════════════════════════════════════════════ --}}
@if ($role === 'parent')

    <div class="dash-title">Suivi scolaire</div>
    <div class="dash-sub">Bonjour, {{ auth()->user()->name }}</div>

    @if ($children->isEmpty())
        <div style="text-align:center;padding:4rem;color:var(--ink);opacity:.4;">
            Aucun enfant associé à votre compte. Contactez l'administration.
        </div>
    @else
        @foreach ($children as $child)
            @php $ssy = $child['ssy']; @endphp
            <div class="child-card">
                <div class="child-header">
                    <div style="display:flex;align-items:center;gap:.875rem;">
                        <div class="child-avatar">
                            {{ strtoupper(substr($ssy->student->first_name,0,1).substr($ssy->student->last_name,0,1)) }}
                        </div>
                        <div>
                            <div class="child-name">{{ $ssy->student->fullName() }}</div>
                            <div class="child-class">{{ $ssy->schoolClass?->name }} — {{ $ssy->schoolClass?->level?->name }}</div>
                        </div>
                    </div>
                    @if ($child['latest_avg'] !== null)
                        <div style="text-align:right;">
                            <div class="child-avg">{{ number_format($child['latest_avg'],2) }}/20</div>
                            <div class="child-avg-label">{{ $child['latest_period'] }}</div>
                        </div>
                    @endif
                </div>

                <div class="child-body">
                    <div class="child-stat">
                        <div class="child-stat-label">Absences</div>
                        <div class="child-stat-value" style="color:{{ $child['absences'] > 5 ? 'var(--accent-red)' : 'var(--ink)' }};">{{ $child['absences'] }}</div>
                    </div>
                    <div class="child-stat">
                        <div class="child-stat-label">Retards</div>
                        <div class="child-stat-value" style="color:{{ $child['lates'] > 3 ? '#8A6010' : 'var(--ink)' }};">{{ $child['lates'] }}</div>
                    </div>
                    <div class="child-stat">
                        <div class="child-stat-label">Facture restante</div>
                        <div class="child-stat-value" style="font-size:.875rem;color:{{ $child['invoice_balance'] > 0 ? 'var(--accent-red)' : '#166534' }};">
                            {{ number_format($child['invoice_balance'],0,',',' ') }}
                        </div>
                    </div>
                    <div class="child-stat" style="border-right:none;">
                        <div class="child-stat-label">Devoirs en attente</div>
                        <div class="child-stat-value" style="color:{{ $child['pending_homeworks']->count() > 0 ? '#8A6010' : '#166534' }};">{{ $child['pending_homeworks']->count() }}</div>
                    </div>
                </div>

                @if ($child['pending_homeworks']->isNotEmpty())
                    <div class="child-hw">
                        <div class="child-hw-title">Devoirs à rendre</div>
                        @foreach ($child['pending_homeworks'] as $hw)
                            <div class="hw-item">
                                <div>
                                    <div style="font-size:.875rem;font-weight:500;">{{ $hw->title }}</div>
                                    <div style="font-size:.75rem;color:var(--ink);opacity:.5;">{{ $hw->subject?->name }}</div>
                                </div>
                                <span class="hw-due {{ $hw->due_date->diffInDays() <= 2 ? 'hw-urgent' : 'hw-normal' }}">
                                    {{ $hw->due_date->locale('fr')->isoFormat('D MMM') }}
                                </span>
                            </div>
                        @endforeach
                        <a href="{{ route('homeworks.index') }}" style="font-size:.8rem;font-weight:600;color:var(--sidebar-soft);text-decoration:none;display:block;margin-top:.5rem;">Voir tous les devoirs →</a>
                    </div>
                @endif

                <div style="padding:.75rem 1.25rem;border-top:1px solid var(--line);display:flex;gap:.65rem;flex-wrap:wrap;">
                    <a href="{{ route('students.show', $ssy->student) }}" style="font-size:.8125rem;font-weight:600;color:var(--sidebar-soft);text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Fiche complète
                    </a>
                    @if ($child['bulletins']->isNotEmpty())
                        <a href="{{ route('bulletins.show', [$ssy->student, $child['bulletins']->first()]) }}" style="font-size:.8125rem;font-weight:600;color:var(--sidebar-soft);text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Dernier bulletin
                        </a>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Annonces pour les parents --}}
        @if ($announcements->isNotEmpty())
            <div class="w-card" style="margin-top:1.25rem;">
                <div class="w-card-header"><span class="w-card-title">Annonces de l'école</span></div>
                <div class="w-card-body" style="padding-top:.5rem;padding-bottom:.25rem;">
                    @foreach ($announcements as $ann)
                        <div class="ann-item">
                            <div class="ann-item-title">
                                @if($ann->is_pinned)<span class="ann-pin">📌</span>@endif
                                <a href="{{ route('announcements.show', $ann) }}">{{ $ann->title }}</a>
                            </div>
                            <div class="ann-item-meta">{{ $ann->published_at?->locale('fr')->diffForHumans() }}</div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('announcements.index') }}" class="see-more">Toutes les annonces →</a>
            </div>
        @endif
    @endif

@endif

</div>

{{-- Remplacer @push('scripts') ... @endpush par : --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    @if (in_array($role, ['admin', 'directeur']))
    // ── Admin : inscriptions + niveaux ────────────────────────
    (function() {
        const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        const enrollData   = @json($enrollmentsByMonth ?? []);
        const enrollLabels = Object.keys(enrollData).map(m => months[parseInt(m)-1]);

        const enrollCtx = document.getElementById('enrollChart');
        if (enrollCtx) {
            new Chart(enrollCtx, {
                type: 'line',
                data: {
                    labels: enrollLabels,
                    datasets: [{
                        label: 'Inscriptions',
                        data: Object.values(enrollData),
                        borderColor: '#1E2D5A',
                        backgroundColor: 'rgba(30,45,90,.08)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: '#1E2D5A',
                        fill: true,
                        tension: 0.4,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { family: 'JetBrains Mono', size: 10 } } },
                        y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { family: 'JetBrains Mono', size: 10 }, stepSize: 1 }, beginAtZero: true }
                    }
                }
            });
        }

        const levelData = @json($byLevel ?? []);
        const colors    = ['#1E2D5A','#2A3F7E','#3D5A99','#E8A838','#C04020','#166534','#8B5CF6'];
        const levelCtx  = document.getElementById('levelChart');
        if (levelCtx) {
            new Chart(levelCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(levelData),
                    datasets: [{
                        data: Object.values(levelData),
                        backgroundColor: colors.slice(0, Object.keys(levelData).length),
                        borderWidth: 2,
                        borderColor: '#FFFFFF',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 }, padding: 12 } } },
                    cutout: '60%',
                }
            });
        }
    })();
    @endif

    @if ($role === 'comptable')
    // ── Comptable : encaissements mensuels ────────────────────
    (function() {
        const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        const data   = @json($monthlyCollections ?? []);
        const labels = Object.keys(data).map(m => months[parseInt(m)-1]);
        const ctx    = document.getElementById('financeChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Encaissements (DJF)',
                        data: Object.values(data),
                        backgroundColor: 'rgba(30,45,90,.15)',
                        borderColor: '#1E2D5A',
                        borderWidth: 1.5,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { family: 'JetBrains Mono', size: 10 } } },
                        y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { family: 'JetBrains Mono', size: 10 } }, beginAtZero: true }
                    }
                }
            });
        }
    })();
    @endif

    @if ($role === 'surveillant')
    // ── Surveillant : présences 7 jours ──────────────────────
    (function() {
        const weekData = @json($weekData ?? []);
        const ctx      = document.getElementById('weekChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: weekData.map(d => d.day),
                    datasets: [
                        {
                            label: 'Présents',
                            data: weekData.map(d => d.present),
                            backgroundColor: 'rgba(34,197,94,.2)',
                            borderColor: '#22c55e',
                            borderWidth: 1.5,
                            borderRadius: 3,
                        },
                        {
                            label: 'Absents',
                            data: weekData.map(d => d.absent),
                            backgroundColor: 'rgba(224,92,58,.15)',
                            borderColor: '#E05C3A',
                            borderWidth: 1.5,
                            borderRadius: 3,
                        },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { family: 'JetBrains Mono', size: 10 } } },
                        y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { family: 'JetBrains Mono', size: 10 } }, beginAtZero: true }
                    }
                }
            });
        }
    })();
    @endif

});
</script>
@endpush

