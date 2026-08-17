<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\SafetyStandard;

/**
 * ResolveParkedStandardsSeeder — da de alta las normas que faltaban para RESOLVER las
 * citas PARQUEADAS del catálogo de Herramienta/Permisos (2026-08-17). ADITIVO.
 *
 * ── POR QUÉ UN SEEDER APARTE (y no dentro de SafetyCatalogSeeder) ────────────────
 * SafetyCatalogSeeder define NOM-017/NOM-006 con su AÑO VIEJO (-2008/-2014) y el
 * EnrichedCatalogSeeder los "bumpea" IN-PLACE a -2024/-2023. Re-correr SafetyCatalogSeeder
 * standalone sobre una BD ya bumpeada RECREA las filas viejas como duplicados. Para NO
 * disparar ese conflicto al agregar estas normas nuevas, viven aquí: un seeder puramente
 * aditivo que sólo inserta CÓDIGOS NUEVOS (ninguno colisiona con lo existente) y NUNCA
 * re-procesa el catálogo base.
 *
 * ── CÓMO RESUELVE ────────────────────────────────────────────────────────────────
 * StandardCodeResolver hace match EXACTO contra regulation_code; por eso el código se
 * guarda IDÉNTICO a la cadena parqueada (p.ej. '29 CFR 1926.300(c)'), respetando el GRANO
 * de la fuente (1926.300(c) es su fila, NO 1926.300). Varias secciones comparten la URL de
 * su sección (la URL apunta a la sección; el código identifica el inciso). Dos casos se
 * normalizan por cadena cruda en StandardCodeResolver::ADJUDICATED ('1910.243(a)(1)'→sección,
 * 'NOM-010-STPS'→'-2014'); los que traen paréntesis ('...(líquidos inflamables)') resuelven
 * solos por el paso "paren" del resolver.
 *
 * Tras correr ESTE seeder, re-correr ToolPermitCatalogSeeder RE-RESUELVE las parqueadas
 * (crea los vínculos reales y limpia catalog_pending_standards de lo ya resuelto).
 *
 * Nacen PENDIENTES (verified_at NULL), como el resto del catálogo redactado desde oficio.
 * Idempotente: updateOrCreate por regulation_code; NO pisa filas existentes (todos códigos nuevos).
 *
 * ⚠ NO se inventaron URLs. Títulos tomados de la fuente (osha.gov / dir.ca.gov / DOF).
 *   NOM-005-STPS-1998 queda con URL VACÍA a propósito (publicación de 1999, previa al
 *   sistema `codigo` del DOF; no se encontró un nota_detalle canónico).
 *
 * Correr:  php artisan db:seed --class=ResolveParkedStandardsSeeder
 */
class ResolveParkedStandardsSeeder extends Seeder
{
    public function run(): void
    {
        $osha = 'https://www.osha.gov/laws-regs/regulations/standardnumber/';

        // [category_name, badge, regulation_code, reference_url, category_name_en]
        $catalog = [
            // GRUPO 1 · Herramientas — OSHA 29 CFR 1926 Subpart I (Hand and Power Tools) — ~264 citas
            ['Herramientas — requisitos generales: condición de las herramientas', 'OSHA', '29 CFR 1926.300(a)',  $osha.'1926/1926.300', 'Hand and power tools — General requirements: condition of tools'],
            ['Herramientas — requisitos generales: guardas', 'OSHA', '29 CFR 1926.300(b)(1)',  $osha.'1926/1926.300', 'Hand and power tools — General requirements: guarding'],
            ['Herramientas — requisitos generales: guardas', 'OSHA', '29 CFR 1926.300(b)(6)',  $osha.'1926/1926.300', 'Hand and power tools — General requirements: guarding'],
            ['Herramientas — requisitos generales: equipo de protección personal', 'OSHA', '29 CFR 1926.300(c)',  $osha.'1926/1926.300', 'Hand and power tools — General requirements: personal protective equipment'],
            ['Herramientas manuales', 'OSHA', '29 CFR 1926.301',  $osha.'1926/1926.301', 'Hand tools'],
            ['Herramientas eléctricas de mano — eléctricas', 'OSHA', '29 CFR 1926.302(a)',  $osha.'1926/1926.302', 'Power-operated hand tools — Electric'],
            ['Herramientas eléctricas de mano — neumáticas', 'OSHA', '29 CFR 1926.302(b)',  $osha.'1926/1926.302', 'Power-operated hand tools — Pneumatic'],
            ['Discos y herramientas abrasivas', 'OSHA', '29 CFR 1926.303',  $osha.'1926/1926.303', 'Abrasive wheels and tools'],
            ['Herramientas para trabajo en madera', 'OSHA', '29 CFR 1926.304',  $osha.'1926/1926.304', 'Woodworking tools'],
            ['Recipientes de aire (compresores)', 'OSHA', '29 CFR 1926.306',  $osha.'1926/1926.306', 'Air receivers'],

            // GRUPO 2 · Cal/OSHA (Título 8) — la de la inspección con orden de paro — 70 citas
            ['Maquinaria y equipo — partes defectuosas que crean peligro no deben usarse', 'OSHA', 'Cal/OSHA T8 3328(c)',  'https://www.dir.ca.gov/title8/3328.html', 'Machinery and Equipment — defective parts creating a hazard shall not be used'],

            // GRUPO 3 · Eléctrico
            ['Diseño y protección del cableado — GFCI (protección por falla a tierra)', 'OSHA', '29 CFR 1926.404(b)(1)(iii)(D)',  $osha.'1926/1926.404', 'Wiring design and protection — GFCI'],
            ['Diseño y protección del cableado', 'OSHA', '29 CFR 1926.404(b)(1)(i)',  $osha.'1926/1926.404', 'Wiring design and protection'],
            ['Instalaciones eléctricas — general', 'OSHA', '29 CFR 1910.303',  $osha.'1910/1910.303', 'Electrical — General'],

            // GRUPO 4 · General industry, rigging y escaleras
            ['Protección de herramientas portátiles motorizadas', 'OSHA', '29 CFR 1910.243',  $osha.'1910/1910.243', 'Guarding of portable powered tools'],
            ['Eslingas', 'OSHA', '29 CFR 1910.184',  $osha.'1910/1910.184', 'Slings'],
            ['Equipo de aparejo para manejo de materiales', 'OSHA', '29 CFR 1926.251',  $osha.'1926/1926.251', 'Rigging equipment for material handling'],
            ['Escaleras portátiles', 'OSHA', '29 CFR 1926.1053',  $osha.'1926/1926.1053', 'Ladders'],
            ['Líquidos inflamables', 'OSHA', '29 CFR 1910.106',  $osha.'1910/1910.106', 'Flammable liquids'],
            ['Soldadura y corte con gas', 'OSHA', '29 CFR 1926.350',  $osha.'1926/1926.350', 'Gas welding and cutting'],
            ['Prevención de incendios (soldadura y corte)', 'OSHA', '29 CFR 1926.352',  $osha.'1926/1926.352', 'Fire prevention (welding and cutting)'],
            ['Andamios — requisitos generales', 'OSHA', '29 CFR 1926.451',  $osha.'1926/1926.451', 'Scaffolds — General requirements'],

            // GRUPO 5 · STPS
            ['Agentes químicos contaminantes del ambiente laboral — Reconocimiento, evaluación y control', 'STPS', 'NOM-010-STPS-2014',  'https://dof.gob.mx/nota_detalle.php?codigo=5342372&fecha=28/04/2014', 'Airborne chemical contaminants in the workplace — Recognition, evaluation and control'],
            ['Recipientes sujetos a presión, recipientes criogénicos y generadores de vapor o calderas — Funcionamiento — Condiciones de seguridad', 'STPS', 'NOM-020-STPS-2011',  'https://dof.gob.mx/nota_detalle.php?codigo=5229908&fecha=27/12/2011', 'Pressure vessels, cryogenic vessels and steam generators or boilers — Operation — Safety conditions'],
            // ⚠ URL del DOF NO encontrada (1999, previa al `codigo` del DOF) → VACÍA a propósito.
            ['Manejo, transporte y almacenamiento de sustancias químicas peligrosas', 'STPS', 'NOM-005-STPS-1998',  null, 'Handling, transport and storage of hazardous chemical substances'],
        ];

        $hasUrl = Schema::hasColumn('safety_standards', 'reference_url');
        $hasEn  = Schema::hasColumn('safety_standards', 'category_name_en');

        $created = 0; $noop = 0;
        foreach ($catalog as [$name, $badge, $code, $url, $nameEn]) {
            $row = SafetyStandard::updateOrCreate(
                ['regulation_code' => $code],
                ['category_name' => $name, 'regulation_badge' => $badge]
            );
            $row->wasRecentlyCreated ? $created++ : $noop++;

            // URL y EN por asignación DIRECTA (category_name_en no está en $fillable).
            $dirty = false;
            if ($hasUrl && $url && $row->reference_url !== $url) { $row->reference_url = $url; $dirty = true; }
            if ($hasEn && $nameEn && $row->category_name_en !== $nameEn) { $row->category_name_en = $nameEn; $dirty = true; }
            if ($dirty) { $row->save(); }
        }

        if ($this->command) {
            $this->command->info(
                "ResolveParkedStandardsSeeder: ".count($catalog)." normas · {$created} creadas · {$noop} ya existían. "
                ."Re-corre ToolPermitCatalogSeeder para re-resolver las parqueadas."
            );
        }
    }
}
