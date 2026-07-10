<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_school_year_id', 'fee_structure_id', 'invoice_number',
        'amount_due', 'amount_paid', 'issued_at', 'due_at', 'status',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at' => 'date',
    ];

    public function studentSchoolYear()
    {
        return $this->belongsTo(StudentSchoolYear::class);
    }

    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function payments()
    {
        return $this->hasMany(StudentPayment::class);
    }

    public function balance(): int
    {
        return $this->amount_due - $this->amount_paid;
    }
}
