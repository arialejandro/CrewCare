<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;

class cropimageController extends Controller
{
    public function uploadCropImage(Request $request)
    {
        // SEGURIDAD (2026-07-06): validar la entrada y blindar el parseo del data-URI.
        $request->validate(['image' => 'required|string']);

        // Sin el separador ";base64," el explode dejaría índices indefinidos → guarda previa.
        if (strpos($request->image, ';base64,') === false) {
            return back()->with('error', 'Imagen inválida.');
        }

        $users = User::findOrFail(auth()->user()->id);
        $folderPath = public_path('imagesprf/usrs/');
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
