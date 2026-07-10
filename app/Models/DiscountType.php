<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DiscountType extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id', 'name', 'type', 'value',
        'applies_to', 'is_social', 'requires_approval',
        'is_cumulative', 'is_active',
    ];

    protected $casts = [
        'is_social'          => 'boolean',
        'requires_approval'  => 'boolean',
        'is_cumulative'      => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function getFormattedValueAttribute(): string
    {
        return $this->type === 'percentage'
            ? "{$this->value}%"
            : number_format($this->value, 0, ',', ' ') . ' DJF';
    }
}
