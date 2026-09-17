<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPFX · imagen principal del TIPO de efecto (referencia visual de la card del catálogo).
 * Espejo de la migración para migrate:fresh / crewcare_test; en prod se aplica el
 * owner-apply 2026-08-17-sfx-effect-image.sql. Aditiva, nullable, degrade-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sfx_effect_types') && ! Schema::hasColumn('sfx_effect_types', 'image_path')) {
            Schema::table('sfx_effect_types', function (Blueprint $table) {
                $table->string('image_path', 255)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sfx_effect_types', 'image_path')) {
            Schema::table('sfx_effect_types', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }
};
