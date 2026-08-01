<?php
namespace App\Http\Controllers;

use App\Exports\AntigenTestExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\View;

class ExportController extends Controller
{
    public function export()
    {
        return Excel::download(new AntigenTestExport(), 'antigentest.xlsx');
    }
}




