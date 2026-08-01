<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Consumable;

/**
 * ConsumableSeeder — catálogo inicial de SDS/consumibles de efectos especiales de cine
 * (Pilar 3, flag 'sds_sfx'). Materiales comunes de set con sus peligros, precauciones y
 * palabra de advertencia reales/breves para que el Safety los tenga a la mano antes de
 * disparar un efecto. Idempotente: updateOrCreate por `name` (no duplica; refresca datos).
 * DEFENSIVO: si la tabla aún no existe (prod sin el SQL), sale sin tronar.
 *
 * Correr:  php artisan db:seed --class=ConsumableSeeder
 */
class ConsumableSeeder extends Seeder
{
    public function run()
    {
        if (!Schema::hasTable('consumables')) {
            if ($this->command) {
                $this->command->warn('ConsumableSeeder: la tabla `consumables` no existe todavía; se omite.');
            }
            return;
        }

        // [name, type, signal_word, un_number, hazards, precautions]
        $items = [
            [
                'Humo (glicol/glicerina)', 'smoke', 'Atención', null,
                'La niebla de glicol/glicerina irrita ojos y vías respiratorias con exposición prolongada; reduce la visibilidad y puede activar detectores de humo.',
                'Ventilar entre tomas; limitar densidad y tiempo de exposición; EPP respiratorio si la exposición es alta; avisar a elenco con asma; despejar accesos y salidas.',
            ],
            [
                'Haze (aceite mineral)', 'haze', 'Atención', null,
                'Neblina fina y persistente de aceite mineral: reduce visibilidad e irrita levemente las vías respiratorias; deja película sobre superficies.',
                'Controlar densidad con haze meter; ventilación adecuada; avisar a personal sensible; secar superficies que queden aceitosas para evitar resbalones.',
            ],
            [
                'Fluido de niebla (base agua/glicol)', 'smoke', 'Atención', null,
                'La condensación de la niebla vuelve resbalosos pisos y equipos; irritación respiratoria leve; reduce visibilidad.',
                'Señalizar y secar pisos; ventilar entre tomas; mantener extintores accesibles; no dirigir el chorro a personas ni a equipo eléctrico.',
            ],
            [
                'Fuego – gas propano/LP', 'fire', 'Peligro', 'UN1978',
                'Gas inflamable a presión: riesgo de incendio, deflagración y quemaduras; el vapor es más pesado que el aire y se acumula en zonas bajas.',
                'Solo técnico pirotécnico certificado; extintores y manta ignífuga a la mano; distancia de seguridad y perímetro despejado; cortar el suministro tras la toma; prohibido fumar/fuentes de ignición cerca.',
            ],
            [
                'Salvas (municiones de fogueo)', 'blank', 'Peligro', null,
                'Aun sin proyectil, la salva expulsa gases, taco y partículas a alta velocidad y presión: puede causar lesiones graves o mortales a corta distancia; ruido peligroso.',
                'Armero certificado a cargo; nunca apuntar directamente a una persona a corta distancia; distancia mínima segura; protección auditiva; conteo y resguardo de munición; verificar el arma antes y después.',
            ],
            [
                'Gasolina/diésel de utilería', 'fuel', 'Peligro', 'UN1203',
                'Líquido y vapores altamente inflamables; los vapores forman mezclas explosivas; tóxico por inhalación y contacto prolongado con la piel.',
                'Almacenar en contenedores homologados y ventilados, lejos de ignición; cantidades mínimas en set; extintor clase B a la mano; EPP (guantes/lentes); recoger derrames de inmediato.',
            ],
            [
                'Pirotecnia (gerbs/flash powder)', 'pyro', 'Peligro', null,
                'Compuestos energéticos: destello, chispa, calor intenso y proyección de partículas; riesgo de quemaduras, incendio y lesión ocular/auditiva.',
                'Exclusivo de pirotécnico licenciado; distancias y perímetro de seguridad; extintores y personal de contra-incendio; protección ocular/auditiva; carga mínima probada; nunca reutilizar cargas fallidas.',
            ],
            [
                'CO₂ / hielo seco', 'cryo', 'Atención', 'UN1845',
                'El CO₂ desplaza el oxígeno (asfixia en zonas bajas/confinadas) y provoca quemaduras por frío (-78 °C) al contacto; niebla reduce visibilidad.',
                'Ventilación y monitoreo de O₂/CO₂ en espacios cerrados; guantes criogénicos; nunca en sótanos/fosos sin extracción; manipular con pinzas; señalizar zona.',
            ],
            [
                'Nieve artificial (celulosa)', 'chemical', 'Atención', null,
                'Polvo de celulosa: irrita vías respiratorias y ojos; vuelve resbalosas las superficies; puede ser combustible en concentración de polvo.',
                'Mascarilla contra polvo y lentes; limitar polvo en suspensión; señalizar y limpiar pisos resbalosos; mantener alejado de fuentes de ignición.',
            ],
            [
                'Sangre falsa (glicerina/colorante)', 'chemical', 'Atención', null,
                'Producto pegajoso a base de glicerina/colorante: mancha, irrita ojos y mucosas, y deja superficies muy resbalosas; puede provocar reacción en piel sensible.',
                'Prueba de parche en piel sensible; enjuague de ojos disponible; limpiar y secar pisos de inmediato; guantes; verificar que sea grado cosmético/apto para piel.',
            ],
            [
                'Solventes/thinner', 'chemical', 'Peligro', null,
                'Líquido y vapores inflamables; tóxicos por inhalación; irritan piel, ojos y vías respiratorias; efecto narcótico en exposición alta.',
                'Usar con ventilación forzada; EPP (guantes de nitrilo, lentes, respirador con cartucho orgánico); lejos de chispas/llamas; trapos impregnados en recipiente metálico cerrado; cantidades mínimas.',
            ],
            [
                'Niebla criogénica (nitrógeno líquido)', 'cryo', 'Peligro', 'UN1977',
                'Nitrógeno líquido a -196 °C: quemaduras criogénicas severas y asfixia por desplazamiento de oxígeno; la expansión gaseosa puede sobrepresurizar recipientes cerrados.',
                'Operador entrenado; guantes/careta criogénicos; ventilación y monitoreo de O₂; nunca en espacio confinado sin extracción; recipientes con venteo; señalizar y despejar el área.',
            ],
        ];

        $nuevos = 0;
        $orden  = 0;
        foreach ($items as [$name, $type, $signal, $un, $hazards, $precautions]) {
            $c = Consumable::updateOrCreate(
                ['name' => $name],
                [
                    'type'        => $type,
                    'signal_word' => $signal,
                    'un_number'   => $un,
                    'hazards'     => $hazards,
                    'precautions' => $precautions,
                    'is_active'   => 1,
                    'sort_order'  => $orden,
                ]
            );
            if ($c->wasRecentlyCreated) {
                $nuevos++;
            }
            $orden++;
        }

        if ($this->command) {
            $this->command->info("ConsumableSeeder: catálogo sembrado ({$nuevos} nuevos, " . Consumable::count() . ' consumibles totales).');
        }
    }
}
