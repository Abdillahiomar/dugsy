<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // ex: "Exonération sociale", "Remise fratrie", "Remise personnel", "Bourse"

            // Type : percentage (%) ou fixed (montant fixe en DJF)
            $table->string('type')->default('percentage');

            // Valeur (ex: 50 pour 50% ou 30000 pour 30 000 DJF)
            $table->unsignedInteger('value')->default(0);

            // S'applique sur : tuition (scolarité), inscription, both
            $table->string('applies_to')->default('tuition');

            // Exonération sociale = nécessite pièce justificative
            $table->boolean('is_social')->default(false);

            // Nécessite approbation de l'admin avant application
            $table->boolean('requires_approval')->default(false);

            // Peut être cumulée avec d'autres remises
            $table->boolean('is_cumulative')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_types');
    }
};
