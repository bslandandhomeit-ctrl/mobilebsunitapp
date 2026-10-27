<?php
namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

/** Verify a recent Firebase phone sign-in; never trust a client-supplied phone. */
class FirebasePhoneIdentity
{
    public function verify($token)
    {
        $project = config('customer_registration.firebase_project_id');
        if (!$project) throw new \RuntimeException('Firebase project is not configured.');
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new \InvalidArgumentException('Invalid phone verification.');
        $header = json_decode($this->decode($parts[0]), true);
        $claims = json_decode($this->decode($parts[1]), true);
        if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            throw new \InvalidArgumentException('Invalid phone verification.');
        }
        $certificates = $this->certificates();
        if (!isset($certificates[$header['kid']]) || openssl_verify(
            $parts[0].'.'.$parts[1], $this->decode($parts[2]),
            $certificates[$header['kid']], OPENSSL_ALGO_SHA256
        ) !== 1) throw new \InvalidArgumentException('Invalid phone verification signature.');

        $now = time();
        if (($claims['aud'] ?? '') !== $project ||
            ($claims['iss'] ?? '') !== 'https://securetoken.google.com/'.$project ||
            !is_string($claims['sub'] ?? null) || strlen($claims['sub']) < 1 || strlen($claims['sub']) > 128 ||
            !is_numeric($claims['exp'] ?? null) || $claims['exp'] <= $now ||
            !is_numeric($claims['iat'] ?? null) || $claims['iat'] > $now ||
            !is_numeric($claims['auth_time'] ?? null) || $claims['auth_time'] > $now ||
            $claims['auth_time'] < $now - 600 ||
            ($claims['firebase']['sign_in_provider'] ?? '') !== 'phone' ||
            !preg_match('/^\+855[1-9][0-9]{7,8}$/', $claims['phone_number'] ?? '')) {
            throw new \InvalidArgumentException('Phone verification expired or invalid. Verify your phone again.');
        }
        return ['uid' => $claims['sub'], 'phone_number' => '0'.substr($claims['phone_number'], 4)];
    }

    protected function certificates()
    {
        $key = 'customer-registration.firebase-certificates';
        $certificates = Cache::get($key);
        if (is_array($certificates)) return $certificates;
        $response = (new Client(['timeout' => 10, 'connect_timeout' => 5]))->get(
            'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com'
        );
        $certificates = json_decode((string) $response->getBody(), true);
        if (!is_array($certificates) || !$certificates) throw new \RuntimeException('Unable to verify phone identity.');
        preg_match('/max-age=(\d+)/', $response->getHeaderLine('Cache-Control'), $matches);
        Cache::put($key, $certificates, min(3600, (int) ($matches[1] ?? 300)));
        return $certificates;
    }

    private function decode($value)
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) throw new \InvalidArgumentException('Invalid phone verification.');
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) throw new \InvalidArgumentException('Invalid phone verification.');
        return $decoded;
    }
}
