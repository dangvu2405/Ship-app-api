<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials');
        }

        $token = $user->createToken('auth-token')->plainTextToken;
        $user->update(['last_login_at' => now()]);
        $user->load(['driver', 'roles.permissions']);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @return array{user: User, token: string}
     */
    public function socialLogin(string $provider, ?string $accessToken, ?string $idToken): array
    {
        $profile = match ($provider) {
            'google' => $this->resolveGoogleProfile($accessToken, $idToken),
            'facebook' => $this->resolveFacebookProfile($accessToken),
            'apple' => $this->resolveAppleProfile($idToken),
            default => throw new AuthenticationException('Unsupported social provider'),
        };

        if ($profile['provider_id'] === '') {
            throw new AuthenticationException('Invalid social profile');
        }

        /** @var User|null $user */
        $user = User::where('social_provider', $provider)
            ->where('social_provider_id', $profile['provider_id'])
            ->first();

        if (! $user && isset($profile['email'])) {
            $emailMatchedUser = User::where('email', $profile['email'])->first();

            if ($emailMatchedUser && ($profile['email_verified'] ?? false) !== true) {
                throw new AuthenticationException('Provider email is not verified');
            }

            if ($emailMatchedUser && ($profile['email_verified'] ?? false) === true) {
                $user = $emailMatchedUser;
            }
        }

        if ($user && $user->status !== 'active') {
            throw new AuthenticationException('Your account is inactive');
        }

        if (! $user) {
            $user = DB::transaction(fn (): User => $this->createUserFromSocialProfile($provider, $profile));
        } else {
            $user->fill([
                'social_provider' => $provider,
                'social_provider_id' => $profile['provider_id'],
            ]);

            if (! empty($profile['avatar_url']) && empty($user->avatar_url)) {
                $user->avatar_url = $profile['avatar_url'];
            }

            $user->last_login_at = now();
            $user->save();
        }

        $token = $user->createToken('auth-token')->plainTextToken;
        $user->load(['driver', 'roles.permissions']);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function register(array $payload): User
    {
        $user = User::create([
            'username' => $payload['username'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
            'status' => 'active',
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->roles()->syncWithoutDetaching([$adminRole->id]);
        $user->load(['driver', 'roles.permissions']);

        return $user;
    }

    public function logout(User $user): void
    {
        $this->revokeCurrentToken($user);
    }

    public function refresh(User $user): string
    {
        $this->revokeCurrentToken($user);

        return $user->createToken('auth-token')->plainTextToken;
    }

    /**
     * @return array{provider_id: string, email?: string, username?: string, avatar_url?: string, email_verified?: bool}
     */
    private function resolveGoogleProfile(?string $accessToken, ?string $idToken): array
    {
        if ($idToken) {
            $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (! $response->successful()) {
                throw new AuthenticationException('Invalid Google token');
            }

            $data = $response->json();
            $issuer = (string) ($data['iss'] ?? '');
            if (! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
                throw new AuthenticationException('Invalid Google token issuer');
            }

            $audience = (string) ($data['aud'] ?? '');
            $expectedAudience = (string) config('services.google.client_id', '');
            if ($expectedAudience !== '' && $audience !== $expectedAudience) {
                throw new AuthenticationException('Google token audience mismatch');
            }

            return [
                'provider_id' => (string) ($data['sub'] ?? ''),
                'email' => isset($data['email']) ? (string) $data['email'] : null,
                'username' => isset($data['name']) ? (string) $data['name'] : null,
                'avatar_url' => isset($data['picture']) ? (string) $data['picture'] : null,
                'email_verified' => filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        if (! $accessToken) {
            throw new AuthenticationException('Google access token is required');
        }

        $response = Http::timeout(10)
            ->withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if (! $response->successful()) {
            throw new AuthenticationException('Invalid Google access token');
        }

        $data = $response->json();

        return [
            'provider_id' => (string) ($data['sub'] ?? ''),
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'username' => isset($data['name']) ? (string) $data['name'] : null,
            'avatar_url' => isset($data['picture']) ? (string) $data['picture'] : null,
            'email_verified' => filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @return array{provider_id: string, email?: string, username?: string, avatar_url?: string, email_verified?: bool}
     */
    private function resolveFacebookProfile(?string $accessToken): array
    {
        if (! $accessToken) {
            throw new AuthenticationException('Facebook access token is required');
        }

        $response = Http::timeout(10)->get('https://graph.facebook.com/me', [
            'fields' => 'id,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            throw new AuthenticationException('Invalid Facebook access token');
        }

        $data = $response->json();

        return [
            'provider_id' => (string) ($data['id'] ?? ''),
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'username' => isset($data['name']) ? (string) $data['name'] : null,
            'avatar_url' => (string) data_get($data, 'picture.data.url', ''),
            'email_verified' => filter_var($data['verified'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @return array{provider_id: string, email?: string, username?: string, avatar_url?: string, email_verified?: bool}
     */
    private function resolveAppleProfile(?string $idToken): array
    {
        if (! $idToken) {
            throw new AuthenticationException('Apple id token is required');
        }

        $segments = explode('.', $idToken);
        if (count($segments) < 3) {
            throw new AuthenticationException('Invalid Apple id token');
        }

        $header = json_decode($this->decodeBase64Url($segments[0]), true);
        if (! is_array($header)) {
            throw new AuthenticationException('Invalid Apple token header');
        }

        $kid = (string) ($header['kid'] ?? '');
        $alg = (string) ($header['alg'] ?? '');
        if ($kid === '' || $alg !== 'RS256') {
            throw new AuthenticationException('Invalid Apple token header values');
        }

        $signingInput = $segments[0].'.'.$segments[1];
        $signature = $this->decodeBase64Url($segments[2] ?? '');
        if (! is_string($signature) || $signature === '') {
            throw new AuthenticationException('Invalid Apple token signature');
        }

        if (! $this->verifyAppleSignature($signingInput, $signature, $kid)) {
            throw new AuthenticationException('Invalid Apple token signature');
        }

        $payload = json_decode($this->decodeBase64Url($segments[1]), true);
        if (! is_array($payload)) {
            throw new AuthenticationException('Invalid Apple id token payload');
        }

        $issuer = (string) ($payload['iss'] ?? '');
        if (! in_array($issuer, ['https://appleid.apple.com', 'https://appleid.apple.com/'], true)) {
            throw new AuthenticationException('Invalid Apple token issuer');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $exp < now()->timestamp) {
            throw new AuthenticationException('Apple token is expired');
        }

        $audience = (string) ($payload['aud'] ?? '');
        $authorizedParty = (string) ($payload['azp'] ?? '');
        $expectedAudience = (string) config('services.apple.client_id', '');
        if ($expectedAudience !== '' && $audience !== $expectedAudience && $authorizedParty !== $expectedAudience) {
            throw new AuthenticationException('Apple token audience mismatch');
        }

        return [
            'provider_id' => (string) ($payload['sub'] ?? ''),
            'email' => isset($payload['email']) ? (string) $payload['email'] : null,
            'username' => isset($payload['email']) ? (string) explode('@', (string) $payload['email'])[0] : null,
            'avatar_url' => null,
            'email_verified' => filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @param array{provider_id: string, email?: string, username?: string, avatar_url?: string, email_verified?: bool} $profile
     */
    private function createUserFromSocialProfile(string $provider, array $profile): User
    {
        $email = $profile['email'] ?? null;
        if (! $email) {
            throw new AuthenticationException('Social account does not provide email');
        }

        $baseUsername = $profile['username'] ?? explode('@', $email)[0];
        $username = $this->generateUniqueUsername($baseUsername);

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'status' => 'active',
            'avatar_url' => $profile['avatar_url'] ?? null,
            'social_provider' => $provider,
            'social_provider_id' => $profile['provider_id'],
            'last_login_at' => now(),
        ]);

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $user->roles()->syncWithoutDetaching([$staffRole->id]);

        return $user;
    }

    private function generateUniqueUsername(string $candidate): string
    {
        $sanitized = Str::of($candidate)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '_')
            ->trim('_')
            ->value();

        $base = $sanitized !== '' ? $sanitized : 'user';
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$counter;
            $counter++;
        }

        return $username;
    }

    private function decodeBase64Url(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    private function verifyAppleSignature(string $signingInput, string $signature, string $kid): bool
    {
        $keys = Cache::remember('apple:oauth:public_keys', now()->addHours(6), function (): array {
            $response = Http::timeout(10)->get('https://appleid.apple.com/auth/keys');
            if (! $response->successful()) {
                throw new AuthenticationException('Unable to fetch Apple public keys');
            }

            $payload = $response->json();

            return is_array($payload['keys'] ?? null) ? $payload['keys'] : [];
        });

        $matchingKey = collect($keys)->first(
            fn (mixed $key): bool => is_array($key) && ($key['kid'] ?? '') === $kid
        );

        if (! is_array($matchingKey)) {
            return false;
        }

        $x5c = $matchingKey['x5c'][0] ?? null;
        if (! is_string($x5c) || $x5c === '') {
            return false;
        }

        $certificatePem = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split($x5c, 64, "\n")
            ."-----END CERTIFICATE-----\n";

        $publicKey = openssl_pkey_get_public($certificatePem);
        if ($publicKey === false) {
            return false;
        }

        $verified = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        openssl_free_key($publicKey);

        return $verified;
    }

    private function revokeCurrentToken(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
