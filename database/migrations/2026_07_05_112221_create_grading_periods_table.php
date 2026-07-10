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
       Schema::create('grading_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();

            $table->string('period');           // "Trimestre 1", "Trimestre 2", "Trimestre 3"
            $table->date('open_from');          // début de la saisie
            $table->date('open_until');         // fin de la saisie
            $table->boolean('is_open')->default(false); // ouverture manuelle/fermeture
            $table->text('note')->nullable();   // message aux enseignants

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_periods');
    }
};
