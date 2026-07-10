<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            // Plan d'échéancier par défaut pour ce niveau
            $table->foreignId('installment_plan_id')
                ->nullable()
                ->after('amount')
                ->constrained()
                ->nullOnDelete();

            // Type de frais : tuition (scolarité) ou other
            $table->string('type')->default('tuition')->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installment_plan_id');
            $table->dropColumn('type');
        });
    }
};
