<?php

namespace Tests\Feature\Unidades;

use App\Models\IssuedPermit;
use App\Models\Production;
use App\Models\Unit;
use App\Support\CurrentProduction;
use App\Support\CurrentUnit;
use Tests\QaTestCase;

/**
 * UNIDADES · 2b · dominio PERMISOS — la vigencia es POR UNIDAD. El número de día colisiona entre unidades
 * (el "día 3" de la 2ª unidad no es el "día 3" de la principal), así que IssuedPermit::vigenteFor scopea por
 * la unidad vigente. Con una sola unidad, idéntico a hoy (probado por el resto de la suite Permit).
 */
class PermitUnitVigenciaTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1])->save();
        CurrentProduction::forget();

        return $prod;
    }

    public function test_la_vigencia_del_permiso_es_por_unidad(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);

        // Dos permisos del MISMO código y MISMO número de día, en unidades distintas.
        IssuedPermit::create(['production_id' => $prod->id, 'unit_id' => null,     'permit_code' => 'HOT', 'permit_name' => 'Trabajo en caliente', 'shoot_day' => 3, 'is_active' => 1]);
        IssuedPermit::create(['production_id' => $prod->id, 'unit_id' => $u2->id,  'permit_code' => 'HOT', 'permit_name' => 'Trabajo en caliente', 'shoot_day' => 3, 'is_active' => 1]);

        // Vigente = principal → devuelve el de la principal (unit_id null).
        session()->forget(CurrentUnit::SESSION_KEY);
        CurrentUnit::forget();
        $vPrin = IssuedPermit::vigenteFor('HOT', 3);
        $this->assertNotNull($vPrin);
        $this->assertNull($vPrin->unit_id, 'La principal ve su permiso.');

        // Vigente = 2ª unidad → devuelve el de la 2ª unidad, no el de la principal.
        session()->put(CurrentUnit::SESSION_KEY, $u2->id);
        CurrentUnit::forget();
        $vU2 = IssuedPermit::vigenteFor('HOT', 3);
        $this->assertNotNull($vU2);
        $this->assertSame((int) $u2->id, (int) $vU2->unit_id, 'La 2ª unidad ve SU permiso, no el de la principal.');
    }
}
