<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_fee_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();

            // Frais d'inscription (one-time à l'inscription initiale)
            $table->unsignedInteger('inscription_fee')->default(0);

            // Frais de réinscription (chaque nouvelle année)
            $table->unsignedInteger('reinscription_fee')->default(0);

            // Méthodes de paiement acceptées
            $table->json('payment_methods')->nullable();
            // ex: ["especes", "d-money", "virement", "cheque"]

            // Exonération sociale
            $table->boolean('allow_social_exemption')->default(false);

            // Remises activées
            $table->boolean('allow_discounts')->default(true);

            // Pénalité de retard (% par mois)
            $table->unsignedTinyInteger('late_fee_percentage')->default(0);

            // Notes/conditions générales
            $table->text('terms')->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_fee_configs');
    }
};
