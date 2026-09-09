<?php

namespace Tests\Feature\Incident;

use App\Models\hazardnotification;
use App\Models\InjuryReport;
use App\Models\unsafecond;
use App\Models\User;
use Tests\QaTestCase;

/**
 * AISLAMIENTO POR PROPIEDAD de los reportes de seguridad (auditoría #1, 2026-08-21).
 *
 * Decisión del owner: aislar a los SAFETY ENTRE SÍ — "un safety no ve lo que hace otro safety",
 * igual que los médicos entre sí — para que no choquen en DSR/Injury/Actos/Condiciones. Cada quien
 * ve SÓLO lo que capturó; sólo la CONSOLIDACIÓN (permiso `safety.consolidate`: super-admin,
 * line-producer, coordinator, auditor) ve todo. El safety-officer NO consolida → queda aislado.
 * Ver App\Support\ReportVisibility + SafetyConsolidatePermissionSeeder.
 */
class ReportOwnershipVisibilityTest extends QaTestCase
{
    private function visibleIds($response, string $key)
    {
        return $response->assertOk()->viewData($key)->getCollection()->pluck('id');
    }

    /** LA CLAVE: un safety-officer NO ve el reporte de OTRO safety-officer. */
    public function test_un_safety_no_ve_el_injury_de_otro_safety(): void
    {
        $safetyA = $this->makeUser('safety-officer');
        $safetyB = $this->makeUser('safety-officer');
        $mine  = InjuryReport::create(['name' => 'A', 'what_happened' => 'x', 'created_by_id' => $safetyA->id]);
        $other = InjuryReport::create(['name' => 'B', 'what_happened' => 'x', 'created_by_id' => $safetyB->id]);

        $this->actingAs($safetyA);
        $ids = $this->visibleIds($this->get(route('injury_reports.index')), 'injuryReports');
        $this->assertTrue($ids->contains($mine->id), 'el safety ve el suyo');
        $this->assertFalse($ids->contains($other->id), 'el safety NO ve el de otro safety (aislamiento)');
    }

    /** La CONSOLIDACIÓN (line-producer) ve los reportes de TODOS los safety. */
    public function test_la_consolidacion_ve_todos_los_injuries(): void
    {
        $safetyA = $this->makeUser('safety-officer');
        $safetyB = $this->makeUser('safety-officer');
        $a = InjuryReport::create(['name' => 'A', 'what_happened' => 'x', 'created_by_id' => $safetyA->id]);
        $b = InjuryReport::create(['name' => 'B', 'what_happened' => 'x', 'created_by_id' => $safetyB->id]);

        $this->actingAsRole('line-producer');
        $ids = $this->visibleIds($this->get(route('injury_reports.index')), 'injuryReports');
        $this->assertTrue($ids->contains($a->id) && $ids->contains($b->id), 'la consolidación ve todo');
    }

    /** El mismo aislamiento en Actos y Condiciones inseguras. */
    public function test_actos_y_condiciones_aislan_por_autor(): void
    {
        $safetyA = $this->makeUser('safety-officer');
        $safetyB = $this->makeUser('safety-officer');

        $ha = $this->ownedBy(hazardnotification::create(['production_name' => 'D', 'description_hazard_unsafe_act' => 'a']), $safetyA);
        $hb = $this->ownedBy(hazardnotification::create(['production_name' => 'D', 'description_hazard_unsafe_act' => 'b']), $safetyB);
        $ca = $this->ownedBy(unsafecond::create(['production_name' => 'D', 'name_loc' => 'S', 'description_unsafe_cond' => 'a']), $safetyA);
        $cb = $this->ownedBy(unsafecond::create(['production_name' => 'D', 'name_loc' => 'S', 'description_unsafe_cond' => 'b']), $safetyB);

        // safetyA (tiene hazards.view) ve sólo lo suyo en ambos listados.
        $this->actingAs($safetyA);
        $haz = $this->visibleIds($this->get(route('hazard_notifications.index')), 'hazardNotifications');
        $this->assertTrue($haz->contains($ha->id) && ! $haz->contains($hb->id), 'acto: safety aislado');
        $cond = $this->visibleIds($this->get(route('unsafenotifications.index')), 'unsafenotifications');
        $this->assertTrue($cond->contains($ca->id) && ! $cond->contains($cb->id), 'condición: safety aislado');

        // auditor (safety.consolidate) ve los ajenos también.
        $this->actingAsRole('auditor');
        $this->assertTrue($this->visibleIds($this->get(route('hazard_notifications.index')), 'hazardNotifications')->contains($hb->id));
        $this->assertTrue($this->visibleIds($this->get(route('unsafenotifications.index')), 'unsafenotifications')->contains($cb->id));
    }

    /** Fija created_by_id por atributo (por si no es fillable) y devuelve el modelo. */
    private function ownedBy($model, User $u)
    {
        $model->created_by_id = $u->id;
        $model->save();
        return $model;
    }
}
