<?php

namespace Tests\Feature\Deliveries;

use App\Models\CallPackage;
use App\Models\Department;
use App\Models\FileDelivery;
use App\Models\FileDeliveryRecipient;
use App\Models\Payee;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\FileDeliveryDispatcher;
use App\Support\Features;
use App\Support\PdfWatermarker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\QaTestCase;

/**
 * ENVÍO DE ARCHIVOS CON MARCA DE AGUA — motor de marca de agua, outbox/despachador, envío del llamado
 * (crew llamado) y módulo de distribución (todos los activos). Correo `array` en tests → no hay SMTP.
 */
class FileDeliveryTest extends QaTestCase
{
    private int $prodId;
    private string $ds = '2026-08-22';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prodId = (int) DB::table('productions')->min('id');
        DB::table('productions')->where('id', $this->prodId)->update(['active' => 1]);
        CurrentProduction::forget();
    }

    /** PDF real de $pages páginas con texto (para probar que la marca conserva el contenido). */
    private function tinyPdf(int $pages = 2): string
    {
        $pdf = new Fpdi('P', 'pt', [612, 1008]);
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 18);
            $pdf->SetXY(50, 60);
            $pdf->Cell(0, 20, 'Contenido base pagina ' . $i, 0, 1);
        }

        return $pdf->Output('S');
    }

    // ------------------------------------------------------------------ motor de marca de agua

    public function test_marca_de_agua_conserva_paginas_y_agrega_tinta(): void
    {
        $base   = $this->tinyPdf(2);
        $marked = PdfWatermarker::diagonal($base, 'Juan Pérez Ñandú');

        $this->assertStringStartsWith('%PDF', $marked);
        $this->assertGreaterThan(strlen($base), strlen($marked), 'la marca debe agregar contenido');

        // Sigue teniendo 2 páginas (FPDI conserva el original).
        $tmp = tempnam(sys_get_temp_dir(), 'wm');
        file_put_contents($tmp, $marked);
        $this->assertSame(2, (new Fpdi())->setSourceFile($tmp));
        @unlink($tmp);
    }

    public function test_marca_de_agua_texto_vacio_devuelve_original(): void
    {
        $base = $this->tinyPdf(1);
        $this->assertSame($base, PdfWatermarker::diagonal($base, '   '));
    }

    public function test_nombre_de_marca_creditos_o_primer_nombre_primer_apellido(): void
    {
        // Con crédito → se usa tal cual.
        $conCred = $this->makeUser('crew', ['name' => 'Mariana', 'lname' => 'Arismendi Castro', 'ncreditos' => 'Mar Arismendi']);
        $this->assertSame('Mar Arismendi', User::creditShortName($conCred));

        // Sin crédito → primer nombre + PRIMER apellido (no el apellido completo).
        $sinCred = $this->makeUser('crew', ['name' => 'Mariana Isabel', 'lname' => 'Arismendi Castro', 'ncreditos' => '']);
        $this->assertSame('Mariana Arismendi', User::creditShortName($sinCred));
    }

    // ------------------------------------------------------------------ despachador (outbox)

    public function test_despachador_marca_envia_y_marca_como_enviado(): void
    {
        $base = 'deliveries/base-test.pdf';
        Storage::disk('local')->put($base, $this->tinyPdf(1));

        $delivery = FileDelivery::create([
            'production_id' => $this->prodId, 'source_type' => FileDelivery::SOURCE_MANUAL,
            'title' => 'Aviso', 'base_path' => $base, 'base_name' => 'aviso.pdf', 'watermark' => true,
        ]);
        foreach (['Ana Uno', 'Beto Dos'] as $i => $name) {
            FileDeliveryRecipient::create([
                'file_delivery_id' => $delivery->id, 'name' => $name,
                'email' => 'r' . $i . '@qa.test', 'watermark_text' => $name,
            ]);
        }

        $res = FileDeliveryDispatcher::drain(10);

        $this->assertSame(2, $res['sent']);
        $this->assertSame(0, $res['failed']);
        $this->assertSame(2, $delivery->recipients()->where('status', 'sent')->count());
        $this->assertNotNull($delivery->recipients()->first()->sent_at);
    }

    public function test_despachador_reintenta_y_falla_si_no_hay_base(): void
    {
        $delivery = FileDelivery::create([
            'production_id' => $this->prodId, 'source_type' => FileDelivery::SOURCE_MANUAL,
            'title' => 'X', 'base_path' => 'deliveries/no-existe.pdf', 'base_name' => 'x.pdf', 'watermark' => true,
        ]);
        $r = FileDeliveryRecipient::create([
            'file_delivery_id' => $delivery->id, 'name' => 'Sin Base', 'email' => 'a@qa.test',
        ]);

        // 3 intentos (cada corrida bump attempts; retrocedo last_attempt_at para saltar el TTL de reclamo).
        for ($i = 0; $i < FileDeliveryRecipient::MAX_ATTEMPTS; $i++) {
            FileDeliveryDispatcher::drain(10);
            $r->refresh();
            if ($r->status !== FileDeliveryRecipient::FAILED) {
                $r->update(['last_attempt_at' => now()->subMinutes(3)]);
            }
        }

        $this->assertSame(FileDeliveryRecipient::FAILED, $r->status);
        $this->assertSame(FileDeliveryRecipient::MAX_ATTEMPTS, $r->attempts);
        $this->assertNotNull($r->error);
    }

    // ------------------------------------------------------------------ envío del llamado

    public function test_envio_del_llamado_encola_con_nombre_en_creditos(): void
    {
        $admin = $this->actingAsRole('coordinator');
        $crew  = $this->makeCalledCrew('Arte', 'Cred Itado');

        // Paquete APROBADO con un congelado real.
        $frozen = 'call-packages/' . $this->prodId . '/' . $this->ds . '-aprobado.pdf';
        Storage::disk('local')->put($frozen, $this->tinyPdf(2));
        CallPackage::create([
            'production_id' => $this->prodId, 'call_date' => $this->ds,
            'status' => CallPackage::APPROVED, 'approved_at' => now(),
            'frozen_path' => $frozen, 'frozen_schedule' => [],
        ]);

        $this->post(route('callsheet.package.send.crew', ['date' => $this->ds]))->assertRedirect();

        $delivery = FileDelivery::where('source_type', FileDelivery::SOURCE_CALL_PACKAGE)->first();
        $this->assertNotNull($delivery);
        $this->assertSame($frozen, $delivery->base_path);

        $rec = $delivery->recipients()->where('user_id', $crew->id)->first();
        $this->assertNotNull($rec);
        $this->assertSame(User::creditShortName($crew), $rec->watermark_text);
        // La ráfaga inline ya lo mandó (correo array).
        $this->assertSame('sent', $rec->status);
    }

    // ------------------------------------------------------------------ distribución general

    public function test_distribucion_envia_a_activos_y_excluye_desactivados(): void
    {
        $this->actingAsRole('super-admin');
        $activo = $this->makeUser('crew', ['name' => 'Activo Uno', 'ncreditos' => 'Activo Uno', 'activo' => 1]);
        $baja   = $this->makeUser('crew', ['name' => 'De Baja', 'ncreditos' => 'De Baja', 'activo' => 0]);

        $file = UploadedFile::fake()->createWithContent('memo.pdf', $this->tinyPdf(1));
        $this->post(route('deliveries.store'), [
            'title' => 'Aviso general', 'body' => 'Hola equipo', 'document' => $file, 'watermark' => '1',
        ])->assertRedirect();

        $delivery = FileDelivery::where('source_type', FileDelivery::SOURCE_MANUAL)->latest('id')->first();
        $this->assertNotNull($delivery);
        $emails = $delivery->recipients()->pluck('email')->all();
        $this->assertContains($activo->email, $emails);
        $this->assertNotContains($baja->email, $emails, 'un desactivado NO debe recibir');
    }

    // ------------------------------------------------------------------ PDF adicional (flag)

    public function test_pdf_adicional_404_sin_flag(): void
    {
        $this->actingAsRole('coordinator');
        Features::flush();
        DB::table('feature_flags')->updateOrInsert(['key' => 'callsheet_extra_docs'], ['enabled' => 0, 'updated_at' => now()]);
        Features::flush();

        $file = UploadedFile::fake()->createWithContent('extra.pdf', $this->tinyPdf(1));
        $this->post(route('callsheet.package.extra', ['date' => $this->ds]), ['extra' => $file])->assertNotFound();
    }

    public function test_pdf_adicional_se_agrega_con_flag(): void
    {
        $this->actingAsRole('coordinator');
        DB::table('feature_flags')->updateOrInsert(['key' => 'callsheet_extra_docs'], ['enabled' => 1, 'updated_at' => now()]);
        Features::flush();

        $this->get(route('callsheet.package', ['date' => $this->ds]));   // crea el paquete
        $file = UploadedFile::fake()->createWithContent('extra.pdf', $this->tinyPdf(1));
        $this->post(route('callsheet.package.extra', ['date' => $this->ds]), ['extra' => $file])->assertRedirect();

        $pkg = CallPackage::where('production_id', $this->prodId)->where('call_date', $this->ds)->first();
        $this->assertCount(1, $pkg->extra_docs);
        $this->assertSame('extra.pdf', $pkg->extra_docs[0]['name']);

        Features::flush();
    }

    /** Crew LLAMADO ese día (con email + ncreditos), para que aparezca en calledCrewRecipients. */
    private function makeCalledCrew(string $dept, string $name): User
    {
        $u   = $this->makeUser('crew', ['name' => $name, 'lname' => 'QA', 'ncreditos' => $name]);
        $did = (int) Department::where('name', $dept)->value('id');
        DB::table('production_user')->updateOrInsert(
            ['production_id' => $this->prodId, 'user_id' => $u->id],
            ['department_id' => $did, 'role' => 'crew', 'is_lead' => 0, 'created_at' => now(), 'updated_at' => now()]
        );
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => $u->name, 'user_id' => $u->id]);
        $c = $payee->contracts()->create([
            'production_id' => $this->prodId, 'concept' => 'crew_work',
            'department_id' => $did, 'contracted_by_user_id' => $u->id,
            'payment_frequency' => 'weekly', 'is_active' => 1,
        ]);
        DB::table('payee_contract_work_dates')->insert(['payee_contract_id' => $c->id, 'work_date' => $this->ds]);
        DB::table('contract_envelopes')->insert([
            'payee_contract_id' => $c->id, 'production_id' => $this->prodId,
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $u;
    }
}
