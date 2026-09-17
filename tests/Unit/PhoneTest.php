<?php

namespace Tests\Unit;

use App\Support\Phone;
use Tests\TestCase;

/**
 * El normalizador de teléfono para wa.me: sin código de país, WhatsApp lee la lada
 * local como país (55 = Brasil) y abre un chat equivocado. Un local de 10 dígitos
 * debe recibir +52; lo que ya trae código de país se respeta.
 */
class PhoneTest extends TestCase
{
    public function test_local_10_digits_gets_mexico_country_code(): void
    {
        $this->assertSame('525512345678', Phone::whatsapp('5512345678'));
    }

    public function test_strips_formatting_before_normalizing(): void
    {
        $this->assertSame('525512345678', Phone::whatsapp('(55) 1234-5678'));
    }

    public function test_number_with_country_code_is_left_intact(): void
    {
        // Ya trae +52 → 12 dígitos, no se le antepone otra vez.
        $this->assertSame('525512345678', Phone::whatsapp('+52 55 1234 5678'));
    }

    public function test_international_prefix_00_is_stripped(): void
    {
        $this->assertSame('525512345678', Phone::whatsapp('00 52 55 1234 5678'));
    }

    public function test_foreign_number_with_its_own_code_is_respected(): void
    {
        // US: 1 + 10 dígitos = 11 dígitos → no es un local de 10, se respeta tal cual.
        $this->assertSame('14155550123', Phone::whatsapp('+1 415 555 0123'));
    }

    public function test_country_code_is_configurable_for_non_mexico(): void
    {
        // Instalación de otro país: la lada país se configura, no se hardcodea.
        // Un local de 10 dígitos con lada país '1' (EUA/Canadá) → +1.
        $this->assertSame('14155550123', Phone::whatsapp('4155550123', '1'));
    }

    public function test_default_country_code_follows_config(): void
    {
        config(['services.whatsapp.country_code' => '34']); // España
        $this->assertSame('346112233440', Phone::whatsapp('6112233440')); // local de 10 dígitos → +34
    }

    public function test_empty_or_junk_returns_empty(): void
    {
        $this->assertSame('', Phone::whatsapp(null));
        $this->assertSame('', Phone::whatsapp(''));
        $this->assertSame('', Phone::whatsapp('sin teléfono'));
    }
}
