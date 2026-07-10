<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_school_year_id', 'evaluation_id', 'score', 'comment',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function studentSchoolYear()
    {
        return $this->belongsTo(StudentSchoolYear::class);
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }
}
