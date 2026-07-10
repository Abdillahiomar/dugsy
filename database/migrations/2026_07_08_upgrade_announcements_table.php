<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Renommer created_by → user_id (même colonne, différent nom)
            // On ajoute user_id et on garde created_by pour ne pas casser
            $table->foreignId('user_id')
                ->nullable()
                ->after('school_id')
                ->constrained('users')
                ->nullOnDelete();

            // Colonnes manquantes
            $table->json('target_roles')->default('["all"]')->after('content');
            $table->boolean('is_pinned')->default(false)->after('target_roles');
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->string('file_path')->nullable()->after('expires_at');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_size')->nullable()->after('file_name');
        });

        // Migrer les données : copier created_by → user_id
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE announcements SET user_id = created_by WHERE user_id IS NULL'
        );

        // Migrer audience → target_roles
        // audience: 'all' | 'teachers' | 'parents' | 'students'
        $map = [
            'all'      => '["all"]',
            'teachers' => '["enseignant"]',
            'parents'  => '["parent"]',
            'students' => '["parent"]', // parents au nom des élèves
        ];

        foreach ($map as $old => $new) {
            \Illuminate\Support\Facades\DB::statement(
                "UPDATE announcements SET target_roles = '$new' WHERE audience = '$old'"
            );
        }
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id', 'target_roles', 'is_pinned',
                'expires_at', 'file_path', 'file_name', 'file_size',
            ]);
        });
    }
};