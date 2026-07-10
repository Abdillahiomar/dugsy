<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bulletin extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_school_year_id', 'period', 'average', 'rank',
        'general_comment', 'pdf_path', 'generated_at',
    ];

    protected $casts = [
        'average' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function studentSchoolYear()
    {
        return $this->belongsTo(StudentSchoolYear::class);
    }
}
