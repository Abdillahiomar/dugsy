<?php

namespace App\Http\Controllers;

use App\Models\Bulletin;
use App\Models\Student;
use App\Services\BulletinService;
use Illuminate\Http\Request;

class BulletinController extends Controller
{
    public function show(Student $student, Bulletin $bulletin)
    {
        return redirect()->route('bulletins.show', [$student, $bulletin]);
    }

    

    public function pdf(Student $student, Bulletin $bulletin)
{
    $ssy     = $bulletin->studentSchoolYear()
            ->with(['schoolClass.level', 'academicYear', 'schoolClass.classSubjects.subject'])
            ->first();
    $data   = (new BulletinService())->calculate($ssy, $bulletin->period);
    $school = $student->school;
    $config = \App\Services\GradingConfigService::get($student->school_id);

    // Absences de l'élève pour cette année
    $absenceStats = [
        'absent'  => \App\Models\Attendance::whereHas('studentSchoolYear',
            fn ($q) => $q->where('id', $ssy->id)
        )->where('status','absent')->count(),
        'late'    => \App\Models\Attendance::whereHas('studentSchoolYear',
            fn ($q) => $q->where('id', $ssy->id)
        )->where('status','late')->count(),
        'excused' => \App\Models\Attendance::whereHas('studentSchoolYear',
            fn ($q) => $q->where('id', $ssy->id)
        )->where('status','excused')->count(),
    ];

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.bulletin', compact(
        'bulletin', 'student', 'ssy', 'data', 'school', 'config', 'absenceStats'
    ))->setPaper('A4', 'portrait');

    return $pdf->download("bulletin-{$student->matricule}-{$bulletin->period}.pdf");
}
}
