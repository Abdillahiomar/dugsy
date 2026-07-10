<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id', 'first_name', 'last_name', 'phone', 'email',
        'profession', 'address',
    ];


    // app/Models/Guardian.php
public function user()
{
    return $this->belongsTo(\App\Models\User::class);
}

public function hasAccount(): bool
{
    return ! is_null($this->user_id);
}

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_guardian')
            ->withPivot('relationship', 'is_primary_contact')
            ->withTimestamps();
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
