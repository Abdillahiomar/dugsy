<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory, BelongsToTenant;

    // Model Subject — $fillable
    protected $fillable = ['school_id', 'name', 'code', 'coefficient', 'color', 'cycles'];

    public function classSubjects()
    {
        return $this->hasMany(ClassSubjectTeacher::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }
}
