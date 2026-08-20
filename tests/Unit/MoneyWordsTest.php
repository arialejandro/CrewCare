<?php

namespace Tests\Unit;

use App\Support\MoneyWords;
use PHPUnit\Framework\TestCase;

class MoneyWordsTest extends TestCase
{
    public function test_pesos_en_letras(): void
    {
        $this->assertSame('SETECIENTOS VEINTE MIL PESOS 00/100 M.N.', MoneyWords::pesos(720000));
        $this->assertSame('MIL QUINIENTOS PESOS 50/100 M.N.', MoneyWords::pesos(1500.50));
        $this->assertSame('CIEN PESOS 00/100 M.N.', MoneyWords::pesos(100));
        $this->assertSame('CERO PESOS 00/100 M.N.', MoneyWords::pesos(0));
    }

    public function test_moneda_dolar(): void
    {
        $this->assertStringContainsString('DÓLARES', MoneyWords::pesos(50, 'USD'));
    }
}
