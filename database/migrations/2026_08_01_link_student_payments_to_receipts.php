<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
{
    Schema::table('student_payments', function (Blueprint $t) {
        $t->foreignId('payment_receipt_id')->nullable()->after('school_id')
          ->constrained()->cascadeOnDelete();
    });

    // Un reçu rétroactif par paiement historique
    $payments = DB::table('student_payments as sp')
        ->join('student_invoices as si', 'si.id', '=', 'sp.student_invoice_id')
        ->join('student_school_years as ssy', 'ssy.id', '=', 'si.student_school_year_id')
        ->whereNull('sp.payment_receipt_id')
        ->select('sp.*', 'si.academic_year_id', 'ssy.student_id')
        ->orderBy('sp.id')
        ->get();

    $counters = [];
    foreach ($payments as $p) {
        $y = (int) date('Y', strtotime($p->paid_at));
        $key = $p->school_id . '-' . $y;
        $counters[$key] = ($counters[$key] ?? 0) + 1;

        $receiptId = DB::table('payment_receipts')->insertGetId([
            'school_id'        => $p->school_id,
            'academic_year_id' => $p->academic_year_id,
            'student_id'       => $p->student_id,
            'receipt_number'   => sprintf('REC-%d-%05d', $y, $counters[$key]),
            'amount'           => $p->amount,
            'method'           => $p->method ?? 'cash',
            'reference'        => $p->reference ?? null,
            'paid_at'          => $p->paid_at,
            'received_by'      => DB::table('users')->where('school_id', $p->school_id)->value('id'),
            'note'             => 'Reçu reconstitué lors de la migration',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        DB::table('student_payments')->where('id', $p->id)
          ->update(['payment_receipt_id' => $receiptId]);
    }

    // Amorcer document_sequences pour ne pas réutiliser des numéros
    foreach ($counters as $key => $last) {
        [$schoolId, $y] = explode('-', $key);
        DB::table('document_sequences')->updateOrInsert(
            ['school_id' => $schoolId, 'type' => 'receipt', 'year' => $y],
            ['last_number' => $last, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    Schema::table('student_payments', function (Blueprint $t) {
        $t->foreignId('payment_receipt_id')->nullable(false)->change();
    });
}
    
};