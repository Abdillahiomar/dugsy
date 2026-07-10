<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();

            // Numéro de la tranche (1, 2, 3...)
            $table->unsignedTinyInteger('order');

            // Label affiché sur la facture
            $table->string('label');
            // ex: "1ère tranche", "Inscription + 1ère tranche", "Tranche de Janvier"

            // Pourcentage du montant total (toutes tranches = 100%)
            $table->unsignedTinyInteger('percentage');

            // Mois d'échéance (1=Jan ... 12=Dec) — relatif à l'année scolaire
            $table->unsignedTinyInteger('due_month')->nullable();

            // Jour du mois
            $table->unsignedTinyInteger('due_day')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plan_items');
    }
};
