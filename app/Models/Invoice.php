<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id', 'subscription_id', 'payment_id', 'invoice_number',
        'amount', 'issued_at', 'due_at', 'status', 'pdf_path',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at' => 'date',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
