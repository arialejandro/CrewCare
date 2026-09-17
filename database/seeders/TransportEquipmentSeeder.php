<?php

namespace Database\Seeders;

use App\Models\TransportEquipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * TransportEquipmentSeeder — set inicial del catálogo de equipamiento de corridas (Bloque 2 §2).
 *
 * El equipamiento sale como ÍCONO en la orden. Editable por transpo: esto sólo siembra un
 * arranque razonable. Idempotente (updateOrCreate por `code`); NUNCA pisa `is_active` (si el
 * owner desactivó uno a mano, se respeta). Las corridas guardan estos `code` en su JSON.
 */
class TransportEquipmentSeeder extends Seeder
{
    /** code, name_es, name_en, icon (clave de SVG que resuelve la vista). */
    private const ITEMS = [
        ['tag',       'Tag / etiqueta',      'Tag',            'tag'],
        ['hielera',   'Hielera',             'Cooler',         'cooler'],
        ['agua',      'Agua',                'Water',          'droplet'],
        ['radio',     'Radio',               'Radio',          'signal'],
        ['gps',       'GPS',                 'GPS',            'map-pin'],
        ['rampa',     'Rampa',               'Ramp',           'ramp'],
        ['cadenas',   'Cadenas / amarres',   'Chains',         'link'],
        ['carrito',   'Carrito / diablito',  'Hand truck',     'cart'],
        ['sillas',    'Sillas',              'Chairs',         'chair'],
        ['sombrilla', 'Sombrilla / carpa',   'Umbrella',       'umbrella'],
        ['botiquin',  'Botiquín',            'First-aid kit',  'first-aid'],
        ['extintor',  'Extintor',            'Extinguisher',   'fire'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('transport_equipment')) {
            throw new \RuntimeException('Falta la tabla `transport_equipment`. Aplica primero database/owner-apply/2026-08-24-transport-order.sql');
        }

        $n = 0;
        foreach (self::ITEMS as $i => [$code, $es, $en, $icon]) {
            TransportEquipment::updateOrCreate(
                ['code' => $code],
                [
                    'name_es'    => $es,
                    'name_en'    => $en,
                    'icon'       => $icon,
                    'sort_order' => $i,
                    // is_active NO se toca.
                ]
            );
            $n++;
        }

        $this->command->info("TransportEquipmentSeeder: {$n} equipamientos (idempotente por code; is_active intacto).");
    }
}
