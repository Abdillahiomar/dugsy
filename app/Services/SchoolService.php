<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SchoolService
{
    const SESSION_KEY = 'current_school';

    /**
     * Retourne l'école courante.
     */
    public static function current(): ?School
    {
        // Si une école est sélectionnée dans la session
        if ($schoolId = Session::get(self::SESSION_KEY)) {
            return School::find($schoolId);
        }

        $user = Auth::user();

        if (!$user) {
            return null;
        }

        // SuperAdmin : première école par défaut
        if ($user->hasRole('SuperAdmin')) {
            $school = School::orderBy('name')->first();

            if ($school) {
                Session::put(self::SESSION_KEY, $school->id);
            }

            return $school;
        }

        // Utilisateur normal
        if ($user->school_id) {
            Session::put(self::SESSION_KEY, $user->school_id);

            return School::find($user->school_id);
        }

        return null;
    }

    /**
     * Changer l'école.
     */
    public static function switchTo(int $schoolId): void
    {
        Session::put(self::SESSION_KEY, $schoolId);

        // On réinitialise aussi l'année académique
        Session::forget('current_academic_year');
    }

    /**
     * ID de l'école courante.
     */
    public static function currentId(): ?int
    {
        return static::current()?->id;
    }

    /**
     * Toutes les écoles.
     */
    public static function all()
    {
        return School::orderBy('name')->get();
    }
}