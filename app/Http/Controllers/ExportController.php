<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;

/**
 * ExportController — export CSV del crew activo (antes resetController@expCsv → /nophoto).
 *
 * Dos correcciones al mover (2026-07-06):
 *   (a) SCOPE por departamento: se aplica User::applyDepartmentScope() (mismo criterio que
 *       los listados de crew) para NO volcar PII de TODO el crew. El super-admin (y roles con
 *       `crew.view.all-departments`) sigue viendo a todos; los roles acotados solo su(s) depto(s).
 *   (b) FIX del shadowing de la variable del loop (`foreach ($nowr as $nowr)` → `$u`).
 * Las columnas y el formato de salida quedan idénticos.
 */
class ExportController extends Controller
{
    public function expCsv()
    {
        // Scope por departamento: reutiliza la misma regla que las listas de crew.
        $nowr = User::applyDepartmentScope(User::where('activo', '=', 1), auth()->user())->get();
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

            foreach ($nowr as $u) {
                $row['NOMBRE']  = $u->name;
                $row['APELLIDO']    = $u->lname;
                $row['APELLIDO2']    = $u->lname2;
                $row['F.NAC']    = $u->borndate;
                $row['SEXO']    = $u->sex;
                $row['TELEFONO']    = $u->phone;
                $row['EMAIL']    = $u->email;
                $row['PUESTO']    = $u->puestodepartamento;


                fputcsv($file, array($row['NOMBRE'], $row['APELLIDO'], $row['APELLIDO2'], $row['F.NAC'], $row['SEXO'], $row['TELEFONO'], $row['EMAIL'], $row['PUESTO']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
