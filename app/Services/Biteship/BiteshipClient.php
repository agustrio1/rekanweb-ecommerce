<?php

namespace App\Services\Biteship;

class BiteshipClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct(array $config)
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->baseUrl = (string) ($config['base_url'] ?? 'https://api.biteship.com');
        $this->timeout = (int) ($config['timeout'] ?? 10);
    }

    public static function make(): self
    {
        return new self(require __DIR__ . '/../../../config/biteship.php');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{ok: bool, status: int, data: array, error: string}
     */
    public function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path . ($query ? '?' . http_build_query($query) : '');

        return $this->request('GET', $url, $path, null);
    }

    /**
     * @return array{ok: bool, status: int, data: array, error: string}
     */
    public function post(string $path, array $payload): array
    {
        return $this->request('POST', $this->baseUrl . $path, $path, $payload);
    }

    private function request(string $method, string $url, string $path, ?array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'BITESHIP_API_KEY belum diatur.'];
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        // BARIS curl_close($ch); DIHAPUS DI SINI

        if ($body === false) {
            error_log('Biteship cURL error: ' . $curlError);
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Tidak dapat terhubung ke Biteship.'];
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            error_log('Biteship invalid JSON (HTTP ' . $status . ') on ' . $path);
            return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Respons Biteship tidak valid.'];
        }

        if ($status < 200 || $status >= 300) {
            error_log('Biteship HTTP ' . $status . ' on ' . $path . ': ' . (string) ($data['error'] ?? ''));
            return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => (string) ($data['error'] ?? 'Permintaan ke Biteship gagal.')];
        }

        return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
    }
}