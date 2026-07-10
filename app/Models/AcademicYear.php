<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id', 'label', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function schoolClasses()
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function studentSchoolYears()
    {
        return $this->hasMany(StudentSchoolYear::class);
    }

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }
}
