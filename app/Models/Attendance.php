<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'student_school_year_id',
        'date',
        'session_start',
        'session_end',
        'subject_id',
        'status',
        'justification',
        'justification_path',
        'recorded_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // ── Relations ────────────────────────────────────────────────

    public function studentSchoolYear()
    {
        return $this->belongsTo(StudentSchoolYear::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function recorder()
    {
        return $this->belongsTo(Staff::class, 'recorded_by');
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** Libellé de la séance ex: "09:30 – 11:30" */
    public function sessionLabel(): string
    {
        if (! $this->session_start) return 'Journée entière';

        $start = substr($this->session_start, 0, 5);
        $end   = $this->session_end ? ' – ' . substr($this->session_end, 0, 5) : '';

        return $start . $end;
    }

    /** URL publique du document justificatif */
    public function justificationUrl(): ?string
    {
        return $this->justification_path
            ? asset('storage/' . $this->justification_path)
            : null;
    }

    /** Extension du justificatif pour l'icône */
    public function justificationExtension(): ?string
    {
        if (! $this->justification_path) return null;
        return strtolower(pathinfo($this->justification_path, PATHINFO_EXTENSION));
    }
}
