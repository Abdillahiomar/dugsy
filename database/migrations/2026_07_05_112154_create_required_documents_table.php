<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('required_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('name');               // "Acte de naissance", "Carnet de santé"...
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);  // obligatoire ou optionnel
            $table->string('applies_to')->default('all');    // all | new | reenroll
            $table->json('applies_to_levels')->nullable();   // null = tous les niveaux
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Table pivot : documents fournis par élève lors de l'inscription
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('required_document_id')->constrained()->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending'); // pending | provided | rejected
            $table->string('rejected_reason')->nullable();
            $table->timestamp('provided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('required_documents');
        Schema::dropIfExists('student_documents');
    }
};
