<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class cropimageController extends Controller
{
    public function index()
    {
        return view('crop-image-upload');
    }
    public function uploadCropImages(Request $request)
    {
        $users = User::findOrFail(auth()->user()->id);
        $datosproducto = request()->except(['_token','_method']);
        $image_parts = explode(";base64,", $request->image);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[0];
        $image_base64 = base64_decode($image_parts[0]);
        if($request->hasFile('imgperfil')){
            $productos = User::findOrFail($users->id);
            // $datosproducto['imgperfil']=$request->file('imgperfil')->store('usrs','imagesperf');
       
            $datosproducto['imgperfil']=$request->file('imgperfil',$image_base64)->store('usrs','imagesprf');
        }
        User::where('id', "=" ,$users->id)->update($datosproducto);
        return redirect('/profile');
    }

    public function uploadCropImage(Request $request)
    {
        $users = User::findOrFail(auth()->user()->id);
        $folderPath =public_path('imagesprf/usrs/');
        $image_parts = explode(";base64,", $request->image);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1];
        $image_base64 = base64_decode($image_parts[1]);
        $imageName = uniqid() . '.png';
        $imageFullPath = $folderPath.$imageName;
        file_put_contents($imageFullPath, $image_base64);

        $users->imgperfil= $imageName;
        $users->update();

        
   
        return response()->json(['success'=>'Crop Image Uploaded Successfully']);
    }
}