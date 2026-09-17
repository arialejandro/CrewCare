<?php

namespace Tests\Feature\Permit;

use App\Models\Department;
use App\Models\IssuedPermit;
use App\Models\Permit;
use App\Models\PermitPoint;
use App\Models\Tool;
use App\Models\ToolCheckPoint;
use App\Models\ToolInspection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * Base del vertical PERMISOS + INSPECCIÓN.
 *
 * NO es una clase de prueba (no termina en Test.php → phpunit no la colecta): sólo
 * fábrica de filas de catálogo SINTÉTICAS y helpers de rol. Se construyen tipos y
 * puntos propios (prefijo QA-) para NO depender de los códigos exactos del catálogo
 * de fábrica y poder fijar un gate con un `outcome_if_fail` conocido/desconocido —
 * imprescindible para probar la regla dura "Gate caído nunca da APTA".
 */
abstract class PermitVerticalTestCase extends QaTestCase
{
    /** Roles con permiso en AMBAS verticales (ver ToolInspection/PermitIssuance PermissionsSeeder). */
    protected function grantedRoles(): array
    {
        return ['safety-officer', 'line-producer', 'super-admin'];
    }

    /** Roles SIN permiso (no aparecen en el grant de ninguno de los dos seeders). */
    protected function deniedRoles(): array
    {
        return ['coordinator', 'hod', 'medic', 'crew', 'auditor'];
    }

    protected function aDepartmentId(): int
    {
        $id = (int) Department::where('active', 1)->orderBy('id')->value('id');
        $this->assertGreaterThan(0, $id, 'La fábrica debe sembrar al menos un departamento activo.');
        return $id;
    }

    // ----------------------------------------------------------------------
    // Catálogo de PERMISO sintético (Permit + PermitPoints, todos compuerta).
    // ----------------------------------------------------------------------

    /**
     * @param  array  $attrs   overrides del Permit (site_scope, ext_auth_mandatory, name…)
     * @param  int    $points  cuántos puntos-compuerta crear
     * @param  bool   $siteSensitive  marca los puntos como sensibles al sitio (para reverificar)
     */
    protected function makePermitCatalog(array $attrs = [], int $points = 2, bool $siteSensitive = false): Permit
    {
        $sfx = strtoupper(Str::random(6));
        $permit = Permit::create(array_merge([
            'code'               => 'QA-PER-' . $sfx,
            'permit_key'         => 'qa_permit',
            'family'             => 'QA',
            'name'               => 'Permiso QA de prueba',
            'definition'         => 'Permiso sintético para QA.',
            'site_scope'         => IssuedPermit::SCOPE_INDIFFERENT,
            'ext_auth_mandatory' => 0,
            'is_active'          => 1,
            'sort_order'         => 0,
        ], $attrs));

        for ($i = 1; $i <= $points; $i++) {
            PermitPoint::create([
                'code'           => 'QA-PP-' . $sfx . '-' . $i,
                'permit_id'      => $permit->id,
                'text_es'        => "Punto compuerta QA #$i",
                'executor'       => 'safety',
                'is_gate'        => 1,
                'site_sensitive' => $siteSensitive ? 1 : 0,
                'sort_order'     => $i,
                'is_active'      => 1,
            ]);
        }

        return $permit->fresh();
    }

    /** Los códigos de los puntos ACTIVOS de un permiso, en orden. */
    protected function permitPointCodes(Permit $permit): array
    {
        return $permit->points()->where('is_active', 1)->orderBy('sort_order')->orderBy('code')->pluck('code')->all();
    }

    /** Payload base para emitir un permiso (todos los puntos en 'cumple'). */
    protected function permitStorePayload(Permit $permit, array $overrides = []): array
    {
        $answers = [];
        foreach ($this->permitPointCodes($permit) as $code) {
            $answers[$code] = 'cumple';
        }

        return array_merge([
            'activity_description' => 'Actividad QA de prueba en el set.',
            'site_label'           => 'Locación A',
            'acceptor_name'        => 'Ejecutante Designado',
            'answers'              => $answers,
        ], $overrides);
    }

    // ----------------------------------------------------------------------
    // Catálogo de HERRAMIENTA sintético (Tool + ToolCheckPoints + pivote).
    // ----------------------------------------------------------------------

    /**
     * @param  array  $pointSpecs  lista de ['gate'=>bool,'outcome'=>?string,'text'=>?string]
     * @param  array  $toolAttrs   overrides del Tool
     */
    protected function makeToolWithPoints(array $pointSpecs, array $toolAttrs = []): Tool
    {
        $sfx = strtoupper(Str::random(6));
        $tool = Tool::create(array_merge([
            'code'              => 'QA-TOOL-' . $sfx,
            'name'              => 'Herramienta QA',
            'is_wildcard'       => 0,
            'is_active'         => 1,
            'inspection_regime' => 'por_evento',
            'sort_order'        => 0,
        ], $toolAttrs));

        $i = 0;
        foreach ($pointSpecs as $spec) {
            $i++;
            $cp = ToolCheckPoint::create([
                'code'            => 'QA-CP-' . $sfx . '-' . $i,
                'scope'           => 'universal',
                'text_es'         => $spec['text'] ?? ('Punto de comprobación QA #' . $i),
                'is_gate'         => ! empty($spec['gate']) ? 1 : 0,
                'outcome_if_fail' => $spec['outcome'] ?? null,
                'sort_order'      => $i,
                'is_active'       => 1,
            ]);
            DB::table('check_point_tool')->insert([
                'tool_check_point_id' => $cp->id,
                'tool_id'             => $tool->id,
            ]);
        }

        return $tool->fresh();
    }

    /** Los códigos de los puntos de un tipo, en el mismo orden que los sirve el controlador. */
    protected function toolPointCodes(Tool $tool): array
    {
        return DB::table('check_point_tool as ct')
            ->join('tool_check_points as p', 'p.id', '=', 'ct.tool_check_point_id')
            ->where('ct.tool_id', $tool->id)
            ->where('p.is_active', 1)
            ->orderBy('p.sort_order')
            ->orderBy('p.code')
            ->pluck('p.code')->all();
    }

    /**
     * Payload base para ejecutar el checklist de una herramienta.
     *
     * @param  array  $answers  código => 'ok'|'fail' (los faltantes se rellenan 'ok')
     */
    protected function inspectionStorePayload(Tool $tool, array $answers = [], array $overrides = []): array
    {
        $full = [];
        foreach ($this->toolPointCodes($tool) as $code) {
            $full[$code] = $answers[$code] ?? 'ok';
        }

        return array_merge([
            'department_id' => $this->aDepartmentId(),
            'answers'       => $full,
        ], $overrides);
    }

    /** Sella un ToolInspection a nivel modelo (mismo patrón que los controladores). */
    protected function sealInspection(array $attrs = []): ToolInspection
    {
        $user = $this->makeUser('safety-officer');
        $insp = ToolInspection::create(array_merge([
            'tool_name'          => 'Herramienta QA',
            'tool_code'          => 'QA-TOOL-X',
            'verdict'            => ToolInspection::VERDICT_APTA,
            'checklist_snapshot' => [['code' => 'QA-CP-1', 'answer' => 'ok', 'is_gate' => true]],
            'is_active'          => 1,
        ], $attrs));
        $insp->refresh();
        $insp->signDocument($user);

        return $insp;
    }
}
