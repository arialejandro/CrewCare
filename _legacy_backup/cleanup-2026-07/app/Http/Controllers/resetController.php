<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User;
// Imports muertos eliminados (2026-06-26): departamento/puesto/userpuesto/usuariosnotificacione/Hash;
// y tras quitar newdayRep/newWR/PhotoReminder también Request/DB/Mail — el único método vivo (expCsv) usa solo User.

class resetController extends Controller
{

    // --- newdayRep / newWR ELIMINADOS (2026-06-26) — eran DUPLICADOS GET-sin-auth del comando
    //     programado `encuestas:task` (App\Console\Commands\encuestasTask, Kernel diario 04:00),
    //     que ya resetea `encuestadiaria=0` de los activos + envía el recordatorio "DAILY REPORT".
    //     El reset diario lo hace el cron (`schedule:run`), no una URL pública.
    // --- newTD / CleanR / Nresult (resets de cola PCR, COVID) ELIMINADOS — Lote 3b COVID-DECOMMISSION (2026-06-25) ---


    // --- PhotoReminder ELIMINADO (2026-06-26) — recordatorio de FOTO DE GAFETE (enviaba correos.photo a
    //     usuarios con age=0). Era rústico (1er loop no-op), GET-sin-auth y sin disparador en la UI. A
    //     PETICIÓN DEL OWNER se REIMPLEMENTARÁ "más práctico": comando programado `badge:reminder`
    //     (mismo patrón que `encuestas:task`). Pendiente en PROGRESS.md. El template correos/photo.blade.php
    //     se CONSERVA para esa reimplementación.

    public function expCsv()
    {
       
        $nowr = User::where('activo','=', 1)->get();
        $fileName = 'nophoto.csv';
        $headers = array(
            "Content-Encoding"    => "UTF-8",
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('NOMBRE', 'APELLIDO', 'APELLIDO2', 'F.NAC', 'SEXO', 'TELEFONO', 'EMAIL', 'PUESTO');

        $callback = function() use($nowr, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($nowr as $nowr) {
                $row['NOMBRE']  = $nowr->name;
                $row['APELLIDO']    = $nowr->lname;
                $row['APELLIDO2']    = $nowr->lname2;
                $row['F.NAC']    = $nowr->borndate;
                $row['SEXO']    = $nowr->sex;
                $row['TELEFONO']    = $nowr->phone;
                $row['EMAIL']    = $nowr->email;
                $row['PUESTO']    = $nowr->puestodepartamento;
                    

                fputcsv($file, array($row['NOMBRE'], $row['APELLIDO'], $row['APELLIDO2'], $row['F.NAC'], $row['SEXO'], $row['TELEFONO'], $row['EMAIL'], $row['PUESTO']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

}