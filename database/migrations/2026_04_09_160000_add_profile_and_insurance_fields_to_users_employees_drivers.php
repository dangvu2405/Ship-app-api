<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_url')->nullable()->after('email');
            $table->string('emergency_contact_name')->nullable()->after('last_login_at');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
            $table->string('residential_address')->nullable()->after('emergency_contact_phone');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('avatar_url')->nullable()->after('address');
            $table->string('national_id_no', 30)->nullable()->after('avatar_url');
            $table->date('national_id_issue_date')->nullable()->after('national_id_no');
            $table->string('national_id_issue_place')->nullable()->after('national_id_issue_date');
            $table->string('social_insurance_no', 30)->nullable()->after('national_id_issue_place');
            $table->string('health_insurance_no', 30)->nullable()->after('social_insurance_no');
            $table->date('insurance_registered_at')->nullable()->after('health_insurance_no');
            $table->string('bank_name')->nullable()->after('resign_date');
            $table->string('bank_account_no', 50)->nullable()->after('bank_name');
            $table->string('bank_account_name')->nullable()->after('bank_account_no');

            $table->index('social_insurance_no');
            $table->index('health_insurance_no');
            $table->index('national_id_no');
        });

        Schema::table('drivers', function (Blueprint $table): void {
            $table->string('license_image_url')->nullable()->after('license_no');
            $table->string('identity_image_url')->nullable()->after('license_image_url');
            $table->string('driver_insurance_no', 30)->nullable()->after('identity_image_url');
            $table->date('driver_insurance_expired_date')->nullable()->after('driver_insurance_no');
            $table->string('health_certificate_no', 30)->nullable()->after('driver_insurance_expired_date');
            $table->date('health_certificate_expired_date')->nullable()->after('health_certificate_no');

            $table->index('driver_insurance_no');
            $table->index('health_certificate_no');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropIndex(['driver_insurance_no']);
            $table->dropIndex(['health_certificate_no']);
            $table->dropColumn([
                'license_image_url',
                'identity_image_url',
                'driver_insurance_no',
                'driver_insurance_expired_date',
                'health_certificate_no',
                'health_certificate_expired_date',
            ]);
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['social_insurance_no']);
            $table->dropIndex(['health_insurance_no']);
            $table->dropIndex(['national_id_no']);
            $table->dropColumn([
                'avatar_url',
                'national_id_no',
                'national_id_issue_date',
                'national_id_issue_place',
                'social_insurance_no',
                'health_insurance_no',
                'insurance_registered_at',
                'bank_name',
                'bank_account_no',
                'bank_account_name',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'avatar_url',
                'emergency_contact_name',
                'emergency_contact_phone',
                'residential_address',
            ]);
        });
    }
};
