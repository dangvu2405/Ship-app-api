<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (! Schema::hasTable('offices')) {
            Schema::create('offices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->text('address')->nullable();
                $table->unsignedBigInteger('manager_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('office_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->decimal('base_salary', 15, 2)->default(0);
                $table->integer('level')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->string('email')->unique()->nullable();
                $table->string('phone', 20)->nullable();
                $table->date('dob')->nullable();
                $table->enum('gender', ['male', 'female', 'other'])->nullable();
                $table->text('address')->nullable();
                $table->foreignId('office_id')->constrained()->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('position_id')->constrained()->restrictOnDelete();
                $table->enum('type', ['office', 'driver'])->default('office');
                $table->enum('status', ['active', 'inactive', 'resigned'])->default('active');
                $table->date('join_date')->nullable();
                $table->date('resign_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('login_logs')) {
            Schema::create('login_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('ip', 45)->nullable();
                $table->string('device', 255)->nullable();
                $table->timestamp('login_at');
                $table->timestamp('logout_at')->nullable();
                $table->enum('status', ['active', 'logged_out', 'expired'])->default('active');
                $table->string('action', 50)->default('login');
                $table->string('performed_by', 255)->nullable();
                $table->timestamps();
                $table->index('user_id');
                $table->index('login_at');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('offices');
    }
};
