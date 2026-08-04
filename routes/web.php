<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\BulletinController;
use App\Http\Controllers\ReceiptController;
//use Livewire\Livewire;


Route::get('/favicon.ico', function () {
    $school = auth()->user()?->school;

    if ($school?->logo_path) {
        $path = public_path('storage/schools/logos/' . basename($school->logo_path));

        if (file_exists($path)) {
            return response(file_get_contents($path), 200)
                ->header('Content-Type', mime_content_type($path));
        }
    }

    // Fallback
    $default = public_path('favicon-default.ico');
    if (file_exists($default)) {
        return response(file_get_contents($default), 200)
            ->header('Content-Type', 'image/x-icon');
    }

    abort(404);
})->middleware('auth');

Route::view('/', 'welcome')->name('home');


Route::middleware(['auth', 'verified'])->group(function () {

    // ── Accessible à tous les rôles connectés ─────────────────────
    //Volt::route('dashboard', 'dashboard')->name('dashboard');

    

    Route::get('/dashboard', \App\Livewire\Dashboard\DashboardPage::class)
    ->name('dashboard')
    ->middleware(['auth','verified']);

     Volt::route('students/enroll', 'students.enroll')->name('students.enroll');
    // ── Élèves ─────────────────────────────────────────────────────
    Route::middleware('can:students.view')->group(function () {
        Volt::route('students', 'students.index')->name('students.index');
        Volt::route('students/{student}', 'students.show')->name('students.show');
    });

    Route::middleware('can:students.show')->group(function () {
        Volt::route('parent/students/{student}', 'students.show')->name('parent_students.show');
    });

    Route::middleware('auth')->group(function () {
        Volt::route('parent/children', 'parent.children')->name('parent.children');
    });

    Route::middleware('can:students.enroll')->group(function () {
        //Volt::route('students/enroll', 'students.enroll')->name('students.enroll');
        Volt::route('students/{student}/reenroll', 'students.reenroll')->name('students.reenroll');
    });

   

    Route::middleware('can:students.edit')->group(function () {
        Volt::route('students/{student}/edit', 'students.edit')->name('students.edit');
    });

    // ── Classes ────────────────────────────────────────────────────
    Route::middleware('can:classes.view')->group(function () {
        Volt::route('classes', 'classes.index')->name('classes.index');
        Volt::route('classes/{schoolClass}/subjects', 'classes.subjects')->name('classes.subjects');
    });

    Route::middleware('can:subjects.view')->group(function () {
        Volt::route('subjects', 'matieres.index')->name('subjects.index');
    });

    // ── Notes ──────────────────────────────────────────────────────
    Route::middleware('can:grades.view')->group(function () {
        Volt::route('grades', 'grades.index')->name('grades.index');
    });

    Route::middleware('can:bulletins.view')->group(function () {
        Volt::route('bulletins/list', 'bulletins.index')->name('bulletins.index');
        Volt::route('bulletins/class/{schoolClass}', 'bulletins.class')->name('bulletins.class');
        Volt::route('students/{student}/bulletins/{bulletin}', 'bulletins.show')->name('bulletins.show');
        Route::get('students/{student}/bulletins/{bulletin}/pdf',
            [App\Http\Controllers\BulletinController::class, 'pdf'])->name('bulletins.pdf');
    });


    // PDF individuel
    Route::get('bulletins/{student}/{bulletin}/pdf',
        [BulletinController::class, 'pdf']
    )->name('bulletins.pdf')->middleware('can:bulletins.view');

    // PDF groupé — TOUS les bulletins d'une classe en un seul fichier
    Route::get('bulletins/batch/{schoolClass}/{period}',
        [BulletinController::class, 'batchPdf']
    )->name('bulletins.batch-pdf')->middleware('can:bulletins.generate');

    // ── Absences ───────────────────────────────────────────────────
    Route::middleware('can:absences.view')->group(function () {
        Volt::route('absences', 'absences.index')->name('absences.index');
    });

    // ── Finances ───────────────────────────────────────────────────
   

    Route::middleware(['auth', 'verified'])->prefix('finances')->name('finances.')->group(function () {
        Volt::route('/', 'finance.dashboard')->name('index')->middleware('can:finance.view');
        Volt::route('/encaissement', 'finance.collect')->name('collect');
        Volt::route('/journal', 'finance.cashbook')->name('cashbook')->middleware('can:finance.view');
        Volt::route('/impayes', 'finance.receivables')->name('receivables')->middleware('can:finance.view');
        Route::get('/recus/{receipt}', ReceiptController::class)->name('receipt')->middleware('can:finance.view');
    });;

    

    // ── Configuration (admin seulement) ───────────────────────────
    Route::middleware('can:school.settings')->group(function () {
        Volt::route('school-config/general', 'school-general')->name('school-config.general');
    });

    Route::middleware('can:fees.manage')->group(function () {
        Volt::route('school-config/fees', 'fee-settings')->name('school-config.fees');
    });

    // ── Personnel ──────────────────────────────────────────────────
    Route::middleware('can:staff.view')->group(function () {
        Volt::route('staff', 'staff.index')->name('staff.index');
        Volt::route('staff/{staff}/edit', 'staff.edit')->name('staff.edit');
    });

    // ── Utilisateurs ───────────────────────────────────────────────
    Route::middleware('can:users.view')->group(function () {
        Volt::route('users', 'users.index')->name('users.index');
    });

    // ── Années académiques ─────────────────────────────────────────
    Route::middleware('can:academic_years.view')->group(function () {
        Volt::route('academic-years', 'academic-years.index')->name('academic-years.index');
    });


    Route::middleware('can:school.settings')->group(function () {
        Volt::route('school-config/general',   'school-general')       ->name('school-config.general');
        Volt::route('school-config/fees',      'fee-settings')         ->name('school-config.fees');
        Volt::route('school-config/admission', 'school-config.admission')->name('school-config.admission');
        Volt::route('school-config/grading',   'school-config.grading') ->name('school-config.grading');
    });


    Route::middleware('can:homeworks.view')->group(function () {
        Volt::route('homeworks', 'homeworks.index')->name('homeworks.index');
        Volt::route('homeworks/{homework}', 'homeworks.show')->name('homeworks.show');
    });

    Route::middleware('can:announcements.view')->group(function () {
        Volt::route('announcements', 'announcements.index')->name('announcements.index');
        Volt::route('announcements/{announcement}', 'announcements.show')->name('announcements.show');
    });


    Route::middleware('can:timetable.view')->group(function () {
        Volt::route('timetable', 'timetable.index')->name('timetable.index');
    });

    Route::middleware('can:events.view')->group(function () {
        Volt::route('calendar', 'calendar.index')->name('calendar.index');
    });
});




require __DIR__.'/settings.php';
