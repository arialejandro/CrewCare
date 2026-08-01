<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HazardEventPpeSeeder — EPP mínimo por FAMILIA DE RIESGO (2026-07-22).
 *
 * Puebla hazard_events.required_ppe. El mapeo se autoriza por CATEGORÍA (38 familias) y
 * no evento por evento: 207 autorizaciones individuales serían imposibles de mantener y,
 * sobre todo, el EPP lo determina la CLASE de riesgo, no el matiz de cada evento. Todos
 * los eventos de una categoría heredan el mismo mínimo.
 *
 * VOCABULARIO CERRADO (27 piezas), tomado del catálogo del PROPIO OWNER en
 * sfx_effect_types.required_ppe y no de una lista inventada. De ahí vienen decisiones que
 * a primera vista sorprenden y son correctas:
 *   · "Extintor asignado" y "Monitor personal de O2" no se visten, pero el owner ya los
 *     tenía dentro de required_ppe: en el set, el extintor asignado es tan exigible como
 *     el guante.
 *   · "Casco" y "Casco de stunt (integral / oculto)" son piezas DISTINTAS: en cine no es
 *     el mismo casco el de la parrilla que el de un rollover.
 *   · Cuatro familias de guante y tres piezas de fuego se nombran por separado porque así
 *     las nombra el owner; colapsarlas habría borrado su voz.
 *   · "Cubrebocas" (que sí está hoy en el formulario del DSR) quedó FUERA: es legado COVID,
 *     y para polvo, sílice, humos y solventes lo correcto es "Respirador".
 *
 * NO ENTRA AQUÍ LA PLANTILLA: safety diver, armero, wrangler, pirotécnico, banderero y
 * vigía de fuego son PERSONAL OBLIGATORIO, no equipo de protección. Ya viven en las normas
 * N:M del evento; meterlos aquí ensuciaría el campo.
 *
 * ADITIVO E IDEMPOTENTE: sólo escribe donde required_ppe está vacío, así que re-correrlo
 * no pisa los ajustes que el owner haya hecho a mano.
 *
 *   php artisan db:seed --class=HazardEventPpeSeeder
 */
class HazardEventPpeSeeder extends Seeder
{
    /**
     * EPP básico de set. Lo reciben los eventos SIN categoría — hoy 19 de 207, casi todos
     * de taller y carpintería (sierra de mesa, esmeriladora, clavadora, flash de soldadura)
     * y de manejo manual de cargas. Llevan además protección auditiva y facial porque son
     * justamente los de herramienta ruidosa y proyección de partícula.
     */
    const EPP_SIN_CATEGORIA = [
        'Casco', 'Chaleco', 'Botas', 'Guantes', 'Protección ocular',
        'Protección auditiva', 'Protección facial',
    ];

    /**
     * Categoría => EPP mínimo. Cada entrada lleva encima el porqué que la autoriza.
     */
    public static function mapa()
    {
        return [
            // Hallazgo organizativo (egresos, cables, load-in a oscuras): se exige el EPP básico de set más visibilidad para quien transita y despeja rutas.
            'access'            => ['Chaleco', 'Casco', 'Botas'],
            // Cualquier intervención en distro, tablero o luminaria energizada se hace con guantes dieléctricos y careta por arco; el casco cubre la aproximación a líneas aéreas.
            'electrical'        => ['Guantes dieléctricos', 'Protección facial', 'Protección ocular', 'Botas', 'Casco'],
            // Mismo criterio que el catálogo de llama abierta del owner: nada de sintéticos sobre la piel, guante térmico y extintor asignado a quien opera el efecto o el trabajo en caliente.
            'fire'              => ['Ropa de algodón/Nomex sin sintéticos', 'Guantes resistentes al calor', 'Extintor asignado', 'Protección ocular'],
            // Parrilla, andamio, plataforma y escalera: arnés con línea de vida más casco, porque en altura el riesgo es doble (el que cae y el que recibe la herramienta).
            'heights'           => ['Arnés', 'Casco', 'Guantes', 'Botas'],
            // Agua abierta o tanque: flotación y protección térmica mandan; el antiderrapante es para la orilla, el borde de tanque y la embarcación.
            'water'             => ['Chaleco salvavidas', 'Traje térmico', 'Calzado antiderrapante'],
            // Junto a vialidad activa o montacargas en foro, lo que salva es que el operador y el conductor te vean: el chaleco de alta visibilidad es la pieza principal.
            'traffic'           => ['Chaleco', 'Casco', 'Botas'],
            // Exposición sostenida a sol, lluvia y frío: la protección es la ropa y el bloqueador, no un equipo de tarea.
            'weather'           => ['Bloqueador', 'Impermeable', 'Traje térmico', 'Protección ocular'],
            // Sílice, humos de soldadura, moho, solventes y haze: la vía de entrada es respiratoria, así que el respirador con cartucho correcto es obligatorio, no opcional.
            'hazmat'            => ['Respirador', 'Protección ocular', 'Guantes de nitrilo', 'Botas'],
            // Pisos que ceden, estibas que se vuelcan y clavos en el piso: protección de cabeza, pie y mano como mínimo irrenunciable.
            'structural'        => ['Casco', 'Botas', 'Guantes', 'Protección ocular'],
            // Nadie entra a foso, tanque seco, sótano o túnel sin medir la atmósfera y sin arnés para poder extraerlo desde afuera.
            'confined'          => ['Monitor personal de O2', 'Respirador', 'Arnés', 'Casco', 'Botas'],
            // Bota de caña alta y guante de manejo contra víbora, planta urticante y fauna; el nitrilo es aparte, para sangre y fluidos.
            'biological'        => ['Botas', 'Guantes', 'Guantes de nitrilo', 'Protección ocular'],
            // Perímetro y control de curiosos en vía pública: el chaleco además identifica a quien tiene la autoridad de mover gente.
            'crowd'             => ['Chaleco', 'Casco', 'Botas'],
            // Cajón heredado que mezcla armas, pirotecnia, stunts, dron y agua: se le da el mínimo común de esas familias y se pide bajar el hallazgo a la clave fina que corresponda.
            'special'           => ['Protección ocular', 'Protección auditiva', 'Casco de stunt (integral / oculto)', 'Guantes', 'Extintor asignado'],
            // Rollover, choque y ramp jump: casco integral de acción (nunca casco de obra), sujeción de cinco puntos con soporte cervical y Nomex por el riesgo de fuego tras el impacto.
            'stunts_vehicular'  => ['Casco de stunt (integral / oculto)', 'Cinturón de 5 puntos y soporte cervical', 'Ropa de algodón/Nomex sin sintéticos', 'Guantes', 'Extintor asignado'],
            // Caída a air bag o descenso con decelerator: pads bajo vestuario y arnés compatible con el sistema de recepción.
            'stunts_high_fall'  => ['Casco de stunt (integral / oculto)', 'Pads de stunt (rodilleras / coderas)', 'Arnés', 'Guantes'],
            // El performer cuelga de un arnés de vuelo: esa es la pieza que lo sostiene, y los pads cubren el impacto contra estructura o piso.
            'wire_work'         => ['Arnés', 'Casco de stunt (integral / oculto)', 'Pads de stunt (rodilleras / coderas)', 'Guantes'],
            // Arma blanca de utilería y tiro con arco: los ojos son lo que no se recupera, y el escudo protege al crew fuera de cuadro en la línea de tiro.
            'fight_combat'      => ['Protección ocular', 'Pads de stunt (rodilleras / coderas)', 'Guantes', 'Escudo / blindaje'],
            // Con salvas la protección ocular y auditiva es obligatoria para todos en la zona, no sólo para quien dispara.
            'firearms'          => ['Protección ocular', 'Protección auditiva', 'Escudo / blindaje', 'Guantes'],
            // Fuego sobre persona: traje y capucha ignífugos con gel en piel expuesta, y el crew de extinción parado con extintor asignado desde antes del rodar.
            'fire_burn'         => ['Traje/buzo ignífugo', 'Capucha ignífuga', 'Gel retardante en piel expuesta', 'Guantes resistentes al calor', 'Protección facial', 'Extintor asignado'],
            // En pirotecnia y explosión manda la protección facial y auditiva: la onda y el debris llegan antes de que alcances a voltear.
            'pyro_sfx'          => ['Protección ocular', 'Protección auditiva', 'Protección facial', 'Guantes', 'Casco', 'Extintor asignado'],
            // Vía activa: el chaleco de alta visibilidad es lo que te hace visible al maquinista y al vigía, y el ruido del convoy tapa la voz de mando.
            'railroad'          => ['Chaleco', 'Casco', 'Botas', 'Protección auditiva'],
            // Reality en operativo o disturbio: EPP básico más identificación visible; el control real es el repliegue, no el equipo.
            'uncontrolled_env'  => ['Chaleco', 'Casco', 'Botas', 'Protección ocular'],
            // Buceo, tanque y aguas rápidas: flotación, traje térmico y casco de acción (no de obra); el safety diver es plantilla obligatoria, no EPP.
            'water_work'        => ['Chaleco salvavidas', 'Traje térmico', 'Casco de stunt (integral / oculto)', 'Calzado antiderrapante'],
            // Bajo rotor: gafas selladas contra el rotor wash y protección auditiva, más chaleco para que la tripulación ubique a todos en la zona.
            'aerial_work'       => ['Protección ocular', 'Protección auditiva', 'Casco', 'Chaleco'],
            // Quien esté bajo la zona de vuelo lleva ocular y casco por hélice y caída; el extintor es por la batería LiPo en carga o tras impacto.
            'drones_uas'        => ['Protección ocular', 'Casco', 'Chaleco', 'Extintor asignado'],
            // La primera protección es la distancia y el wrangler; el guante y la bota son para el manejo, el casco para la monta y el nitrilo por zoonosis.
            'animals_wrangler'  => ['Guantes', 'Botas', 'Casco de stunt (integral / oculto)', 'Guantes de nitrilo'],
            // Equipo energizado junto al tanque: guante dieléctrico para quien conecta y flotación para quien trabaja al borde o dentro del agua.
            'electrical_water'  => ['Guantes dieléctricos', 'Botas', 'Chaleco salvavidas', 'Protección ocular'],
            // Grúa y technocrane: casco por el barrido y el contrapeso, chaleco para que el spotter distinga quién está dentro de la zona de exclusión.
            'camera_crane'      => ['Casco', 'Guantes', 'Botas', 'Chaleco'],
            // Operador en hostess tray o crew sobre process trailer: van amarrados con arnés o cinturón de cinco puntos, nunca sólo agarrados del rig.
            'camera_car'        => ['Casco de stunt (integral / oculto)', 'Arnés', 'Cinturón de 5 puntos y soporte cervical', 'Chaleco'],
            // El operador de Steadicam no ve sus pies: suela antiderrapante y arnés cuando la toma pasa por escalera o borde de altura.
            'stabilized_rig'    => ['Calzado antiderrapante', 'Arnés', 'Guantes'],
            // En scissor o condor el arnés va anclado a la canastilla, nunca a la estructura de al lado, y el casco cubre el golpe por vuelco o caída de objeto.
            'aerial_platform'   => ['Arnés', 'Casco', 'Botas', 'Guantes'],
            // Carga suspendida sobre gente: casco y zona despejada abajo, arnés para el tramoyista que trabaja el punto de anclaje arriba.
            'rigging_hoist'     => ['Casco', 'Guantes', 'Arnés', 'Botas'],
            // Litio y distribución DC de alta corriente: el arco al conectar quema cara y manos, y la fuga térmica se atiende con extintor asignado a la estación de carga.
            'portable_power'    => ['Protección ocular', 'Protección facial', 'Guantes dieléctricos', 'Extintor asignado'],
            // Alto voltaje del picture car eléctrico: guante dieléctrico y careta para intervenir el sistema, más extintor por reignición de la batería de tracción.
            'ev_hybrid'         => ['Guantes dieléctricos', 'Protección facial', 'Protección ocular', 'Botas', 'Extintor asignado'],
            // Traslado y maniobra de crew: EPP básico al bajar equipo; el control real es el cinturón, el turnaround y el conductor descansado.
            'utility_transport' => ['Chaleco', 'Botas', 'Guantes'],
            // Figuración de acción y estampida: pads bajo vestuario para el extra y chaleco para el staff que controla el flujo de la multitud.
            'crowd_action'      => ['Pads de stunt (rodilleras / coderas)', 'Botas', 'Chaleco'],
            // El menor lleva el mismo EPP que el adulto en esa acción, tallado a su medida: EPP de adulto suelto no protege, estorba.
            'minors_physical'   => ['Casco de stunt (integral / oculto)', 'Pads de stunt (rodilleras / coderas)', 'Arnés', 'Guantes'],
            // Campamento base: tránsito interno de vehículos, generadores y catering; visibilidad, calzado y extintor accesible junto a la planta y la cocina.
            'base_camp'         => ['Chaleco', 'Botas', 'Guantes', 'Extintor asignado'],
        ];
    }

    public function run()
    {
        if (!Schema::hasColumn('hazard_events', 'required_ppe')) {
            $this->command->warn('hazard_events.required_ppe no existe: aplica primero database/owner-apply/2026-07-22-epp-por-evento.sql');
            return;
        }

        $mapa         = self::mapa();
        $conCategoria = 0;
        $sinCategoria = 0;

        foreach (DB::table('hazard_events')->select('id', 'category', 'required_ppe')->get() as $ev) {
            // ADITIVO: no se pisa lo que ya tenga contenido.
            if (!empty($ev->required_ppe) && $ev->required_ppe !== 'null' && $ev->required_ppe !== '[]') {
                continue;
            }

            $cat = trim((string) $ev->category);
            if ($cat !== '' && isset($mapa[$cat])) {
                $epp = $mapa[$cat];
                $conCategoria++;
            } else {
                // Sin categoría (o con una que no está en el mapa): EPP básico de set.
                $epp = self::EPP_SIN_CATEGORIA;
                $sinCategoria++;
            }

            DB::table('hazard_events')->where('id', $ev->id)->update([
                'required_ppe' => json_encode($epp, JSON_UNESCAPED_UNICODE),
            ]);
        }

        $this->command->info('EPP poblado: ' . $conCategoria . ' eventos por su categoría + ' . $sinCategoria . ' con el EPP básico de set.');
    }
}
