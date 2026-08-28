<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CATÁLOGO ORGANIZACIONAL — fusión semilla+vivo, ESQUEMA (delta #114).
 * Gemelo de database/owner-apply/2026-08-27-catalog-fusion-schema.sql.
 *
 * ADITIVO: agrega a `positions` y `departments` las columnas declaradas por la semilla, y crea
 * dos tablas hijas (alias es/en + alias ambiguos con regla). NO renumera ni toca ids vivos.
 * La SIEMBRA de las 255 filas va por CatalogFusionSeeder, no aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (! Schema::hasColumn('positions', 'catalog_key')) {
                $table->string('catalog_key', 120)->nullable()->after('name_en');
            }
            if (! Schema::hasColumn('positions', 'rank')) {
                $table->smallInteger('rank')->default(60)->after('catalog_key');   // 10 jefatura … 60 operativo
            }
            if (! Schema::hasColumn('positions', 'binding')) {
                $table->string('binding', 16)->default('unit')->after('rank');     // production | block | unit
            }
            if (! Schema::hasColumn('positions', 'hod_capable')) {
                $table->boolean('hod_capable')->default(false)->after('binding');
            }
            if (! Schema::hasColumn('positions', 'grade')) {
                $table->string('grade', 20)->nullable()->after('hod_capable');     // manager … operator
            }
            if (! Schema::hasColumn('positions', 'existence')) {
                $table->string('existence', 16)->default('core')->after('grade');  // core | conditional | jurisdictional
            }
        });
        if (! $this->indexExists('positions', 'positions_catalog_key_idx')) {
            Schema::table('positions', fn (Blueprint $t) => $t->index('catalog_key', 'positions_catalog_key_idx'));
        }

        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'catalog_key')) {
                $table->string('catalog_key', 120)->nullable()->after('name_en');
            }
            if (! Schema::hasColumn('departments', 'existence')) {
                $table->string('existence', 16)->default('core')->after('catalog_key');
            }
            if (! Schema::hasColumn('departments', 'account_hint')) {
                $table->string('account_hint', 20)->nullable()->after('existence');
            }
        });
        if (! $this->indexExists('departments', 'departments_catalog_key_idx')) {
            Schema::table('departments', fn (Blueprint $t) => $t->index('catalog_key', 'departments_catalog_key_idx'));
        }

        if (! Schema::hasTable('catalog_aliases')) {
            Schema::create('catalog_aliases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('entity_type', 16);   // 'position' | 'department'
                $table->unsignedBigInteger('entity_id');
                $table->string('lang', 2);            // 'es' | 'en'
                $table->string('alias', 160);
                $table->timestamps();
                $table->unique(['entity_type', 'entity_id', 'lang', 'alias'], 'catalog_aliases_unique');
                $table->index('entity_type', 'catalog_aliases_type_idx');
                $table->index(['entity_type', 'entity_id'], 'catalog_aliases_entity_idx');
            });
        }

        if (! Schema::hasTable('catalog_ambiguous_aliases')) {
            Schema::create('catalog_ambiguous_aliases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('alias', 80);
                $table->string('rule', 32);           // 'require_department' | 'grade_prefix'
                $table->string('note', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique('alias', 'catalog_ambiguous_alias_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_ambiguous_aliases');
        Schema::dropIfExists('catalog_aliases');
        Schema::table('positions', function (Blueprint $table) {
            foreach (['catalog_key', 'rank', 'binding', 'hod_capable', 'grade', 'existence'] as $c) {
                if (Schema::hasColumn('positions', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('departments', function (Blueprint $table) {
            foreach (['catalog_key', 'existence', 'account_hint'] as $c) {
                if (Schema::hasColumn('departments', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return count(\DB::select(
            "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?",
            [$table, $index]
        )) > 0;
    }
};
