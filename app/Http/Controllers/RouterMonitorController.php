<?php

namespace App\Http\Controllers;

use App\Models\NetworkRouter;
use Illuminate\Support\Facades\Cache;
use RouterOS\Client;
use RouterOS\Query;
use Throwable;

class RouterMonitorController extends Controller
{
    public function __invoke()
    {
        // Sementara memakai router dengan ID paling awal.
        $router = \App\Services\ImportWilayah::routers()->reorder('id')->first();

        if (!$router) {
            return response()->json(['state' => 'unconfigured'])
                ->header('Cache-Control', 'private, no-store');
        }

        $cache = Cache::store('file');
        $version = hash('sha256', $router->updated_at->format('Y-m-d H:i:s.u'));
        $key = 'router-monitor.'.$router->id.'.'.$version;
        $snapshot = $cache->get($key);

        if ($snapshot && time() - $snapshot['checked_ts'] < 3) {
            return $this->output($snapshot);
        }

        $lock = $cache->lock($key.'.lock', 90);

        if (!$lock->get()) {
            return $this->output($snapshot ?: ['state' => 'pending']);
        }

        $client = null;

        try {
            // Periksa ulang cache setelah mendapatkan lock.
            $latest = $cache->get($key);
            if ($latest && time() - $latest['checked_ts'] < 3) {
                return $this->output($latest);
            }
            $snapshot = $latest ?: $snapshot;

            $client = new Client([
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
                'socket_timeout' => 5,
                'socket_blocking' => true,
                'throw_timeout_exception' => true,
                'attempts' => 1,
                'delay' => 1,
            ]);

            $identity = $client->query(new Query('/system/identity/print'))->read();
            $resource = $client->query(new Query('/system/resource/print'))->read();
            $resource = $resource[0] ?? [];

            if (
                !isset($identity[0]['name'], $resource['uptime']) ||
                !array_key_exists('cpu-load', $resource)
            ) {
                throw new \RuntimeException('Incomplete system response');
            }

            $rx = null;
            $tx = null;
            $trafficState = $router->wan_interface ? 'failed' : 'unconfigured';

            if ($router->wan_interface) {
                try {
                    $query = (new Query('/interface/monitor-traffic'))
                        ->equal('interface', $router->wan_interface)
                        ->equal('once', 'true');

                    $traffic = $client->query($query)->read();
                    $traffic = $traffic[0] ?? [];

                    if (
                        isset($traffic['rx-bits-per-second'], $traffic['tx-bits-per-second']) &&
                        is_numeric($traffic['rx-bits-per-second']) &&
                        is_numeric($traffic['tx-bits-per-second'])
                    ) {
                        $rx = (float) $traffic['rx-bits-per-second'] / 1000000;
                        $tx = (float) $traffic['tx-bits-per-second'] / 1000000;
                        $trafficState = 'ok';
                    }
                } catch (Throwable $ignored) {
                    // Gagal baca WAN tidak dianggap gagal login API.
                }
            }

            $result = [
                'state' => 'connected',
                'checked_ts' => time(),
                'data_at' => now()->toIso8601String(),
                'router_name' => $router->name,
                'wan' => $router->wan_interface,
                'identity' => (string) $identity[0]['name'],
                'cpu' => (string) $resource['cpu-load'],
                'uptime' => (string) $resource['uptime'],
                'rx' => $rx,
                'tx' => $tx,
                'traffic_state' => $trafficState,
            ];
        } catch (Throwable $ignored) {
            // Pertahankan data terakhir, tetapi tandai sebagai data lama.
            $result = array_merge($snapshot ?: [], [
                'state' => 'failed',
                'checked_ts' => time(),
                'router_name' => $router->name,
                'wan' => $router->wan_interface,
            ]);
        } finally {
            if ($client !== null && method_exists($client, 'disconnect')) {
                try {
                    $client->disconnect();
                } catch (Throwable $ignored) {
                }
            }

            // Lock dilepas setelah hasil ditulis di bawah.
            if (!isset($result)) {
                $lock->release();
            }
        }

        try {
            $cache->put($key, $result, now()->addDay());
        } finally {
            $lock->release();
        }

        return $this->output($result);
    }

    private function output(array $data)
    {
        return response()->json($data)
            ->header('Cache-Control', 'private, no-store');
    }
}
