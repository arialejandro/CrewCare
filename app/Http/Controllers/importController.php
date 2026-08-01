<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Imports\QueueImport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class importController extends Controller
{
    public function importcrew()
    {
        return view ('imports/import');
           
    }
    public function store(Request $request)
    {
        // SEGURIDAD (2026-07-06): validar el archivo antes de importarlo.
        $request->validate(['import_file' => 'required|file|mimes:xlsx,xls,csv']);
        $file = $request->file('import_file');
        Excel::import(new QueueImport, $file );
        return redirect()->route('adduser')->with('succes', 'Crew Members imported');
    }
}




