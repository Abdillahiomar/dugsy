<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class StudentInvoice extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id', 'academic_year_id', 'student_school_year_id', 'fee_structure_id',
        'invoice_number', 'amount_due', 'amount_paid', 'issued_at', 'due_at', 'status',
    ];

    protected $casts = [
        'issued_at'   => 'date',
        'due_at'      => 'date',
        'amount_due'  => 'integer',
        'amount_paid' => 'integer',
    ];

    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function studentSchoolYear() { return $this->belongsTo(StudentSchoolYear::class); }
    public function feeStructure() { return $this->belongsTo(FeeStructure::class); }
    public function payments() { return $this->hasMany(StudentPayment::class); }

    public function balance(): int
    {
        return max(0, $this->amount_due - $this->amount_paid);
    }

    public function isSettled(): bool
    {
        return $this->balance() === 0;
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['pending', 'partial', 'overdue']);
    }
}
