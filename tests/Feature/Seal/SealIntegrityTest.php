<?php

namespace Tests\Feature\Seal;

use App\Models\DailyReport;
use App\Models\hazardnotification;
use App\Models\InjuryReport;
use App\Models\IssuedPermit;
use App\Models\MedevacPoster;
use App\Models\ScoutingReport;
use App\Support\SealVerifier;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * QA — VERTICAL DE SELLOS DIGITALES (integridad a nivel modelo + SealVerifier::resolve).
 *
 * El corazón de confianza de la app: cada documento sellable calcula un SHA-256 de su
 * payload canónico (attributesToArray ksorteado, sin uuid/timestamps) y lo guarda en
 * digital_signatures. verifyLatestSignature() lo RECOMPUTA y compara con hash_equals.
 * SealVerifier::resolve(tipo, uuid) es lo que consume el verificador PÚBLICO.
 *
 * Se sella con los MISMOS métodos que usan los controladores (signDocument /
 * signDocumentAsSystem), tras refresh() — igual que InjuryReportController@store — para
 * que el hash firmado coincida con lo que releerá una carga fresca desde la BD.
 */
class SealIntegrityTest extends QaTestCase
{
    /** Crea un InjuryReport sellado por un usuario y devuelve [modelo, firmante]. */
    private function sellarInjury(array $attrs = []): InjuryReport
    {
        $user = $this->makeUser('safety-officer');
        $injury = InjuryReport::create(array_merge([
            'name'          => 'Juan Pérez',
            'phone'         => '5512345678',
            'body_part'     => 'Mano derecha',
            'what_happened' => 'Corte con herramienta',
            'hospital'      => 'Hospital Ángeles',
        ], $attrs));
        // Igual que el controlador: recarga el estado canónico (observers) antes de sellar.
        $injury->refresh();
        $injury->signDocument($user);

        return $injury;
    }

    // ------------------------------------------------------------------
    // 1) SELLADO REAL: un documento sellado existe y se verifica ÍNTEGRO.
    // ------------------------------------------------------------------

    public function test_injury_sellado_tiene_firma_y_verifica_integro(): void
    {
        $injury = $this->sellarInjury();

        $this->assertTrue($injury->signatures()->exists(), 'El injury sellado debe tener firma.');
        $this->assertNotEmpty($injury->uuid, 'El injury debe recibir uuid al crearse.');

        // Recarga FRESCA desde BD (como hace el verificador) y verifica.
        $fresh = InjuryReport::where('uuid', $injury->uuid)->first();
        $this->assertTrue($fresh->verifyLatestSignature(), 'Documento intacto debe dar VÁLIDO.');

        $acuse = SealVerifier::resolve('injury', $injury->uuid);
        $this->assertIsArray($acuse);
        $this->assertSame('ok', $acuse['verdict']);
        $this->assertSame('Reporte de accidente', $acuse['type_label']);
        $this->assertSame('INJ-' . str_pad((string) $injury->id, 4, '0', STR_PAD_LEFT), $acuse['folio']);
        $this->assertNotNull($acuse['sealed_at']);
    }

    // ------------------------------------------------------------------
    // 2) PRIVACIDAD: resolve() devuelve un DTO ACOTADO, jamás PII.
    // ------------------------------------------------------------------

    public function test_resolve_devuelve_dto_acotado_sin_pii(): void
    {
        $injury = $this->sellarInjury(['name' => 'Confidencial Nombre', 'phone' => '5599998888']);

        $acuse = SealVerifier::resolve('injury', $injury->uuid);

        // Exactamente las 9 claves declaradas: 5 de integridad + 4 de vigencia.
        $esperadas = ['type_label', 'folio', 'uuid', 'sealed_at', 'verdict',
                      'retired', 'retired_at', 'retired_label', 'superseded_folio'];
        sort($esperadas);
        $reales = array_keys($acuse);
        sort($reales);
        $this->assertSame($esperadas, $reales, 'El DTO no debe crecer con claves nuevas sin revisión.');

        // Ninguna PII del documento debe aparecer en el acuse serializado.
        $blob = json_encode($acuse, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Confidencial Nombre', $blob);
        $this->assertStringNotContainsString('5599998888', $blob);
        $this->assertStringNotContainsString('Hospital', $blob);
    }

    // ------------------------------------------------------------------
    // 3) ⭐ TEST ESTRELLA: alterar el documento en BD => ALTERADO.
    // ------------------------------------------------------------------

    public function test_alteracion_en_bd_de_atributo_sellado_se_detecta(): void
    {
        $injury = $this->sellarInjury(['name' => 'Nombre Original']);

        // Baseline: íntegro.
        $this->assertSame('ok', SealVerifier::resolve('injury', $injury->uuid)['verdict']);

        // Manipulación directa en BD de una columna QUE ENTRA al hash.
        DB::table('injury_reports')->where('id', $injury->id)->update(['name' => 'Nombre ALTERADO']);

        $fresh = InjuryReport::where('uuid', $injury->uuid)->first();
        $this->assertFalse(
            $fresh->verifyLatestSignature(),
            'FALLO DE INTEGRIDAD: una alteración del contenido NO fue detectada.'
        );

        $acuse = SealVerifier::resolve('injury', $injury->uuid);
        $this->assertSame(
            'altered',
            $acuse['verdict'],
            'FALLO DE INTEGRIDAD: el verificador reporta un documento alterado como válido.'
        );
    }

    public function test_alteracion_de_columna_de_texto_libre_tambien_se_detecta(): void
    {
        $injury = $this->sellarInjury(['what_happened' => 'Relato original de los hechos']);

        DB::table('injury_reports')->where('id', $injury->id)
            ->update(['what_happened' => 'Relato manipulado por un tercero']);

        $acuse = SealVerifier::resolve('injury', $injury->uuid);
        $this->assertSame('altered', $acuse['verdict']);
    }

    // ------------------------------------------------------------------
    // 4) DOCUMENTO SIN SELLO => estado 'unsealed' (ni ok ni altered).
    // ------------------------------------------------------------------

    public function test_documento_sin_sello_reporta_unsealed(): void
    {
        // Creado pero NUNCA firmado.
        $injury = InjuryReport::create(['name' => 'Sin sellar', 'what_happened' => 'x']);

        $this->assertFalse($injury->signatures()->exists());
        $this->assertNull($injury->verifyLatestSignature());

        $acuse = SealVerifier::resolve('injury', $injury->uuid);
        $this->assertSame('unsealed', $acuse['verdict']);
    }

    // ------------------------------------------------------------------
    // 5) UUID inexistente / tipo inválido => null (no truena).
    // ------------------------------------------------------------------

    public function test_uuid_inexistente_resuelve_null(): void
    {
        $this->assertNull(SealVerifier::resolve('injury', '00000000-0000-0000-0000-000000000000'));
    }

    public function test_tipo_invalido_resuelve_null(): void
    {
        $this->assertNull(SealVerifier::resolve('noexiste', '00000000-0000-0000-0000-000000000000'));
    }

    // ------------------------------------------------------------------
    // 6) COBERTURA MULTI-TIPO: el `tipo` enruta al modelo correcto.
    // ------------------------------------------------------------------

    public function test_scout_sella_verifica_y_detecta_alteracion(): void
    {
        $user = $this->makeUser('safety-officer');
        $scout = ScoutingReport::create([
            'location_name'    => 'Bodega Norte',
            'location_address' => 'Calle 1',
            'production_name'  => 'Demo Film',
        ]);
        $scout->refresh();
        $scout->signDocument($user);

        $acuse = SealVerifier::resolve('scout', $scout->uuid);
        $this->assertSame('ok', $acuse['verdict']);
        $this->assertSame('Scouting de locación', $acuse['type_label']);

        DB::table('scouting_reports')->where('id', $scout->id)
            ->update(['location_name' => 'Locación FALSA']);

        $this->assertSame('altered', SealVerifier::resolve('scout', $scout->uuid)['verdict']);
    }

    public function test_dsr_sellado_como_sistema_verifica_y_detecta_alteracion(): void
    {
        $dsr = DailyReport::create([
            'report_date'       => '2026-08-01',
            'location_name'     => 'Set A',
            'executive_summary' => 'Jornada sin incidentes',
        ]);
        $dsr->refresh();
        // El DSR se auto-sella COMO SISTEMA (user_id NULL) al cerrarse el día.
        $dsr->signDocumentAsSystem('sistema:cierre-24h');

        $acuse = SealVerifier::resolve('dsr', $dsr->uuid);
        $this->assertSame('ok', $acuse['verdict']);
        $this->assertSame('Reporte diario de seguridad', $acuse['type_label']);

        DB::table('daily_reports')->where('id', $dsr->id)
            ->update(['executive_summary' => 'Resumen reescrito']);

        $this->assertSame('altered', SealVerifier::resolve('dsr', $dsr->uuid)['verdict']);
    }

    public function test_medevac_sellado_verifica_por_folio_propio(): void
    {
        $user = $this->makeUser('safety-officer');
        $mdvc = MedevacPoster::create([
            'location_label' => 'Locación 3',
            'issued_by_name' => 'Safety',
            'payload'        => ['hospital' => 'X'],
        ]);
        $mdvc->refresh();
        $mdvc->signDocument($user);

        $acuse = SealVerifier::resolve('mdvc', $mdvc->uuid);
        $this->assertSame('ok', $acuse['verdict']);
        // El modelo tiene folio() propio: el acuse debe respetarlo (MDVC-####).
        $this->assertSame($mdvc->folio(), $acuse['folio']);

        DB::table('medevac_posters')->where('id', $mdvc->id)
            ->update(['location_label' => 'Otra locación']);
        $this->assertSame('altered', SealVerifier::resolve('mdvc', $mdvc->uuid)['verdict']);
    }

    // ------------------------------------------------------------------
    // 7) TRES ESTADOS: retiro (cierre) NO se lee como alteración.
    //    Prueba de paso el signatureExcludes (estado posterior al sello).
    // ------------------------------------------------------------------

    public function test_permiso_cerrado_sigue_integro_y_marca_vigencia(): void
    {
        $user = $this->makeUser('safety-officer');
        $permit = IssuedPermit::create([
            'permit_name'          => 'Trabajo en caliente',
            'activity_description' => 'Soldadura de estructura',
            'issuer_name'          => 'Safety Officer',
            'acceptor_name'        => 'Ejecutante Designado',
        ]);
        $permit->refresh();
        $permit->signDocument($user);

        $this->assertSame('ok', SealVerifier::resolve('perm', $permit->uuid)['verdict']);

        // CERRAR es cambio de ESTADO (columnas hash-excluidas), no de contenido.
        DB::table('issued_permits')->where('id', $permit->id)
            ->update(['closed_at' => now(), 'is_active' => 0, 'close_notes' => 'Fin de jornada']);

        $acuse = SealVerifier::resolve('perm', $permit->uuid);
        // El sello SIGUE íntegro: cerrar no altera.
        $this->assertSame('ok', $acuse['verdict'], 'Cerrar un permiso NO debe leerse como ALTERADO.');
        $this->assertTrue($acuse['retired'], 'Un permiso cerrado debe marcarse como no vigente.');
        $this->assertSame('Cerrado', $acuse['retired_label']);
    }

    // ------------------------------------------------------------------
    // 8) ROBUSTEZ: un documento con JSON + decimales, INTACTO, NO debe
    //    dar falso positivo 'altered' (round-trip de casts BD <-> modelo).
    //    Si esto fallara, docs reales de prod aparecerían "alterados".
    // ------------------------------------------------------------------

    public function test_json_y_decimales_intactos_no_dan_falso_positivo(): void
    {
        $user = $this->makeUser('safety-officer');
        $injury = InjuryReport::create([
            'name'                => 'Con estructuras',
            'what_happened'       => 'y',
            'injury_type'         => ['laceracion', 'contusion'],
            'root_cause_analysis' => ['factor' => 'humano', 'detalle' => ['a' => 1, 'b' => 2]],
            'ppe_details'         => ['casco' => true, 'guantes' => false],
            'latitude'            => 19.4326077,
            'longitude'           => -99.1332080,
            'hours_worked_prior'  => 8.5,
        ]);
        $injury->refresh();
        $injury->signDocument($user);

        // Carga fresca desde BD, sin tocar nada.
        $fresh = InjuryReport::where('uuid', $injury->uuid)->first();
        $this->assertTrue(
            $fresh->verifyLatestSignature(),
            'FALSO POSITIVO: un documento intacto con JSON/decimales se reporta ALTERADO.'
        );
        $this->assertSame('ok', SealVerifier::resolve('injury', $injury->uuid)['verdict']);
    }

    public function test_alteracion_de_columna_sellada_del_permiso_si_se_detecta(): void
    {
        $user = $this->makeUser('safety-officer');
        $permit = IssuedPermit::create([
            'permit_name'          => 'Trabajo en altura',
            'activity_description' => 'Montaje en andamio',
            'issuer_name'          => 'Safety',
            'acceptor_name'        => 'Ejecutante',
        ]);
        $permit->refresh();
        $permit->signDocument($user);

        // activity_description SÍ entra al hash → alterarla debe detectarse.
        DB::table('issued_permits')->where('id', $permit->id)
            ->update(['activity_description' => 'Actividad distinta a la autorizada']);

        $this->assertSame('altered', SealVerifier::resolve('perm', $permit->uuid)['verdict']);
    }

    // ------------------------------------------------------------------
    // 9) TIPO 'haz' + override NULLABLE_HASH_EXCLUDES + "bomba" de coerción
    //    int (consequence tinyint). Confirma detección en la matriz 5×5 y
    //    que un campo nullable-excluido, al pasar de null a valor, se detecta.
    // ------------------------------------------------------------------

    public function test_haz_con_matriz_y_json_intacto_y_alteracion_de_consecuencia(): void
    {
        $user = $this->makeUser('safety-officer');
        $haz = hazardnotification::create([
            'production_name'               => 'Demo',
            'description_hazard_unsafe_act' => 'Cable expuesto',
            'likelihood'                    => 'C',
            'consequence'                   => 3, // tinyint — histórica "bomba" de coerción int
            'human_factor'                  => ['prisa', 'fatiga'],
        ]);
        $haz->refresh();
        $haz->signDocument($user);

        // Intacto (con matriz int + JSON) → sin falso positivo.
        $this->assertSame('ok', SealVerifier::resolve('haz', $haz->uuid)['verdict']);

        // Alterar la CONSECUENCIA (eje de la matriz 5×5) debe detectarse.
        DB::table('hazardnotifications')->where('id', $haz->id)->update(['consequence' => 5]);
        $this->assertSame('altered', SealVerifier::resolve('haz', $haz->uuid)['verdict']);
    }

    public function test_haz_columna_nullable_excluida_al_pasar_de_null_a_valor_se_detecta(): void
    {
        $user = $this->makeUser('safety-officer');
        // involved_user_id NULL al sellar → excluido del hash (NULLABLE_HASH_EXCLUDES).
        $haz = hazardnotification::create([
            'production_name'               => 'Demo',
            'description_hazard_unsafe_act' => 'Piso mojado sin señal',
        ]);
        $haz->refresh();
        $haz->signDocument($user);
        $this->assertSame('ok', SealVerifier::resolve('haz', $haz->uuid)['verdict']);

        // Un tercero le INYECTA un involucrado (null -> valor): ahora SÍ entra al hash → alterado.
        DB::table('hazardnotifications')->where('id', $haz->id)->update(['involved_user_id' => 7]);
        $this->assertSame(
            'altered',
            SealVerifier::resolve('haz', $haz->uuid)['verdict'],
            'Inyectar un valor en una columna nullable-excluida debe detectarse como alteración.'
        );
    }
}
