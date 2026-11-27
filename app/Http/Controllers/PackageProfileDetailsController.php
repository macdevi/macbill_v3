<?php

namespace App\Http\Controllers;

use App\Models\NetworkRouter;
use App\Services\PackageProfileGateway;
use App\Services\PackageRateLimit;
use Illuminate\Http\Request;

class PackageProfileDetailsController extends Controller
{
    public function __invoke(
        Request $request,
        NetworkRouter $router,
        PackageProfileGateway $gateway
    ) {
        $data = $request->validate([
            'profile' => ['required', 'string', 'max:255'],
        ]);

        try {
            $row = $gateway->read($router, $data['profile']);
        } catch (\Throwable $ignored) {
            return response()->json([
                'message' => 'Profil gagal dibaca. Periksa koneksi dan hak baca API.',
            ], 502);
        }

        if (trim($row['rate']) === '') {
            return response()->json([
                'message' => 'Profil ini tidak memiliki rate-limit eksplisit. Isian paket tidak diubah. Tentukan limit secara manual bila diperlukan.',
            ], 422);
        }

        try {
            $parsed = PackageRateLimit::parse($row['rate']);
        } catch (\Throwable $ignored) {
            return response()->json([
                'message' => 'Format rate-limit profil ini belum didukung editor. Isian paket tidak diubah.',
            ], 422);
        }

        return response()->json([
            'router' => $router->name,
            'profile' => $row['name'],
            'rate_limit' => $row['rate'],
            'download_mbps' => round($parsed['download'], 3),
            'upload_mbps' => round($parsed['upload'], 3),
        ])->header('Cache-Control', 'private, no-store');
    }
}
