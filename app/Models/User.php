<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string|null $avatar_url
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'full_name',
        'phone',
        'social_provider',
        'social_provider_id',
        'avatar_url',
        'role',
        'must_change_password',
        'password',
        'status',
        'emergency_contact_phone',
        'residential_address',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Accessors

    /**
     * Returns the stored avatar URL, or a DiceBear generated avatar when none is set.
     *
     * DiceBear is a free, open-source API — no key required.
     * Style "initials" renders the user's initials (e.g. "JD" for "john_doe").
     * The seed is the username so the same user always gets the same avatar.
     *
     * Frontend can override by uploading a real photo (stored in avatar_url column).
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): string {
                if ($value !== null && $value !== '') {
                    return $value;
                }

                $seed = urlencode($this->username ?? $this->email ?? 'user');

                return "https://api.dicebear.com/7.x/initials/svg?seed={$seed}&backgroundColor=3b82f6,8b5cf6,ec4899,f97316,10b981&backgroundType=gradientLinear&fontSize=40&bold=true";
            },
            set: fn ($value) => $value,
        );
    }

    // Relationships

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function exportLogs(): HasMany
    {
        return $this->hasMany(ExportLog::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Companies explicitly assigned to this user (multi-tenant support).
     * Returns an empty collection for legacy single-tenant users.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies')
            ->withPivot('is_default')
            ->withTimestamps()
            ->orderByPivot('is_default', 'desc')
            ->orderBy('name');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Return all companies this user can access, formatted as tenant objects
     * for the frontend tenant-selection flow.
     *
     * Returns [] for legacy users (no user_companies rows) so the frontend
     * skips the selector and goes straight to /dashboard.
     *
     * @return array<int, array{id: int, name: string, code: string, logo_url: string|null, status: string}>
     */
    public function resolveTenants(): array
    {
        $companies = $this->hasRole('admin') || $this->hasRole('super_admin')
            ? Company::query()->where('status', 'active')->orderBy('name')->get()
            : $this->resolveAssignedCompanies();

        if ($companies->isEmpty()) {
            return [];
        }

        return $companies->map(fn (Company $c): array => [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'logo_url' => $c->logo_url ?? null,
            'status' => $c->status,
        ])->values()->all();
    }

    // Helper methods

    /**
     * Check if the user has a role globally or within a specific company.
     * Pass $companyId to restrict the check to that tenant context.
     */
    public function hasRole(string $roleName, ?int $companyId = null): bool
    {
        // Check legacy role column
        if (Schema::hasColumn('users', 'role') && is_string($this->role) && $this->role !== '') {
            if ($this->role === $roleName) {
                return true;
            }
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            return false;
        }

        // Check many-to-many roles table
        return $this->roles()
            ->where('name', $roleName)
            ->when($companyId !== null && Schema::hasColumn('roles', 'company_id'), fn ($query) => $query->where('roles.company_id', $companyId))
            ->exists();
    }

    /**
     * Check if the user has a permission globally or within a specific company.
     * Admin with a global role always passes regardless of company.
     */
    public function hasPermission(string $permissionCode, ?int $companyId = null): bool
    {
        if ($this->hasRole('admin', $companyId) || $this->hasRole('super_admin', $companyId)) {
            return true;
        }

        if (! Schema::hasTable('user_permissions')) {
            return false;
        }

        [$module, $action] = str_contains($permissionCode, ':')
            ? explode(':', $permissionCode, 2)
            : [$permissionCode, null];

        $query = UserPermission::query()
            ->where('user_id', $this->id)
            ->where('module', $module);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($action !== null) {
            $column = "can_{$action}";
            if (Schema::hasColumn('user_permissions', $column)) {
                return $query->where($column, true)->exists();
            }
        }

        return $query->where(function ($q): void {
            $q->where('can_view', true)
                ->orWhere('can_create', true)
                ->orWhere('can_edit', true)
                ->orWhere('can_delete', true)
                ->orWhere('can_approve', true)
                ->orWhere('can_export', true);
        })->exists();
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    private function resolveAssignedCompanies(): Collection
    {
        if (Schema::hasTable('user_permissions')) {
            $ids = UserPermission::query()
                ->where('user_id', $this->id)
                ->distinct()
                ->pluck('company_id');

            if ($ids->isNotEmpty()) {
                return Company::query()->whereIn('id', $ids)->where('status', 'active')->orderBy('name')->get();
            }
        }

        return collect();
    }

    /**
     * Send password reset email through dedicated Mailable in app/Mail.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword((string) $token));
    }
}
