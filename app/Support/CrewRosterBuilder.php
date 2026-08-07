<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CrewRosterBuilder — arma el "Crew List" AGRUPADO por departamento para el documento de
 * export (admin/crew-export). SOLO LECTURA.
 *
 * DOS EJES INDEPENDIENTES (no se mezclan):
 *   · FORMATO  → lo pone la vista (documento vertical, bandas, columnas).
 *   · ORDEN    → lo pone ESTA clase, y es JERÁRQUICO, no alfabético:
 *       - Departamentos por el `sort_order` del catálogo `departments` (ya poblado con el orden
 *         canónico del llamado). Un depto que NO cae en ese orden (etiqueta legacy libre que no
 *         empata con ningún nombre del catálogo) se manda al FINAL y se REPORTA (no se acomoda a
 *         mano).
 *       - Dentro de cada depto, los puestos por el `sort_order` de `positions` (jerárquico:
 *         HOD primero). Sin puesto resoluble → al final del grupo, desempate por apellido.
 *
 * Un departamento SIN gente no aparece (solo se crean grupos para usuarios presentes).
 * Respeta el scope por departamento del visor (mismo helper que los listados de crew): quien
 * ve algo lo sigue viendo, ni más ni menos.
 */
class CrewRosterBuilder
{
    /**
     * @param  \App\Models\User  $viewer
     * @return array{groups: array, unordered: array, total: int, production: ?string}
     */
    public static function build(User $viewer): array
    {
        $productionId = CurrentProduction::id();
        $production   = CurrentProduction::get();

        // --- Catálogos (mapas en memoria; departments ~38 filas, positions ~230) ---
        $deptSortById = [];   // id  => sort_order
        $deptNameById = [];   // id  => name
        $deptSortByName = []; // name => sort_order (para empatar etiquetas legacy por nombre exacto)
        foreach (DB::table('departments')->get(['id', 'name', 'sort_order']) as $d) {
            $deptSortById[$d->id] = $d->sort_order;
            $deptNameById[$d->id] = $d->name;
            if (!array_key_exists($d->name, $deptSortByName)) {
                $deptSortByName[$d->name] = $d->sort_order;
            }
        }

        $posSortById = []; // id => sort_order
        $posNameById = []; // id => name
        foreach (DB::table('positions')->get(['id', 'name', 'sort_order']) as $p) {
            $posSortById[$p->id] = $p->sort_order;
            $posNameById[$p->id] = $p->name;
        }

        // --- Pivote de la producción vigente: user_id => {department_id, position_id} ---
        $pivot = [];
        if ($productionId) {
            foreach (DB::table('production_user')
                        ->where('production_id', $productionId)
                        ->get(['user_id', 'department_id', 'position_id']) as $r) {
                $pivot[$r->user_id] = $r;
            }
        }

        // --- Crew activo, ACOTADO por el scope del visor (misma regla que usuarioscrud) ---
        $query = User::applyDepartmentScope(DB::table('users')->where('activo', 1), $viewer);
        $users = $query->get(['id', 'name', 'lname', 'lname2', 'email', 'phone', 'zone', 'puestodepartamento']);

        $groups = []; // deptKey => ['label', 'sort', 'people'=>[]]
        foreach ($users as $u) {
            $pv = $pivot[$u->id] ?? null;

            // Departamento: nombre canónico del catálogo (por FK del pivote) o etiqueta legacy `zone`.
            if ($pv && $pv->department_id && isset($deptNameById[$pv->department_id])) {
                $deptName = $deptNameById[$pv->department_id];
                $deptSort = $deptSortById[$pv->department_id];        // catálogo => siempre en orden
            } else {
                $deptName = ($u->zone !== null && $u->zone !== '') ? $u->zone : null;
                // Solo cae en orden si la etiqueta empata EXACTO con un nombre del catálogo;
                // si no, sort = null => va al final y se reporta (no se acomoda a mano).
                $deptSort = ($deptName !== null && isset($deptSortByName[$deptName]))
                    ? $deptSortByName[$deptName] : null;
            }
            $deptKey   = $deptName === null ? '__none__' : $deptName;
            $deptLabel = $deptName === null ? 'Sin departamento' : $deptName;

            // Puesto (Cargo): nombre del catálogo (por FK) o etiqueta legacy `puestodepartamento`.
            $posName = null;
            $posSort = null;
            if ($pv && $pv->position_id && isset($posNameById[$pv->position_id])) {
                $posName = $posNameById[$pv->position_id];
                $posSort = $posSortById[$pv->position_id] ?? null;
            } elseif ($u->puestodepartamento !== null && $u->puestodepartamento !== '') {
                $posName = $u->puestodepartamento;
            }

            $fullName = trim(preg_replace('/\s+/', ' ',
                ($u->name ?? '') . ' ' . ($u->lname ?? '') . ' ' . ($u->lname2 ?? '')));

            $person = [
                'cargo'    => $posName ?? '',
                'name'     => $fullName,
                'email'    => $u->email ?? '',
                'phone'    => $u->phone ?? '',
                '_posSort' => $posSort,
                '_lname'   => mb_strtolower(trim(($u->lname ?? '') . ' ' . ($u->name ?? ''))),
            ];

            if (!isset($groups[$deptKey])) {
                $groups[$deptKey] = ['label' => $deptLabel, 'sort' => $deptSort, 'people' => []];
            }
            $groups[$deptKey]['people'][] = $person;
        }

        // --- Ordena los grupos: los canónicos por sort_order; los sin orden (sort null) al final. ---
        $canonical = [];
        $trailing  = [];
        foreach ($groups as $key => $g) {
            if ($g['sort'] === null) {
                $trailing[$key] = $g;
            } else {
                $canonical[$key] = $g;
            }
        }
        uasort($canonical, function ($a, $b) {
            return [$a['sort'], $a['label']] <=> [$b['sort'], $b['label']];
        });
        uasort($trailing, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        $ordered = $canonical + $trailing;

        // --- Ordena las personas dentro de cada grupo: puesto (sort_order), luego apellido. ---
        foreach ($ordered as &$g) {
            usort($g['people'], function ($a, $b) {
                $sa = $a['_posSort'] === null ? PHP_INT_MAX : $a['_posSort'];
                $sb = $b['_posSort'] === null ? PHP_INT_MAX : $b['_posSort'];
                if ($sa !== $sb) {
                    return $sa <=> $sb;
                }
                return strcmp($a['_lname'], $b['_lname']);
            });
        }
        unset($g);

        // Departamentos que NO cayeron en el orden canónico (para reportar en la UI/documento).
        $unordered = array_values(array_map(function ($g) {
            return ['label' => $g['label'], 'count' => count($g['people'])];
        }, $trailing));

        return [
            'groups'     => array_values($ordered),
            'unordered'  => $unordered,
            'total'      => $users->count(),
            'production' => $production ? ($production->name ?? null) : null,
        ];
    }
}
