<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove Lark/Feishu integration artifacts from the database.
     *
     * Replaces deleted migrations that added lark_user_id and lark_event_logs.
     */
    public function up(): void
    {
        Schema::dropIfExists('lark_event_logs');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lark_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('lark_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'lark_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('lark_user_id')->nullable()->unique()->after('email');
            });
        }

        if (! Schema::hasTable('lark_event_logs')) {
            Schema::create('lark_event_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('event_id')->nullable()->index();
                $table->string('request_id')->nullable();
                $table->string('event_type')->nullable()->index();
                $table->boolean('signature_valid')->default(false);
                $table->boolean('replay_blocked')->default(false);
                $table->string('status')->default('received')->index();
                $table->json('headers')->nullable();
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }
};
