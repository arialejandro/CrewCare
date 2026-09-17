<?php

namespace Tests\Feature\RiskMap;

use App\Models\HazardEvent;
use App\Models\RiskMap;
use App\Models\RiskMapMarker;
use App\Models\RiskMapView;
use App\Models\ScoutingReport;
use App\Support\CurrentProduction;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * Base del vertical MAPEO DE RIESGOS Y RECURSOS (delta #50).
 *
 * NO es una clase de prueba (no termina en Test.php → phpunit no la colecta): sólo
 * fábrica de filas SINTÉTICAS y helpers de rol. El módulo entero va con un ÚNICO
 * permiso (riskmap.issue), repartido por RiskMapPermissionsSeeder a safety-officer y
 * super-admin. El resto de los 8 roles NO lo tienen.
 */
abstract class RiskMapVerticalTestCase extends QaTestCase
{
    /** Roles con riskmap.issue (ver RiskMapPermissionsSeeder). */
    protected function grantedRoles(): array
    {
        return ['safety-officer', 'super-admin'];
    }

    /** Roles SIN riskmap.issue (los otros 6 de la matriz de fábrica). */
    protected function deniedRoles(): array
    {
        return ['line-producer', 'coordinator', 'hod', 'medic', 'crew', 'auditor'];
    }

    /**
     * Un scouting mínimo. Si $withHazard, su risk_assessment cita un HazardEvent real
     * (para poder colocar un marcador de peligro elegible). Devuelve el modelo.
     */
    protected function makeScouting(bool $withHazard = false, array $overrides = []): ScoutingReport
    {
        $ra = [];
        if ($withHazard) {
            $eid = (int) HazardEvent::query()->orderBy('id')->value('id');
            $ra = [[
                'event_id'   => $eid,
                'event_name' => 'Riesgo QA',
                'key'        => 'electrical',
                'rating'     => 'Alto',
            ]];
        }

        return ScoutingReport::create(array_merge([
            'location_name'    => 'Loc RiskMap ' . Str::random(6),
            'location_address' => 'Calle QA 1',
            'production_id'    => CurrentProduction::id(),
            'risk_assessment'  => $ra,
        ], $overrides));
    }

    /** El primer event_id citado en el risk_assessment de un scouting (o 0). */
    protected function firstEligibleEventId(ScoutingReport $sc): int
    {
        $ra = $sc->risk_assessment;
        if (is_array($ra) && ! empty($ra[0]['event_id'])) {
            return (int) $ra[0]['event_id'];
        }
        return 0;
    }

    /** Crea un mapeo BORRADOR (modelo) ligado a un scouting nuevo. */
    protected function makeDraftMap(?ScoutingReport $sc = null, array $overrides = []): RiskMap
    {
        $sc = $sc ?: $this->makeScouting();

        return RiskMap::create(array_merge([
            'scouting_id'   => $sc->id,
            'project_id'    => $sc->production_id ?: CurrentProduction::id(),
            'title'         => 'Mapeo QA ' . Str::random(6),
            'status'        => 'draft',
            'version'       => 1,
        ], $overrides));
    }

    /** Agrega una vista (modelo) con imagen ya "resuelta" y, si se pide, un marcador de recurso. */
    protected function addView(RiskMap $map, array $overrides = []): RiskMapView
    {
        $order = (int) $map->views()->max('sort_order') + 1;

        return RiskMapView::create(array_merge([
            'risk_map_id'         => $map->id,
            'sort_order'          => $order,
            'view_type'           => 'set',
            'label'               => 'Vista QA',
            'image_original_path' => '/storage/riskmaps/qa.jpg',
            'image_source'        => 'upload',
        ], $overrides));
    }

    /** Marcador de RECURSO (no requiere evento elegible). */
    protected function addResourceMarker(RiskMapView $view, array $overrides = []): RiskMapMarker
    {
        $order = (int) $view->markers()->max('sort_order') + 1;

        return RiskMapMarker::create(array_merge([
            'view_id'       => $view->id,
            'sort_order'    => $order,
            'kind'          => 'resource',
            'resource_type' => 'extintor',
            'x_pct'         => 40.125,
            'y_pct'         => 60.250,
            'label_side'    => 'right',
        ], $overrides));
    }

    /**
     * Sella un mapeo COMPLETO (modelo): mapa + 1 vista + 1 marcador de recurso, y firma
     * como safety-officer. Devuelve el mapa refrescado y sellado (mismo patrón que el
     * controlador seal()).
     */
    protected function sealMap(array $mapOverrides = [], array $markerOverrides = []): RiskMap
    {
        $user = $this->makeUser('safety-officer');
        $map  = $this->makeDraftMap(null, $mapOverrides);
        $view = $this->addView($map);
        $this->addResourceMarker($view, $markerOverrides);

        $map->status    = 'sealed';
        $map->folio     = 'RMAP-' . str_pad((string) $map->id, 4, '0', STR_PAD_LEFT);
        $map->sealed_at = now();
        $map->sealed_by = $user->id;
        $map->save();
        $map->refresh();
        $map->signDocument($user);

        return $map->fresh(['views.markers']);
    }
}
