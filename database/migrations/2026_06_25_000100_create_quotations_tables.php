<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COTIZACIÓN — módulo nuevo (aditivo). Espeja el owner-apply 2026-08-17-quotations.sql
 * (la BD real se toca con ESE, no con migrate). Aquí solo para que la suite (RefreshDatabase)
 * tenga las tablas. La cotización nace ANTES del payee → no cuelga de payees.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('production_id');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('location_name', 191)->nullable();
                $table->string('emitter_name', 191);
                $table->string('emitter_email', 191)->nullable();
                $table->string('status', 20)->default('recibida');
                $table->unsignedBigInteger('current_version_id')->nullable();
                $table->unsignedBigInteger('payee_id')->nullable();
                $table->unsignedBigInteger('accepted_by_user_id')->nullable();
                $table->dateTime('accepted_at')->nullable();
                $table->unsignedBigInteger('accepted_version_id')->nullable();
                $table->string('accepted_doc_hash', 191)->nullable();
                $table->mediumText('acceptance_signature_image')->nullable();
                $table->string('acceptance_sheet_path', 255)->nullable();
                $table->char('uuid', 36)->nullable();
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->timestamps();
                $table->index('production_id', 'quotations_production_idx');
                $table->index('department_id', 'quotations_department_idx');
                $table->index('payee_id', 'quotations_payee_idx');
                $table->index('status', 'quotations_status_idx');
            });
        }

        if (! Schema::hasTable('quotation_versions')) {
            Schema::create('quotation_versions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('quotation_id');
                $table->integer('version_no')->default(1);
                $table->unsignedBigInteger('supersedes_id')->nullable();
                $table->string('source_kind', 8)->default('items');
                $table->string('pdf_path', 255)->nullable();
                $table->string('pdf_original_name', 191)->nullable();
                $table->char('pdf_sha256', 64)->nullable();
                $table->string('quotation_number', 60)->nullable();
                $table->date('issued_at')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('payment_terms', 500)->nullable();
                $table->string('bank_details', 500)->nullable();
                $table->boolean('iva_included')->default(false);
                $table->decimal('iva_rate', 5, 2)->default(16.00);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('iva_amount', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->string('change_note', 500)->nullable();
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->timestamps();
                $table->index('quotation_id', 'quotation_versions_quotation_idx');
                $table->index('supersedes_id', 'quotation_versions_supersedes_idx');
            });
        }

        if (! Schema::hasTable('quotation_items')) {
            Schema::create('quotation_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('quotation_version_id');
                $table->integer('sort_order')->default(0);
                $table->string('description', 255);
                $table->text('detail')->nullable();
                $table->decimal('quantity', 12, 2)->default(1);
                $table->decimal('days', 12, 2)->nullable();
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
                $table->index('quotation_version_id', 'quotation_items_version_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotation_versions');
        Schema::dropIfExists('quotations');
    }
};
