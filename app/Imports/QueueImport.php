<?php

namespace App\Imports;

use App\Models\user;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class QueueImport extends Model implements ToModel, WithHeadingRow
{
    
    
    public function model(array $row)
    {
        return new user([
        'name'=> $row['nombre'],
        'lname'=> $row['apellido1'],
        'lname2'=> $row['apellido2'],
        'borndate'=> $row['nacimiento'],
        'sex'=> $row['sexo'],
        'phone'=> $row['telefono'],
        'email'=> $row['correo'],
        'zone'=> $row['zona'],
        'puestodepartamento'=> $row['puesto'],
        // 'age' (credencial impresa) ELIMINADO del mapeo: la impresión vive ahora en la tabla
        // badge_prints, no en users.age. El crew importado entra como "no impreso" (correcto).
        'password'=> Hash::make ($row['contrase']),
        'activo'=> $row['trabajando'],
        'imgperfil'=> $row['foto'],
        // COVID ELIMINADO del mapeo de import (enfermo/temperatura/inline/tested/resultpcr/labn) — desacople COVID (2026-06-25)
        // PASO A (2026-07-19): el valor 2 se DESCARTA al importar. Era el falso marcador de
        // "médico" (botón "Convertir a Médico", ya eliminado) y este import era su último
        // reinyector silencioso: un Excel con dprueba=2 volvía a marcar gente como médico
        // sin pasar por ningún permiso de UI, deshaciendo el saneamiento. Los grupos legacy
        // 0/1/3 se siguen importando tal cual. La identidad médica es el rol Spatie `medic`.
        'daytest'=> ((int) $row['dprueba'] === 2) ? null : $row['dprueba'], // grupo legacy

            //
        ]);
    }
}

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */