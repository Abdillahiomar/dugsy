<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\School;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            // ── Maternelle ────────────────────────────────
            ['name' => 'Petite Section',  'cycle' => 'Maternelle', 'order' => 1],
            ['name' => 'Moyenne Section', 'cycle' => 'Maternelle', 'order' => 2],
            ['name' => 'Grande Section',  'cycle' => 'Maternelle', 'order' => 3],

            // ── Primaire ──────────────────────────────────
            ['name' => 'CP',  'cycle' => 'Primaire', 'order' => 4],
            ['name' => 'CE1', 'cycle' => 'Primaire', 'order' => 5],
            ['name' => 'CE2', 'cycle' => 'Primaire', 'order' => 6],
            ['name' => 'CM1', 'cycle' => 'Primaire', 'order' => 7],
            ['name' => 'CM2', 'cycle' => 'Primaire', 'order' => 8],

            // ── Collège ───────────────────────────────────
            ['name' => '6ème', 'cycle' => 'Collège', 'order' => 9],
            ['name' => '5ème', 'cycle' => 'Collège', 'order' => 10],
            ['name' => '4ème', 'cycle' => 'Collège', 'order' => 11],
            ['name' => '3ème', 'cycle' => 'Collège', 'order' => 12],

            // ── Lycée ─────────────────────────────────────
            ['name' => 'Seconde',   'cycle' => 'Lycée', 'order' => 13],
            ['name' => 'Première',  'cycle' => 'Lycée', 'order' => 14],
            ['name' => 'Terminale', 'cycle' => 'Lycée', 'order' => 15],
        ];

        School::all()->each(function (School $school) use ($levels) {
            foreach ($levels as $level) {
                Level::updateOrCreate(
                    ['school_id' => $school->id, 'name' => $level['name']],
                    ['order' => $level['order'], 'cycle' => $level['cycle']]
                );
            }
        });
    }
}