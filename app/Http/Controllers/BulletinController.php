<?php

namespace App\Http\Controllers;

use App\Models\Bulletin;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use App\Services\BulletinService;
use App\Services\GradingConfigService;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class BulletinController extends Controller
{
    /**
     * PDF individuel d'un bulletin.
     */
    public function pdf(Student $student, Bulletin $bulletin)
    {
        $service  = new BulletinService();
        $schoolId = auth()->user()->school_id;
        $config   = GradingConfigService::get($schoolId);

        $ssy = $bulletin->studentSchoolYear()
            ->with(['schoolClass.level', 'academicYear', 'schoolClass.classSubjects.subject'])
            ->first();

        $data   = $service->calculate($ssy, $bulletin->period);
        $school = auth()->user()->school;

        // Absences
        $absenceStats = null;
        if ($config->show_absences_on_bulletin) {
            $absenceStats = [
                'absent'  => Attendance::where('student_school_year_id', $ssy->id)->where('status', 'absent')->count(),
                'late'    => Attendance::where('student_school_year_id', $ssy->id)->where('status', 'late')->count(),
                'excused' => Attendance::where('student_school_year_id', $ssy->id)->where('status', 'excused')->count(),
            ];
        }

        $pdf = Pdf::loadView('pdf.bulletin', compact(
            'student', 'bulletin', 'ssy', 'data', 'school', 'config', 'absenceStats'
        ))->setPaper('a4', 'portrait');

        $filename = 'bulletin_'.$student->matricule.'_'.$bulletin->period.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * PDF groupé — tous les bulletins d'une classe pour une période.
     * Une page A4 par bulletin.
     */
    public function batchPdf(Request $request, SchoolClass $schoolClass, string $period)
    {
        $period   = urldecode($period);
        $schoolId = auth()->user()->school_id;
        $year     = AcademicYearService::current();
        $config   = GradingConfigService::get($schoolId);
        $service  = new BulletinService();
        $school   = auth()->user()->school;

        // Récupérer les élèves triés alphabétiquement
        $ssys = StudentSchoolYear::where('school_class_id', $schoolClass->id)
            ->where('academic_year_id', $year?->id)
            ->with(['student', 'schoolClass.level', 'academicYear', 'schoolClass.classSubjects.subject'])
            ->get()
            ->sortBy('student.last_name');

        // Construire les données de chaque bulletin
        $bulletins = $ssys->map(function ($ssy) use ($service, $config, $period) {
            $bulletin = Bulletin::where('student_school_year_id', $ssy->id)
                ->where('period', $period)
                ->first();

            if (! $bulletin) return null; // ignorer les non-générés

            $data = $service->calculate($ssy, $period);

            $absenceStats = null;
            if ($config->show_absences_on_bulletin) {
                $absenceStats = [
                    'absent'  => \App\Models\Attendance::where('student_school_year_id', $ssy->id)->where('status', 'absent')->count(),
                    'late'    => \App\Models\Attendance::where('student_school_year_id', $ssy->id)->where('status', 'late')->count(),
                    'excused' => \App\Models\Attendance::where('student_school_year_id', $ssy->id)->where('status', 'excused')->count(),
                ];
            }

            return [
                'ssy'          => $ssy,
                'student'      => $ssy->student,
                'bulletin'     => $bulletin,
                'data'         => $data,
                'absenceStats' => $absenceStats,
            ];
        })->filter()->values(); // retirer les null

        if ($bulletins->isEmpty()) {
            abort(404, 'Aucun bulletin généré pour cette classe et cette période.');
        }

        // Générer le PDF groupé — une page par bulletin
        $pdf = Pdf::loadView('pdf.bulletins-batch', compact(
            'bulletins', 'schoolClass', 'period', 'school', 'config'
        ))
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'sans-serif',
            'dpi'                  => 150,
        ]);

        $filename = 'bulletins_'
            . \Illuminate\Support\Str::slug($schoolClass->name) . '_'
            . \Illuminate\Support\Str::slug($period) . '.pdf';

        return $pdf->download($filename);
    }
}
