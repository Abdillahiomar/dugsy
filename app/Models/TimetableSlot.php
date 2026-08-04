<?php
// app/Models/TimetableSlot.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableSlot extends Model
{
    protected $fillable = [
        'school_id', 'academic_year_id', 'school_class_id',
        'subject_id', 'staff_id',
        'day_of_week', 'start_time', 'end_time',
        'room', 'color',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    // Jours de la semaine à Djibouti (commence dimanche)
    public static array $DAYS = [
        0 => 'Dimanche',
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
    ];

    // Jours ouvrables (pas vendredi après-midi ni samedi souvent)
    public static array $SCHOOL_DAYS = [0, 1, 2, 3, 4]; // Dim→Jeu

    public function school()       { return $this->belongsTo(School::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function schoolClass()  { return $this->belongsTo(SchoolClass::class); }
    public function subject()      { return $this->belongsTo(Subject::class); }
    public function staff()        { return $this->belongsTo(Staff::class); }

    public function dayLabel(): string
    {
        return self::$DAYS[$this->day_of_week] ?? '?';
    }

    public function duration(): int // en minutes
    {
        return \Carbon\Carbon::parse($this->start_time)
            ->diffInMinutes(\Carbon\Carbon::parse($this->end_time));
    }

    public function effectiveColor(): string
    {
        return $this->color ?? $this->subject?->color ?? '#1E2D5A';
    }
}
