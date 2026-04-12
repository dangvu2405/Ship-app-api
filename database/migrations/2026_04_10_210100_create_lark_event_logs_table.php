<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lark_event_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->nullable()->index();
            $table->string('request_id')->nullable()->index();
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

    public function down(): void
    {
        Schema::dropIfExists('lark_event_logs');
    }
};
