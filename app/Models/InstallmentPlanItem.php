<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentPlanItem extends Model
{
    protected $fillable = [
        'installment_plan_id', 'order', 'label', 'percentage', 'due_month', 'due_day',
    ];

    public function plan()
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }
}
