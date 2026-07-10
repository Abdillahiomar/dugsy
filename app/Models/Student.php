<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'school_id', 'matricule', 'first_name', 'last_name', 'birth_date',
        'birth_place', 'gender', 'photo_path', 'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function schoolYears()
    {
        return $this->hasMany(StudentSchoolYear::class);
    }

    public function currentSchoolYear()
    {
        return $this->hasOne(StudentSchoolYear::class)
            ->whereHas('academicYear', fn ($q) => $q->where('is_active', true));
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
            ->withPivot('relationship', 'is_primary_contact')
            ->withTimestamps();
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
