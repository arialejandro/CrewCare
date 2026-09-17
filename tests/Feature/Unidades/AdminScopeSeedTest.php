<?php

namespace Tests\Feature\Unidades;

use App\Models\Production;
use Illuminate\Support\Facades\Schema;
use Tests\QaTestCase;

/**
 * UNIDADES · 2c §4 — el campo de ALCANCE ADMINISTRATIVO existe con default 'global' y NINGUNA rama cableada.
 * Es un seed: catálogo/contratos/padrón/contabilidad/clínico siguen globales; el día que haga falta separar
 * uno es trabajo de un módulo (ver el delta de la migración). No hay UI (un interruptor muerto es peor).
 */
class AdminScopeSeedTest extends QaTestCase
{
    public function test_el_campo_existe_con_default_global(): void
    {
        $this->assertTrue(Schema::hasColumn('productions', 'admin_scope'), 'El campo de alcance existe.');

        // Una producción nueva nace 'global' (default de columna).
        $prod = Production::query()->orderBy('id')->first();
        $this->assertSame('global', $prod->admin_scope, 'El default es global.');
    }
}
