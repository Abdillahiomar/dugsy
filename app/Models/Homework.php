<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Homework extends Model
{
    protected $table = 'homeworks'; // ← AJOUTER
    protected $fillable = [
        'school_id','academic_year_id','school_class_id','subject_id','staff_id',
        'title','description','file_path','file_name',
        'due_date','is_mandatory','allow_submission',
    ];

    protected $casts = [
        'due_date'         => 'date',
        'is_mandatory'     => 'boolean',
        'allow_submission' => 'boolean',
    ];

    public function school()      { return $this->belongsTo(School::class); }
    public function academicYear(){ return $this->belongsTo(AcademicYear::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class); }
    public function subject()     { return $this->belongsTo(Subject::class); }
    public function staff()       { return $this->belongsTo(Staff::class); }
    public function submissions() { return $this->hasMany(HomeworkSubmission::class); }

    public function fileUrl(): ?string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast();
    }

    public function statusLabel(): string
    {
        if ($this->isOverdue()) return 'Terminé';
        $diff = now()->diffInDays($this->due_date, false);
        if ($diff <= 2) return 'Urgent';
        return 'En cours';
    }

    public function statusColor(): string
    {
        return match($this->statusLabel()) {
            'Terminé' => 'rgba(0,0,0,.1)',
            'Urgent'  => 'rgba(224,92,58,.1)',
            default   => 'rgba(30,120,80,.1)',
        };
    }
}

// ── app/Models/HomeworkSubmission.php ────────────────────────────────────

