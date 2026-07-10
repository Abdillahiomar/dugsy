<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Identité
            $table->string('short_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('short_name');
            $table->string('slogan')->nullable()->after('description');

            // Coordonnées complètes
            $table->string('address2')->nullable()->after('address');
            $table->string('city')->nullable()->after('address2');
            $table->string('country')->default('Djibouti')->after('city');
            $table->string('postal_code')->nullable()->after('country');
            $table->string('phone2')->nullable()->after('phone');
            $table->string('fax')->nullable()->after('phone2');
            $table->string('website')->nullable()->after('email');

            // Réseaux sociaux
            $table->string('facebook')->nullable()->after('website');
            $table->string('instagram')->nullable()->after('facebook');
            $table->string('twitter')->nullable()->after('instagram');

            // Académique
            $table->string('school_type')->nullable()->after('twitter');
            // ex: Privé, Public, Laïque, Islamique, International
            $table->string('ministry_code')->nullable()->after('school_type');
            $table->string('director_name')->nullable()->after('ministry_code');

            // Logo + bannière
            $table->string('banner_path')->nullable()->after('logo_path');

            // Couleurs de l'école (pour personnalisation future)
            $table->string('primary_color')->nullable()->after('banner_path');
            $table->string('secondary_color')->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'short_name', 'description', 'slogan',
                'address2', 'city', 'country', 'postal_code',
                'phone2', 'fax', 'website',
                'facebook', 'instagram', 'twitter',
                'school_type', 'ministry_code', 'director_name',
                'banner_path', 'primary_color', 'secondary_color',
            ]);
        });
    }
};
