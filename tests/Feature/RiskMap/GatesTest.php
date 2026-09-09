<?php

namespace Tests\Feature\RiskMap;

/**
 * RBAC del MAPEO DE RIESGOS — un ÚNICO gate (permission:riskmap.issue) blinda TODO el
 * módulo, incluida la URL directa. Sólo safety-officer y super-admin lo tienen; los
 * otros 6 roles reciben 403. El invitado cae al login (auth corre antes del permiso).
 *
 * El verificador PÚBLICO (QR) NO va aquí: vive sin sesión y se prueba en PublicVerifierTest.
 */
class GatesTest extends RiskMapVerticalTestCase
{
    public static function grantedProvider(): array
    {
        return [['safety-officer'], ['super-admin']];
    }

    public static function deniedProvider(): array
    {
        return [['line-producer'], ['coordinator'], ['hod'], ['medic'], ['crew'], ['auditor']];
    }

    // ------------------------------------------------------------------ INDEX

    /** @dataProvider grantedProvider */
    public function test_index_abre_para_roles_con_permiso(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('riskmaps.index'))->assertOk();
    }

    /** @dataProvider deniedProvider */
    public function test_index_cerrado_para_roles_sin_permiso(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('riskmaps.index'))->assertForbidden();
    }

    // ------------------------------------------------------------- EDIT / DOC

    /** @dataProvider grantedProvider */
    public function test_editor_y_documento_abren_para_roles_con_permiso(string $role): void
    {
        $map = $this->makeDraftMap();
        $this->actingAsRole($role);

        $this->get(route('riskmaps.edit', $map->id))->assertOk();
        $this->get(route('riskmaps.document', $map->id))->assertOk();
    }

    /** @dataProvider deniedProvider */
    public function test_editor_y_documento_cerrados_para_roles_sin_permiso(string $role): void
    {
        $map = $this->makeDraftMap();
        $this->actingAsRole($role);

        $this->get(route('riskmaps.edit', $map->id))->assertForbidden();
        $this->get(route('riskmaps.document', $map->id))->assertForbidden();
    }

    // ----------------------------------------------------------------- STORE

    public function test_store_crea_borrador_y_salta_al_editor_para_safety(): void
    {
        $sc = $this->makeScouting();
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('riskmaps.store'), ['scouting_id' => $sc->id]);

        $map = \App\Models\RiskMap::latest('id')->first();
        $this->assertNotNull($map, 'El store debe persistir el borrador.');
        $resp->assertRedirect(route('riskmaps.edit', $map->id));
        $this->assertSame('draft', $map->status);
        $this->assertSame($sc->id, (int) $map->scouting_id);
    }

    /** @dataProvider deniedProvider */
    public function test_store_cerrado_para_roles_sin_permiso(string $role): void
    {
        $sc = $this->makeScouting();
        $this->actingAsRole($role);

        // El middleware corta ANTES del controlador: 403, y nada se persiste.
        $this->post(route('riskmaps.store'), ['scouting_id' => $sc->id])->assertForbidden();
        $this->assertDatabaseMissing('risk_maps', ['scouting_id' => $sc->id]);
    }

    // --------------------------------------------------------------- INVITADO

    public function test_rutas_de_edicion_exigen_sesion(): void
    {
        $map = $this->makeDraftMap();

        $this->get(route('riskmaps.index'))->assertRedirect(route('login'));
        $this->get(route('riskmaps.edit', $map->id))->assertRedirect(route('login'));
        $this->post(route('riskmaps.store'), ['scouting_id' => $map->scouting_id])
            ->assertRedirect(route('login'));
    }
}
