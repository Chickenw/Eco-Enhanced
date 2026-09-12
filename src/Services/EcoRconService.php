<?php

namespace GameNest\GameNestEcoEnhanced\Services;

use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use RuntimeException;

class EcoRconService
{
    private const SERVERDATA_RESPONSE_VALUE = 0;
    private const SERVERDATA_EXECCOMMAND = 2;
    private const SERVERDATA_AUTH_RESPONSE = 2;
    private const SERVERDATA_AUTH = 3;

    public function testConnection(Server $server): bool
    {
        $socket = $this->connect($server);

        fclose($socket);

        return true;
    }

    public function execute(Server $server, string $command): string
    {
        $socket = $this->connect($server);

        try {
            $requestId = random_int(1000, 999999);

            $this->writePacket(
                $socket,
                $requestId,
                self::SERVERDATA_EXECCOMMAND,
                $command
            );

            $response = $this->readPacket($socket);

            if ($response === null) {
                throw new RuntimeException(
                    'Eco RCON returned no response.'
                );
            }

            return trim($response['body']);

        } finally {
            fclose($socket);
        }
    }

    private function connect(Server $server)
    {
        $server->loadMissing('allocation');

        $host = $server->allocation?->ip;

        if (!$host) {
            throw new RuntimeException(
                'Eco server has no primary allocation IP.'
            );
        }

        $port = (int) $this->getEnvironmentVariable(
            $server,
            'RCON_PORT',
            ((int) $server->allocation->port) + 2
        );

        $password = (string) $this->getEnvironmentVariable(
            $server,
            'RCON_PW',
            ''
        );

        if (trim($password) === '') {
            throw new RuntimeException(
                'Eco RCON password is not configured.'
            );
        }

        $errno = 0;
        $error = '';

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $error,
            5,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            throw new RuntimeException(
                "Unable to connect to Eco RCON at {$host}:{$port}: {$error} ({$errno})"
            );
        }

        stream_set_timeout($socket, 5);

        $requestId = random_int(1000, 999999);

        $this->writePacket(
            $socket,
            $requestId,
            self::SERVERDATA_AUTH,
            $password
        );

        $response = $this->readPacket($socket);

        if ($response === null) {
            fclose($socket);

            throw new RuntimeException(
                'Eco RCON did not respond to authentication.'
            );
        }

        if (
            $response['type'] !== self::SERVERDATA_AUTH_RESPONSE ||
            $response['id'] === -1
        ) {
            fclose($socket);

            throw new RuntimeException(
                'Eco RCON authentication failed.'
            );
        }

        return $socket;
    }

    private function writePacket(
        $socket,
        int $id,
        int $type,
        string $body
    ): void {
        $payload =
            $this->packInt32($id) .
            $this->packInt32($type) .
            $body .
            "\x00\x00";

        $packet =
            $this->packInt32(strlen($payload)) .
            $payload;

        $written = fwrite($socket, $packet);

        if ($written === false || $written !== strlen($packet)) {
            throw new RuntimeException(
                'Failed to write Eco RCON packet.'
            );
        }
    }

    private function readPacket($socket): ?array
    {
        $sizeData = $this->readExact($socket, 4);

        if ($sizeData === null) {
            return null;
        }

        $size = $this->unpackInt32($sizeData);

        if ($size < 10 || $size > 1024 * 1024) {
            throw new RuntimeException(
                "Invalid Eco RCON packet size: {$size}"
            );
        }

        $payload = $this->readExact($socket, $size);

        if ($payload === null) {
            throw new RuntimeException(
                'Incomplete Eco RCON packet.'
            );
        }

        $id = $this->unpackInt32(
            substr($payload, 0, 4)
        );

        $type = $this->unpackInt32(
            substr($payload, 4, 4)
        );

        $body = substr(
            $payload,
            8,
            $size - 10
        );

        return [
            'id' => $id,
            'type' => $type,
            'body' => $body,
        ];
    }

    private function readExact($socket, int $length): ?string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread(
                $socket,
                $length - strlen($buffer)
            );

            if ($chunk === false) {
                throw new RuntimeException(
                    'Failed reading Eco RCON socket.'
                );
            }

            if ($chunk === '') {
                $meta = stream_get_meta_data($socket);

                if ($meta['timed_out'] ?? false) {
                    throw new RuntimeException(
                        'Eco RCON connection timed out.'
                    );
                }

                if (feof($socket)) {
                    return $buffer === '' ? null : $buffer;
                }

                usleep(10000);

                continue;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function packInt32(int $value): string
    {
        if ($value < 0) {
            $value += 4294967296;
        }

        return pack('V', $value);
    }

    private function unpackInt32(string $data): int
    {
        $value = unpack('Vvalue', $data)['value'];

        if ($value >= 2147483648) {
            $value -= 4294967296;
        }

        return $value;
    }

    private function getEnvironmentVariable(
        Server $server,
        string $environmentVariable,
        mixed $default = null
    ): mixed {
        $eggVariable = EggVariable::query()
            ->where('egg_id', $server->egg_id)
            ->where('env_variable', $environmentVariable)
            ->first();

        if (!$eggVariable) {
            return $default;
        }

        $serverVariable = ServerVariable::query()
            ->where('server_id', $server->id)
            ->where('variable_id', $eggVariable->id)
            ->first();

        if (!$serverVariable) {
            return $eggVariable->default_value ?? $default;
        }

        if ($serverVariable->variable_value === '') {
            return $default;
        }

        return $serverVariable->variable_value;
    }
}
