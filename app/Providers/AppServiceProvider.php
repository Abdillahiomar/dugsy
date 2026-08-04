<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use APP\Models;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Volt\Volt;
use Livewire\Livewire;  

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Student::class => \App\Policies\StudentPolicy::class,];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
        Volt::mount([
            resource_path('views/livewire'),
        ]);

        Livewire::component('dashboard.widgets.stats-grid',       \App\Livewire\Dashboard\Widgets\StatsGrid::class);
        Livewire::component('dashboard.widgets.revenue-chart',    \App\Livewire\Dashboard\Widgets\RevenueChart::class);
        Livewire::component('dashboard.widgets.attendance-chart', \App\Livewire\Dashboard\Widgets\AttendanceChart::class);
        Livewire::component('dashboard.widgets.payment-chart',    \App\Livewire\Dashboard\Widgets\PaymentChart::class);
        Livewire::component('dashboard.widgets.enrollment-chart', \App\Livewire\Dashboard\Widgets\EnrollmentChart::class);
        Livewire::component('dashboard.widgets.recent-payments',  \App\Livewire\Dashboard\Widgets\RecentPayments::class);
        Livewire::component('dashboard.widgets.top-debtors',      \App\Livewire\Dashboard\Widgets\TopDebtors::class);

        // app/Providers/AppServiceProvider.php — dans boot()
        if (auth()->check() && auth()->user()->school_id) {
            $smtp = \App\Models\SchoolSmtpConfig::where('school_id', auth()->user()->school_id)
                ->where('is_active', true)
                ->first();

            if ($smtp) {
                config([
                    'mail.mailers.smtp.host'       => $smtp->host,
                    'mail.mailers.smtp.port'       => $smtp->port,
                    'mail.mailers.smtp.encryption' => $smtp->encryption !== 'none' ? $smtp->encryption : null,
                    'mail.mailers.smtp.username'   => $smtp->username,
                    'mail.mailers.smtp.password'   => $smtp->password,
                    'mail.from.address'            => $smtp->from_email,
                    'mail.from.name'               => $smtp->from_name,
                ]);
            }
        }


    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
