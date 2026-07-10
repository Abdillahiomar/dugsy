<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\ClassSubjectTeacher;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StudentSchoolYear;
use Illuminate\Support\Collection;

class BulletinService
{
    /**
     * Calcule les données d'un bulletin pour un élève et une période.
     * Retourne un tableau structuré sans écrire en base.
     */
    public function calculate(StudentSchoolYear $ssy, string $period): array
    {
        $config = GradingConfigService::get($ssy->student->school_id);
        $class = $ssy->schoolClass()->with('classSubjects.subject', 'classSubjects.teacher.user')->first();

        // Notes de l'élève pour cette période
        $grades = Grade::where('student_school_year_id', $ssy->id)
            ->whereHas('evaluation', fn ($q) => $q->where('period', $period))
            ->with(['evaluation.subject'])
            ->get();

        // Construire les lignes par matière
        $subjectLines = collect();
        $totalPoints  = 0;
        $totalCoeffs  = 0;

        foreach ($class->classSubjects as $cs) {
            $subject       = $cs->subject;
            $subjectGrades = $grades->filter(
                fn ($g) => $g->evaluation->subject_id === $subject->id
            );

            if ($subjectGrades->isEmpty()) {
                $subjectLines->push([
                    'subject_id'   => $subject->id,
                    'subject_name' => $subject->name,
                    'subject_code' => $subject->code,
                    'subject_color'=> $subject->color,
                    'coefficient'  => $subject->coefficient,
                    'teacher'      => $cs->teacher?->user?->name ?? '—',
                    'grades'       => [],
                    'average'      => null,
                    'mention'      => '—',
                    'appreciation' => '',
                ]);
                continue;
            }

            $avg      = round($subjectGrades->avg('score'), 2);
            $coeff    = $subject->coefficient;
            $totalPoints += $avg * $coeff;
            $totalCoeffs += $coeff;

            $mention     = \App\Services\GradingConfigService::mention($avg, $config);
            $appreciation = \App\Services\GradingConfigService::appreciation($avg, $config);

            $subjectLines->push([
                'subject_id'   => $subject->id,
                'subject_name' => $subject->name,
                'subject_code' => $subject->code,
                'subject_color'=> $subject->color,
                'coefficient'  => $coeff,
                'teacher'      => $cs->teacher?->user?->name ?? '—',
                'grades'       => $subjectGrades->map(fn ($g) => [
                    'score'    => $g->score,
                    'max'      => $g->evaluation->max_score,
                    'type'     => $g->evaluation->type,
                    'date'     => $g->evaluation->date?->format('d/m/Y'),
                ])->values()->toArray(),
                'average'      => $avg,
                'mention'      => $this->mention($avg),
                'appreciation' => $this->appreciation($avg),
            ]);
        }

        $generalAverage = $totalCoeffs > 0
            ? round($totalPoints / $totalCoeffs, 2)
            : null;

        // Rang dans la classe
        $rank = $this->calculateRank($ssy, $period, $generalAverage);

        // Nombre d'élèves dans la classe
        $classCount = StudentSchoolYear::where('school_class_id', $ssy->school_class_id)
            ->where('academic_year_id', $ssy->academic_year_id)
            ->count();

        return [
            'ssy'             => $ssy,
            'period'          => $period,
            'subject_lines'   => $subjectLines,
            'general_average' => $generalAverage,
            'mention' => $generalAverage !== null ? \App\Services\GradingConfigService::mention($generalAverage, $config) : '—',
            'rank'            => $rank,
            'class_count'     => $classCount,
            'total_coeffs'    => $totalCoeffs,
        ];
    }

    /**
     * Génère ou met à jour un bulletin en base.
     */
    public function generateBulletin(StudentSchoolYear $ssy, string $period, string $comment = ''): Bulletin
    {
        $data = $this->calculate($ssy, $period);

        return Bulletin::updateOrCreate(
            ['student_school_year_id' => $ssy->id, 'period' => $period],
            [
                'average'         => $data['general_average'],
                'rank'            => $data['rank'],
                'general_comment' => $comment ?: null,
                'generated_at'    => now(),
            ]
        );
    }

    /**
     * Calcule le rang de l'élève dans la classe pour une période.
     */
    private function calculateRank(StudentSchoolYear $ssy, string $period, ?float $myAverage): ?int
    {
        if ($myAverage === null) return null;

        $classmates = StudentSchoolYear::where('school_class_id', $ssy->school_class_id)
            ->where('academic_year_id', $ssy->academic_year_id)
            ->where('id', '!=', $ssy->id)
            ->with(['grades.evaluation'])
            ->get();

        $rank = 1;
        foreach ($classmates as $mate) {
            $mateGrades = $mate->grades()
                ->whereHas('evaluation', fn ($q) => $q->where('period', $period))
                ->with(['evaluation.subject'])
                ->get();

            if ($mateGrades->isEmpty()) continue;

            // Calculer la moyenne pondérée du camarade
            $points = 0;
            $coeffs = 0;
            foreach ($mateGrades->groupBy('evaluation.subject_id') as $subjectGrades) {
                $coeff   = $subjectGrades->first()->evaluation->subject?->coefficient ?? 1;
                $avg     = $subjectGrades->avg('score');
                $points += $avg * $coeff;
                $coeffs += $coeff;
            }

            $mateAvg = $coeffs > 0 ? $points / $coeffs : 0;
            if ($mateAvg > $myAverage) $rank++;
        }

        return $rank;
    }

    public function mention(float $avg): string
    {
        return match(true) {
            $avg >= 18  => 'Très Bien',
            $avg >= 16  => 'Bien',
            $avg >= 14  => 'Assez Bien',
            $avg >= 12  => 'Passable',
            $avg >= 10  => 'Passable',
            default     => 'Insuffisant',
        };
    }

    public function appreciation(float $avg): string
    {
        return match(true) {
            $avg >= 18  => 'Excellent travail, continuez ainsi.',
            $avg >= 16  => 'Très bon résultat, félicitations.',
            $avg >= 14  => 'Bon travail, des efforts à maintenir.',
            $avg >= 12  => 'Résultats satisfaisants, peut mieux faire.',
            $avg >= 10  => 'Résultats justes, des efforts sont nécessaires.',
            $avg >= 8   => 'Résultats insuffisants, travail à intensifier.',
            default     => 'Résultats très insuffisants, redoubler d\'efforts.',
        };
    }
}
