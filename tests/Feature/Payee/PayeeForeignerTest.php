<?php

namespace Tests\Feature\Payee;

use App\Support\PayeePackage;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/** VERIFICACIÓN del delta de EXTRANJERO: el paquete alterna por nacionalidad, sin missing permanente. */
class PayeeForeignerTest extends QaTestCase
{
    public function test_foreigner_gets_alternate_package(): void
    {
        $pid = DB::table('productions')->min('id');
        $mx = PayeePackage::identityRequirements($pid, 'fisica', 'mexicana')->pluck('code');
        $ex = PayeePackage::identityRequirements($pid, 'fisica', 'extranjera')->pluck('code');

        // El mexicano lleva INE/32-D; el extranjero NO.
        $this->assertTrue($mx->contains('INE'));
        $this->assertTrue($mx->contains('OPINION_32D'));
        $this->assertFalse($ex->contains('INE'), 'el extranjero no tiene INE');
        $this->assertFalse($ex->contains('OPINION_32D'), 'ni 32-D');
        $this->assertFalse($ex->contains('CSF'), 'ni CSF');

        // El extranjero lleva su paquete alterno.
        $this->assertTrue($ex->contains('DOC_PASAPORTE'));
        $this->assertTrue($ex->contains('DOC_RESIDENCIA_FISCAL'));
        $this->assertFalse($mx->contains('DOC_PASAPORTE'), 'el mexicano no');

        // Comparten los de nacionalidad NULL (ambas).
        $this->assertTrue($ex->contains('COMP_DOMICILIO'));
        $this->assertTrue($mx->contains('COMP_DOMICILIO'));

        // Ningún requerido del extranjero es mexicano-only → puede llegar al 100% sin missing eterno.
        foreach ($ex as $code) {
            $this->assertNotContains($code, ['INE', 'CSF', 'OPINION_32D']);
        }
    }
}
