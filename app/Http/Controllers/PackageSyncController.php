<?php

namespace App\Http\Controllers;

use App\Models\InternetPackage;
use App\Models\NetworkRouter;
use App\Services\PackageProfileGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PackageSyncController extends Controller
{
    private function target(InternetPackage $package, NetworkRouter $router): array
    {
        abort_unless($package->service_type === 'pppoe', 422,
            'Push hanya tersedia untuk PPPoE.');
        abort_unless(
            $package->download_mbps !== null && $package->upload_mbps !== null,
            422, 'Isi dan simpan download/upload terlebih dahulu.'
        );

        $mapping = $package->routers()->where('network_routers.id', $router->id)->first();
        abort_unless($mapping, 422, 'Router tidak dipetakan pada paket ini.');

        return [
            'package_id' => $package->id,
            'package_name' => $package->name,
            'router_id' => $router->id,
            'router_name' => $router->name,
            'router_version' => hash('sha256', serialize($router->getRawOriginal())),
            'profile' => (string) $mapping->pivot->normal_profile,
            'rate' => $package->rate_limit ?: (
                $this->number($package->upload_mbps).'M/'.
                $this->number($package->download_mbps).'M'
            ),
        ];
    }

    private function number($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function blockers(array $target, NetworkRouter $router, array $before): array
    {
        $reasons = [];

        if (in_array($target['profile'], ['default', 'default-encryption'], true)) {
            $reasons[] = 'Push ke profil bawaan diblokir.';
        }

        if ($router->isolation_method === 'ppp_profile'
            && $router->isolation_profile === $target['profile']) {
            $reasons[] = 'Profil ini adalah profil isolir.';
        }

        if (DB::table('internet_package_router')
            ->where('network_router_id', $router->id)
            ->where('normal_profile', $target['profile'])
            ->where('internet_package_id', '!=', $target['package_id'])
            ->exists()) {
            $reasons[] = 'Profil ini juga dipetakan ke paket lain. Pisahkan pemetaannya dahulu.';
        }

        try {
            \App\Services\PackageRateLimit::parse($target['rate']);
        } catch (\Throwable $ignored) {
            $reasons[] = 'Format rate-limit paket belum valid.';
        }

        return $reasons;
    }

    public function preview(
        Request $request,
        InternetPackage $package,
        NetworkRouter $router,
        PackageProfileGateway $gateway
    ) {
        $target = $this->target($package, $router);

        try {
            $before = $gateway->read($router, $target['profile']);
        } catch (\Throwable $ignored) {
            return response()->json([
                'message' => 'Profil gagal dibaca. Periksa koneksi API, hak baca, dan nama profil.',
            ], 502);
        }

        $reasons = $this->blockers($target, $router, $before);
        $same = $before['rate'] === $target['rate'];
        $token = null;

        if (!$reasons) {
            $token = (string) Str::uuid();
            Cache::store('file')->put('package-preview.'.$token, [
                'user_id' => $request->user()->id,
                'target' => $target,
                'before' => $before,
            ], now()->addMinutes(5));
        }

        return response()->json([
            'package' => $target['package_name'],
            'router' => $target['router_name'],
            'profile' => $target['profile'],
            'before' => $before['rate'],
            'after' => $target['rate'],
            'same' => $same,
            'reasons' => $reasons,
            'token' => $token,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function push(
        Request $request,
        InternetPackage $package,
        NetworkRouter $router,
        PackageProfileGateway $gateway
    ) {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'confirmed' => ['required', 'accepted'],
        ]);

        $cache = Cache::store('file');
        $saved = $cache->get('package-preview.'.$data['token']);
        abort_unless($saved && (int) $saved['user_id'] === (int) $request->user()->id,
            409, 'Preview kedaluwarsa. Lakukan preview ulang.');

        $target = $this->target($package, $router);
        abort_unless($saved['target'] === $target, 409,
            'Konfigurasi berubah. Lakukan preview ulang.');

        $lock = $cache->lock(
            'profile-write.'.hash('sha256', $router->id.'|'.$target['profile']),
            120
        );

        abort_unless($lock->get(), 409,
            'Profil sedang diproses. Tunggu lalu preview ulang.');

        try {
            // Token hanya dapat dipakai satu kali.
            $approved = $cache->pull('package-preview.'.$data['token']);
            abort_unless($approved && $approved === $saved, 409,
                'Preview sudah digunakan atau kedaluwarsa.');

            $reasons = $this->blockers($target, $router, $saved['before']);
            abort_if(count($reasons) > 0, 409, implode(' ', $reasons));

            // package-push-noop: periksa ulang, tanpa menulis jika sudah sama.
            if ($saved['before']['rate'] === $target['rate']) {
                try {
                    $actual = $gateway->read($router, $target['profile']);
                } catch (\Throwable $ignored) {
                    return response()->json([
                        'message' => 'Pemeriksaan ulang gagal. Tidak ada perintah perubahan yang dikirim. Lakukan preview ulang.',
                    ], 502);
                }

                if ($actual !== $saved['before']) {
                    return response()->json([
                        'message' => 'Profil berubah setelah preview. Tidak ada perintah perubahan yang dikirim. Lakukan preview ulang.',
                    ], 409);
                }

                Log::info('Package push checked without changes', [
                    'user_id' => $request->user()->id,
                    'package_id' => $package->id,
                    'router_id' => $router->id,
                    'profile' => $target['profile'],
                ]);

                return response()->json([
                    'message' => 'Tidak ada perubahan yang perlu dikirim. Rate-limit profil '.
                        $target['profile'].' pada router '.$router->name.
                        ' sudah sama dengan paket. Tidak ada perintah perubahan yang dijalankan.',
                    'verified' => true,
                    'changed' => false,
                ]);
            }

            $audit = (string) Str::uuid();
            $auditData = [
                'user_id' => $request->user()->id,
                'package_id' => $package->id,
                'router_id' => $router->id,
                'profile' => $target['profile'],
                'before' => $saved['before'],
                'after' => $target['rate'],
                'attempted_at' => now()->toIso8601String(),
            ];

            // Catat nilai lama sebelum mencoba menulis.
            $cache->put('package-push-audit.'.$audit, $auditData, now()->addDays(30));
            Log::info('Package profile push requested', [
                'audit_id' => $audit,
            ] + $auditData);

            try {
                $actual = $gateway->write($router, $saved['before'], $target['rate']);
                $verified = $actual['rate'] === $target['rate'];
            } catch (\Throwable $ignored) {
                Log::warning('Package profile push result uncertain', ['audit_id' => $audit]);

                return response()->json([
                    'message' => 'Push tidak dapat dipastikan atau profil sudah berubah. Jangan langsung mengulang. Lakukan preview untuk memeriksa kondisi router.',
                ], 502);
            }

            Log::info('Package profile push checked', [
                'audit_id' => $audit,
                'verified' => $verified,
                'actual_rate' => $actual['rate'],
            ]);

            return response()->json([
                'message' => $verified
                    ? 'Push berhasil. Pembacaan ulang profil sesuai. Tidak ada sesi pelanggan yang diputus.'
                    : 'Router merespons, tetapi nilai hasil baca berbeda. Lakukan preview ulang sebelum tindakan berikutnya.',
                'verified' => $verified,
            ]);
        } finally {
            $lock->release();
        }
    }
}
