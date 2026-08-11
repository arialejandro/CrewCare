<?php

namespace Tests\Feature\Pae;

use App\Models\EmergencyActionPlan;
use App\Models\HazardEvent;
use App\Models\ScoutingReport;
use App\Support\CurrentProduction;
use App\Support\EmergencyActionPlanBuilder;
use App\Support\PaeOrgChart;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * Base del vertical PAE · PLAN DE ATENCIÓN A EMERGENCIAS (2026-08-06).
 *
 * NO es una clase de prueba (no termina en Test.php). Fábrica de scoutings elegibles y de
 * PAE sellados (a nivel modelo, como el controlador: builder → payload congelado → firma).
 *
 * El módulo va con un ÚNICO permiso (pae.issue), repartido por PaePermissionsSeeder a
 * safety-officer y super-admin. NADIE más (ni line-producer): emitir el plan de emergencias
 * es acto del safety.
 */
abstract class PaeVerticalTestCase extends QaTestCase
{
    /** Roles con pae.issue (ver PaePermissionsSeeder). */
    protected function grantedRoles(): array
    {
        return ['safety-officer', 'super-admin'];
    }

    /** Roles SIN pae.issue (los otros 6 de la matriz de fábrica). */
    protected function deniedRoles(): array
    {
        return ['line-producer', 'coordinator', 'hod', 'medic', 'crew', 'auditor'];
    }

    /** Un scouting con hospital + (opcional) un riesgo evaluado, para el bloque de locación. */
    protected function makeScouting(array $overrides = []): ScoutingReport
    {
        $eid = (int) HazardEvent::query()->orderBy('id')->value('id');

        return ScoutingReport::create(array_merge([
            'location_name'    => 'Loc PAE ' . Str::random(6),
            'location_address' => 'Av. QA 100',
            'production_id'    => CurrentProduction::id(),
            'nearest_hospital' => 'Hospital QA Central',
            'hospital_address' => 'Calle Salud 5',
            'latitude'         => 19.4326077,
            'longitude'        => -99.1332080,
            'risk_assessment'  => [[
                'event_id'   => $eid,
                'event_name' => 'Riesgo eléctrico QA',
                'key'        => 'electrical',
                'rating'     => 'Alto',
            ]],
        ], $overrides));
    }

    /** Payload base del formulario POST /pae (una locación). */
    protected function storePayload(array $scoutingIds, array $overrides = []): array
    {
        $contacts = [];
        foreach (PaeOrgChart::SLOTS as $slot) {
            $contacts[$slot['key']] = ['name' => '', 'phone' => '', 'radio' => ''];
        }
        // Un contacto real para el organigrama congelado.
        $contacts['coordinador_emergencia'] = ['name' => 'Safety QA', 'phone' => '5550001', 'radio' => 'CH1'];

        return array_merge([
            'scoutings' => $scoutingIds,
            'shoot_day' => 7,
            'plan_date' => now()->toDateString(),
            'unit_name' => 'Unidad A',
            'contacts'  => $contacts,
        ], $overrides);
    }

    /**
     * Sella un PAE a nivel modelo (como el controlador: builder → create → refresh → firma).
     * Devuelve el plan sellado.
     */
    protected function sealPlan(array $scoutingIds = [], array $opts = []): EmergencyActionPlan
    {
        $user = $this->makeUser('safety-officer');

        if (empty($scoutingIds)) {
            $scoutingIds = [$this->makeScouting()->id];
        }
        $ordered = ScoutingReport::whereIn('id', $scoutingIds)->get()
            ->sortBy(fn ($s) => array_search($s->id, $scoutingIds))->values()->all();

        $shootDay = $opts['shoot_day'] ?? 5;
        $contacts = [];
        foreach (PaeOrgChart::SLOTS as $slot) {
            $contacts[] = ['key' => $slot['key'], 'label' => $slot['label'], 'name' => '', 'phone' => '', 'radio' => ''];
        }
        $contacts[0]['name'] = $opts['contact_name'] ?? 'Safety QA';

        $payload = EmergencyActionPlanBuilder::build($ordered, [
            'shoot_day' => $shootDay,
            'plan_date' => now()->toDateString(),
            'unit_name' => $opts['unit_name'] ?? 'Unidad A',
            'contacts'  => $contacts,
        ]);

        $plan = EmergencyActionPlan::create([
            'production_id'  => $ordered[0]->production_id ?: CurrentProduction::id(),
            'shoot_day'      => $shootDay,
            'plan_date'      => now()->toDateString(),
            'unit_name'      => $opts['unit_name'] ?? 'Unidad A',
            'plan_label'     => $opts['plan_label'] ?? ('Día ' . $shootDay),
            'payload'        => $payload,
            'issued_by_id'   => $user->id,
            'issued_by_name' => $user->name,
            'issued_at'      => now(),
            'is_active'      => 1,
        ]);

        $plan->refresh();
        $plan->signDocument($user);

        return $plan->fresh();
    }
}
