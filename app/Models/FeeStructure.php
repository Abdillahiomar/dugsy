<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
    'school_id', 'academic_year_id', 'level_id', 'label',
    'amount', 'inscription_fee', 'reinscription_fee',
    'frequency', 'installment_plan_id', 'type',
];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function invoices()
    {
        return $this->hasMany(StudentInvoice::class);
    }

    public function installmentPlan()
    {
        return $this->belongsTo(\App\Models\InstallmentPlan::class);
    }
}
