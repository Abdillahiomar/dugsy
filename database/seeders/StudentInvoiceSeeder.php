<?php

namespace Database\Seeders;

use App\Models\FeeStructure;
use App\Models\StudentInvoice;
use App\Models\StudentPayment;
use App\Models\StudentSchoolYear;
use Illuminate\Database\Seeder;

class StudentInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        StudentSchoolYear::with('schoolClass.level', 'academicYear')->get()->each(function (StudentSchoolYear $ssy) {
            $fee = FeeStructure::where('academic_year_id', $ssy->academic_year_id)
                ->where('level_id', $ssy->schoolClass->level_id)
                ->first();

            if (! $fee) {
                return;
            }

            $invoice = StudentInvoice::create([
                'student_school_year_id' => $ssy->id,
                'fee_structure_id' => $fee->id,
                'invoice_number' => 'FACT-' . $ssy->id . '-' . now()->format('Y'),
                'amount_due' => $fee->amount,
                'amount_paid' => 0,
                'issued_at' => $ssy->enrolled_at,
                'due_at' => now()->addMonths(2),
                'status' => 'unpaid',
            ]);

            // 60% de chance que l'élève ait déjà payé une partie ou la totalité
            if (random_int(1, 100) <= 60) {
                $paidAmount = random_int(1, 100) <= 50
                    ? $fee->amount // paiement complet
                    : (int) ($fee->amount * 0.5); // paiement partiel

                StudentPayment::create([
                    'student_invoice_id' => $invoice->id,
                    'amount' => $paidAmount,
                    'method' => 'd-money',
                    'reference' => 'PAY-' . strtoupper(uniqid()),
                    'paid_at' => now()->subDays(random_int(1, 30)),
                ]);

                $invoice->update([
                    'amount_paid' => $paidAmount,
                    'status' => $paidAmount >= $fee->amount ? 'paid' : 'partially_paid',
                ]);
            }
        });
    }
}
