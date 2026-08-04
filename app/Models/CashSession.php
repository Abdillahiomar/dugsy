<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CashSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id', 'user_id', 'opened_at', 'opening_float',
        'closed_at', 'expected_cash', 'counted_cash', 'variance', 'status', 'notes',
    ];

    protected $casts = [
        'opened_at'     => 'datetime',
        'closed_at'     => 'datetime',
        'opening_float' => 'integer',
        'expected_cash' => 'integer',
        'counted_cash'  => 'integer',
        'variance'      => 'integer',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function receipts() { return $this->hasMany(PaymentReceipt::class); }

    public function isOpen(): bool { return $this->status === 'open'; }

    /** Espèces théoriques : fond de caisse + encaissements cash non annulés. */
    public function expectedCash(): int
    {
        return $this->opening_float + (int) $this->receipts()
            ->valid()->where('method', 'cash')->sum('amount');
    }
}