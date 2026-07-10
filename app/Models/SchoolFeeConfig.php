<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SchoolFeeConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id', 'academic_year_id',
        'inscription_fee', 'reinscription_fee',
        'payment_methods', 'allow_social_exemption',
        'allow_discounts', 'late_fee_percentage', 'terms',
    ];

    protected $casts = [
        'payment_methods'        => 'array',
        'allow_social_exemption' => 'boolean',
        'allow_discounts'        => 'boolean',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
