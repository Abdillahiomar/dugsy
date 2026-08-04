<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReceipt extends Model
{
    use HasFactory, BelongsToTenant;

    public const METHODS = [
        'cash'          => 'Espèces',
        'dmoney'        => 'D-Money',
        'bank_transfer' => 'Virement bancaire',
        'cheque'        => 'Chèque',
    ];

    protected $fillable = [
        'school_id', 'academic_year_id', 'student_id', 'cash_session_id',
        'receipt_number', 'amount', 'method', 'reference', 'paid_at',
        'received_by', 'note', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'paid_at'   => 'datetime',
        'voided_at' => 'datetime',
        'amount'    => 'integer',
    ];

    public function student() { return $this->belongsTo(Student::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function cashSession() { return $this->belongsTo(CashSession::class); }
    public function receivedBy() { return $this->belongsTo(User::class, 'received_by'); }
    public function voidedBy() { return $this->belongsTo(User::class, 'voided_by'); }
    public function lines() { return $this->hasMany(StudentPayment::class, 'payment_receipt_id'); }

    public function isVoided(): bool { return $this->voided_at !== null; }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    /** Portée par défaut de TOUT reporting : un reçu annulé n'existe pas comptablement. */
    public function scopeValid($q) { return $q->whereNull('voided_at'); }
}