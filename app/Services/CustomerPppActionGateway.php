<?php

namespace App\Services;

use App\Models\NetworkRouter;
use Carbon\CarbonImmutable;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class CustomerPppActionGateway
{
    private ?Client $client = null;

    protected function open(NetworkRouter $router): void
    {
        $this->client = new Client(new Config([
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
        ]));
    }

    protected function close(): void
    {
        if ($this->client !== null) {
            try {
                if (method_exists($this->client, 'disconnect')) {
                    $this->client->disconnect();
                }
            } catch (\Throwable $ignored) {
            }

            $this->client = null;
        }
    }

    protected function query(
        string $command,
        array $equals = [],
        array $where = []
    ): array {
        $query = new Query($command);

        foreach ($equals as $key => $value) {
            $query->equal($key, (string) $value);
        }

        foreach ($where as $key => $value) {
            $query->where($key, (string) $value);
        }

        $rows = $this->client->query($query)->read();

        if (! is_array($rows)) {
            throw new \RuntimeException('Respons router tidak valid.');
        }

        foreach ($rows as $row) {
            if (
                ! is_array($row)
                || array_key_exists('!trap', $row)
                || array_key_exists('!fatal', $row)
                || array_key_exists('message', $row)
            ) {
                throw new \RuntimeException('Respons router tidak valid.');
            }
        }

        return $rows;
    }

    private function profile(string $name): void
    {
        $rows = $this->query(
            '/ppp/profile/print',
            ['.proplist' => '.id,name'],
            ['name' => $name]
        );

        if (
            count($rows) !== 1
            || empty($rows[0]['.id'])
            || ($rows[0]['name'] ?? null) !== $name
        ) {
            throw new \RuntimeException('Profil router tidak tersedia.');
        }
    }

    private function account(string $username): array
    {
        $rows = $this->query(
            '/ppp/secret/print',
            ['.proplist' => '.id,name,profile,service,disabled'],
            ['name' => $username]
        );

        if (count($rows) !== 1) {
            throw new \RuntimeException('Akun lokal tidak dapat dipastikan.');
        }

        $row = $rows[0];

        if (
            empty($row['.id'])
            || ($row['name'] ?? null) !== $username
            || ! isset($row['profile'])
            || ! is_string($row['profile'])
            || ! in_array($row['service'] ?? null, ['pppoe', 'any'], true)
            || ! array_key_exists('disabled', $row)
            || ! in_array(
                $row['disabled'],
                [false, 'false', 'no', '0', 0],
                true
            )
        ) {
            throw new \RuntimeException('Kondisi akun tidak aman untuk diubah.');
        }

        return [
            'id' => (string) $row['.id'],
            'profile' => $row['profile'],
        ];
    }

    /**
     * Verifikasi baca-saja untuk mengambil alih pencatatan isolir.
     * Tidak mengubah profil dan tidak memutus sesi.
     */
    public function inspectIsolation(
        NetworkRouter $router,
        string $username,
        string $normalProfile,
        string $isolationProfile
    ): array {
        if (
            trim($username) === ''
            || trim($normalProfile) === ''
            || trim($isolationProfile) === ''
            || $normalProfile === $isolationProfile
        ) {
            throw new \InvalidArgumentException('Pemetaan isolir tidak valid.');
        }

        try {
            $this->open($router);
            $this->profile($normalProfile);
            $this->profile($isolationProfile);

            $before = $this->account($username);

            if ($before['profile'] !== $isolationProfile) {
                throw new \RuntimeException('Profil akun bukan profil isolir.');
            }

            $sessions = $this->sessions($username);
            $after = $this->account($username);

            if ($after !== $before) {
                throw new \RuntimeException('Akun berubah saat pemeriksaan.');
            }

            return [
                'account_id' => $after['id'],
                'profile' => $after['profile'],
                'session_count' => count($sessions),
                'verification_scope' => 'account_profile_only',
                'verified_at' => CarbonImmutable::now(
                    InvoicePeriod::TZ
                )->toIso8601String(),
            ];
        } finally {
            $this->close();
        }
    }

    private function sessions(string $username): array
    {
        $rows = $this->query(
            '/ppp/active/print',
            ['.proplist' => '.id,name,service'],
            ['name' => $username, 'service' => 'pppoe']
        );

        if (count($rows) > 100) {
            throw new \RuntimeException('Jumlah sesi tidak wajar.');
        }

        $ids = [];

        foreach ($rows as $row) {
            if (
                empty($row['.id'])
                || ($row['name'] ?? null) !== $username
                || ($row['service'] ?? null) !== 'pppoe'
                || isset($ids[(string) $row['.id']])
            ) {
                throw new \RuntimeException('Identitas sesi tidak dapat dipastikan.');
            }

            $ids[(string) $row['.id']] = true;
        }

        return array_keys($ids);
    }

    /**
     * beforeWrite wajib menyimpan penanda operasi secara durable.
     * Setelah callback berhasil, semua kegagalan harus diperlakukan
     * sebagai hasil belum pasti oleh pemanggil, bukan dicoba ulang otomatis.
     */
    public function change(
        NetworkRouter $router,
        string $username,
        string $expectedProfile,
        string $targetProfile,
        callable $beforeWrite
    ): array {
        if (
            trim($username) === ''
            || trim($expectedProfile) === ''
            || trim($targetProfile) === ''
            || $expectedProfile === $targetProfile
        ) {
            throw new \InvalidArgumentException('Pemetaan tindakan tidak valid.');
        }

        try {
            $this->open($router);

            $this->profile($expectedProfile);
            $this->profile($targetProfile);

            $before = $this->account($username);

            if ($before['profile'] !== $expectedProfile) {
                throw new \RuntimeException('Profil akun sudah berubah atau tidak dikenali.');
            }

            $oldSessions = $this->sessions($username);

            $beforeWrite([
                'account_id' => $before['id'],
                'username' => $username,
                'from_profile' => $expectedProfile,
                'to_profile' => $targetProfile,
                'session_count' => count($oldSessions),
            ]);

            $current = $this->account($username);

            if ($current !== $before) {
                throw new \RuntimeException('Akun berubah sebelum tindakan.');
            }

            $this->query('/ppp/secret/set', [
                '.id' => $before['id'],
                'profile' => $targetProfile,
            ]);

            $changed = $this->account($username);

            if (
                $changed['id'] !== $before['id']
                || $changed['profile'] !== $targetProfile
            ) {
                throw new \RuntimeException('Perubahan profil belum terverifikasi.');
            }

            $resetCount = 0;

            foreach ($oldSessions as $id) {
                $rows = $this->query(
                    '/ppp/active/print',
                    ['.proplist' => '.id,name,service'],
                    ['.id' => $id]
                );

                if ($rows === []) {
                    continue;
                }

                if (
                    count($rows) !== 1
                    || ($rows[0]['.id'] ?? null) !== $id
                    || ($rows[0]['name'] ?? null) !== $username
                    || ($rows[0]['service'] ?? null) !== 'pppoe'
                ) {
                    throw new \RuntimeException('Sesi berubah. Tindakan dihentikan.');
                }

                $this->query('/ppp/active/remove', ['.id' => $id]);
                $resetCount++;
            }

            $remaining = $this->sessions($username);

            if (array_intersect($oldSessions, $remaining) !== []) {
                throw new \RuntimeException('Pemutusan sesi lama belum terverifikasi.');
            }

            $after = $this->account($username);

            if (
                $after['id'] !== $before['id']
                || $after['profile'] !== $targetProfile
            ) {
                throw new \RuntimeException('Hasil akhir akun belum terverifikasi.');
            }

            return [
                'profile' => $targetProfile,
                'reset_sessions' => $resetCount,
                'verified_at' => CarbonImmutable::now(
                    InvoicePeriod::TZ
                )->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Tindakan router tidak selesai atau hasilnya belum dapat dipastikan.'
            );
        } finally {
            $this->close();
        }
    }

    /**
     * Membuat akun PPPoE baru. Tidak pernah menimpa akun yang sudah ada.
     * beforeWrite wajib menyimpan penanda operasi secara durable.
     */
    public function provision(
        NetworkRouter $router,
        string $username,
        string $password,
        string $profile,
        callable $beforeWrite
    ): array {
        if (trim($username) === '' || $password === '' || trim($profile) === '') {
            throw new \InvalidArgumentException('Data akun tidak valid.');
        }

        try {
            $this->open($router);
            $this->profile($profile);

            $existing = $this->query(
                '/ppp/secret/print',
                ['.proplist' => '.id,name'],
                ['name' => $username]
            );

            if ($existing !== []) {
                throw new \RuntimeException('Username sudah ada di router.');
            }

            $beforeWrite([
                'username' => $username,
                'profile' => $profile,
                'existing_accounts' => 0,
            ]);

            $this->query('/ppp/secret/add', [
                'name' => $username,
                'password' => $password,
                'profile' => $profile,
                'service' => 'pppoe',
            ]);

            $created = $this->account($username);

            if ($created['profile'] !== $profile) {
                throw new \RuntimeException('Akun baru belum terverifikasi.');
            }

            return [
                'account_id' => $created['id'],
                'profile' => $created['profile'],
                'verified_at' => CarbonImmutable::now(
                    InvoicePeriod::TZ
                )->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Tindakan router tidak selesai atau hasilnya belum dapat dipastikan.'
            );
        } finally {
            $this->close();
        }
    }

    /**
     * Menghapus akun PPPoE dari router. Tidak pernah menyentuh akun lain.
     * Bila akun sudah tidak ada, dianggap selesai tanpa menulis apa pun.
     * beforeWrite wajib menyimpan penanda operasi secara durable.
     */
    public function removeAccount(
        NetworkRouter $router,
        string $username,
        callable $beforeWrite
    ): array {
        if (trim($username) === '') {
            throw new \InvalidArgumentException('Data akun tidak valid.');
        }

        try {
            $this->open($router);

            $rows = $this->query(
                '/ppp/secret/print',
                ['.proplist' => '.id,name,service'],
                ['name' => $username]
            );

            if (count($rows) > 1) {
                throw new \RuntimeException('Akun tidak dapat dipastikan.');
            }

            $verifiedAt = fn () => CarbonImmutable::now(
                InvoicePeriod::TZ
            )->toIso8601String();

            if ($rows === []) {
                return [
                    'absent' => true,
                    'removed' => false,
                    'reset_sessions' => 0,
                    'verified_at' => $verifiedAt(),
                ];
            }

            $row = $rows[0];

            if (
                empty($row['.id'])
                || ($row['name'] ?? null) !== $username
                || ! in_array($row['service'] ?? null, ['pppoe', 'any'], true)
            ) {
                throw new \RuntimeException('Akun tidak aman untuk dihapus.');
            }

            $id = (string) $row['.id'];
            $sessions = $this->sessions($username);

            $beforeWrite([
                'account_id' => $id,
                'username' => $username,
                'session_count' => count($sessions),
            ]);

            $this->query('/ppp/secret/remove', ['.id' => $id]);

            $left = $this->query(
                '/ppp/secret/print',
                ['.proplist' => '.id,name'],
                ['name' => $username]
            );

            if ($left !== []) {
                throw new \RuntimeException('Penghapusan akun belum terverifikasi.');
            }

            $reset = 0;

            foreach ($sessions as $sid) {
                $active = $this->query(
                    '/ppp/active/print',
                    ['.proplist' => '.id,name,service'],
                    ['.id' => $sid]
                );

                if ($active === []) {
                    continue;
                }

                if (
                    count($active) !== 1
                    || ($active[0]['.id'] ?? null) !== $sid
                    || ($active[0]['name'] ?? null) !== $username
                    || ($active[0]['service'] ?? null) !== 'pppoe'
                ) {
                    throw new \RuntimeException('Sesi berubah. Tindakan dihentikan.');
                }

                $this->query('/ppp/active/remove', ['.id' => $sid]);
                $reset++;
            }

            if (array_intersect($sessions, $this->sessions($username)) !== []) {
                throw new \RuntimeException('Pemutusan sesi belum terverifikasi.');
            }

            return [
                'absent' => false,
                'removed' => true,
                'account_id' => $id,
                'reset_sessions' => $reset,
                'verified_at' => $verifiedAt(),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Tindakan router tidak selesai atau hasilnya belum dapat dipastikan.'
            );
        } finally {
            $this->close();
        }
    }
}
