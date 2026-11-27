<?php

namespace App\Http\Controllers;

use App\Models\NetworkRouter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class RouterConnectionTestController extends Controller
{
    public function __invoke(NetworkRouter $router)
    {
        $client = null;
        $stage = 'connection';

        try {
            $config = new Config([
                'host' => $router->host,
                'port' => (int) $router->port,
                'user' => $router->username,
                'pass' => $router->password,
                'ssl' => (bool) $router->use_tls,
                'ssl_options' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'ciphers' => 'DEFAULT',
                ],
                'timeout' => 5,
                'socket_timeout' => 10,
                'socket_blocking' => true,
                'throw_timeout_exception' => true,
                'attempts' => 1,
                'delay' => 1,
            ]);

            $client = new Client($config);
            $stage = 'reading';

            $identityRows = $client->query(
                new Query('/system/identity/print')
            )->read();

            $resourceRows = $client->query(
                new Query('/system/resource/print')
            )->read();

            $identity = $identityRows[0] ?? [];
            $resource = $resourceRows[0] ?? [];

            if (
                ! is_array($identity) ||
                ! is_array($resource) ||
                ! isset($identity['name'], $resource['version'], $resource['uptime']) ||
                ! array_key_exists('cpu-load', $resource)
            ) {
                throw new \RuntimeException('Incomplete router response');
            }

            $result = [
                'router_id' => $router->id,
                'ok' => true,
                'checked_at' => now()->toIso8601String(),
                'identity' => (string) $identity['name'],
                'version' => (string) $resource['version'],
                'cpu' => (string) $resource['cpu-load'],
                'uptime' => (string) $resource['uptime'],
            ];
        } catch (Throwable $exception) {
            // Jangan simpan pesan exception mentah atau kredensial ke log.
            Log::warning('Router API test failed', [
                'router_id' => $router->id,
                'stage' => $stage,
                'exception_class' => get_class($exception),
            ]);

            $result = [
                'router_id' => $router->id,
                'ok' => false,
                'checked_at' => now()->toIso8601String(),
                'message' => $stage === 'reading'
                    ? 'Koneksi dibuka, tetapi pembacaan data belum berhasil. Periksa hak baca akun API.'
                    : 'Koneksi atau autentikasi API belum berhasil. Periksa alamat, port, layanan API, firewall, akun, dan sertifikat jika memakai TLS.',
            ];
        } finally {
            if ($client !== null && method_exists($client, 'disconnect')) {
                try {
                    $client->disconnect();
                } catch (Throwable $ignored) {
                    // Penutupan koneksi tidak mengubah hasil pemeriksaan.
                }
            }
        }

        $result['config_version'] = $router->updated_at->toIso8601String();

        Cache::store('file')->put(
            'dashboard-router-check.'.$router->id,
            $result,
            now()->addDay()
        );

        return redirect()->route('routers.index')
            ->with('router_test', $result);
    }
}
