<?php

namespace Tests\Feature\Calendario;

use App\Models\Production;
use App\Models\ShootDay;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentProduction;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * CRUD de unidades: la principal siempre está (NULL), el alta NO hace backfill, y la baja es por
 * desactivación sin tocar los documentos de la unidad.
 */
class UnitCrudTest extends QaTestCase
{
    private function admin(): User
    {
        $u = $this->makeUser('super-admin');
        try {
            $u->givePermissionTo('settings.manage');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }

        return $u;
    }

    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'start_date' => '2026-10-05'])->save();
        CurrentProduction::forget();

        return $prod;
    }

    public function test_la_pagina_lista_la_unidad_principal(): void
    {
        $this->prod();
        $this->actingAs($this->admin())
            ->get(route('production.units.index'))
            ->assertOk()
            ->assertSee('Unidades')
            ->assertSee('Unidad principal');
    }

    public function test_alta_de_unidad_no_hace_backfill(): void
    {
        $prod = $this->prod();
        // Días de la principal (unit_id NULL) ya existentes.
        ShootDay::create(['production_id' => $prod->id, 'unit_id' => null, 'shoot_date' => '2026-10-05', 'is_shoot_day' => true]);
        ShootDay::create(['production_id' => $prod->id, 'unit_id' => null, 'shoot_date' => '2026-10-06', 'is_shoot_day' => true]);

        $this->actingAs($this->admin())
            ->post(route('production.units.store'), ['name' => 'Segunda unidad'])
            ->assertRedirect(route('production.units.index'));

        $this->assertSame(1, Unit::count(), 'Se creó la unidad.');
        // NADA se movió: los días existentes siguen en la principal (unit_id NULL).
        $this->assertSame(2, ShootDay::whereNull('unit_id')->count(), 'El alta NO hizo backfill.');
        $this->assertSame(0, ShootDay::whereNotNull('unit_id')->count());
    }

    public function test_desactivar_una_unidad_no_toca_sus_documentos(): void
    {
        $prod = $this->prod();
        $u    = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        $doc  = ShootDay::create(['production_id' => $prod->id, 'unit_id' => $u->id, 'shoot_date' => '2026-11-16', 'is_shoot_day' => true]);

        $this->actingAs($this->admin())
            ->post(route('production.units.toggle', $u->id))
            ->assertRedirect(route('production.units.index'));

        $this->assertFalse($u->fresh()->is_active, 'La unidad quedó desactivada.');
        $this->assertSame((int) $u->id, (int) $doc->fresh()->unit_id, 'El documento CONSERVA su unidad (no se tocó).');
    }
}
