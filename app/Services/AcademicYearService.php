<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AcademicYearService
{
    const SESSION_KEY = 'current_academic_year_id';

    /**
     * Retourne l'année académique actuellement sélectionnée.
     * Priorité : session → année active → première année disponible.
     */
    public static function current(): ?AcademicYear
    {
        $schoolId = Auth::user()?->school_id;
        if (! $schoolId) return null;

        $sessionId = Session::get(self::SESSION_KEY);

        if ($sessionId) {
            $year = AcademicYear::where('id', $sessionId)
                ->where('school_id', $schoolId)
                ->first();
            if ($year) return $year;
        }

        // Fallback : année active
        $year = AcademicYear::where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();

        if ($year) {
            Session::put(self::SESSION_KEY, $year->id);
            return $year;
        }

        // Fallback ultime : la plus récente
        return AcademicYear::where('school_id', $schoolId)
            ->latest('starts_at')
            ->first();
    }

    /**
     * Change l'année sélectionnée dans la session.
     */
    public static function switchTo(int $yearId): void
    {
        Session::put(self::SESSION_KEY, $yearId);
    }

    /**
     * Retourne l'ID de l'année courante (pratique pour les requêtes).
     */
    public static function currentId(): ?int
    {
        return static::current()?->id;
    }

    /**
     * Retourne toutes les années de l'école connectée, triées du plus récent.
     */
    public static function allForCurrentSchool()
    {
        $schoolId = Auth::user()?->school_id;
        if (! $schoolId) return collect();

        return AcademicYear::where('school_id', $schoolId)
            ->orderByDesc('starts_at')
            ->get();
    }
}