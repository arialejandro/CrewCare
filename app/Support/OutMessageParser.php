<?php

namespace App\Support;

/**
 * OutMessageParser — LÓGICA PURA que lee un mensaje de salida tal como lo escriben en sus grupos:
 *
 *     Cámara 18:30 Juan Perez 21:00
 *
 * Departamento con su hora y, opcionalmente, uno o más nombres con su hora. NO depende de nada externo:
 * los catálogos (departamentos, crew) entran como ARRAYS, así el parser se prueba con puras cadenas.
 * El servicio de registro (App\Support\OutRegistrar) y la pantalla lo consumen igual; el webhook de
 * la capa Meta también.
 *
 * 🔑 ANTE LA DUDA NO ADIVINA: devuelve qué no entendió en `issues` y NO asigna. "Un out mal asignado
 * es peor que un out no registrado." Un departamento que no casa con confianza (exacto normalizado o
 * alias) → NO se asigna. Un nombre que no casa (o casa con varios) → se reporta y se omite ESA persona,
 * pero la salida del depto sigue si el depto y su hora sí se entendieron.
 *
 * @return array{
 *   ok: bool,                     // ¿se entendió una salida de DEPARTAMENTO asignable?
 *   department: ?array,           // ['id'=>int,'name'=>string,'matched_text'=>string]
 *   department_time: ?string,     // 'HH:MM'
 *   individuals: array,           // [['user_id'=>int,'name'=>string,'matched_text'=>string,'time'=>'HH:MM'], ...]
 *   issues: array,                // ['empty','no_time','unknown_department:...','bad_time:...','unknown_person:...','ambiguous_person:...']
 * }
 */
class OutMessageParser
{
    /**
     * @param string $text          el mensaje crudo
     * @param array  $departments   [ ['id'=>1,'name'=>'Cámara','aliases'=>['cam','camara']], ... ]
     * @param array  $crew          [ ['user_id'=>10,'name'=>'Juan Pérez','department_id'=>1], ... ]
     */
    public static function parse(string $text, array $departments = [], array $crew = []): array
    {
        $result = [
            'ok'              => false,
            'department'      => null,
            'department_time' => null,
            'individuals'     => [],
            'issues'          => [],
        ];

        $raw = trim($text);
        if ($raw === '') {
            $result['issues'][] = 'empty';

            return $result;
        }

        // Tokeniza por espacios en blanco. Cada token es palabra o "token de hora".
        $tokens = preg_split('/\s+/u', $raw);
        $tokens = array_values(array_filter($tokens, function ($t) {
            return $t !== '';
        }));

        // Marca cuáles tokens son HORA (y su valor normalizado, o false si es hora-imposible).
        $timeAt = [];   // idx => 'HH:MM' | false(hora imposible)
        foreach ($tokens as $i => $tok) {
            if (self::looksLikeTime($tok)) {
                $timeAt[$i] = self::normalizeTime($tok);   // 'HH:MM' o null si imposible
            }
        }

        if (empty($timeAt)) {
            // Nada que se parezca a una hora: no hay salida que registrar.
            $result['issues'][] = 'no_time';

            return $result;
        }

        // Segmenta: [palabras del depto][hora depto] ( [palabras persona][hora persona] )*
        // El primer token-hora cierra el segmento del departamento; cada hora siguiente cierra una persona.
        $segments = [];             // [ ['words'=>[...], 'timeIdx'=>int], ... ]
        $currentWords = [];
        foreach ($tokens as $i => $tok) {
            if (array_key_exists($i, $timeAt)) {
                $segments[] = ['words' => $currentWords, 'timeIdx' => $i];
                $currentWords = [];
            } else {
                $currentWords[] = $tok;
            }
        }
        // Palabras sobrantes tras la última hora (p.ej. un nombre sin hora) → se ignoran salvo aviso.
        $trailing = $currentWords;

        // --- Segmento 1: DEPARTAMENTO ---
        $deptSeg = array_shift($segments);
        $deptWords = $deptSeg['words'];
        $deptTimeVal = $timeAt[$deptSeg['timeIdx']];

        if (empty($deptWords)) {
            // Una hora sin nada delante ("18:30 ...") → no sabemos de qué depto.
            $result['issues'][] = 'unknown_department:';
        } else {
            $deptText = implode(' ', $deptWords);
            $dept = self::matchDepartment($deptText, $departments);
            if ($dept === null) {
                $result['issues'][] = 'unknown_department:' . $deptText;
            } elseif ($deptTimeVal === null) {
                $result['issues'][] = 'bad_time:' . $tokens[$deptSeg['timeIdx']];
            } else {
                $result['department'] = [
                    'id'           => $dept['id'],
                    'name'         => $dept['name'],
                    'matched_text' => $deptText,
                ];
                $result['department_time'] = $deptTimeVal;
                $result['ok'] = true;
            }
        }

        // Departamento resuelto (o null) para acotar la búsqueda de personas.
        $deptId = $result['department']['id'] ?? null;

        // --- Segmentos siguientes: PERSONAS ---
        foreach ($segments as $seg) {
            $nameWords = $seg['words'];
            $timeVal   = $timeAt[$seg['timeIdx']];
            if (empty($nameWords)) {
                continue;   // dos horas seguidas: nada que nombrar
            }
            $nameText = implode(' ', $nameWords);

            if ($timeVal === null) {
                $result['issues'][] = 'bad_time:' . $tokens[$seg['timeIdx']];
                continue;
            }

            $match = self::matchPerson($nameText, $crew, $deptId);
            if ($match === 'ambiguous') {
                $result['issues'][] = 'ambiguous_person:' . $nameText;
                continue;
            }
            if ($match === null) {
                $result['issues'][] = 'unknown_person:' . $nameText;
                continue;
            }

            $result['individuals'][] = [
                'user_id'      => $match['user_id'],
                'name'         => $match['name'],
                'matched_text' => $nameText,
                'time'         => $timeVal,
            ];
        }

        if (! empty($trailing)) {
            $result['issues'][] = 'dangling_text:' . implode(' ', $trailing);
        }

        return $result;
    }

    // ---------------------------------------------------------------------------------------
    // Horas
    // ---------------------------------------------------------------------------------------

    /** ¿El token TIENE FORMA de hora? (dígitos con ':' opcional). No valida el rango: eso lo hace normalizeTime. */
    public static function looksLikeTime(string $tok): bool
    {
        return (bool) preg_match('/^\d{1,2}:\d{1,2}$/', $tok) || (bool) preg_match('/^\d{3,4}$/', $tok);
    }

    /**
     * Normaliza una hora humana a 'HH:MM', o null si es IMPOSIBLE. Acepta:
     *   '18:30' · '9:30' · '9:5'(→09:05) · '1830'(→18:30) · '930'(→09:30) · '0905'(→09:05).
     * NO acepta la hora entera pelada ('9') a propósito: en estos mensajes un número suelto de 1-2
     * dígitos es más probable un dato basura que "las 9 en punto"; que lo escriban con ':' o 4 dígitos.
     *
     * @return string|null  'HH:MM' o null (imposible / no es hora)
     */
    public static function normalizeTime(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        $h = null;
        $m = null;

        if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $raw, $mm)) {
            $h = (int) $mm[1];
            // Un solo dígito de minuto = unidades (5 → 05), NO decenas (evita leer "9:5" como 9:50).
            $m = strlen($mm[2]) === 1 ? (int) $mm[2] : (int) $mm[2];
        } elseif (preg_match('/^(\d{3,4})$/', $raw, $mm)) {
            $digits = $mm[1];
            $mPart  = substr($digits, -2);
            $hPart  = substr($digits, 0, strlen($digits) - 2);
            $h = (int) $hPart;
            $m = (int) $mPart;
        } else {
            return null;
        }

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;   // hora imposible
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    // ---------------------------------------------------------------------------------------
    // Matching
    // ---------------------------------------------------------------------------------------

    /** Normaliza para comparar: minúsculas, sin acentos, espacios colapsados, sin puntuación de borde. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $from = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù','â','ê','î','ô','û'];
        $to   = ['a','e','i','o','u','u','n','a','e','i','o','u','a','e','i','o','u'];
        $s = str_replace($from, $to, $s);
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);   // fuera puntuación
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }

    /**
     * Casa un texto de departamento contra el catálogo. SOLO match de confianza: exacto normalizado o
     * un alias declarado. Nada de adivinar por subcadena/prefijo (un out mal asignado es peor). null si
     * no casa con confianza.
     *
     * @return array|null  el elemento del catálogo, o null
     */
    public static function matchDepartment(string $text, array $departments): ?array
    {
        $needle = self::norm($text);
        if ($needle === '') {
            return null;
        }
        foreach ($departments as $d) {
            if (self::norm($d['name']) === $needle) {
                return $d;
            }
            foreach (($d['aliases'] ?? []) as $alias) {
                if (self::norm($alias) === $needle) {
                    return $d;
                }
            }
        }

        return null;
    }

    /**
     * Casa un nombre contra el crew (acotado al depto si se conoce). Devuelve el elemento, o null (nadie),
     * o el string 'ambiguous' (varios) — que NO se asigna. Un nombre casa si su forma normalizada es
     * IGUAL o si todas las palabras del texto están contenidas en el nombre del crew (p.ej. "Juan Perez"
     * casa "Juan Carlos Pérez"). Se prioriza el crew del mismo depto.
     *
     * @return array|string|null
     */
    public static function matchPerson(string $text, array $crew, ?int $deptId)
    {
        $needle = self::norm($text);
        if ($needle === '') {
            return null;
        }
        $needleWords = explode(' ', $needle);

        $pools = [];
        if ($deptId !== null) {
            $pools[] = array_values(array_filter($crew, function ($c) use ($deptId) {
                return isset($c['department_id']) && (int) $c['department_id'] === (int) $deptId;
            }));
        }
        $pools[] = $crew;   // fallback: todo el crew

        foreach ($pools as $pool) {
            $hits = [];
            foreach ($pool as $c) {
                $name = self::norm($c['name']);
                if ($name === $needle) {
                    $hits[] = $c;

                    continue;
                }
                // todas las palabras del texto presentes en el nombre (subconjunto), y al menos 2
                // palabras para no casar por un solo apellido común.
                if (count($needleWords) >= 2) {
                    $nameWords = explode(' ', $name);
                    $all = true;
                    foreach ($needleWords as $w) {
                        if (! in_array($w, $nameWords, true)) {
                            $all = false;
                            break;
                        }
                    }
                    if ($all) {
                        $hits[] = $c;
                    }
                }
            }
            // dedup por user_id
            $byId = [];
            foreach ($hits as $h) {
                $byId[$h['user_id']] = $h;
            }
            $hits = array_values($byId);

            if (count($hits) === 1) {
                return $hits[0];
            }
            if (count($hits) > 1) {
                return 'ambiguous';
            }
            // 0 en este pool → probar el siguiente (todo el crew)
        }

        return null;
    }
}
