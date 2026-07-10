<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingPeriod extends Model
{
    protected $fillable = [
        'school_id','academic_year_id','period',
        'open_from','open_until','is_open','note',
    ];
    protected $casts = [
        'is_open'    => 'boolean',
        'open_from'  => 'date',
        'open_until' => 'date',
    ];
    public function school()       { return $this->belongsTo(School::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
 
    public function isCurrentlyOpen(): bool {
        if (! $this->is_open) return false;
        $now = now();
        if ($this->open_from && $now->lt($this->open_from)) return false;
        if ($this->open_until && $now->gt($this->open_until)) return false;
        return true;
    }
}
