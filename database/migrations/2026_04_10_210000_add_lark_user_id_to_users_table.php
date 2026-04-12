<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('lark_user_id')->nullable()->unique()->after('email');
            $table->index('lark_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['lark_user_id']);
            $table->dropUnique(['lark_user_id']);
            $table->dropColumn('lark_user_id');
        });
    }
};
