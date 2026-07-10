<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InstallmentPlan extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id', 'name', 'installments_count', 'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(InstallmentPlanItem::class)->orderBy('order');
    }

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }
}
