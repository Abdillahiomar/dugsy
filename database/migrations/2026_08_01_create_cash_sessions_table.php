<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->dateTime('opened_at');
            $t->unsignedBigInteger('opening_float')->default(0);  // fond de caisse
            $t->dateTime('closed_at')->nullable();
            $t->unsignedBigInteger('expected_cash')->nullable();  // théorique
            $t->unsignedBigInteger('counted_cash')->nullable();   // compté physiquement
            $t->bigInteger('variance')->nullable();               // SIGNÉ : counted - expected
            $t->string('status', 12)->default('open');            // open | closed
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['school_id', 'status']);
            $t->index(['school_id', 'user_id', 'status']);
        });
    }

    
        public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
    
};