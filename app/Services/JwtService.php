<?php

namespace App\Services;

use Exception;

class JwtService
{
    private string $secret;
    private int $expirationTime; // en secondes

    /**
     * Initialise le service JWT
     * @param string $secret Clé secrète (utilise getenv('JWT_SECRET') si vide)
     * @param int $expirationTime Durée de vie en secondes (défaut: 7 jours)
     * @throws Exception si JWT_SECRET n'est pas configurée
     */
    public function __construct(string $secret = '', int $expirationTime = 604800)
    {
        $this->secret = $secret ?: getenv('JWT_SECRET');

        if (!$this->secret) {
            throw new Exception(
                'SÉCURITÉ: JWT_SECRET environment variable must be set. ' .
                'Generate with: openssl rand -base64 32'
            );
        }

        $this->expirationTime = $expirationTime;
    }

    /**
     * Génère un token JWT
     * @param array $payload Données à encoder (ex: ['user_id' => 1, 'pseudo' => 'john'])
     * @return string Le token JWT
     */
    public function generate(array $payload): string
    {
        // Ajouter timestamps
        $payload['iat'] = time();  // Issued at
        $payload['exp'] = time() + $this->expirationTime;  // Expiration

        // Header
        $header = $this->encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]);

        // Payload
        $encodedPayload = $this->encode($payload);

        // Signature
        $signature = $this->sign("{$header}.{$encodedPayload}");

        return "{$header}.{$encodedPayload}.{$signature}";
    }

    /**
     * Valide et décode un token JWT
     * @param string $token Le token à valider
     * @return array Le payload décodé
     * @throws Exception si le token est invalide ou expiré
     */
    public function validate(string $token): array
    {
        // Vérifier le format: header.payload.signature
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Format de token invalide');
        }

        [$header, $payload, $signature] = $parts;

        // Vérifier la signature
        $expectedSignature = $this->sign("{$header}.{$payload}");
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Signature du token invalide');
        }

        // Décoder le payload
        $decodedPayload = $this->decode($payload);

        // Vérifier l'expiration
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            throw new Exception('Token expiré');
        }

        return $decodedPayload;
    }

    /**
     * Encode un array en base64url
     */
    private function encode(array $data): string
    {
        $json = json_encode($data);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Décode une chaîne base64url en array
     */
    private function decode(string $data): array
    {
        // Ajouter les caractères padding manquants
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }

        $json = base64_decode(strtr($data, '-_', '+/'));
        return json_decode($json, true) ?? [];
    }

    /**
     * Signe un message avec HMAC-SHA256
     */
    private function sign(string $message): string
    {
        $signature = hash_hmac('sha256', $message, $this->secret, true);
        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
