<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogo — COLAPSA los departamentos vacíos duplicados en su PADRE (2026-08-28).
 *
 * La fusión dejó 4 deptos separados Y VACÍOS (el back mostraría un bloque vacío mientras sus puestos
 * viven bajo el hermano). Se colapsan: mueve lo que tengan (puestos + gente) al padre, hereda los
 * alias en el padre (para que el import siga resolviendo "Craft"→Catering), y DESACTIVA el hijo
 * (no se borra: su historia importa). Legal y Clearance, Foto Fija → se quedan solos (no se tocan).
 *
 * Idempotente: re-correr no duplica (updateOrInsert de alias, mover 0 filas, desactivar ya-inactivo).
 * DATOS (no esquema) → seeder, encadenado tras CatalogFusionSeeder. Empareja por NOMBRE (ids varían).
 */
class CatalogCollapseEmptyDeptsSeeder extends Seeder
{
    /** hijo => padre. */
    private const MAP = [
        'Casting de Extras' => 'Casting',
        'Craft Service'     => 'Alimentación',       // "Catering"
        'Continuidad'       => 'Asistentes de Dirección',
        'Servicios Médicos' => 'Salud y Seguridad',  // sólo cuando son médicos; la AMBULANCIA es PROVEEDOR
    ];

    public function run(): void
    {
        foreach (self::MAP as $childName => $parentName) {
            $child  = DB::table('departments')->where('name', $childName)->first();
            $parent = DB::table('departments')->where('name', $parentName)->first();
            if (! $child || ! $parent || (int) $child->id === (int) $parent->id) {
                continue;
            }

            // Mueve lo que tenga al padre (idempotente; hoy 0 puestos / 0 gente).
            DB::table('positions')->where('department_id', $child->id)->update(['department_id' => $parent->id]);
            DB::table('production_user')->where('department_id', $child->id)->update(['department_id' => $parent->id]);

            $this->inheritAliases($child, $parent);

            // Desactiva el hijo (NO se borra).
            DB::table('departments')->where('id', $child->id)->update(['active' => 0]);
        }
    }

    /** Los alias del hijo (+ su nombre) sobreviven en el PADRE; luego se retiran del hijo. */
    private function inheritAliases(object $child, object $parent): void
    {
        // El nombre del hijo, normalizado, como alias es/en del padre.
        $childNorm = Str::lower(Str::ascii($child->name));
        foreach (['es', 'en'] as $lang) {
            $this->ensureAlias($parent->id, $lang, $childNorm);
        }

        // Cada alias del hijo → al padre (si falta).
        foreach (DB::table('catalog_aliases')->where('entity_type', 'department')->where('entity_id', $child->id)->get() as $a) {
            $this->ensureAlias($parent->id, $a->lang, $a->alias);
        }

        // Retira los alias del hijo (ya redundantes; evita que un import resuelva a un depto inactivo).
        DB::table('catalog_aliases')->where('entity_type', 'department')->where('entity_id', $child->id)->delete();
    }

    private function ensureAlias(int $deptId, string $lang, string $alias): void
    {
        $exists = DB::table('catalog_aliases')
            ->where('entity_type', 'department')->where('entity_id', $deptId)
            ->where('lang', $lang)->where('alias', $alias)->exists();
        if (! $exists) {
            DB::table('catalog_aliases')->insert([
                'entity_type' => 'department', 'entity_id' => $deptId, 'lang' => $lang, 'alias' => $alias,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
