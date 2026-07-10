<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'staff';

    protected $fillable = [
        'school_id', 'user_id', 'matricule', 'position', 'hired_at', 'phone',
    ];

    protected $casts = [
        'hired_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classSubjects()
    {
        return $this->hasMany(ClassSubjectTeacher::class);
    }

    public function mainClasses()
    {
        return $this->hasMany(SchoolClass::class, 'main_teacher_id');
    }
}
