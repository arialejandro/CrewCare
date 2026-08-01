<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IndicatorTerm;
use App\Support\ClinicalTextNormalizer;

/**
 * IndicatorTermSeeder — PROPUESTA de términos indicadores (delta #45), 2026-07-31.
 *
 * ⚠ Es una PROPUESTA, NO un catálogo cerrado ni una verdad clínica: todos nacen
 *   `is_clinician_verified = 0`. El owner/médico la ajusta (agrega, quita, reclasifica).
 *   Lo que no cae en ningún grupo queda SIN CLASIFICAR en el panel — y si esa cubeta
 *   crece, es señal de que faltan términos que agregar aquí.
 *
 * DECISIÓN: se EXCLUYEN los analgésicos/antipiréticos genéricos (paracetamol, ibuprofeno)
 * como término de MEDICAMENTO: son inespecíficos y clasificarían de más. El grupo se alcanza
 * por el DIAGNÓSTICO en esos casos (dos señales independientes: dx o medicamento).
 *
 * Los términos salen del botiquín de referencia y del vocabulario clínico común. Cada término
 * se guarda NORMALIZADO (minúsculas, sin acentos) además de su forma visible.
 *
 * ADITIVO E IDEMPOTENTE: updateOrCreate por (group_key, match_kind, term). Re-correrlo no duplica
 * y NO pisa `is_clinician_verified` si el médico ya lo puso en 1 (solo refresca display/nota).
 *
 * Correr:  php artisan db:seed --class=IndicatorTermSeeder
 */
class IndicatorTermSeeder extends Seeder
{
    /**
     * [group_key => ['medicamento' => [display...], 'diagnostico' => [display...]]]
     * ~36 términos: corto por diseño. El display lleva acentos; el `term` se normaliza al sembrar.
     */
    const PROPOSAL = [
        'gastrointestinal' => [
            'medicamento' => ['Loperamida', 'Diosmectita', 'Racecadotrilo', 'Butilhioscina'],
            'diagnostico' => ['diarrea', 'vómito', 'gastroenteritis'],
        ],
        'respiratorio' => [
            'medicamento' => ['Ambroxol', 'Dextrometorfano', 'Salbutamol'],
            'diagnostico' => ['tos', 'faringitis', 'gripa'],
        ],
        'dermico' => [
            'medicamento' => ['Mupirocina', 'Clorhexidina', 'Sulfadiazina'],
            'diagnostico' => ['herida', 'quemadura', 'dermatitis'],
        ],
        'alergico' => [
            'medicamento' => ['Loratadina', 'Cetirizina', 'Difenhidramina'],
            'diagnostico' => ['alergia', 'urticaria', 'prurito'],
        ],
        'oftalmico' => [
            'medicamento' => ['Tobramicina', 'Lágrimas artificiales'],
            'diagnostico' => ['conjuntivitis', 'ojo rojo', 'irritación ocular'],
        ],
        'musculoesqueletico' => [
            'medicamento' => ['Naproxeno', 'Diclofenaco', 'Ketorolaco'],
            'diagnostico' => ['esguince', 'lumbalgia', 'contractura'],
        ],
    ];

    public function run()
    {
        $note = 'Propuesta 2026-07-31 (botiquín de referencia + vocabulario común); sin verificar por médico.';
        $count = 0;

        foreach (self::PROPOSAL as $group => $kinds) {
            foreach ($kinds as $kind => $terms) {
                foreach ($terms as $display) {
                    $term = ClinicalTextNormalizer::normalize($display);
                    if ($term === '') {
                        continue;
                    }
                    IndicatorTerm::updateOrCreate(
                        ['group_key' => $group, 'match_kind' => $kind, 'term' => $term],
                        ['display_term' => $display, 'is_active' => 1, 'source_note' => $note]
                        // OJO: NO se toca is_clinician_verified aquí → si el médico ya lo puso en 1,
                        // re-sembrar no lo revierte (updateOrCreate solo escribe las claves dadas).
                    );
                    $count++;
                }
            }
        }

        if ($this->command) {
            $this->command->info("IndicatorTermSeeder: {$count} términos propuestos en ".count(self::PROPOSAL)." grupos (is_clinician_verified=0).");
        }
    }
}
