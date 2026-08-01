<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC foundation — additive / parallel (strangler).
 *
 * Reusable GLOBAL department catalog. Seeded from ORG-TAXONOMY.md as a TEMPLATE
 * (production_id = NULL on positions). Does NOT touch the legacy `departamentos`
 * table — that stays intact for the old code.
 */
class CreateDepartmentsTable extends Migration
{
    public function up()
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // Optional taxonomy metadata from ORG-TAXONOMY.md (Canales de Radio).
            $table->string('radio_channel', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('departments');
    }
}
