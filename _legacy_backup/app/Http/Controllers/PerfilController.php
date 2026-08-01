<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class PerfilController extends Controller
{
    public function index(){
        $users = User::findOrFail(auth()->user()->id);
        return view('perfil', compact('users'));
    }

    public function indexb(){
        $users = User::findOrFail(auth()->user()->id);
        return view('profile', compact('users'));
    }

    public function update(Request $request){
        $users = User::findOrFail(auth()->user()->id);
        $datosproducto = request()->except(['_token','_method']);
        if($request->hasFile('imgperfil')){
            
            $productos = User::findOrFail($users->id);
            //unlink($productos->Foto);
            
           // $datosproducto['imgperfil']=$request->file('imgperfil')->store('usrs','imagesperf');
            $datosproducto['imgperfil']=$request->file('imgperfil')->store('usrs','imagesprf');
           
        }
        // $request->validate([
        //     'imgperfil' => 'required|image|mimes:jpg,jpeg,gif,png|max:2048'
        // ]);
        // $file = $request->file('imgperfil');
        // if($file->isValid()){
        //     $destinationpath = '/imgperfil';
        //     $image=date('YmdHis').'.'.$file->getClientOriginalExtension();
        //     $file->move($destinationpath,$image);
        // }
        User::where('id', "=" ,$users->id)->update($datosproducto);
        return redirect('/profile');
    }
    public function pruebachedule()
    {
        $users = DB::table('users')->where('activo','=',1)->get();
        foreach ($users as $user) {
            $producto = user::find($user->id);
            $producto->encuestadiaria=0;
            $producto->update();
        }
    }
    public function changepassword()
    {
        $users = User::findOrFail(auth()->user()->id);
        return view("changepassword",compact('users'));
 
    }
    public function updatepassword(Request $request,$id)
    {
        $user = User::find($id);
        $input = $request->except('password');
        if (! $request->filled('password')) {
            $user->fill($input)->save();
            return redirect('/home');
        }else{
            $user->password = Hash::make($request->password);
            $user->fill($input)->save();
            return redirect('/home'); 
        }
    }
}