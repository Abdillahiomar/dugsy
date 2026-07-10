<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_smtp_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();

            // Serveur SMTP
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption')->default('tls'); // tls, ssl, none
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // chiffré via $casts

            // Expéditeur
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();

            // Réponse à
            $table->string('reply_to_email')->nullable();

            // Test + statut
            $table->boolean('is_verified')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('is_active')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_smtp_configs');
    }
};
