<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Junction table: which companies a user can access.
 *
 * Flow:
 *   0 rows → tenants[] = []  → frontend goes to /dashboard (legacy single-tenant)
 *   1 row  → tenants[] = [X] → frontend auto-selects X, sends X-Tenant-ID on every request
 *   N rows → tenants[] = [...] → frontend shows /select-tenant picker
 *
 * Admin users can still access any company via the X-Tenant-ID header
 * without needing a row here (EnsureTenantContext grants admins full override).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->boolean('is_default')->default(false)->comment('Pre-select this company if multiple are assigned');
            $table->timestamps();

            $table->unique(['user_id', 'company_id']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_companies');
    }
};
