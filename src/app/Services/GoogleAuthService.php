<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de autenticação Google (Story 5.1).
 * Valida token (ID ou Access) e retorna dados do usuário para criação/atualização.
 */
class GoogleAuthService
{
    /**
     * Verifica o token do Google (ID Token ou Access Token) e retorna dados do usuário ou null.
     *
     * @return array{name: string, email: string, sub: string, picture?: string}|null
     */
    public function verifyToken(string $token): ?array
    {
        try {
            Log::info('Verificando token do Google', ['token_length' => strlen($token)]);

            if (str_starts_with($token, 'ya29.')) {
                return $this->verifyAccessToken($token);
            }

            return $this->verifyIdToken($token);
        } catch (\Exception $e) {
            Log::error('Exceção ao verificar token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Busca ou cria usuário a partir dos dados do Google. Retorna o User.
     */
    public function findOrCreateUser(array $googleUser): User
    {
        $user = User::where('email', $googleUser['email'])->first();

        if (!$user) {
            return User::create([
                'name' => $googleUser['name'],
                'email' => $googleUser['email'],
                'email_verified_at' => now(),
                'google_id' => $googleUser['sub'],
                'avatar' => $googleUser['picture'] ?? null,
                'password' => Hash::make(uniqid()),
            ]);
        }

        $user->update([
            'google_id' => $googleUser['sub'],
            'avatar' => $googleUser['picture'] ?? $user->avatar,
        ]);

        return $user;
    }

    private function verifyAccessToken(string $token): ?array
    {
        Log::info('Token detectado como Access Token');

        $url = 'https://oauth2.googleapis.com/tokeninfo?access_token=' . $token;
        $response = @file_get_contents($url);

        if ($response === false) {
            Log::error('Falha ao validar Access Token');
            return null;
        }

        $tokenData = json_decode($response, true);
        if (isset($tokenData['error'])) {
            Log::error('Access Token inválido', ['error' => $tokenData['error']]);
            return null;
        }

        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token;
        $userResponse = @file_get_contents($userInfoUrl);

        if ($userResponse === false) {
            Log::error('Falha ao buscar dados do usuário');
            return null;
        }

        $userData = json_decode($userResponse, true);
        if (isset($userData['error'])) {
            Log::error('Erro ao buscar dados do usuário', ['error' => $userData['error']]);
            return null;
        }

        return array_merge($tokenData, $userData);
    }

    private function verifyIdToken(string $token): ?array
    {
        Log::info('Token detectado como ID Token');

        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $token;
        $response = @file_get_contents($url);

        if ($response === false) {
            Log::error('Falha ao validar ID Token');
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            Log::error('ID Token inválido', ['error' => $data['error']]);
            return null;
        }

        return $data;
    }
}
