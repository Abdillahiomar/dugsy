<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('matricule');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender')->nullable(); // M, F
            $table->string('photo_path')->nullable();
            $table->string('status')->default('active'); // active, transferred, graduated, dropped
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'matricule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
