<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolAdmissionConfig extends Model
{
    protected $fillable = [
        'school_id','min_age_years','max_age_years',
        'requires_entrance_test','entrance_test_description',
        'enforce_capacity','priority_siblings','priority_staff_children',
        'admission_conditions','enrollment_open_from','enrollment_open_until',
        'is_enrollment_open',
    ];
    protected $casts = [
        'requires_entrance_test'  => 'boolean',
        'enforce_capacity'        => 'boolean',
        'priority_siblings'       => 'boolean',
        'priority_staff_children' => 'boolean',
        'is_enrollment_open'      => 'boolean',
        'enrollment_open_from'    => 'date',
        'enrollment_open_until'   => 'date',
    ];
    public function school() { return $this->belongsTo(School::class); }
}
