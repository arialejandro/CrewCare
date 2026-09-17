<?php

namespace Database\Seeders;

use App\Models\CallDay;
use App\Models\CallDeptOffset;
use App\Models\Department;
use App\Models\Payee;
use App\Models\Position;
use App\Models\Production;
use App\Models\User;
use App\Support\CallSheetEngine;
use App\Support\CurrentProduction;
use Carbon\Carbon;
use Database\Seeders\Concerns\SoloEnLocal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CallSheetDemoSeeder — CREW DE DEMOSTRACIÓN para probar/visualizar el llamado (2026-08-23).
 *
 * Crea ~100 personas repartidas por departamento (con su puesto), TODAS llamadas HOY, + la config
 * del día (general 07:00, contingente, comidas auto, offsets de precall) + notas globales. Así, al
 * abrir /llamado, el back sale POBLADO y se puede ver la función y evaluar si desborda.
 *
 * REVERSIBLE: cada persona lleva el marcador de email `@llamado.demo`. Re-correr PURGA y rehace
 * (idempotente). Para quitarlo todo:  php artisan db:seed --class=CallSheetDemoRemoveSeeder
 *
 * CANDADO DE ENTORNO (SoloEnLocal): datos de prueba NUNCA a un cliente.
 * Uso:  php artisan db:seed --class=CallSheetDemoSeeder
 */
class CallSheetDemoSeeder extends Seeder
{
    use SoloEnLocal;

    /** Marcador reversible: el dominio del email distingue al crew de demo. */
    public const EMAIL = '@llamado.demo';

    public function run()
    {
        $this->exigirEntornoLocal('crew de demostración del llamado');

        $pid = CurrentProduction::id();
        if (! $pid) {
            if ($this->command) { $this->command->warn('CallSheetDemoSeeder: no hay producción vigente.'); }
            return;
        }

        self::purge($pid);   // idempotente

        $deptId = Department::pluck('id', 'name')->all();

        $first = ['Mariana', 'Alonso', 'Sofía', 'Tomás', 'Rodrigo', 'Clara', 'Mateo', 'Paulina', 'Diego', 'Valeria',
            'Sebastián', 'Natalia', 'Gustavo', 'Inés', 'Lucía', 'Germán', 'Andrés', 'Regina', 'Emilio', 'Fernanda',
            'Bruno', 'Ximena', 'Iván', 'Camila', 'Héctor', 'Daniela', 'Pablo', 'Renata', 'Óscar', 'Alejandra',
            'Marco', 'Adriana', 'Julio', 'Carmen', 'Rafael', 'Gabriela', 'Ernesto', 'Paola', 'Ricardo', 'Mónica',
            'Arturo', 'Verónica', 'Joel', 'Andrea', 'Manuel', 'Rosa', 'Felipe', 'Lorena', 'Enrique', 'Silvia'];
        $last = ['Alarcón', 'Villarreal', 'Carvajal', 'Arismendi', 'Mendoza', 'Santillán', 'Beltrán', 'Rivas', 'Castañeda', 'Nájera',
            'Valdés', 'Cordero', 'Lemus', 'Barrientos', 'Morales', 'Orozco', 'Salas', 'Peña', 'Cuevas', 'Domínguez',
            'Ríos', 'Guzmán', 'Alcántara', 'Pérez', 'Bautista', 'Loza', 'Vega', 'Ureña', 'Chávez', 'Ramírez',
            'Solís', 'Fuentes', 'Bravo', 'Ibáñez', 'Palma', 'Aranda', 'Sotelo', 'Zamora', 'Núñez', 'Aguilar'];

        // [departamento, puesto del jefe, puesto del resto, cuántos NO-jefe].
        $plan = [
            ['Dirección', 'Director', 'Asistente de Director', 2],
            ['Asistentes de Dirección', '1er AD', 'Set PA', 5],
            ['Producción', 'Productor en Línea', 'Gerente de Unidad', 3],
            ['Oficina de Producción', 'Coordinador de Producción', 'APOC', 3],
            ['Cámara', 'Director de Fotografía', 'Operador de Cámara', 6],
            ['Eléctricos', 'Gaffer', 'Eléctrico', 5],
            ['Grips', 'Key Grip', 'Grip', 5],
            ['Sonido', 'Sonido Directo', 'Operador de Boom', 2],
            ['Arte', 'Diseñador de Producción', 'Director de Arte', 4],
            ['Decoración', 'Decorador', 'Set Dresser', 5],
            ['Vestuario', 'Diseñador de Vestuario', 'Vestuarista', 5],
            ['Maquillaje y Peinados', 'Diseñador de M&P', 'Asst. Maquillaje', 4],
            ['Locaciones', 'Gerente de Locaciones', 'Asst. de Locaciones', 6],
            ['Transportación', 'Coordinador de Transporte', 'Chofer', 8],
            ['Utilería', 'Jefe de Utilería', 'Asistente de Utilería', 3],
            ['Construcción', 'Constructor', 'Carpintero', 5],
            ['Stunts', 'Coordinador de Stunts', 'Stunt', 3],
            ['Efectos Especiales', 'Supervisor de Efectos Especiales', 'Efectos Especiales', 2],
            ['Salud y Seguridad', 'Supervisor de Salud y Seguridad', 'Asistente de S&S', 2],
            ['Alimentación', 'Coordinador de Craft', 'Cafetero', 3],
        ];

        $today = Carbon::today()->toDateString();
        $i = 0;
        $created = 0;

        foreach ($plan as [$dept, $hodPos, $crewPos, $cnt]) {
            $did = $deptId[$dept] ?? null;
            if (! $did) { continue; }

            for ($k = 0; $k <= $cnt; $k++) {
                $isHod = $k === 0;
                $pos   = $isHod ? $hodPos : $crewPos;
                $name  = $first[$i % count($first)];
                $lname = $last[($i * 7 + 3) % count($last)];
                $i++;

                $u = User::forceCreate([
                    'name' => $name, 'lname' => $lname, 'ncreditos' => $name . ' ' . $lname,
                    'puestodepartamento' => $pos,
                    'email' => 'csdemo' . $i . self::EMAIL,
                    'password' => Hash::make('Demo1234!'),
                    'admin' => 0, 'activo' => 1,
                ]);

                DB::table('production_user')->insert([
                    'production_id' => $pid, 'user_id' => $u->id, 'department_id' => $did,
                    'position_id' => Position::where('name', $pos)->where('department_id', $did)->whereNull('production_id')->value('id'),
                    'role' => 'crew', 'is_lead' => $isHod ? 1 : 0, 'created_at' => now(), 'updated_at' => now(),
                ]);

                $payee = Payee::create(['legal_nature' => 'fisica', 'name' => $name . ' ' . $lname, 'user_id' => $u->id]);
                $c = $payee->contracts()->create([
                    'production_id' => $pid, 'concept' => 'crew_work', 'department_id' => $did,
                    'contracted_by_user_id' => $u->id, 'payment_frequency' => 'weekly', 'is_active' => 1,
                ]);
                DB::table('payee_contract_work_dates')->insert(['payee_contract_id' => $c->id, 'work_date' => $today]);
                DB::table('contract_envelopes')->insert([
                    'payee_contract_id' => $c->id, 'production_id' => $pid,
                    'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $created++;
            }
        }

        // Config del día de HOY.
        $cd = CallDay::updateOrCreate(
            ['production_id' => $pid, 'call_date' => $today],
            ['general_call' => '07:00:00', 'cast_count' => 12, 'bg_count' => 45, 'sign_enabled' => 1]
        );
        $cd->meals()->delete();
        $s = 0;
        foreach (CallSheetEngine::autoMealSet('07:00') as [$l, $o]) {
            $cd->meals()->create(['label' => $l, 'enabled' => 1, 'offset_minutes' => $o, 'sort_order' => $s++]);
        }

        // Offsets de precall por depto.
        foreach (['Locaciones' => -120, 'Producción' => -60, 'Cámara' => -15, 'Eléctricos' => -60,
                  'Grips' => -60, 'Maquillaje y Peinados' => -105, 'Vestuario' => -90, 'Transportación' => -180] as $dn => $off) {
            if (! empty($deptId[$dn])) {
                CallDeptOffset::updateOrCreate(['production_id' => $pid, 'department_id' => $deptId[$dn]], ['offset_minutes' => $off, 'literal_value' => null]);
            }
        }

        // Notas globales.
        $prod = Production::find($pid);
        $set = is_array($prod->settings) ? $prod->settings : [];
        $set['callsheet_safety_bar'] = 'Tu seguridad es primero · No hay llamado forzado sin aprobación del UPM · Los pick ups salen a la hora marcada';
        $set['callsheet_notes'] = "Producción no se hace responsable de autos particulares.\n"
            . "Hospital más cercano: Hospital General — Tel. 555-0100.\n"
            . "Aviso anti-acoso: reporta cualquier conducta indebida al depto. de H&S.\n"
            . "Revisar el orden de transportación antes de cada movimiento.";
        $prod->settings = $set;
        $prod->save();
        CurrentProduction::forget();

        if ($this->command) {
            $this->command->info("CallSheetDemoSeeder: {$created} personas de crew de demo, llamadas el {$today}. Abre /llamado.");
        }
    }

    /** Borra TODO lo del demo del llamado (idempotente / reversible), acotado a la producción. */
    public static function purge($pid): void
    {
        $ids = User::where('email', 'like', '%' . self::EMAIL)->pluck('id');
        if ($ids->isNotEmpty()) {
            $payeeIds    = Payee::whereIn('user_id', $ids)->pluck('id');
            $contractIds = DB::table('payee_contracts')->whereIn('payee_id', $payeeIds)->pluck('id');
            DB::table('payee_contract_work_dates')->whereIn('payee_contract_id', $contractIds)->delete();
            DB::table('contract_envelopes')->whereIn('payee_contract_id', $contractIds)->delete();
            DB::table('payee_contracts')->whereIn('id', $contractIds)->delete();
            DB::table('payees')->whereIn('id', $payeeIds)->delete();
            DB::table('production_user')->whereIn('user_id', $ids)->delete();
            DB::table('call_person_schedules')->whereIn('user_id', $ids)->delete();
            User::whereIn('id', $ids)->delete();
        }

        if ($pid) {
            CallDeptOffset::where('production_id', $pid)->delete();
            $dayIds = DB::table('call_days')->where('production_id', $pid)->pluck('id');
            DB::table('call_day_meals')->whereIn('call_day_id', $dayIds)->delete();
            DB::table('call_days')->where('production_id', $pid)->delete();

            $prod = Production::find($pid);
            if ($prod) {
                $set = is_array($prod->settings) ? $prod->settings : [];
                unset($set['callsheet_notes'], $set['callsheet_safety_bar']);
                $prod->settings = $set ?: null;
                $prod->save();
            }
        }
    }
}
