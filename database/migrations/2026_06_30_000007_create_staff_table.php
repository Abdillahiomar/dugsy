<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('matricule')->nullable();
            $table->string('position')->nullable(); // enseignant, comptable, surveillant...
            $table->date('hired_at')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'matricule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
