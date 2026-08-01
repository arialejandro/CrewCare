<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC foundation — additive. REUSABLE position catalog.
 *
 * FLEXIBILITY REQUIREMENT (PROGRESS.md "REQUISITO FIRME — NADA preestablecido por
 * produccion"): a position is NOT hardcoded to one production's org chart.
 *   - production_id = NULL  -> GLOBAL catalog template (seeded from ORG-TAXONOMY.md),
 *                              reusable by any production.
 *   - production_id = <id>  -> CUSTOM position specific to that production
 *                              (e.g. a "Set PA" that exists in one production but not
 *                              another). Each production adapts the template + adds its own.
 *
 * is_hod mirrors the [HOD] markers from ORG-TAXONOMY.md §2.
 */
class CreatePositionsTable extends Migration
{
    public function up()
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            // NULL = global catalog template; non-NULL = position custom to a production.
            $table->foreignId('production_id')->nullable()->constrained('productions')->cascadeOnDelete();
            $table->boolean('is_hod')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            // A given position name is unique within a department + production scope
            // (NULL production_id groups the global catalog).
            $table->unique(['department_id', 'production_id', 'name'], 'positions_dept_prod_name_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('positions');
    }
}
