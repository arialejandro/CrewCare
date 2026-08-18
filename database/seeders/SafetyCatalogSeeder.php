<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\SafetyStandard;

/**
 * SafetyCatalogSeeder — Catálogo NORMATIVO expandido (2026-06-28, +reference_url 2026-06-28,
 * +updateOrCreate/category_name_en/GEN-001 2026-07-13).
 *
 * Expande la tabla `safety_standards` (que hoy alimenta el dropdown de hallazgos del Daily
 * Report) con el marco completo consultado: los 45 AMPTP/CSATF Safety Bulletins + referencias
 * clave de OSHA/Cal-OSHA + NOMs de STPS. La idea: un solo catálogo que cualquier reporte
 * (Daily, Hazard, Cond. Insegura, Lesión, Scouting) use como dropdown → llenar más rápido y
 * auto-etiquetar la norma aplicable (badge + code) + ENLAZAR al boletín/norma oficial.
 *
 * ── ITEM #1 (reference_url) ──────────────────────────────────────────────────────────────
 * Cada fila trae la URL OFICIAL del boletín/norma (4º elemento de cada tupla):
 *   - CSATF: la liga del boletín EN ESPAÑOL de csatf.org (sufijo -sp / _SP.pdf; verificadas una
 *     por una abriendo cada PDF "BOLETÍN DE SEGURIDAD N.º X"). Mezcla de PDFs directos
 *     (/wp-content/.../*_SP.pdf) y page-slugs (con "/" final). DERECHOS DE AUTOR: NO copiamos el
 *     contenido del boletín a la app; solo enlazamos al original.
 *   - OSHA: la liga del standardnumber en osha.gov / dir.ca.gov (Cal/OSHA T8).
 *   - STPS: la página oficial de STPS (las NOMs cambian de URL en el DOF; el landing de STPS
 *     es la liga estable; el owner puede afinar a deep-link por NOM después).
 * Requiere la columna nueva — el OWNER la aplica FUERA de Laravel (no migrate):
 *   ALTER TABLE safety_standards ADD COLUMN reference_url VARCHAR(500) NULL AFTER regulation_code;
 * Si la columna aún no existe, el seeder NO falla: sincroniza las filas y omite las URLs
 * (Schema::hasColumn lo detecta) para que puedas correrlo antes o después del ALTER.
 *
 * ── (2026-07-13) COHERENCIA ──────────────────────────────────────────────────────────────
 *   - updateOrCreate por `regulation_code`: si algún nombre/badge quedó mal, se CORRIGE
 *     idempotentemente (antes era firstOrCreate, que conservaba nombres malos). El catálogo de
 *     abajo es la fuente de verdad de category_name + regulation_badge.
 *   - category_name_en (5º elemento): traducción al inglés de cine/H&S. La columna ya existe
 *     (Módulo 14) y `SafetyStandard::getCategoryNameLocalizedAttribute()` la sirve cuando el
 *     locale es 'en'. Se ESCRIBE por asignación directa (no vía el array de updateOrCreate):
 *     `category_name_en` NO está en $fillable de SafetyStandard (y ese modelo no se toca en esta
 *     ola), así que un mass-assign la descartaría en silencio; la asignación directa persiste
 *     igual que reference_url. Guard: solo si Schema::hasColumn (PROD aún puede no tener el ALTER).
 *   - Entrada canónica NUEVA "Condiciones de seguridad general" (GEN-001): opción catch-all para
 *     hallazgos generales que no encajan en un boletín/NOM específico.
 *
 * IDEMPOTENTE: updateOrCreate por `regulation_code` (no duplica filas) + escritura de URL/EN solo
 * si difiere. Re-correr no cambia nada. Correr con:  php artisan db:seed --class=SafetyCatalogSeeder
 *
 * NOTA de datos: en el catálogo original la fila de "Vías Férreas" estaba como Bulletin #29, pero
 * el oficial es #28 (Railroad); #29 es Globos aerostáticos. Este seeder usa la numeración oficial.
 *
 * Badges (coinciden con los colores de dailyreports/show): CSATF (boletines AMPTP), OSHA, STPS,
 * GENERAL (entrada catch-all GEN-001).
 */
class SafetyCatalogSeeder extends Seeder
{
    public function run()
    {
        $csatf = 'https://www.csatf.org';
        $stpsHome = 'https://www.gob.mx/stps';

        // [category_name, badge, regulation_code, reference_url, category_name_en]
        $catalog = [
            // ───────────── AMPTP / CSATF Safety Bulletins #1–#45 (badge CSATF) ─────────────
            // URLs en ESPAÑOL (boletines en español de csatf.org). Verificadas una por una
            // (se abrió cada PDF y dice "BOLETÍN DE SEGURIDAD N.º X"). OJO con los slugs raros
            // que se conservan VERBATIM: #8 trae el typo "camerra"; #44/#45 usan la forma
            // safety-bulletin-44_sp / -45-sp; #2 y #25 usan guiones bajos. Los page-slugs llevan
            // "/" final (sin ella csatf.org hace 301); los PDFs directos NO llevan "/" final.
            ['Armas de fuego, balas de salva y municiones de utilería', 'CSATF', 'Bulletin #1',  $csatf.'/01-safety-bulletin-firearms-sp', 'Firearms, blanks, and prop ammunition'],
            ['Prohibición de munición real en set', 'CSATF', 'Bulletin #2',  $csatf.'/02_safety_bltn_live_ammunition-sp', 'Prohibition of live ammunition on set'],
            ['Helicópteros en producción', 'CSATF', 'Bulletin #3',  $csatf.'/wp-content/uploads/2018/11/03HELICOPTER_SP.pdf', 'Helicopters in production'],
            ['Stunts / escenas de acción', 'CSATF', 'Bulletin #4',  $csatf.'/04-safety-bulletin-stunts-sp/', 'Stunts / action scenes'],
            ['Conciencia de seguridad (general)', 'CSATF', 'Bulletin #5',  $csatf.'/wp-content/uploads/2018/11/05SAFETY_AWARENESS_SP.pdf', 'Safety awareness (general)'],
            ['Manejo de animales', 'CSATF', 'Bulletin #6',  $csatf.'/06-safety-bulletin-animal-handling-sp', 'Animal handling'],
            ['Operaciones de buceo', 'CSATF', 'Bulletin #7',  $csatf.'/07-safety-bulletin-diving-sp/', 'Diving operations'],
            ['Camera cars y process trailers', 'CSATF', 'Bulletin #8',  $csatf.'/08-safety-bulletin-camerra-cars-sp/', 'Camera cars and process trailers'],
            ['Base camps (campamento base)', 'CSATF', 'Bulletin #9',  $csatf.'/09-safety-bulletin-base-camps-sp/', 'Base camps'],
            ['Niebla / humo atmosférico artificial (fog & haze)', 'CSATF', 'Bulletin #10',  $csatf.'/10-safety-bulletin-fog-haze-sp/', 'Fog & haze (artificial atmospheric smoke)'],
            ['Aeronaves de ala fija', 'CSATF', 'Bulletin #11',  $csatf.'/wp-content/uploads/2018/11/11FIXED_WING_SP.pdf', 'Fixed-wing aircraft'],
            ['Reptiles venenosos', 'CSATF', 'Bulletin #12',  $csatf.'/12-safety-bulletin-venomous-reptiles-sp', 'Venomous reptiles'],
            ['Combustibles e inflamables', 'CSATF', 'Bulletin #13',  $csatf.'/13-safety-bulletin-flammable-fuels-sp/', 'Flammable fuels'],
            ['Paracaidismo', 'CSATF', 'Bulletin #14',  $csatf.'/14-safety-bulletin-parachutes-january-sp/', 'Parachuting'],
            ['Embarcaciones / seguridad acuática', 'CSATF', 'Bulletin #15',  $csatf.'/15-safety-bulletin-boating-august-sp/', 'Boating / water safety'],
            ['Pirotecnia y efectos especiales', 'CSATF', 'Bulletin #16',  $csatf.'/16-safety-bulletin-pyrotechnic-may-sp/', 'Pyrotechnics and special effects'],
            ['Riesgos por agua (water hazards)', 'CSATF', 'Bulletin #17',  $csatf.'/17-safety-bulletin-water-hazards-sp/', 'Water hazards'],
            ['Air bags / sistemas de caída para stunts', 'CSATF', 'Bulletin #18',  $csatf.'/18-safety-bulletin-air-bags-sp/', 'Air bags / stunt fall systems'],
            ['Fuego abierto (open flame)', 'CSATF', 'Bulletin #19',  $csatf.'/19-safety-bulletin-flames-september-sp/', 'Open flame'],
            ['Motocicletas', 'CSATF', 'Bulletin #20',  $csatf.'/20-safety-bulletin-motorcycles-sp', 'Motorcycles'],
            ['Ropa adecuada y EPP', 'CSATF', 'Bulletin #21',  $csatf.'/21-safety-bulletin-clothing-sp/', 'Appropriate clothing and PPE'],
            ['Plataformas elevadoras (scissor / boom lifts)', 'CSATF', 'Bulletin #22',  $csatf.'/22-safety-bulletin-platforms-sp/', 'Aerial work platforms (scissor / boom lifts)'],
            ['Distribución eléctrica portátil', 'CSATF', 'Bulletin #23',  $csatf.'/23-safety-bulletin-electrical-sp/', 'Portable electrical distribution'],
            ['Patógenos en sangre / materiales infecciosos', 'CSATF', 'Bulletin #24',  $csatf.'/24-safety-bulletin-bloodborne-sp/', 'Bloodborne pathogens / infectious materials'],
            ['Grúas de cámara (camera cranes)', 'CSATF', 'Bulletin #25',  $csatf.'/25-safety-bulletin-camera_cranes-sp', 'Camera cranes'],
            ['Locaciones exteriores urbanas', 'CSATF', 'Bulletin #26',  $csatf.'/wp-content/uploads/2018/11/26URBAN_LOCATIONS_SP.pdf', 'Urban exterior locations'],
            ['Plantas venenosas', 'CSATF', 'Bulletin #27',  $csatf.'/wp-content/uploads/2018/11/27PLANTS_SP.pdf', 'Poisonous plants'],
            ['Seguridad en vías férreas (railroad)', 'CSATF', 'Bulletin #28',  $csatf.'/28-safety-bulletin-railroads-sp/', 'Railroad safety'],
            ['Globos aerostáticos', 'CSATF', 'Bulletin #29',  $csatf.'/wp-content/uploads/2018/11/29Balloon_SP.pdf', 'Hot air balloons'],
            ['Props con filo, punzantes o proyectiles', 'CSATF', 'Bulletin #30',  $csatf.'/30-safety-bulletin-props-sp/', 'Bladed, pointed, or projectile props'],
            ['Fauna silvestre local', 'CSATF', 'Bulletin #31',  $csatf.'/31-indigenous-wildlife-august-2021-spanish', 'Indigenous wildlife'],
            ['Manejo de alimentos en producción', 'CSATF', 'Bulletin #32',  $csatf.'/wp-content/uploads/2018/11/32FOOD_HANDLING_SP.pdf', 'Food handling in production'],
            ['Actores infantiles (bebés)', 'CSATF', 'Bulletin #33',  $csatf.'/33-safety-bulletin-infant-actors-sp/', 'Infant actors'],
            ['Trabajo en frío extremo', 'CSATF', 'Bulletin #34',  $csatf.'/34-safety-bulletin-cold-temps-sp/', 'Work in extreme cold'],
            ['Prevención de enfermedad por calor (outdoor heat)', 'CSATF', 'Bulletin #35',  $csatf.'/35-safety-bulletin-heat-illness-sp', 'Heat illness prevention (outdoor heat)'],
            ['Drones / sistemas aéreos no tripulados (UAS)', 'CSATF', 'Bulletin #36',  $csatf.'/36-safety-bulletin-november-sp', 'Drones / unmanned aerial systems (UAS)'],
            ['Restricción vehicular (cinturones / arneses)', 'CSATF', 'Bulletin #37',  $csatf.'/37-safety-bulletin-restraint-systems-sp/', 'Vehicle restraint (seatbelts / harnesses)'],
            ['Clima inclemente o severo (incl. rayos)', 'CSATF', 'Bulletin #38',  $csatf.'/38-safety-bulletin-inclement-weather-sp/', 'Inclement or severe weather (incl. lightning)'],
            ['Plásticos espumados (foam) en sets / props', 'CSATF', 'Bulletin #39',  $csatf.'/wp-content/uploads/2018/11/39FOAMED_PLASTICS_SP.pdf', 'Foamed plastics (foam) in sets / props'],
            ['Vehículos utilitarios no-cámara', 'CSATF', 'Bulletin #40',  $csatf.'/wp-content/uploads/2018/11/40NON-CAMERA-UTILITY-VEHICLES_SP.pdf', 'Non-camera utility vehicles'],
            ['Gimbals', 'CSATF', 'Bulletin #41',  $csatf.'/41-safety-bulletin-gimbals-sp', 'Gimbals'],
            ['Sistemas de conducción alternativa', 'CSATF', 'Bulletin #42',  $csatf.'/wp-content/uploads/2018/11/42ALTERNATIVE_DRIVING_SYSTEMS_SP.pdf', 'Alternative driving systems'],
            ['Free driving (conducción libre en cámara)', 'CSATF', 'Bulletin #43',  $csatf.'/wp-content/uploads/2018/11/43-Recommended_Guidelines_for_Free_Driving_SP.pdf', 'Free driving (on-camera)'],
            ['Transmisores de radiofrecuencia (RF)', 'CSATF', 'Bulletin #44',  $csatf.'/safety-bulletin-44_sp/', 'Radiofrequency (RF) transmitters'],
            ['Tomas largas o sucesivas', 'CSATF', 'Bulletin #45',  $csatf.'/safety-bulletin-45-sp/', 'Long or successive takes'],

            // ───────────── OSHA / Cal-OSHA (badge OSHA) ─────────────
            ['Registro de lesiones y enfermedades (OSHA 300/301)', 'OSHA', '29 CFR 1904',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1904', 'Injury and illness recordkeeping (OSHA 300/301)'],
            ['Programa de Prevención de Lesiones y Enfermedades (IIPP)', 'OSHA', 'Cal/OSHA T8 §3203',  'https://www.dir.ca.gov/title8/3203.html', 'Injury and Illness Prevention Program (IIPP)'],
            ['Protección contra caídas', 'OSHA', '29 CFR 1926 Subpart M',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1926/1926SubpartM', 'Fall protection'],
            ['Control de energía peligrosa (LOTO) / eléctrico', 'OSHA', '29 CFR 1910.147',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.147', 'Hazardous energy control (LOTO) / electrical'],
            ['Espacios confinados con permiso', 'OSHA', '29 CFR 1910.146',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.146', 'Permit-required confined spaces'],
            ['Comunicación de peligros (HazCom)', 'OSHA', '29 CFR 1910.1200',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.1200', 'Hazard communication (HazCom)'],

            // ───────────── STPS — NOMs (badge STPS) — landing oficial estable ─────────────
            ['Comisión de Seguridad e Higiene / investigación de accidentes', 'STPS', 'NOM-019-STPS-2011',  $stpsHome, 'Safety and Health Commission / accident investigation'],
            ['Diagnóstico y programa de seguridad y salud (SST)', 'STPS', 'NOM-030-STPS-2009',  $stpsHome, 'Occupational health and safety diagnosis and program (OHS)'],
            ['Trabajos en altura', 'STPS', 'NOM-009-STPS-2011',  $stpsHome, 'Work at height'],
            ['Prevención y protección contra incendios', 'STPS', 'NOM-002-STPS-2010',  $stpsHome, 'Fire prevention and protection'],
            ['Seguridad eléctrica en el trabajo', 'STPS', 'NOM-029-STPS-2011',  $stpsHome, 'Electrical safety at work'],
            ['Equipo de protección personal (EPP)', 'STPS', 'NOM-017-STPS-2008',  $stpsHome, 'Personal protective equipment (PPE)'],
            ['Ergonomía / manejo manual de cargas', 'STPS', 'NOM-036-1-STPS-2018',  $stpsHome, 'Ergonomics / manual materials handling'],
            ['Sustancias químicas peligrosas', 'STPS', 'NOM-018-STPS-2015',  $stpsHome, 'Hazardous chemical substances'],
            ['Espacios confinados', 'STPS', 'NOM-033-STPS-2015',  $stpsHome, 'Confined spaces'],
            ['Construcción — condiciones de seguridad', 'STPS', 'NOM-031-STPS-2011',  $stpsHome, 'Construction — safety conditions'],

            // ───────────── OSHA — normas AÑADIDAS para el catálogo de eventos (2026-07-13) ─────────────
            // Cubren construcción/adaptación de foros/transversal (andamios, ruido, maquinaria,
            // montacargas, rigging, sílice, orden y limpieza, EPP general). Códigos = blanco N:N
            // de HazardEvent.standards(). URLs en osha.gov (mismo patrón que el bloque OSHA de arriba).
            ['Andamios (scaffolds)', 'OSHA', '29 CFR 1926 Subpart L',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1926/1926SubpartL', 'Scaffolds'],
            ['Exposición a ruido ocupacional', 'OSHA', '29 CFR 1910.95',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.95', 'Occupational noise exposure'],
            ['Guardas y protección de maquinaria', 'OSHA', '29 CFR 1910.212',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.212', 'Machine guarding'],
            ['Montacargas / vehículos industriales motorizados', 'OSHA', '29 CFR 1910.178',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.178', 'Powered industrial trucks (forklifts)'],
            ['Grúas y aparejos (rigging) en construcción', 'OSHA', '29 CFR 1926 Subpart CC',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1926/1926SubpartCC', 'Cranes and rigging in construction'],
            ['Sílice cristalina respirable (polvo)', 'OSHA', '29 CFR 1926.1153',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1926/1926.1153', 'Respirable crystalline silica'],
            ['Superficies de tránsito y trabajo (orden y limpieza)', 'OSHA', '29 CFR 1910.22',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.22', 'Walking-working surfaces (housekeeping)'],
            ['Equipo de protección personal (general)', 'OSHA', '29 CFR 1910.132',  'https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.132', 'Personal protective equipment (general)'],

            // ───────────── STPS — NOMs AÑADIDAS para el catálogo de eventos (2026-07-13) ─────────────
            ['Ruido', 'STPS', 'NOM-011-STPS-2001',  $stpsHome, 'Occupational noise'],
            ['Iluminación en el centro de trabajo', 'STPS', 'NOM-025-STPS-2008',  $stpsHome, 'Workplace lighting'],
            ['Sistemas y dispositivos de protección de maquinaria', 'STPS', 'NOM-004-STPS-1999',  $stpsHome, 'Machinery guarding and protection devices'],
            ['Manejo y almacenamiento de materiales', 'STPS', 'NOM-006-STPS-2014',  $stpsHome, 'Materials handling and storage'],
            ['Soldadura y corte', 'STPS', 'NOM-027-STPS-2008',  $stpsHome, 'Welding and cutting'],
            ['Edificios, locales e instalaciones (condiciones del centro de trabajo)', 'STPS', 'NOM-001-STPS-2008',  $stpsHome, 'Buildings, premises and installations'],
            ['Condiciones térmicas elevadas o abatidas', 'STPS', 'NOM-015-STPS-2001',  $stpsHome, 'Elevated or lowered thermal conditions'],
            ['Vibraciones', 'STPS', 'NOM-024-STPS-2001',  $stpsHome, 'Vibrations'],

            // ───────────── GENERAL — entrada catch-all (2026-07-13) ─────────────
            // "Condiciones de seguridad general": para hallazgos que no encajan en un boletín/NOM
            // específico. Sin URL oficial (concepto transversal); badge GENERAL propio.
            ['Condiciones de seguridad general', 'GENERAL', 'GEN-001',  null, 'General safety conditions'],
        ];

        // ¿Existen ya las columnas? (el owner las aplica fuera de Laravel).
        $hasUrlColumn = Schema::hasColumn('safety_standards', 'reference_url');
        $hasEnColumn  = Schema::hasColumn('safety_standards', 'category_name_en');

        // ── GUARD anti-duplicado del BUMP STPS (2026-08-17) ──────────────────────────────
        // EnrichedCatalogSeeder RENOMBRA in-place la fila vieja al año nuevo (mismo id):
        //   NOM-017-STPS-2008 → -2024   ·   NOM-006-STPS-2014 → -2023  (ver EnrichedCatalogSeeder::$stpsBump).
        // Este catálogo SIGUE listando el año VIEJO A PROPÓSITO: los hazard_events lo CITAN
        // por ese código y HazardEventSeeder corre ANTES del bump, así que la 1ª pasada DEBE
        // crear la vieja para que esos eventos resuelvan; luego el bump la renombra. El problema
        // es re-correr este seeder DESPUÉS del bump: recrearía la vieja como DUPLICADO de la
        // nueva. El guard lo evita: si la versión BUMPEADA ya existe, se salta la vieja.
        // (No se cambia el año aquí a propósito: eso obligaría a tocar la data de eventos/SFX
        //  que cita el año viejo — en uso, fuera del alcance de este arreglo.)
        $bumpedAway = ['NOM-017-STPS-2008' => 'NOM-017-STPS-2024', 'NOM-006-STPS-2014' => 'NOM-006-STPS-2023'];

        $urlsWritten = 0;
        $enWritten   = 0;
        $bumpSkipped = 0;
        foreach ($catalog as [$name, $badge, $code, $url, $nameEn]) {
            if (isset($bumpedAway[$code]) && SafetyStandard::where('regulation_code', $bumpedAway[$code])->exists()) {
                $bumpSkipped++;
                continue;   // ya bumpeada → NO recrear la vieja (evita el duplicado post-bump)
            }
            // Clave de idempotencia = regulation_code. updateOrCreate CORRIGE name/badge si
            // quedaron mal (antes firstOrCreate los conservaba). category_name y regulation_badge
            // SÍ están en $fillable de SafetyStandard, así que el mass-assign los escribe.
            $row = SafetyStandard::updateOrCreate(
                ['regulation_code' => $code],
                ['category_name' => $name, 'regulation_badge' => $badge]
            );

            $dirty = false;

            // URL: FORZAR el valor del catálogo (este seeder es la fuente de verdad de las ligas).
            // Pisa solo si difiere → sigue idempotente. Asignación directa (no bloqueada por $fillable).
            if ($hasUrlColumn && $url && $row->reference_url !== $url) {
                $row->reference_url = $url;
                $urlsWritten++;
                $dirty = true;
            }

            // category_name_en: asignación DIRECTA a propósito (no está en $fillable de
            // SafetyStandard y ese modelo no se toca en esta ola; un mass-assign la descartaría).
            if ($hasEnColumn && $nameEn && $row->category_name_en !== $nameEn) {
                $row->category_name_en = $nameEn;
                $enWritten++;
                $dirty = true;
            }

            if ($dirty) {
                $row->save();
            }
        }

        $this->command->info('SafetyCatalogSeeder: catálogo normativo sincronizado ('.SafetyStandard::count().' filas totales).');
        if ($bumpSkipped > 0) {
            $this->command->info("SafetyCatalogSeeder: {$bumpSkipped} norma(s) STPS ya bumpeada(s) → NO recreada(s) (guard anti-duplicado).");
        }
        if ($hasUrlColumn) {
            $this->command->info("SafetyCatalogSeeder: reference_url actualizado en {$urlsWritten} filas (boletines CSATF en español).");
        } else {
            $this->command->warn('SafetyCatalogSeeder: la columna `reference_url` NO existe aún → URLs OMITIDAS. Aplica el ALTER y vuelve a correr el seeder.');
        }
        if ($hasEnColumn) {
            $this->command->info("SafetyCatalogSeeder: category_name_en actualizado en {$enWritten} filas (traducción EN).");
        } else {
            $this->command->warn('SafetyCatalogSeeder: la columna `category_name_en` NO existe aún → traducciones EN OMITIDAS. Aplica el ALTER y vuelve a correr el seeder.');
        }
    }
}
