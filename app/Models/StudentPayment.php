<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class StudentPayment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id', 'payment_receipt_id', 'student_invoice_id',
        'amount', 'method', 'reference', 'paid_at', 'received_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount'  => 'integer',
    ];

    public function receipt() { return $this->belongsTo(PaymentReceipt::class, 'payment_receipt_id'); }
    public function invoice() { return $this->belongsTo(StudentInvoice::class, 'student_invoice_id'); }
    public function receivedBy() { return $this->belongsTo(Staff::class, 'received_by'); }
}