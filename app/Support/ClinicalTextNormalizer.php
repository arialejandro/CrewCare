<?php

namespace App\Support;

/**
 * ClinicalTextNormalizer — normaliza texto clínico SOLO PARA COMPARAR (delta #45).
 *
 * Lo que se MUESTRA nunca cambia: esto es una función pura que produce una forma canónica
 * (minúsculas, sin acentos, sin espacios dobles, sin puntuación) para que "Paracetamol",
 * "paracetamol" y "  paracetamol " se resuelvan a la MISMA entrada, y para cotejar el nombre
 * del medicamento y el texto del diagnóstico contra los términos indicadores.
 *
 * NO hay catálogo cerrado: esto solo compara cadenas. La fuente de verdad (lo tecleado) queda
 * intacta en la consulta sellada.
 */
class ClinicalTextNormalizer
{
    /** Forma canónica para comparar. Vacío si la entrada es vacía. */
    public static function normalize(?string $s): string
    {
        $s = (string) $s;
        $s = mb_strtolower($s, 'UTF-8');
        // Acentos y ñ → base ASCII (comparación insensible a acentos).
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        ]);
        // Cualquier cosa que no sea letra/dígito → espacio; colapsa espacios; recorta.
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        return $s;
    }

    /**
     * ¿El término (ya normalizado) aparece como PALABRA(S) completas dentro del texto normalizado?
     * Frontera de palabra para no clasificar "gastos" como "tos" ni "escara" como "cara".
     */
    public static function containsTerm(string $haystackNorm, string $termNorm): bool
    {
        if ($termNorm === '' || $haystackNorm === '') {
            return false;
        }
        return (bool) preg_match('/\b' . preg_quote($termNorm, '/') . '\b/', $haystackNorm);
    }
}
