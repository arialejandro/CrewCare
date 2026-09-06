<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * XML DE LA FACTURA (CFDI) — campos ADITIVOS. La factura SIEMPRE es PDF + XML (el PDF es solo la
 * "representación impresa"; el XML es la factura). Del XML se extraen, con SimpleXML, los datos que
 * arman el enlace de verificación del SAT SIN teclear:
 *   external_authorizations: cfdi_uuid (folio fiscal), cfdi_rfc_emisor, cfdi_rfc_receptor,
 *   cfdi_total (VERBATIM del XML, el verificador es quisquilloso con decimales), cfdi_sello, xml_path.
 *   document_types.expects_cfdi_xml: marca los tipos que SON factura (aceptan/parsean XML).
 *
 * NADA obligatorio: una factura vieja subida sin XML queda como está (cfdi_* NULL) y simplemente no se
 * le puede armar el enlace. Guardas hasColumn → idempotente.
 */
class AddCfdiFields extends Migration
{
    public function up()
    {
        if (Schema::hasTable('external_authorizations')) {
            Schema::table('external_authorizations', function (Blueprint $t) {
                if (! Schema::hasColumn('external_authorizations', 'cfdi_uuid'))         { $t->string('cfdi_uuid', 40)->nullable()->after('folio'); }
                if (! Schema::hasColumn('external_authorizations', 'cfdi_rfc_emisor'))   { $t->string('cfdi_rfc_emisor', 20)->nullable()->after('cfdi_uuid'); }
                if (! Schema::hasColumn('external_authorizations', 'cfdi_rfc_receptor')) { $t->string('cfdi_rfc_receptor', 20)->nullable()->after('cfdi_rfc_emisor'); }
                if (! Schema::hasColumn('external_authorizations', 'cfdi_total'))        { $t->string('cfdi_total', 30)->nullable()->after('cfdi_rfc_receptor'); }
                if (! Schema::hasColumn('external_authorizations', 'cfdi_sello'))        { $t->text('cfdi_sello')->nullable()->after('cfdi_total'); }
                if (! Schema::hasColumn('external_authorizations', 'xml_path'))          { $t->string('xml_path', 255)->nullable()->after('cfdi_sello'); }
            });
        }

        if (Schema::hasTable('document_types') && ! Schema::hasColumn('document_types', 'expects_cfdi_xml')) {
            Schema::table('document_types', function (Blueprint $t) {
                $t->boolean('expects_cfdi_xml')->default(false);
            });
        }
    }

    public function down()
    {
        foreach (['cfdi_uuid', 'cfdi_rfc_emisor', 'cfdi_rfc_receptor', 'cfdi_total', 'cfdi_sello', 'xml_path'] as $col) {
            if (Schema::hasColumn('external_authorizations', $col)) {
                Schema::table('external_authorizations', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
        if (Schema::hasColumn('document_types', 'expects_cfdi_xml')) {
            Schema::table('document_types', fn (Blueprint $t) => $t->dropColumn('expects_cfdi_xml'));
        }
    }
}
