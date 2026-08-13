<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        // (2026-07-12) MÓDULO 13: silo médico del reporte de lesión (viewMedical).
        \App\Models\InjuryReport::class => \App\Policies\InjuryReportPolicy::class,
        // (2026-07-20) PASO Injury: propiedad del anexo médico (create=médico; edit/delete=dueño).
        \App\Models\Addendum::class => \App\Policies\AddendumPolicy::class,
        // (2026-08-13) PASO 4 · quien cobra: visibilidad "quien contrata es quien ve" + serve gateado.
        \App\Models\Payee::class => \App\Policies\PayeePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // RBAC foundation: super-admin implicitly passes EVERY permission check
        // (spatie-recommended pattern). New code checks permissions via @can / $user->can;
        // super-admin short-circuits to true so newly added permissions need no re-seed.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
