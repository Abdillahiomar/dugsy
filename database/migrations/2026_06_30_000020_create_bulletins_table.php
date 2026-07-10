<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_school_year_id')->constrained()->cascadeOnDelete();
            $table->string('period'); // trimestre 1, trimestre 2...
            $table->decimal('average', 5, 2)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->text('general_comment')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_school_year_id', 'period'], 'bulletin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
    }
};
