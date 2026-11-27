<?php

namespace App\Http\Controllers;

use App\Models\NetworkRouter;
use App\Services\RouterProfileReader;

class RouterProfilesController extends Controller
{
    public function __invoke(NetworkRouter $router, RouterProfileReader $reader)
    {
        try {
            return response()->json([
                'profiles' => $reader->read($router),
            ])->header('Cache-Control', 'private, no-store');
        } catch (\Throwable $ignored) {
            return response()->json([
                'message' => 'Gagal membaca profil PPP. Periksa koneksi API dan hak baca akun.',
            ], 502)->header('Cache-Control', 'private, no-store');
        }
    }
}
