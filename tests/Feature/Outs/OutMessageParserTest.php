<?php

namespace Tests\Feature\Outs;

use App\Support\OutMessageParser;
use Tests\TestCase;

/**
 * El parser es LÓGICA PURA (§5): se prueba con puras cadenas y catálogos como arrays, sin BD.
 * Cubre los casos que pidió el owner, incluido el más importante: ANTE LA DUDA NO ADIVINA.
 */
class OutMessageParserTest extends TestCase
{
    private function departments(): array
    {
        return [
            ['id' => 1, 'name' => 'Cámara', 'aliases' => ['cam']],
            ['id' => 2, 'name' => 'Arte', 'aliases' => []],
            ['id' => 3, 'name' => 'Dirección de Fotografía', 'aliases' => []],
        ];
    }

    private function crew(): array
    {
        return [
            ['user_id' => 10, 'name' => 'Juan Pérez', 'department_id' => 1],
            ['user_id' => 11, 'name' => 'María López', 'department_id' => 1],
            ['user_id' => 12, 'name' => 'Ana Ruiz', 'department_id' => 2],
        ];
    }

    /** @test */
    public function mensaje_bien_escrito(): void
    {
        $r = OutMessageParser::parse('Cámara 18:30 Juan Perez 21:00', $this->departments(), $this->crew());

        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['department']['id']);
        $this->assertSame('18:30', $r['department_time']);
        $this->assertCount(1, $r['individuals']);
        $this->assertSame(10, $r['individuals'][0]['user_id']);
        $this->assertSame('21:00', $r['individuals'][0]['time']);
        $this->assertEmpty($r['issues']);
    }

    /** @test */
    public function hora_sin_ceros_y_compacta(): void
    {
        $this->assertSame('09:05', OutMessageParser::normalizeTime('9:5'));
        $this->assertSame('09:30', OutMessageParser::normalizeTime('930'));
        $this->assertSame('18:30', OutMessageParser::normalizeTime('1830'));
        $this->assertSame('09:05', OutMessageParser::normalizeTime('0905'));

        $r = OutMessageParser::parse('Cámara 930', $this->departments(), $this->crew());
        $this->assertTrue($r['ok']);
        $this->assertSame('09:30', $r['department_time']);
    }

    /** @test */
    public function con_acento_y_sin_acento_casan_igual(): void
    {
        $r = OutMessageParser::parse('Camara 18:30', $this->departments(), $this->crew());
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['department']['id']);
    }

    /** @test */
    public function departamento_por_alias(): void
    {
        $r = OutMessageParser::parse('cam 18:30', $this->departments(), $this->crew());
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['department']['id']);
    }

    /** @test */
    public function varias_personas_en_un_mensaje(): void
    {
        $r = OutMessageParser::parse('Cámara 18:30 Juan Perez 21:00 Maria Lopez 22:15', $this->departments(), $this->crew());
        $this->assertTrue($r['ok']);
        $this->assertCount(2, $r['individuals']);
        $this->assertSame(10, $r['individuals'][0]['user_id']);
        $this->assertSame(11, $r['individuals'][1]['user_id']);
        $this->assertSame('22:15', $r['individuals'][1]['time']);
    }

    /** @test */
    public function nombre_que_no_existe_se_reporta_pero_el_depto_sigue(): void
    {
        $r = OutMessageParser::parse('Cámara 18:30 Pedro Páramo 21:00', $this->departments(), $this->crew());
        $this->assertTrue($r['ok']);                       // el depto sí se entendió
        $this->assertSame(1, $r['department']['id']);
        $this->assertEmpty($r['individuals']);             // el nombre no se asignó
        $this->assertContains('unknown_person:Pedro Páramo', $r['issues']);
    }

    /** @test */
    public function departamento_que_no_existe_no_se_asigna(): void
    {
        $r = OutMessageParser::parse('Utilería 18:30', $this->departments(), $this->crew());
        $this->assertFalse($r['ok']);
        $this->assertNull($r['department']);
        $this->assertContains('unknown_department:Utilería', $r['issues']);
    }

    /** @test */
    public function hora_imposible(): void
    {
        $this->assertNull(OutMessageParser::normalizeTime('25:99'));

        $r = OutMessageParser::parse('Cámara 25:99', $this->departments(), $this->crew());
        $this->assertFalse($r['ok']);
        $this->assertContains('bad_time:25:99', $r['issues']);
    }

    /** @test */
    public function mensaje_que_no_tiene_nada_que_ver(): void
    {
        $r = OutMessageParser::parse('hola qué tal como van', $this->departments(), $this->crew());
        $this->assertFalse($r['ok']);
        $this->assertContains('no_time', $r['issues']);
        $this->assertEmpty($r['individuals']);
    }

    /** @test */
    public function mensaje_vacio(): void
    {
        $r = OutMessageParser::parse('   ', $this->departments(), $this->crew());
        $this->assertFalse($r['ok']);
        $this->assertContains('empty', $r['issues']);
    }

    /** @test */
    public function nombre_ambiguo_no_se_adivina(): void
    {
        $crew = [
            ['user_id' => 10, 'name' => 'Juan Pérez', 'department_id' => 1],
            ['user_id' => 99, 'name' => 'Juan Pérez', 'department_id' => 1],   // homónimo en el mismo depto
        ];
        $r = OutMessageParser::parse('Cámara 18:30 Juan Perez 21:00', $this->departments(), $crew);
        $this->assertTrue($r['ok']);
        $this->assertEmpty($r['individuals']);
        $this->assertContains('ambiguous_person:Juan Perez', $r['issues']);
    }
}
