<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Définition des permissions par module ──────────────────────
        $permissions = [

            // Élèves
            'students.view'     => 'Voir les élèves',
            'students.create'   => 'Inscrire un élève',
            'students.edit'     => 'Modifier un élève',
            'students.delete'   => 'Supprimer un élève',
            'students.enroll'   => 'Gérer les inscriptions',

            // Classes
            'classes.view'      => 'Voir les classes',
            'classes.manage'    => 'Gérer les classes',

            // Matières
            'subjects.view'     => 'Voir les matières',
            'subjects.manage'   => 'Gérer les matières',

            // Notes
            'grades.view'       => 'Voir les notes',
            'grades.enter'      => 'Saisir les notes',
            'grades.manage'     => 'Gérer toutes les notes',

            // Bulletins
            'bulletins.view'    => 'Voir les bulletins',
            'bulletins.generate'=> 'Générer les bulletins',

            // Absences
            'absences.view'     => 'Voir les absences',
            'absences.manage'   => 'Gérer les présences/absences',

            // Finances
            'finance.view'      => 'Voir les finances',
            'finance.collect'   => 'Encaisser des paiements',
            'finance.manage'    => 'Gérer les frais et factures',

            // Personnel
            'staff.view'        => 'Voir le personnel',
            'staff.manage'      => 'Gérer le personnel',

            // Années académiques
            'academic_years.view'   => 'Voir les années académiques',
            'academic_years.manage' => 'Gérer les années académiques',

            // Configuration école
            'school.settings'   => 'Configurer l\'école',
            'fees.manage'       => 'Gérer la configuration des frais',

            // Utilisateurs & Rôles
            'users.view'        => 'Voir les utilisateurs',
            'users.manage'      => 'Gérer les utilisateurs et rôles',

            // Annonces
            'announcements.view'   => 'Voir les annonces',
            'announcements.manage' => 'Gérer les annonces',
        ];

        foreach ($permissions as $name => $label) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['label' => $label]
            );
        }

        // ── Rôles et leurs permissions ─────────────────────────────────

        // ADMIN — accès total à l'école
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_keys($permissions));

        // DIRECTEUR — comme admin mais sans gestion utilisateurs
        $director = Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']);
        $director->syncPermissions([
            'students.view', 'students.create', 'students.edit', 'students.enroll',
            'classes.view', 'classes.manage',
            'subjects.view', 'subjects.manage',
            'grades.view', 'grades.manage',
            'bulletins.view', 'bulletins.generate',
            'absences.view', 'absences.manage',
            'finance.view', 'finance.manage',
            'staff.view', 'staff.manage',
            'academic_years.view', 'academic_years.manage',
            'school.settings', 'fees.manage',
            'users.view',
            'announcements.view', 'announcements.manage',
        ]);

        // COMPTABLE — gestion financière + lecture élèves
        $comptable = Role::firstOrCreate(['name' => 'comptable', 'guard_name' => 'web']);
        $comptable->syncPermissions([
            'students.view',
            'classes.view',
            'finance.view', 'finance.collect', 'finance.manage',
            'bulletins.view',
            'absences.view',
            'announcements.view',
        ]);

        // ENSEIGNANT — notes + absences de ses classes
        $teacher = Role::firstOrCreate(['name' => 'enseignant', 'guard_name' => 'web']);
        $teacher->syncPermissions([
            'students.view',
            'classes.view',
            'subjects.view',
            'grades.view', 'grades.enter',
            'bulletins.view', 'bulletins.generate',
            'absences.view', 'absences.manage',
            'announcements.view',
        ]);

        // SURVEILLANT — gestion des présences
        $surveillant = Role::firstOrCreate(['name' => 'surveillant', 'guard_name' => 'web']);
        $surveillant->syncPermissions([
            'students.view',
            'classes.view',
            'absences.view', 'absences.manage',
            'announcements.view',
        ]);

        // PARENT — accès limité à ses propres enfants
        $parent = Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        $parent->syncPermissions([
            'students.view',
            'bulletins.view',
            'finance.view',
            'absences.view',
            'announcements.view',
        ]);

        $this->command->info('✓ Rôles et permissions créés avec succès.');
    }
}
