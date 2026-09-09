<?php

namespace App\Policies;

use App\Models\Payee;
use App\Models\User;

/**
 * PASO 4 · VISIBILIDAD de "quien cobra". Un solo criterio por objeto, DERIVADO de
 * {@see Payee::scopeVisibleTo()} (fuente única): "quien contrata es quien ve" + la liga de
 * crew por departamento, con bypass de `crew.view.all-departments` y super-admin por
 * Gate::before. La usan el detalle del payee, el serve gateado de documentos y la captura por
 * quien contrata (intake). NO mezcla el eje de "aislamiento por propiedad" (no existe aún).
 */
class PayeePolicy
{
    /** Ver la ficha del payee (y, por extensión, sus documentos privados). */
    public function view(User $user, Payee $payee): bool
    {
        return $payee->isVisibleTo($user);
    }

    /** Capturar/editar el intake por quien contrata (mismo criterio que ver). */
    public function capture(User $user, Payee $payee): bool
    {
        return $payee->isVisibleTo($user);
    }
}
