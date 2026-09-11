<?php

namespace Tests\Feature\Unidades;

use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\Production;
use App\Models\Unit;
use App\Models\User;
use App\Support\ContractEmitter;
use App\Support\ContractStatus;
use App\Support\CurrentProduction;
use App\Support\UnitMembership;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * UNIDADES · nombre compuesto en el contrato (§2) + "revisar" (§3), 2026-09-11.
 * El título del contrato lleva la unidad SÓLO para quien vive en una adicional; la principal y los
 * compartidos no llevan sufijo. El número de unidad es ESTABLE (no se deriva del orden). "Revisar"
 * compara la unidad congelada del contrato contra la pertenencia viva, en las dos direcciones.
 */
class UnitCompositeNameTest extends QaTestCase
{
    private function prod(): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'unit_label_format' => Unit::LABEL_LONG])->save();
        CurrentProduction::forget();

        return $prod;
    }

    private function crew(string $tag): User
    {
        return $this->makeUser('crew', ['name' => $tag, 'crewlist_visible' => 1, 'activo' => 1]);
    }

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

    private function crewContract(User $user, Production $prod, ?int $unitNumber, bool $emitted): PayeeContract
    {
        $payee = Payee::create([
            'legal_nature' => Payee::NATURE_FISICA, 'name' => $user->name, 'user_id' => $user->id, 'is_active' => 1,
        ]);

        return PayeeContract::create([
            'payee_id'      => $payee->id,
            'production_id' => $prod->id,
            'concept'       => PayeeContract::CONCEPT_CREW,
            'is_active'     => 1,
            'title'         => 'Primer asistente de dirección',
            'department_id' => Department::query()->value('id'),
            'fee_amount'    => 15000,
            'effective_date' => now()->toDateString(),
            'unit_number'   => $unitNumber,
            'emitted_at'    => $emitted ? now() : null,
        ]);
    }

    public function test_numero_de_unidad_es_estable_no_se_renumera(): void
    {
        $prod = $this->prod();
        $this->actingAs($this->admin());

        $this->post(route('production.units.store'), ['name' => 'Segunda unidad'])->assertRedirect();
        $this->post(route('production.units.store'), ['name' => 'Tercera unidad'])->assertRedirect();

        $u2 = Unit::where('production_id', $prod->id)->where('name', 'Segunda unidad')->first();
        $u3 = Unit::where('production_id', $prod->id)->where('name', 'Tercera unidad')->first();
        $this->assertSame(2, (int) $u2->number, 'La primera adicional es la unidad 2 (la principal es la 1 implícita).');
        $this->assertSame(3, (int) $u3->number);

        // Desactivar la 2 NO renumera la 3, y la 2 conserva su número.
        $this->post(route('production.units.toggle', $u2->id))->assertRedirect();
        $this->assertSame(3, (int) $u3->fresh()->number, 'Apagar la 2 no convierte la 3 en 2.');
        $this->assertSame(2, (int) $u2->fresh()->number);

        // Una NUEVA unidad no reutiliza el 2 (nextNumber = max+1).
        $this->post(route('production.units.store'), ['name' => 'Cuarta unidad'])->assertRedirect();
        $this->assertSame(4, (int) Unit::where('production_id', $prod->id)->where('name', 'Cuarta unidad')->value('number'));
    }

    public function test_estampado_solo_para_exclusivos_de_unidad_adicional(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'number' => 2, 'sort_order' => 1, 'is_active' => true]);

        $positionsAntes = Department::count() >= 0 ? \Illuminate\Support\Facades\DB::table('positions')->count() : 0;

        // Exclusivo de la 2 → título con "Unidad 2" + unit_number 2.
        $exclusivo = $this->crew('Exclusivo' . Str::random(4));
        UnitMembership::setState($u2->id, $exclusivo->id, 'solo');
        $cEx = $this->crewContract($exclusivo, $prod, null, false);
        ContractEmitter::stampUnitLabel($cEx);
        $this->assertSame(2, (int) $cEx->unit_number);
        $this->assertStringEndsWith('Unidad 2', $cEx->title);

        // Principal (sin membresía) → sin sufijo.
        $principal = $this->crew('Principal' . Str::random(4));
        $cPr = $this->crewContract($principal, $prod, null, false);
        ContractEmitter::stampUnitLabel($cPr);
        $this->assertNull($cPr->unit_number);
        $this->assertSame('Primer asistente de dirección', $cPr->title);

        // Compartido (ambas) → sin sufijo (es de la principal que apoya).
        $compartido = $this->crew('Compartido' . Str::random(4));
        UnitMembership::setState($u2->id, $compartido->id, 'ambas');
        $cCo = $this->crewContract($compartido, $prod, null, false);
        ContractEmitter::stampUnitLabel($cCo);
        $this->assertNull($cCo->unit_number);
        $this->assertSame('Primer asistente de dirección', $cCo->title);

        // El catálogo NO se duplicó (estampar no crea puestos).
        $this->assertSame($positionsAntes, \Illuminate\Support\Facades\DB::table('positions')->count());
    }

    public function test_formato_corto_por_produccion(): void
    {
        $prod = $this->prod();
        $prod->forceFill(['unit_label_format' => Unit::LABEL_SHORT])->save();
        CurrentProduction::forget();
        $u2 = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'number' => 2, 'sort_order' => 1, 'is_active' => true]);

        $p = $this->crew('Corto' . Str::random(4));
        UnitMembership::setState($u2->id, $p->id, 'solo');
        $c = $this->crewContract($p, $prod, null, false);
        ContractEmitter::stampUnitLabel($c);
        $this->assertStringEndsWith('U2', $c->title);
    }

    public function test_revisar_detecta_las_dos_direcciones(): void
    {
        $prod = $this->prod();
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'number' => 2, 'sort_order' => 1, 'is_active' => true]);

        // Dirección 1: contrato dice "Unidad 2" pero hoy está en la principal (sin membresía exclusiva).
        $dir1 = $this->crew('Dir1' . Str::random(4));
        $this->crewContract($dir1, $prod, 2, true);   // emitido, unidad 2 congelada

        // Dirección 2: contrato SIN unidad (principal) pero hoy es EXCLUSIVO de la 2.
        $dir2 = $this->crew('Dir2' . Str::random(4));
        UnitMembership::setState($u2->id, $dir2->id, 'solo');
        $this->crewContract($dir2, $prod, null, true);

        // Alineado: contrato unidad 2 y hoy exclusivo de la 2 → OK.
        $ok = $this->crew('Alineado' . Str::random(4));
        UnitMembership::setState($u2->id, $ok->id, 'solo');
        $this->crewContract($ok, $prod, 2, true);

        // No emitido: aunque haya desajuste, no dispara "revisar" (la unidad se congela al emitir).
        $draft = $this->crew('Borrador' . Str::random(4));
        $this->crewContract($draft, $prod, 2, false);   // no emitido

        $s = ContractStatus::forUserIds([$dir1->id, $dir2->id, $ok->id, $draft->id]);
        $this->assertSame(ContractStatus::REV_REVISAR, $s[$dir1->id]['rev_state'], 'Contrato Unidad 2 + hoy principal → revisar.');
        $this->assertSame(ContractStatus::REV_REVISAR, $s[$dir2->id]['rev_state'], 'Contrato principal + hoy exclusivo de la 2 → revisar.');
        $this->assertSame(ContractStatus::REV_OK,      $s[$ok->id]['rev_state'],   'Contrato y unidad coinciden → sin revisar.');
        $this->assertSame(ContractStatus::REV_OK,      $s[$draft->id]['rev_state'], 'Contrato no emitido no dispara revisar.');
    }
}
