<?php

namespace Tests\Feature\Contabilidad;

use App\Models\DocumentType;
use App\Models\ExternalAuthorization;
use App\Models\Payee;
use App\Support\CfdiParser;
use Tests\TestCase;

/**
 * CFDI · caso CONOCIDO (calibrado con un CFDI real, versión 4.0). Congela los valores para que un
 * cambio futuro en el parser o en el armado de las URLs NO lo rompa en silencio. Sin BD: parser puro
 * + modelos en memoria.
 */
class CfdiTest extends TestCase
{
    private function sampleXml(): string
    {
        return file_get_contents(base_path('tests/fixtures/cfdi-sample.xml'));
    }

    public function test_parser_extrae_los_valores_conocidos(): void
    {
        $c = CfdiParser::parse($this->sampleXml());

        $this->assertSame('A1B2C3D4-E5F6-7890-ABCD-EF1234567890', $c['uuid']);   // UUID del TimbreFiscalDigital
        $this->assertSame('XAXX010101000', $c['rfc_emisor']);
        $this->assertSame('EKU9003173C9', $c['rfc_receptor']);
        $this->assertSame('1160.00', $c['total']);              // VERBATIM (dos decimales, sin miles/moneda)
        $this->assertStringEndsWith('==', $c['sello']);         // relleno base64 → va tal cual
        $this->assertCount(4, $c['conceptos']);                 // TODOS los conceptos, no el primero
    }

    public function test_conceptos_y_semanas_cubiertas(): void
    {
        $c = CfdiParser::parse($this->sampleXml());

        $this->assertSame(['SEM', 'SEM', 'CA', 'CA'], array_column($c['conceptos'], 'prefix'));

        $weeks = array_values(array_unique(array_filter(array_column($c['conceptos'], 'week'))));
        sort($weeks);
        $this->assertSame(['2026-09-06', '2026-09-13'], $weeks);  // una factura, DOS semanas
    }

    public function test_url_factura_arma_fe_con_iguales_sin_encode(): void
    {
        $c   = CfdiParser::parse($this->sampleXml());
        $doc = new ExternalAuthorization([
            'cfdi_uuid' => $c['uuid'], 'cfdi_rfc_emisor' => $c['rfc_emisor'], 'cfdi_rfc_receptor' => $c['rfc_receptor'],
            'cfdi_total' => $c['total'], 'cfdi_sello' => $c['sello'],
        ]);

        $url = $doc->satFacturaUrl();
        $this->assertStringContainsString('id=A1B2C3D4-E5F6-7890-ABCD-EF1234567890', $url);
        $this->assertStringContainsString('tt=1160.00', $url);
        $this->assertStringContainsString('fe=wXyZ12==', $url);      // últimos 8 del sello, TAL CUAL
        $this->assertStringNotContainsString('%3D', $url);           // NO url-encode de los iguales
    }

    public function test_detecta_orden_de_fecha_por_factura(): void
    {
        // Productora gringa (MM-DD-YY): 021724 es INEQUÍVOCO (mes 17 imposible en dd-mm) → toda la
        // factura es mdy. Calibrado contra un CFDI real ("THE GRINGO HUNTER"): sus semanas eran
        // 02/10/24 y 02/17/24, no 10 de febrero y "mes 17".
        $this->assertSame('mdy', CfdiParser::detectDateOrder(['021024', '021724']));
        $this->assertSame('2024-02-10', CfdiParser::digitsToWeek('021024', 'mdy'));
        $this->assertSame('2024-02-17', CfdiParser::digitsToWeek('021724', 'mdy'));

        // México (DD-MM-YY) y el caso ambiguo (ambas mitades ≤12) que cae al default México.
        $this->assertSame('dmy', CfdiParser::detectDateOrder(['130926', '060926']));
        $this->assertSame('dmy', CfdiParser::detectDateOrder(['010224', '030424']));
        $this->assertSame('2026-09-13', CfdiParser::digitsToWeek('130926', 'dmy'));
        $this->assertNull(CfdiParser::digitsToWeek('021724', 'dmy'));   // mes 17 inválido en dmy
    }

    public function test_fecha_en_palabras_toma_el_fin_del_rango(): void
    {
        // Factura real (HTLR): "DEL 29 DE JUNIO AL 04 DE JULIO DEL 2026" → fin del rango = 04-jul-2026.
        $this->assertSame('2026-07-04', CfdiParser::spelledWeek('DEL 29 DE JUNIO AL 04 DE JULIO DEL 2026'));
        $this->assertSame('2026-07-04', CfdiParser::spelledWeek('honorarios 04 de julio de 2026'));
        $this->assertSame('2026-09-13', CfdiParser::spelledWeek('semana del 7 al 13 de septiembre de 2026'));
        $this->assertNull(CfdiParser::spelledWeek('SEM021724 sin fecha en palabras'));
    }

    public function test_prefijo_por_catalogo_en_cualquier_posicion(): void
    {
        $codes = ['SEM', 'CA', 'BOX', 'HTLR'];
        $this->assertSame('SEM', CfdiParser::splitConcepto('SEM021724 SUPERVISOR', $codes)['prefix']);   // inicio, pegado
        $this->assertSame('HTLR', CfdiParser::splitConcepto('HTLR HONORARIOS HEALTH AND SAFETY', $codes)['prefix']);
        $this->assertSame('CA', CfdiParser::splitConcepto('Renta CA de vehiculo personal', $codes)['prefix']); // en medio
        $this->assertSame('XYZ', CfdiParser::splitConcepto('XYZ algo aún no en el catálogo', [])['prefix']);   // fallback token inicial
    }

    public function test_url_32d_usa_d1_1(): void
    {
        $doc = new ExternalAuthorization(['sat_folio' => 'ABC12345', 'result_status' => 'positiva', 'issued_at' => '2026-09-06']);
        $doc->setRelation('holder', new Payee(['rfc' => 'XAXX010101000']));
        $doc->setRelation('documentType', new DocumentType(['code' => 'OPINION_32D']));

        $url = $doc->sat32dUrl();
        $this->assertStringContainsString('D1=1&D2=1', $url);
        $this->assertStringContainsString('D3=ABC12345_XAXX010101000_06-09-2026_P', $url);
    }
}
