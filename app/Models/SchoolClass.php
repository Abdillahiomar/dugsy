<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'school_classes';

    protected $fillable = [
        'school_id', 'academic_year_id', 'level_id', 'name',
        'main_teacher_id', 'capacity',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function mainTeacher()
    {
        return $this->belongsTo(Staff::class, 'main_teacher_id');
    }

    public function classSubjects()
    {
        return $this->hasMany(ClassSubjectTeacher::class);
    }

    public function studentSchoolYears()
    {
        return $this->hasMany(StudentSchoolYear::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }
}
