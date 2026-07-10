<?php
// app/Policies/StudentPolicy.php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        // Staff : accès selon permission Spatie
        if ($user->hasAnyRole(['admin','directeur','comptable','enseignant','surveillant'])) {
            return $user->can('students.view');
        }

        // Parent : uniquement ses propres enfants
        if ($user->hasRole('parent')) {
            return $student->guardians()
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }
}