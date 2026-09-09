<?php

namespace Tests\Feature\Ambulance;

use App\Models\AmbulanceInspection;
use App\Models\AmbulanceProvider;
use App\Models\AmbulanceType;
use App\Support\AmbulanceVerdict;
use App\Support\CurrentProduction;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * Base del vertical VERIFICACIÓN DE AMBULANCIAS (NOM-034-SSA3-2013, deltas #51/#52).
 *
 * NO es una clase de prueba (no termina en Test.php → phpunit no la colecta): sólo
 * helpers de rol y fábricas apoyadas en el CATÁLOGO REAL sembrado de fábrica
 * (AmbulanceCatalogSeeder → 6 tipos AMB-01..06 + 92 puntos). Los verticales se
 * construyen sobre datos reales del catálogo (no sintéticos) porque la pertenencia
 * de puntos a un tipo la deriva AmbulanceType::applicablePoints() (rama+nivel, con
 * herencia A⊆B⊆C⊆D), que es exactamente lo que se quiere ejercitar.
 *
 * GATES (Paso 4 · visibilidad): TRES niveles sobre el mismo módulo:
 *   - GESTIONA (ambulance.manage): safety-officer + super-admin → lectura Y escritura.
 *   - VE (ambulance.view): line-producer + coordinator (producción) → SOLO lectura del hub/actas/
 *     proveedores; nada de verificar/sellar/validar.
 *   - NADA: hod (incl. transporte), medic, crew, auditor → 403 en todo el módulo, hasta por URL.
 * El verificador público del acta ('ambu') vive aparte, sin sesión.
 */
abstract class AmbulanceVerticalTestCase extends QaTestCase
{
    /** Roles con ambulance.manage (gestión total). super-admin además por Gate::before. */
    protected function grantedRoles(): array
    {
        return ['safety-officer', 'super-admin'];
    }

    /** Roles con ambulance.view (producción): VEN el módulo, no lo gestionan. */
    protected function viewRoles(): array
    {
        return ['line-producer', 'coordinator'];
    }

    /**
     * Roles SIN ningún permiso de ambulancias → 403 en TODO el módulo. transpo es un HOD:
     * ambulancias es exclusivo de producción y safety, jamás de transporte.
     */
    protected function deniedRoles(): array
    {
        return ['hod', 'medic', 'crew', 'auditor'];
    }

    /** Un tipo terrestre real del catálogo (AMB-04 = cuidados intensivos, hereda A+B+C+D). */
    protected function aTerrestrialType(string $code = 'AMB-04'): AmbulanceType
    {
        $type = AmbulanceType::active()->where('code', $code)->first();
        $this->assertNotNull($type, "El catálogo de fábrica debe traer el tipo {$code}.");
        return $type;
    }

    /** Los códigos de los puntos aplicables a un tipo, en el mismo orden que los sirve el controlador. */
    protected function applicablePointCodes(AmbulanceType $type, ?int $capacityLevel = null): array
    {
        return $type->applicablePoints($capacityLevel)->pluck('code')->all();
    }

    /**
     * Payload base para POST /ambulancia/verificar (ejecuta el checklist COMPLETO).
     * Todos los puntos se responden 'ok' salvo los override que se pasen ('code' => 'fail').
     * Da de alta un proveedor NUEVO en el mismo envío (new_provider_name) para no depender del padrón.
     *
     * @param  array  $answerOverrides  code => 'ok'|'fail'
     */
    protected function inspectStorePayload(AmbulanceType $type, array $answerOverrides = [], array $overrides = []): array
    {
        $answers = [];
        foreach ($this->applicablePointCodes($type) as $code) {
            $answers[$code] = $answerOverrides[$code] ?? 'ok';
        }

        return array_merge([
            'type_id'           => $type->id,
            'new_provider_name' => 'Ambulancias QA ' . Str::random(5),
            'plates'            => 'QA-' . Str::random(4),
            'economic_number'   => 'ECO-' . Str::random(3),
            'answers'           => $answers,
            // Tripulación MÍNIMA por defecto (operador + clínico) para que el camino feliz dé APTA.
            // Los tests del gate de tripulación la sobreescriben con ['crew' => [...]] o ['crew' => []].
            'crew'              => [
                ['name' => 'Operador QA', 'role' => AmbulanceVerdict::ROLE_OPERADOR],
                ['name' => 'TAMP QA',     'role' => AmbulanceVerdict::ROLE_TAMP],
            ],
        ], $overrides);
    }

    /**
     * Sella un AmbulanceInspection A NIVEL MODELO (sin controlador → sin sesión), como
     * hacen los controladores: create → refresh → signDocument. Mismo patrón que
     * PermitVerticalTestCase::sealInspection.
     */
    protected function sealAmbulanceInspection(array $attrs = []): AmbulanceInspection
    {
        $user = $this->makeUser('safety-officer');
        $type = $this->aTerrestrialType();

        $insp = AmbulanceInspection::create(array_merge([
            'production_id'      => CurrentProduction::id(),
            'shoot_day'          => 1,
            'trigger_scope'      => AmbulanceInspection::TRIGGER_FULL,
            'ambulance_type_id'  => $type->id,
            'type_code'          => $type->code,
            'type_name'          => $type->name_es,
            'rama'               => $type->rama,
            'type_level'         => $type->level,
            'provider_name'      => 'Ambulancias QA S.A.',
            'plates'             => 'QA-1234',
            'economic_number'    => 'ECO-9',
            'crew_snapshot'      => [['name' => 'Paramédico QA', 'role' => 'TAMP', 'verified' => true]],
            'checklist_snapshot' => [['code' => 'AMBV-001', 'answer' => 'ok', 'is_gate' => true]],
            'verdict'            => AmbulanceInspection::VERDICT_APTA,
            'observations'       => 'CONFIDENCIAL observación interna del acta.',
            'inspector_name'     => 'Inspector Secreto QA',
            'is_active'          => 1,
        ], $attrs));

        $insp->refresh();
        $insp->signDocument($user);

        return $insp;
    }

    /** Crea un proveedor de ambulancias (empresa) directo, para las pruebas de documentos. */
    protected function makeProvider(array $attrs = []): AmbulanceProvider
    {
        return AmbulanceProvider::create(array_merge([
            'name'      => 'Proveedor QA ' . Str::random(5),
            'is_active' => 1,
        ], $attrs));
    }
}
