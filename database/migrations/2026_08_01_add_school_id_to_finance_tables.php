<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_invoices', function (Blueprint $t) {
            $t->foreignId('school_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $t->foreignId('academic_year_id')->nullable()->after('school_id')->constrained();
        });

        // Backfill depuis la chaîne existante
        DB::statement("
            UPDATE student_invoices si
            JOIN student_school_years ssy ON ssy.id = si.student_school_year_id
            JOIN students s ON s.id = ssy.student_id
            SET si.school_id = s.school_id,
                si.academic_year_id = ssy.academic_year_id
        ");

        Schema::table('student_invoices', function (Blueprint $t) {
            $t->foreignId('school_id')->nullable(false)->change();
            $t->foreignId('academic_year_id')->nullable(false)->change();
            $t->index(['school_id', 'academic_year_id', 'status'], 'si_school_year_status_idx');
            $t->index(['school_id', 'due_at', 'status'], 'si_school_due_status_idx');
        });

        Schema::table('student_payments', function (Blueprint $t) {
            $t->foreignId('school_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::statement("
            UPDATE student_payments sp
            JOIN student_invoices si ON si.id = sp.student_invoice_id
            SET sp.school_id = si.school_id
        ");

        Schema::table('student_payments', function (Blueprint $t) {
            $t->foreignId('school_id')->nullable(false)->change();
            $t->dateTime('paid_at')->change();
            $t->index(['school_id', 'paid_at'], 'sp_school_paid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $t) {
            $t->dropIndex('si_school_year_status_idx');
            $t->dropIndex('si_school_due_status_idx');
            $t->dropConstrainedForeignId('school_id');
            $t->dropConstrainedForeignId('academic_year_id');
        });
        Schema::table('student_payments', function (Blueprint $t) {
            $t->dropIndex('sp_school_paid_idx');
            $t->dropConstrainedForeignId('school_id');
        });
    }
};