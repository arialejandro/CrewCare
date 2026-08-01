<?php
namespace App\Http\Controllers;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\message;
use Carbon\Carbon;
use App\Models\queuevirtual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Mail;


class queueController extends Controller
{
    public function curdtest()
    {
        //$diatest = DB::table('testqueue')->where('mainday')->first();
        $diatest = DB::table('testqueue')->find(1);
        switch ($diatest->mainday) {
            case ("3"):
                        $usuarios = DB::table('users')->where('activo','=', 1)->where('daytest','=',3)->where('inline','=',0)->where('tested','=',0)->orderBy('users.id', 'desc')->paginate(50);
                         return view ('virtualqueue/crudtest', compact('usuarios'));
                    break;
            case ("2"):
                        $usuarios = DB::table('users')->where('activo','=', 1)->where('daytest','=',2)->where('inline','=',0)->where('tested','=',0)->orderBy('users.id', 'desc')->paginate(50);
                        return view ('virtualqueue/crudtest', compact('usuarios'));
                    break;
            case ("4"):
                        $usuarios = DB::table('users')->where('activo','=',1)->where('inline','=',0)->where('tested','=',0)->orderBy('users.id', 'desc')->paginate(50);
                        return view ('virtualqueue/crudtest', compact('usuarios'));
                    break;
            default:
                        $usuarios = DB::table('users')->where('activo','=',1)->where('inline','=',0)->where('tested','=',0)->orderBy('users.id', 'desc')->paginate(50);
                        return view ('virtualqueue/crudtest', compact('usuarios'));
         }
    }

public function searchqueue($valor)
    {
        $diatest = DB::table('testqueue')->find(1);
       switch ($diatest->mainday) {
            
            case ("3"):            
                if ($valor === "vacio") {
                    $usuarios = DB::table('users')->where('activo','=',1)->where('daytest','=',3)->where('inline','=',0)->where('tested','=',0)
                    ->orderBy('users.id', 'desc')->paginate(50);

                }else{
                    $usuarios = DB::table('users')
                    ->where('users.name','LIKE','%' . $valor . '%')
                    ->Orwhere('users.lname','LIKE','%' . $valor . '%')
                    ->Orwhere('users.labn','LIKE','%' . $valor . '%')
                    ->orderBy('id', 'desc')
                    ->paginate(50); 
                }

                return view("componentes.searchqueue",compact('usuarios'));
                break;
            case ("2"):
                
        if ($valor === "vacio") {
            $usuarios = DB::table('users')->where('activo','=',1)->where('daytest','=',2)->where('inline','=',0)->where('tested','=',0)
            ->orderBy('users.id', 'desc')->paginate(50);

        }else{
            $usuarios = DB::table('users')
            ->where('users.name','LIKE','%' . $valor . '%')
            ->Orwhere('users.lname','LIKE','%' . $valor . '%')
            ->Orwhere('users.labn','LIKE','%' . $valor . '%')
            ->orderBy('id', 'desc')
            ->paginate(50); 
        }

        return view("componentes.searchqueue",compact('usuarios'));
        break;
        default:
            if ($valor === "vacio") {
                $usuarios = DB::table('users')->where('activo','=',1)->where('inline','=',0)->where('tested','=',0)
                ->orderBy('users.id', 'desc')->paginate(50);
    
            }else{
                $usuarios = DB::table('users')
                ->where('users.name','LIKE','%' . $valor . '%')
                ->Orwhere('users.lname','LIKE','%' . $valor . '%')
                ->Orwhere('users.labn','LIKE','%' . $valor . '%')
                ->orderBy('id', 'desc')
                ->paginate(50); 
            }
    
            return view("componentes.searchqueue",compact('usuarios'));
    }}

    private static $counter = 1;
    public function userqueue(Request $request,$id)
    {
        $producto = User::find($id);
        $producto->inline=1;
        $producto->labn = self::$counter++;
        $producto->inlined = Carbon::now();
        $producto->update();
        self::$counter++;
        return redirect('/crudtest');
    }

    public function scheduletest()
    {
        $message = Message::first();
        $diatest = DB::table('testqueue')->find(1);
        switch ($diatest->mainday) {
            
            case ("3"):
                $userTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 1)->where('daytest','=',3)->count();
                $userNoTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 0)->where('daytest','=',3)->count();
                $usuarios = DB::table('users')->where('activo','=',1)->where('daytest','=',3)->orderBy('users.inlined', 'asc')->paginate(50);
                return view ('virtualqueue/scheduletest', compact('usuarios', 'userTested', 'userNoTested','diatest','message'));
                break;
            case ("2"):
                $userTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 1)->where('daytest','=',2)->count();
                $userNoTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 0)->where('daytest','=',2)->count();
                $usuarios = DB::table('users')->where('activo','=',1)->where('daytest','=',2)->orderBy('users.inlined', 'asc')->paginate(50);
                return view ('virtualqueue/scheduletest', compact('usuarios', 'userTested', 'userNoTested','diatest','message'));
                break;
            default:
            $userTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 1)->count();
                $userNoTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 0)->count();
                $usuarios = DB::table('users')->where('activo','=',1)->orderBy('users.inlined', 'asc')->paginate(50);
                return view ('virtualqueue/scheduletest', compact('usuarios', 'userTested', 'userNoTested','diatest','message'));
    }
    }

    public function antigentest()
    {
        $user = auth()->user();
        $initial = substr($user->name, 0, 1);
        $diatest = DB::table('testqueue')->find(1);
        switch ($diatest->mainday) {
            
            case ("3"):
                $userTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 1)->where('daytest','=',3)->count();
                $userNoTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 0)->where('daytest','=',3)->count();
                $usuarios = DB::table('users')->where('activo','=',1)->where('daytest','=',3)->where('inline','=',1)->orderBy('users.inlined', 'asc')->paginate(100);
                return view ('virtualqueue/antigentest', compact('usuarios', 'userTested', 'userNoTested','diatest', 'initial'));
                break;
            case ("2"):
                $userTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 1)->where('daytest','=',2)->count();
                $userNoTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 0)->where('daytest','=',2)->count();
                $usuarios = DB::table('users')->where('activo','=',1)->where('daytest','=',2)->where('inline','=',1)->orderBy('users.inlined', 'asc')->paginate(100);
                return view ('virtualqueue/antigentest', compact('usuarios', 'userTested', 'userNoTested','diatest', 'initial'));
                break;
            default:
            $userTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 1)->count();
                $userNoTested = DB::table('users')->where('activo','=',1)->where('tested', '=', 0)->count();
                $usuarios = DB::table('users')->where('activo','=',1)->where('inline','=',1)->orderBy('users.inlined', 'asc')->paginate(100);
                return view ('virtualqueue/antigentest', compact('usuarios', 'userTested', 'userNoTested','diatest', 'initial'));
    }
    }

    public function usertested($id)
    {
        $producto = User::find($id);
        $producto->inline=0;
        $producto->tested=1;
        $producto->update();
        return redirect('/virtualqueue');
    }

    
    
    public function selectb($id)
    {
        $daytest = queuevirtual::find($id);
        $daytest->mainday=2;
        $daytest->update();
        return redirect('/virtualqueue');
    }

    public function selecta(Request $request,$id)
    {
        $daytest = queuevirtual::find($id);
        $daytest->mainday=3;
        $daytest->update();
        return redirect('/virtualqueue');
    }

    public function selectglobal(Request $request,$id)
    {
        $daytest = queuevirtual::find($id);
        $daytest->mainday=4;
        $daytest->update();
        
    }
    public function mymail(Request $request)
{
    $message = Message::first();
    $message->mensaje = strip_tags($request->MyEmail, '<strong>');
    $message->mensaje = html_entity_decode($message->mensaje);
    $message->save();
    return redirect('/virtualqueue');
}

public function enviarCorreoTest(Request $request) {
    $message = Message::first();
    $notifytest = DB::table('users')->where('activo','=', 1)->where('tested', '=', 0)->where('daytest','=',3)->get();
    $subject = "Notificación Salud y Seguridad";
    $correo = $request->input('correo');
    foreach ($notifytest as $value) {
        $data = [
            'nombre' => $value->name,
            'email' => $value->email,
            'mensaje' => $message->mensaje,
        ];
        $for = $value->email;
        Mail::send($correo, $data, function($msj) use ($subject, $for) {
            $msj->from("covid@crewcare.tech", "Equipo COVID");
            $msj->subject($subject);
            $msj->to($for);
            //$msj->attach('https://crewcare.tech/img/PP_ProtocoloCOVID_V3_010223_CC.pdf'); // se debe proporcionar la ruta al archivo PDF
        });
    }
    return redirect('/virtualqueue');
}

    public function remindertest()
    {
        $message = Message::first();
        $diatest = DB::table('testqueue')->find(1);
        switch ($diatest->mainday) {
            
            case ("3"):
                $notifytest = DB::table('users')->where('activo','=', 1)->where('tested', '=', 0)->where('daytest','=',3)->get();
                $subject = "Notificación Salud y Seguridad";
                foreach ($notifytest as $value) {
                    $data = [
                        'nombre' => $value->name,
                        'mensaje' => $message->mensaje,
                    ];
                  $for = $value->email;
                    Mail::send('correos.avisos',$data, function($msj) use($subject,$for){
                        $msj->from("covid@crewcare.tech","Equipo COVID");
                        $msj->subject($subject);
                        $msj->to($for);
                    });

                   /* dd($notifytest);*/
                }
                return redirect('/virtualqueue');
                break;
        case ("2"):
                $notifytest = DB::table('users')->where('activo','=', 1)->where('tested', '=', 0)->where('daytest','=',2)->get();
                $subject = "Notificación | Salud y Seguridad";
                foreach ($notifytest as $value) {
                    $data = [
                        'nombre' => $value->name,
                        'mensaje' => $message->mensaje,
                    ];
                   $for = $value->email;
                    Mail::send('correos.avisos',$data, function($msj) use($subject,$for){
                        $msj->from("covid@crewcare.tech","Equipo COVID");
                        $msj->subject($subject);
                        $msj->to($for);
                    });
                }
                return redirect('/virtualqueue');
                break;
                default:
                $notifytest = DB::table('users')->where('activo','=', 1)->where('tested', '=', 0)->get();
                $subject = "Notificación Salud y Seguridad";
                foreach ($notifytest as $value) {
                    $data = [
                        'nombre' => $value->name,
                        'mensaje' => $message->mensaje,
                    ];
                   $for = $value->email;
                    Mail::send('correos.avisos',$data, function($msj) use($subject,$for){
                        $msj->from("covid@crewcare.tech","Equipo COVID");
                        $msj->subject($subject);
                        $msj->to($for);
                    });
                }
                return redirect('/virtualqueue');
    }}
}




