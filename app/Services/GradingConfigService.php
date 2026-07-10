<?php

namespace App\Services;

use App\Models\SchoolGradingConfig;

class GradingConfigService
{
    private static array $cache = [];

    public static function get(int $schoolId): SchoolGradingConfig
    {
        if (! isset(self::$cache[$schoolId])) {
            self::$cache[$schoolId] = SchoolGradingConfig::firstOrCreate(
                ['school_id' => $schoolId],
                self::defaults()
            );
        }
        return self::$cache[$schoolId];
    }

    public static function defaults(): array
    {
        return [
            'max_score'          => 20,
            'passing_score'      => 10,
            'decimal_places'     => 2,
            'evaluation_weights' => [
                'devoir'        => 40,
                'controle'      => 30,
                'examen'        => 30,
                'interrogation' => 0,
                'tp'            => 0,
            ],
            'evaluation_types'   => ['devoir', 'controle', 'examen'],
            'drop_lowest_grade'  => false,
            'min_grades_per_period' => 1,
            'mentions' => [
                ['label' => 'Très Bien',  'min' => 16],
                ['label' => 'Bien',        'min' => 14],
                ['label' => 'Assez Bien',  'min' => 12],
                ['label' => 'Passable',    'min' => 10],
                ['label' => 'Insuffisant', 'min' => 0],
            ],
            'appreciations' => [
                ['label' => 'Excellent travail, continuez ainsi.',          'min' => 18],
                ['label' => 'Très bon résultat, félicitations.',            'min' => 16],
                ['label' => 'Bon travail, des efforts à maintenir.',        'min' => 14],
                ['label' => 'Résultats satisfaisants, peut mieux faire.',   'min' => 12],
                ['label' => 'Des efforts supplémentaires sont nécessaires.','min' => 10],
                ['label' => 'Résultats insuffisants, travail urgent.',      'min' => 0],
            ],
            'average_method'            => 'weighted_coefficient',
            'period_system'             => 'trimester',
            'show_rank'                 => true,
            'show_class_average'        => true,
            'show_min_max'              => false,
            'show_teacher_appreciation' => true,
            'show_absences_on_bulletin' => true,
        ];
    }

    public static function computeAverage(
        array $grades,
        SchoolGradingConfig $config
    ): ?float {
        if (empty($grades)) return null;

        $weights = $config->evaluation_weights ?? [];

        $normalized = collect($grades)->map(fn ($g) => [
            'score'  => ($config->max_score > 0 && ($g['max'] ?? 0) > 0)
                ? ($g['score'] / $g['max']) * $config->max_score
                : $g['score'],
            'type'   => $g['type'] ?? 'devoir',
            'weight' => $weights[$g['type'] ?? 'devoir'] ?? 1,
        ]);

        if ($config->drop_lowest_grade && $normalized->count() > 1) {
            $minScore = $normalized->min('score');
            $removed  = false;
            $normalized = $normalized->filter(function ($g) use ($minScore, &$removed) {
                if (! $removed && $g['score'] === $minScore) {
                    $removed = true;
                    return false;
                }
                return true;
            })->values();
        }

        $totalWeightedScore = 0;
        $totalWeight        = 0;

        foreach ($normalized as $g) {
            $w = ($g['weight'] > 0) ? $g['weight'] : 1;
            $totalWeightedScore += $g['score'] * $w;
            $totalWeight        += $w;
        }

        if ($totalWeight === 0) return null;

        return round($totalWeightedScore / $totalWeight, $config->decimal_places);
    }

    public static function mention(float $avg, SchoolGradingConfig $config): string
    {
        $mentions = collect($config->mentions ?? self::defaults()['mentions'])
            ->sortByDesc('min');

        foreach ($mentions as $m) {
            if ($avg >= $m['min']) return $m['label'];
        }
        return '—';
    }

    public static function appreciation(float $avg, SchoolGradingConfig $config): string
    {
        $appreciations = collect($config->appreciations ?? self::defaults()['appreciations'])
            ->sortByDesc('min');

        foreach ($appreciations as $a) {
            if ($avg >= $a['min']) return $a['label'];
        }
        return '';
    }

    public static function periods(SchoolGradingConfig $config): array
    {
        return match($config->period_system) {
            'semester' => ['Semestre 1', 'Semestre 2'],
            'annual'   => ['Annuel'],
            default    => ['Trimestre 1', 'Trimestre 2', 'Trimestre 3'],
        };
    }
}