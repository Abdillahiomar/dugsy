<?php
// app/Services/Dashboard/StatisticsService.php

namespace App\Services\Dashboard;

use App\Models\Attendance;
use App\Models\Bulletin;
use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use Illuminate\Support\Facades\Cache;

class StatisticsService
{
    private int    $schoolId;
    private ?object $year;
    private int    $cacheTtl = 300; // 5 minutes

    public function __construct(int $schoolId)
    {
        $this->schoolId = $schoolId;
        $this->year     = AcademicYearService::current();
    }

    private function remember(string $key, callable $cb): mixed
    {
        return Cache::remember(
            "dashboard:{$this->schoolId}:{$key}",
            $this->cacheTtl,
            $cb
        );
    }

    // ── Admin / Directeur ────────────────────────────────────────

    public function adminKpis(): array
    {
        return $this->remember('admin_kpis', function () {
            $yearId = $this->year?->id;

            $totalStudents = StudentSchoolYear::where('academic_year_id', $yearId)
                ->whereHas('student', fn ($q) => $q->where('school_id', $this->schoolId))
                ->count();

            //dd($totalStudents);

            $lastMonthStudents = StudentSchoolYear::where('academic_year_id', $yearId)
                ->whereHas('student', fn ($q) => $q->where('school_id', $this->schoolId))
                ->where('enrolled_at', '<', now()->startOfMonth())
                ->count();

            $totalDue  = StudentInvoice::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->sum('amount_due');

            $totalPaid = StudentInvoice::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->sum('amount_paid');

            $overdueCount = StudentInvoice::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->where('status', 'unpaid')->where('due_at', '<', now())->count();

            $overdueAmount = StudentInvoice::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->where('status', 'unpaid')->where('due_at', '<', now())->sum('amount_due');

            $attRows = Attendance::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->whereBetween('date', [now()->subDays(7), now()])
             ->selectRaw('status, COUNT(*) as cnt')
             ->groupBy('status')
             ->pluck('cnt', 'status')->toArray();

            $total        = array_sum($attRows);
            $presenceRate = $total > 0 ? round((($attRows['present'] ?? 0) / $total) * 100) : 0;
            $recoveryRate = $totalDue > 0 ? round(($totalPaid / $totalDue) * 100) : 0;
            $studentDelta = $lastMonthStudents > 0
                ? round((($totalStudents - $lastMonthStudents) / $lastMonthStudents) * 100, 1)
                : 0;

            return compact(
                'totalStudents', 'studentDelta',
                'totalDue', 'totalPaid',
                'overdueCount', 'overdueAmount',
                'presenceRate', 'recoveryRate'
            );
        });
    }

    public function enrollmentsByMonth(): array
    {
        return $this->remember('enrollments_by_month', function () {
            return StudentSchoolYear::whereHas('student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->where('academic_year_id', $this->year?->id)
             ->selectRaw('MONTH(enrolled_at) as month, COUNT(*) as total')
             ->groupBy('month')->orderBy('month')
             ->pluck('total', 'month')->toArray();
        });
    }

    public function studentsByLevel(): array
    {
        return $this->remember('students_by_level', function () {
            return StudentSchoolYear::whereHas('student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->where('student_school_years.academic_year_id', $this->year?->id)  // ← qualifié
            ->join('school_classes', 'student_school_years.school_class_id', '=', 'school_classes.id')
            ->join('levels', 'school_classes.level_id', '=', 'levels.id')
            ->selectRaw('levels.name as level_name, COUNT(*) as total')
            ->groupBy('levels.name')
            ->pluck('total', 'level_name')
            ->toArray();
        });
    }

    public function revenueByMonth(): array
    {
        return $this->remember('revenue_by_month', function () {
            return StudentInvoice::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->where('status', 'paid')
             ->whereYear('updated_at', now()->year)
             ->selectRaw('MONTH(updated_at) as month, SUM(amount_paid) as total')
             ->groupBy('month')->orderBy('month')
             ->pluck('total', 'month')->toArray();
        });
    }

    public function presenceByDay(): array
    {
        return $this->remember('presence_by_day', function () {
            $days = collect();
            for ($i = 6; $i >= 0; $i--) {
                $day  = now()->subDays($i)->format('Y-m-d');
                $rows = Attendance::whereHas('studentSchoolYear.student',
                    fn ($q) => $q->where('school_id', $this->schoolId)
                )->whereDate('date', $day)
                 ->selectRaw('status, COUNT(*) as cnt')
                 ->groupBy('status')->pluck('cnt', 'status')->toArray();

                $days->push([
                    'date'    => now()->subDays($i)->locale('fr')->isoFormat('ddd D'),
                    'present' => $rows['present'] ?? 0,
                    'absent'  => $rows['absent']  ?? 0,
                    'late'    => $rows['late']     ?? 0,
                ]);
            }
            return $days->toArray();
        });
    }

    // ── Comptable ────────────────────────────────────────────────

    public function accountantKpis(): array
    {
        return $this->remember('accountant_kpis', function () {
            $base = fn () => StudentInvoice::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            );

            $today  = $base()->whereDate('updated_at', today())->where('status','paid')->sum('amount_paid');
            $month  = $base()->whereMonth('updated_at', now()->month)->where('status','paid')->sum('amount_paid');
            $year   = $base()->whereYear('updated_at', now()->year)->where('status','paid')->sum('amount_paid');

            $unpaidCount   = $base()->where('status','unpaid')->count();
            $unpaidAmount  = $base()->where('status','unpaid')->sum('amount_due');
            $overdueAmount = $base()->where('status','unpaid')->where('due_at','<',now())->sum('amount_due');

            // ── Retourner un tableau vide si payment_method n'existe pas ──
            $byMethod = [];

            return compact('today','month','year','unpaidCount','unpaidAmount','overdueAmount','byMethod');
        });
    }

    public function topDebtors(int $limit = 8): array
    {
        return $this->remember("top_debtors_{$limit}", function () use ($limit) {
            return StudentInvoice::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->where('status','unpaid')
             ->selectRaw('student_school_year_id, SUM(amount_due - amount_paid) as balance')
             ->groupBy('student_school_year_id')
             ->orderByDesc('balance')->limit($limit)
             ->with(['studentSchoolYear.student','studentSchoolYear.schoolClass'])
             ->get()
             ->map(fn ($inv) => [
                 'name'    => $inv->studentSchoolYear->student->fullName(),
                 'class'   => $inv->studentSchoolYear->schoolClass?->name,
                 'balance' => (float) $inv->balance,
             ])->toArray();
        });
    }

    // ── Enseignant ───────────────────────────────────────────────

    public function teacherKpis(int $staffId): array
    {
        return $this->remember("teacher_kpis_{$staffId}", function () use ($staffId) {
            $yearId   = $this->year?->id;
            $classIds = \App\Models\ClassSubjectTeacher::where('staff_id', $staffId)
                ->pluck('school_class_id')->unique()->values()->toArray();

            $totalStudents = StudentSchoolYear::whereIn('school_class_id', $classIds)
                ->where('academic_year_id', $yearId)->count();

            $todayAtt = Attendance::whereHas('studentSchoolYear',
                fn ($q) => $q->whereIn('school_class_id', $classIds)->where('academic_year_id', $yearId)
            )->whereDate('date', today())
             ->selectRaw('status, COUNT(*) as cnt')
             ->groupBy('status')->pluck('cnt','status')->toArray();

            $pendingHomeworks = \App\Models\HomeworkSubmission::whereHas('homework',
                fn ($q) => $q->where('staff_id', $staffId)
            )->where('status','submitted')->count();

            return [
                'classIds'        => $classIds,
                'totalStudents'   => $totalStudents,
                'presentToday'    => $todayAtt['present'] ?? 0,
                'absentToday'     => $todayAtt['absent']  ?? 0,
                'pendingHomeworks'=> $pendingHomeworks,
            ];
        });
    }

    public function teacherPresenceByWeek(array $classIds): array
    {
        $key = 'teacher_presence_'.md5(implode('_', $classIds));
        return $this->remember($key, function () use ($classIds) {
            $days = collect();
            for ($i = 6; $i >= 0; $i--) {
                $day  = now()->subDays($i)->format('Y-m-d');
                $rows = Attendance::whereHas('studentSchoolYear',
                    fn ($q) => $q->whereIn('school_class_id', $classIds)
                )->whereDate('date', $day)
                 ->selectRaw('status, COUNT(*) as cnt')
                 ->groupBy('status')->pluck('cnt','status')->toArray();

                $days->push([
                    'date'    => now()->subDays($i)->locale('fr')->isoFormat('ddd'),
                    'present' => $rows['present'] ?? 0,
                    'absent'  => $rows['absent']  ?? 0,
                ]);
            }
            return $days->toArray();
        });
    }

    // ── Surveillant ──────────────────────────────────────────────

    public function surveillantKpis(): array
    {
        return $this->remember('surveillant_kpis', function () {
            $rows = Attendance::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->whereDate('date', today())
             ->selectRaw('status, COUNT(*) as cnt')
             ->groupBy('status')->pluck('cnt','status')->toArray();

            $total   = array_sum($rows);
            $present = $rows['present'] ?? 0;
            $absent  = $rows['absent']  ?? 0;
            $late    = $rows['late']    ?? 0;

            $unjustified = Attendance::whereHas('studentSchoolYear.student',
                fn ($q) => $q->where('school_id', $this->schoolId)
            )->where('status','absent')->whereNull('justification_path')
             ->whereBetween('date',[now()->startOfWeek(),now()])->count();

            return [
                'total' => $total, 'present' => $present, 'absent' => $absent,
                'late'  => $late,
                'rate'  => $total > 0 ? round(($present / $total) * 100) : 0,
                'unjustified' => $unjustified,
            ];
        });
    }

    // ── Parent ───────────────────────────────────────────────────

    public function parentKpis(int $userId): array
    {
        return Cache::remember("dashboard:{$this->schoolId}:parent_{$userId}", 120, function () use ($userId) {
            $guardian = \App\Models\Guardian::where('user_id', $userId)->first();
            if (! $guardian) return [];

            return StudentSchoolYear::whereHas('student', fn ($q) =>
                $q->whereHas('guardians', fn ($q) => $q->where('guardian_id', $guardian->id))
            )->where('academic_year_id', $this->year?->id)
             ->with(['student','schoolClass'])
             ->get()
             ->map(function ($ssy) {
                 $absences    = Attendance::where('student_school_year_id', $ssy->id)->where('status','absent')->count();
                 $invoiceDue  = StudentInvoice::where('student_school_year_id', $ssy->id)->sum('amount_due');
                 $invoicePaid = StudentInvoice::where('student_school_year_id', $ssy->id)->sum('amount_paid');
                 $pendingHw   = Homework::where('school_class_id', $ssy->school_class_id)
                     ->where('due_date','>=',today())
                     ->whereDoesntHave('submissions',fn($q)=>$q->where('student_school_year_id',$ssy->id))
                     ->count();
                 $bulletin = Bulletin::where('student_school_year_id',$ssy->id)->latest('generated_at')->first();

                 return [
                     'name'       => $ssy->student->fullName(),
                     'class'      => $ssy->schoolClass?->name,
                     'absences'   => $absences,
                     'balance'    => $invoiceDue - $invoicePaid,
                     'pending_hw' => $pendingHw,
                     'avg'        => $bulletin?->average,
                     'period'     => $bulletin?->period,
                 ];
             })->toArray();
        });
    }

    public function flushCache(): void
    {
        Cache::forget("dashboard:{$this->schoolId}:admin_kpis");
        Cache::forget("dashboard:{$this->schoolId}:accountant_kpis");
        Cache::forget("dashboard:{$this->schoolId}:surveillant_kpis");
    }
}