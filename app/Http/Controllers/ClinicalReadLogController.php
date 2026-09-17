<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ClinicalReadLogController — VISOR de la bitácora de lectura clínica. SOLO super-admin.
 *
 * Muestra el REGISTRO de quién abrió qué expediente clínico (metadatos), NO el expediente. El
 * expediente sigue visible para médico/HOD/line-producer/coordinador — eso no se toca aquí. La
 * bitácora existe para vigilar lecturas de NO clínicos, así que ni el médico ni el auditor la ven:
 * solo el super-admin. Sin export (por ahora).
 */
class ClinicalReadLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        // SOLO super-admin (403 para cualquier otro rol, auditor incluido). No es un permiso para
        // no engancharse al barrido `%.view` del auditor; es el rol super-admin explícito.
        abort_unless($request->user() && $request->user()->hasRole('super-admin'), 403);

        $reader  = trim((string) $request->query('reader', ''));
        $patient = trim((string) $request->query('patient', ''));
        $from    = $request->query('from');
        $to      = $request->query('to');

        $q = DB::table('clinical_read_logs as l')
            ->leftJoin('users as r', 'r.id', '=', 'l.reader_id')
            ->leftJoin('users as p', 'p.id', '=', 'l.patient_ref')
            ->select(
                'l.*',
                'r.name as reader_name', 'r.lname as reader_lname',
                'p.name as patient_name', 'p.lname as patient_lname'
            );

        if ($reader !== '') {
            $q->where(function ($w) use ($reader) {
                $w->where('r.name', 'like', "%{$reader}%")
                  ->orWhere('r.lname', 'like', "%{$reader}%")
                  ->orWhere('l.reader_id', $reader);
            });
        }
        if ($patient !== '') {
            $q->where(function ($w) use ($patient) {
                $w->where('p.name', 'like', "%{$patient}%")
                  ->orWhere('p.lname', 'like', "%{$patient}%")
                  ->orWhere('l.patient_ref', $patient);
            });
        }
        if ($from) {
            $q->whereDate('l.opened_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('l.opened_at', '<=', $to);
        }

        $logs = $q->orderByDesc('l.opened_at')->paginate(50)->withQueryString();

        return view('admin.clinical-log.index', [
            'logs'    => $logs,
            'filters' => compact('reader', 'patient', 'from', 'to'),
        ]);
    }
}
