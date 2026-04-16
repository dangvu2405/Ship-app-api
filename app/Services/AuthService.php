<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * @return array{user: User, token: string, refreshToken: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials');
        }

        $tokenPair = $this->issueTokenPair($user);
        $user->update(['last_login_at' => now()]);
        LoginLog::query()->create([
            'user_id' => $user->id,
            'ip' => request()->ip(),
            'device' => (string) request()->userAgent(),
            'login_at' => now(),
            'logout_at' => null,
            'status' => 'active',
            'action' => 'login',
            'performed_by' => $user->username,
        ]);
        $user->load(['driver', 'roles.permissions']);

        return [
            'user' => $user,
            'token' => $tokenPair['token'],
            'refreshToken' => $tokenPair['refreshToken'],
        ];
    }

    /**
     * @return array{user: User, token: string, refreshToken: string}
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

        $tokenPair = $this->issueTokenPair($user);
        LoginLog::query()->create([
            'user_id' => $user->id,
            'ip' => request()->ip(),
            'device' => (string) request()->userAgent(),
            'login_at' => now(),
            'logout_at' => null,
            'status' => 'active',
            'action' => 'social_login',
            'performed_by' => $user->username,
        ]);
        $user->load(['driver', 'roles.permissions']);

        return [
            'user' => $user,
            'token' => $tokenPair['token'],
            'refreshToken' => $tokenPair['refreshToken'],
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

    /**
     * @return array{token: string, refreshToken: string}
     */
    public function refresh(User $user): array
    {
        $this->revokeCurrentToken($user);

        return $this->issueTokenPair($user);
    }

    /**
     * @return array{token: string, refreshToken: string}
     */
    public function refreshWithRefreshToken(string $refreshToken): array
    {
        $storedToken = RefreshToken::query()
            ->where('token', $refreshToken)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($storedToken === null) {
            throw new AuthenticationException('Invalid or expired refresh token');
        }

        $user = User::query()
            ->where('id', $storedToken->user_id)
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            throw new AuthenticationException('User not found or inactive');
        }

        return DB::transaction(function () use ($storedToken, $user): array {
            $storedToken->update(['is_revoked' => true]);

            if ($storedToken->access_token_id !== null) {
                PersonalAccessToken::query()->where('id', $storedToken->access_token_id)->delete();
            }

            return $this->issueTokenPair($user);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionsSummary(User $user): array
    {
        $activeSessions = LoginLog::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        $failedLogins = LoginLog::query()
            ->where('user_id', $user->id)
            ->where('action', 'failed_login')
            ->count();

        return [
            'activeSessions' => $activeSessions,
            'failedLogins' => $failedLogins,
        ];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function sessions(User $user, int $perPage = 10): array
    {
        $perPage = max(1, min($perPage, 100));

        $paginator = LoginLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('login_at')
            ->paginate($perPage);

        $logs = collect($paginator->items())
            ->map(static fn (LoginLog $log): array => [
                'id' => (string) $log->id,
                'device' => (string) ($log->device ?? 'Unknown device'),
                'ip' => $log->ip,
                'lastLogin' => optional($log->login_at)->toIso8601String() ?? '',
                'logoutTime' => optional($log->logout_at)->toIso8601String(),
                'status' => $log->status ?? 'active',
            ])
            ->values()
            ->toArray();

        return [
            'logs' => $logs,
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function logs(User $user, ?string $date = null): array
    {
        $isAdmin = $user->hasRole('admin');
        $loginLogsQuery = LoginLog::query()->with('user');
        $auditLogsQuery = AuditLog::query()->with('user');

        if (! $isAdmin) {
            $loginLogsQuery->where('user_id', $user->id);
            $auditLogsQuery->where('user_id', $user->id);
        }

        if ($date !== null && $date !== '') {
            $loginLogsQuery->whereDate('login_at', $date);
            $auditLogsQuery->whereDate('created_at', $date);
        }

        $loginRows = $loginLogsQuery
            ->orderByDesc('login_at')
            ->limit(200)
            ->get()
            ->map(static fn (LoginLog $log): array => [
                'id' => 'login-'.(string) $log->id,
                'username' => (string) ($log->user?->username ?? ''),
                'loginTime' => optional($log->login_at)->toIso8601String(),
                'logoutTime' => optional($log->logout_at)->toIso8601String(),
                'action' => (string) ($log->action ?? 'login'),
                'performedBy' => (string) ($log->performed_by ?? ($log->user?->username ?? 'system')),
            ]);

        $auditRows = $auditLogsQuery
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(static fn (AuditLog $log): array => [
                'id' => 'audit-'.(string) $log->id,
                'username' => (string) ($log->user?->username ?? ''),
                'loginTime' => optional($log->created_at)->toIso8601String(),
                'logoutTime' => null,
                'action' => (string) $log->action,
                'performedBy' => (string) ($log->user?->username ?? 'system'),
            ]);

        return $loginRows
            ->concat($auditRows)
            ->sortByDesc('loginTime')
            ->values()
            ->toArray();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function actions(User $user, array $filters = []): array
    {
        $query = AuditLog::query()
            ->with('user')
            ->orderByDesc('created_at');

        if (! $user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        }

        $username = isset($filters['username']) && is_string($filters['username'])
            ? trim($filters['username'])
            : '';
        if ($username !== '') {
            $query->whereHas('user', static function ($userQuery) use ($username): void {
                $userQuery->where('username', 'like', '%'.$username.'%');
            });
        }

        $action = isset($filters['action']) && is_string($filters['action'])
            ? trim($filters['action'])
            : '';
        if ($action !== '') {
            $query->where('action', 'like', '%'.$action.'%');
        }

        $from = isset($filters['from']) && is_string($filters['from']) ? $filters['from'] : null;
        if ($from !== null && $from !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
                $query->whereDate('created_at', '>=', $from);
            } else {
                $query->where('created_at', '>=', $from);
            }
        }

        $to = isset($filters['to']) && is_string($filters['to']) ? $filters['to'] : null;
        if ($to !== null && $to !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
                $query->whereDate('created_at', '<=', $to);
            } else {
                $query->where('created_at', '<=', $to);
            }
        }

        $statusCode = $filters['status_code'] ?? null;
        if ($statusCode !== null) {
            $query->where('new_data->status_code', (int) $statusCode);
        }

        return $query
            ->limit(500)
            ->get()
            ->map(static function (AuditLog $log): array {
                return [
                    'id' => 'audit-'.(string) $log->id,
                    'username' => (string) ($log->user?->username ?? ''),
                    'action' => (string) $log->action,
                    'resource' => (string) ($log->resource ?? ''),
                    'tableName' => (string) ($log->table_name ?? ''),
                    'recordId' => $log->record_id !== null ? (int) $log->record_id : null,
                    'entityType' => (string) data_get(
                        $log->metadata,
                        'entity_type',
                        $log->table_name !== null ? \Illuminate\Support\Str::singular(str_replace('-', '_', (string) $log->table_name)) : ''
                    ),
                    'statusCode' => (int) data_get($log->new_data, 'status_code', 0),
                    'performedBy' => (string) ($log->user?->username ?? 'system'),
                    'createdAt' => optional($log->created_at)->toIso8601String(),
                ];
            })
            ->values()
            ->toArray();
    }

    public function revokeSession(User $user, string $sessionId): void
    {
        $log = LoginLog::query()
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $log->update([
            'status' => 'logged_out',
            'logout_at' => now(),
            'action' => 'revoke_session',
            'performed_by' => $user->username,
        ]);
    }

    public function lockAccountForSession(User $user, string $sessionId): void
    {
        $log = LoginLog::query()
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        DB::transaction(function () use ($user, $log): void {
            $user->update(['status' => 'inactive']);
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->delete();
            RefreshToken::query()
                ->where('user_id', $user->id)
                ->where('is_revoked', false)
                ->update(['is_revoked' => true]);

            LoginLog::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'expired',
                    'logout_at' => now(),
                    'action' => 'lock_account',
                    'performed_by' => $user->username,
                ]);

            $log->update([
                'status' => 'expired',
                'logout_at' => now(),
                'action' => 'lock_account',
                'performed_by' => $user->username,
            ]);
        });
    }

    public function sendPasswordResetLink(string $email): void
    {
        $status = Password::broker()->sendResetLink(['email' => $email]);

        if (! in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            throw new AuthenticationException('Unable to send password reset link');
        }
    }

    /**
     * @param array{email: string, token: string, password: string, password_confirmation: string} $payload
     */
    public function resetPassword(array $payload): void
    {
        $status = Password::broker()->reset(
            $payload,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new AuthenticationException(__($status));
        }
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
        $revoked = 0;

        if ($token instanceof PersonalAccessToken) {
            $revoked = RefreshToken::query()
                ->where('access_token_id', $token->id)
                ->where('is_revoked', false)
                ->update(['is_revoked' => true]);
            $token->delete();
        }

        if ($revoked === 0) {
            RefreshToken::query()
                ->where('user_id', $user->id)
                ->where('is_revoked', false)
                ->orderByDesc('id')
                ->limit(1)
                ->update(['is_revoked' => true]);
        }
    }

    /**
     * @return array{token: string, refreshToken: string}
     */
    private function issueTokenPair(User $user): array
    {
        $accessToken = $user->createToken('auth-token');
        $refreshToken = RefreshToken::generateToken();

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'token' => $refreshToken,
            'access_token_id' => $accessToken->accessToken->id,
            'expires_at' => now()->addDays(30),
            'is_revoked' => false,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        return [
            'token' => $accessToken->plainTextToken,
            'refreshToken' => $refreshToken,
        ];
    }
}
