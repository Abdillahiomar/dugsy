<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('academic_year_id')->constrained();
            $t->foreignId('student_id')->constrained();
            $t->foreignId('cash_session_id')->nullable()->constrained();
            $t->string('receipt_number', 30);
            $t->unsignedBigInteger('amount');
            $t->string('method', 20);                 // cash | dmoney | bank_transfer | cheque
            $t->string('reference')->nullable();      // n° transaction D-Money, n° chèque
            $t->dateTime('paid_at');
            $t->foreignId('received_by')->constrained('users');
            $t->text('note')->nullable();
            $t->dateTime('voided_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('users');
            $t->string('void_reason')->nullable();
            $t->timestamps();

            $t->unique(['school_id', 'receipt_number']);
            $t->index(['school_id', 'paid_at']);
            $t->index(['school_id', 'academic_year_id', 'voided_at']);
            $t->index(['school_id', 'student_id']);
        });
    }

    
        public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
    
};