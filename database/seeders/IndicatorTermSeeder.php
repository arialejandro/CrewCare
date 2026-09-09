<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IndicatorTerm;
use App\Support\ClinicalTextNormalizer;

/**
 * IndicatorTermSeeder — PROPUESTA de términos indicadores (delta #45), 2026-07-31.
 * Corregida 2026-08-17 con el botiquín REAL de la producción (presupuesto), reemplazando
 * el vocabulario genérico anterior (medicamentos que no existían en el botiquín → grupos
 * que reportarían CERO para siempre).
 *
 * ⚠ Es una PROPUESTA, NO un catálogo cerrado ni una verdad clínica: todos nacen
 *   `is_clinician_verified = 0`. El owner/médico la ajusta (agrega, quita, reclasifica).
 *   Lo que no cae en ningún grupo queda SIN CLASIFICAR en el panel — y si esa cubeta
 *   crece, es señal de que faltan términos que agregar aquí.
 *
 * DECISIÓN (musculoesquelético): se EXCLUYEN los analgésicos ORALES genéricos —naproxeno,
 * diclofenaco, ketorolaco— como término de MEDICAMENTO: se dan para todo (cefalea, cruda,
 * dolor inespecífico) e inflarían el grupo hasta no significar nada (mismo criterio con que
 * ya se excluyó el paracetamol). Ese grupo se alcanza SOBRE TODO POR DIAGNÓSTICO; de
 * medicamento SOLO los específicos (metocarbamol, clorzoxazona, y las presentaciones
 * tópicas/combinadas: "diclofenaco gel", "naproxeno con lidocaína"). El empate es por
 * FRASE COMPLETA con frontera de palabra (ClinicalTextNormalizer::containsTerm), así que
 * un "naproxeno" oral NO cae en el grupo.
 *
 * Cada término se guarda NORMALIZADO (minúsculas, sin acentos) además de su forma visible.
 *
 * ADITIVO + SINCRONIZADO E IDEMPOTENTE:
 *   - updateOrCreate por (group_key, match_kind, term): re-correrlo no duplica y NO pisa
 *     `is_clinician_verified` si el médico ya lo puso en 1 (solo refresca display/nota).
 *   - RETIRO por diferencia: los términos de un grupo/tipo que YA NO están en la propuesta
 *     se marcan is_active=0 —salvo los que un médico ya verificó (is_clinician_verified=1),
 *     que se respetan—. Retirar es is_active=0, NUNCA ->delete() (deja rastro; el agregador
 *     ya filtra is_active=1, así que un retirado deja de contar).
 *
 * Correr:  php artisan db:seed --class=IndicatorTermSeeder
 */
class IndicatorTermSeeder extends Seeder
{
    /**
     * [group_key => ['medicamento' => [display...], 'diagnostico' => [display...]]]
     * Del botiquín real (presupuesto de producción). El display lleva acentos; el `term`
     * se normaliza al sembrar (minúsculas, sin acentos).
     */
    const PROPOSAL = [
        'gastrointestinal' => [
            'medicamento' => ['Loperamida', 'Diosmectita', 'Racecadotrilo', 'Butilhioscina',
                              'Metoclopramida', 'Difenidol', 'Bacillus clausii', 'Electrolitos orales'],
            'diagnostico' => ['diarrea', 'vómito', 'gastroenteritis', 'náusea', 'colitis'],
        ],
        'respiratorio' => [
            'medicamento' => ['Ambroxol', 'Dextrometorfano', 'Benzonatato', 'Oximetazolina', 'Antigripal'],
            'diagnostico' => ['tos', 'faringitis', 'gripa', 'rinitis', 'infección respiratoria'],
        ],
        'dermico' => [
            'medicamento' => ['Nitrofural', 'Ácido acexámico', 'Clindamicina tópica', 'Aciclovir',
                              'Benzocaína', 'Betametasona tópica', 'Dicloxacilina'],
            'diagnostico' => ['herida', 'quemadura', 'dermatitis', 'abrasión', 'absceso'],
        ],
        'alergico' => [
            'medicamento' => ['Loratadina', 'Desloratadina', 'Cetirizina', 'Cloropiramida',
                              'Hidroxicina', 'Epinefrina', 'Dexametasona'],
            'diagnostico' => ['alergia', 'urticaria', 'prurito', 'anafilaxia', 'angioedema'],
        ],
        'oftalmico' => [
            'medicamento' => ['Cloranfenicol', 'Nafazolina', 'Neomicina', 'Polimixina', 'Bacitracina'],
            'diagnostico' => ['conjuntivitis', 'ojo rojo', 'cuerpo extraño', 'irritación ocular'],
        ],
        'musculoesqueletico' => [
            // SOLO específicos (ver DECISIÓN en el docblock): NO naproxeno/diclofenaco/ketorolaco orales.
            'medicamento' => ['Metocarbamol', 'Clorzoxazona', 'Diclofenaco gel', 'Naproxeno con lidocaína'],
            'diagnostico' => ['esguince', 'lumbalgia', 'contractura', 'contusión', 'tendinitis', 'cervicalgia'],
        ],
    ];

    public function run()
    {
        $note = 'Propuesta 2026-08-17 (botiquín real de producción); sin verificar por médico.';
        $upserted = 0;
        $retired  = 0;

        foreach (self::PROPOSAL as $group => $kinds) {
            foreach ($kinds as $kind => $terms) {
                // 1) Upsert de los términos vigentes de la propuesta (reactiva si estaban retirados).
                $keep = [];
                foreach ($terms as $display) {
                    $term = ClinicalTextNormalizer::normalize($display);
                    if ($term === '') {
                        continue;
                    }
                    $keep[] = $term;
                    IndicatorTerm::updateOrCreate(
                        ['group_key' => $group, 'match_kind' => $kind, 'term' => $term],
                        ['display_term' => $display, 'is_active' => 1, 'source_note' => $note]
                        // OJO: NO se toca is_clinician_verified aquí → si el médico ya lo puso en 1,
                        // re-sembrar no lo revierte (updateOrCreate solo escribe las claves dadas).
                    );
                    $upserted++;
                }

                // 2) RETIRO por diferencia: lo que ya no está en la propuesta (de este grupo/tipo)
                //    se marca is_active=0, SALVO lo que un médico ya verificó (se respeta su decisión).
                $retired += IndicatorTerm::where('group_key', $group)
                    ->where('match_kind', $kind)
                    ->where('is_active', 1)
                    ->where('is_clinician_verified', 0)
                    ->whereNotIn('term', $keep)
                    ->update(['is_active' => 0]);
            }
        }

        if ($this->command) {
            $this->command->info(
                "IndicatorTermSeeder: {$upserted} términos vigentes en ".count(self::PROPOSAL).
                " grupos (is_clinician_verified=0) · {$retired} retirados (is_active=0)."
            );
        }
    }
}
