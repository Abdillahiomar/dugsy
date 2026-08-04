<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->string('type', 30);                       // 'receipt', 'invoice'
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('last_number')->default(0);
            $t->timestamps();
            $t->unique(['school_id', 'type', 'year']);
        });
    }

    
        public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
    
};