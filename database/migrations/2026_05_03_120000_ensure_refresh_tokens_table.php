<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `refresh_tokens` was incorrectly dropped by 2026_05_01_090000 (auth/session, not org RBAC).
 * Recreate when missing so Sanctum login + AuthService::issueTokenPair work.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refresh_tokens')) {
            return;
        }

        Schema::create('refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->foreignId('access_token_id')->nullable()->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->boolean('is_revoked')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_revoked']);
            $table->index('expires_at');
            $table->index('token');
        });
    }

    public function down(): void
    {
        // Intentionally empty — dropping again would break login.
    }
};
