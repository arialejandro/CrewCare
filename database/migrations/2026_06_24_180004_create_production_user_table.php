<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC foundation — additive. The heart of the design (AUTH-RBAC-PLAN.md B.2.3):
 * per-production membership + per-production role.
 *
 * Spatie team mode is DISABLED (config/permission.php 'teams' => false), so the
 * contextual "role in THIS production" is stored in the `role` column here. The
 * global spatie role (model_has_roles) answers "what can this person do in general";
 * `production_user.role` answers "what is this person in this specific project"
 * (HOD here, crew there) without creating new global roles. Scoping ("a user only
 * sees their production") is later enforced by a Policy/scope that combines the
 * spatie permission + membership in this pivot.
 */
class CreateProductionUserTable extends Migration
{
    public function up()
    {
        Schema::create('production_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            // Contextual RBAC role in THIS production (mirrors a spatie role slug).
            $table->string('role', 40)->default('crew');
            $table->boolean('is_lead')->default(false);
            $table->timestamps();

            $table->unique(['production_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('production_user');
    }
}
