<?php

namespace App\Services;

use App\Models\ClassSubjectTeacher;
use App\Models\User;

class AccessService
{
    public static function myClassIds(?User $user = null): ?array
    {
        $user ??= auth()->user();

        if ($user->hasAnyRole(['admin', 'directeur', 'comptable', 'surveillant'])) {
            return null; // pas de filtre
        }

        $staff = $user->staff;
        if (! $staff) return [];

        return ClassSubjectTeacher::where('staff_id', $staff->id)
            ->pluck('school_class_id')
            ->unique()->values()->toArray();
    }

    public static function mySubjectIds(?User $user = null): ?array
    {
        $user ??= auth()->user();

        if ($user->hasAnyRole(['admin', 'directeur'])) return null;

        $staff = $user->staff;
        if (! $staff) return [];

        return ClassSubjectTeacher::where('staff_id', $staff->id)
            ->pluck('subject_id')
            ->unique()->values()->toArray();
    }

    public static function canManageClass(int $classId, ?User $user = null): bool
    {
        $ids = self::myClassIds($user);
        return $ids === null || in_array($classId, $ids);
    }
}