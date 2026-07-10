<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolGradingConfig extends Model
{
    protected $fillable = [
        'school_id','max_score','passing_score','decimal_places',
        'evaluation_weights','evaluation_types','drop_lowest_grade',
        'min_grades_per_period','mentions','appreciations',
        'average_method','period_system',
        'show_rank','show_class_average','show_min_max',
        'show_teacher_appreciation','show_absences_on_bulletin',
    ];
    protected $casts = [
        'evaluation_weights'       => 'array',
        'evaluation_types'         => 'array',
        'mentions'                 => 'array',
        'appreciations'            => 'array',
        'drop_lowest_grade'        => 'boolean',
        'show_rank'                => 'boolean',
        'show_class_average'       => 'boolean',
        'show_min_max'             => 'boolean',
        'show_teacher_appreciation'=> 'boolean',
        'show_absences_on_bulletin'=> 'boolean',
    ];
    public function school() { return $this->belongsTo(School::class); }
}
