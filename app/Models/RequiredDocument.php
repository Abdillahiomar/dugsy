<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequiredDocument extends Model
{
    protected $fillable = [
        'school_id','name','description','is_mandatory',
        'applies_to','applies_to_levels','order','is_active',
    ];
    protected $casts = [
        'is_mandatory'      => 'boolean',
        'is_active'         => 'boolean',
        'applies_to_levels' => 'array',
    ];
    public function school() { return $this->belongsTo(School::class); }
    public function studentDocuments() { return $this->hasMany(StudentDocument::class); }
}
