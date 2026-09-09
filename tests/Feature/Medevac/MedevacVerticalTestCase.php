<?php

namespace Tests\Feature\Medevac;

use App\Models\MedevacPoster;
use App\Models\ScoutingReport;
use App\Support\CurrentProduction;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * Base del vertical PÓSTER MEDEVAC (delta #46), primera plantilla del MOTOR DE DOCUMENTOS.
 *
 * NO es una clase de prueba (no termina en Test.php → phpunit no la colecta): helpers de rol,
 * fábrica de scouting (fuente autoritativa del póster) y sellado a nivel modelo.
 *
 * EMITE SÓLO EL SAFETY: permiso PROPIO medevac.issue (no se recicla ninguno). El verificador
 * público del póster ('mdvc') vive aparte, sin sesión. Cada emisión CONGELA su payload y se sella.
 */
abstract class MedevacVerticalTestCase extends QaTestCase
{
    /** Roles con medevac.issue (MedevacPermissionsSeeder). super-admin además por Gate::before. */
    protected function grantedRoles(): array
    {
        return ['safety-officer', 'super-admin'];
    }

    /** Roles SIN medevac.issue. line-producer discrimina: emite permisos pero NO el póster MEDEVAC. */
    protected function deniedRoles(): array
    {
        return ['line-producer', 'coordinator', 'hod', 'medic', 'crew', 'auditor'];
    }

    /** Un scouting con lo mínimo que el póster renderiza (locación + hospital + distancia + GPS). */
    protected function makeScouting(array $attrs = []): ScoutingReport
    {
        return ScoutingReport::create(array_merge([
            'production_id'        => CurrentProduction::id(),
            'location_name'        => 'Locación MEDEVAC ' . Str::random(6),
            'location_address'     => 'Av. Set 100',
            'latitude'             => 19.4326077,
            'longitude'            => -99.1332080,
            'nearest_hospital'     => 'Hospital QA Central',
            'hospital_address'     => 'Calle Salud 1',
            'hospital_distance_km' => 8.4,
            'hospital_eta'         => '14 min',
            'emergency_phone'      => '911',
        ], $attrs));
    }

    /** Sella un MedevacPoster A NIVEL MODELO (sin controlador → sin sesión). */
    protected function sealPoster(array $attrs = [], array $payload = []): MedevacPoster
    {
        $user     = $this->makeUser('safety-officer');
        $scouting = $this->makeScouting();

        $poster = MedevacPoster::create(array_merge([
            'production_id'      => $scouting->production_id,
            'scouting_report_id' => $scouting->id,
            'revision'           => 1,
            'location_label'     => $scouting->location_name,
            'payload'            => array_merge([
                'version'  => 2,
                'location' => ['name' => $scouting->location_name, 'address' => 'Av. Set 100'],
                'hospital' => ['name' => 'Hospital QA Central', 'address' => 'Calle Salud 1'],
                'contacts' => [
                    ['key' => 'set_medic', 'label' => 'Set Medic', 'name' => 'CONFIDENCIAL-MEDICO', 'phone' => '5550001111'],
                ],
            ], $payload),
            'issued_by_name'     => 'CONFIDENCIAL-EMISOR',
            'issued_at'          => now(),
            'is_active'          => 1,
        ], $attrs));

        $poster->refresh();
        $poster->signDocument($user);

        return $poster;
    }
}
