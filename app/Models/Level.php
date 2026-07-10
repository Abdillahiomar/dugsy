<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['school_id', 'name', 'cycle', 'order'];

    public function schoolClasses()
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }
}
