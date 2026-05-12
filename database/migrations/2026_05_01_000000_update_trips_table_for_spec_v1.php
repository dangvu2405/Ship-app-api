<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Per spec.md [O1], the trips table needs significant alteration.
        Schema::table('trips', function (Blueprint $table) {
            // Add new columns
            $table->foreignId('company_id')->constrained('companies')->after('id');
            $table->string('contact_name', 200)->nullable()->after('customer_id');
            $table->string('contact_phone', 20)->nullable()->after('contact_name');
            $table->foreignId('cargo_type_id')->nullable()->constrained('cargo_types')->after('contact_phone');
            $table->text('cargo_description')->nullable()->after('cargo_type_id');
            $table->decimal('cargo_quantity', 10, 2)->nullable()->after('cargo_description');
            $table->string('cargo_unit', 50)->nullable()->after('cargo_quantity');
            $table->decimal('cargo_weight_ton', 8, 2)->nullable()->after('cargo_unit');
            $table->text('cargo_notes')->nullable()->after('cargo_weight_ton');
            $table->foreignId('dispatcher_id')->nullable()->constrained('users')->after('vehicle_id');
            $table->dateTime('assigned_at')->nullable()->after('dispatcher_id');
            $table->foreignId('route_template_id')->nullable()->constrained('route_templates')->after('assigned_at');
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->after('route_template_id');
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->after('origin_location_id');
            $table->date('received_date')->nullable()->after('end_point');
            $table->date('scheduled_date')->nullable()->after('received_date');
            $table->time('scheduled_time_from')->nullable()->after('scheduled_date');
            $table->time('scheduled_time_to')->nullable()->after('scheduled_time_from');
            $table->decimal('actual_distance_km', 8, 2)->nullable()->after('distance_km');
            $table->dateTime('actual_pickup_at')->nullable()->after('end_time');
            $table->dateTime('actual_delivered_at')->nullable()->after('actual_pickup_at');
            $table->decimal('base_price', 15, 2)->default(0)->after('price');
            $table->decimal('surcharge_amount', 15, 2)->default(0)->after('base_price');
            $table->decimal('total_revenue', 15, 2)->nullable()->after('surcharge_amount');
            $table->enum('payment_method', ['bank_transfer', 'cash', 'credit'])->nullable()->after('total_revenue');
            $table->enum('payment_status', ['unpaid', 'invoiced', 'paid'])->default('unpaid')->after('payment_method');
            $table->text('cancellation_reason')->nullable()->after('status');
            $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->after('cancelled_at');
            $table->text('internal_notes')->nullable()->after('cancelled_by');

            // Modify existing columns
            $table->foreignId('driver_id')->nullable()->change();
            $table->foreignId('vehicle_id')->nullable()->change();
            $table->string('status', 50)->default('new')->comment("new/assigned/in_transit/delivered/completed/cancelled")->change();
        });

        // Per spec.md [O2], create trip_stops table
        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->enum('stop_type', ['pickup', 'delivery']);
            $table->unsignedSmallInteger('sequence');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->text('address');
            $table->string('contact_name', 200)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->dateTime('scheduled_time')->nullable();
            $table->dateTime('actual_time')->nullable();
            $table->enum('status', ['pending', 'arrived', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'sequence']);
        });

        // Per spec.md [O3], create trip_surcharges table
        Schema::create('trip_surcharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->string('name', 200);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_surcharges');
        Schema::dropIfExists('trip_stops');

        Schema::table('trips', function (Blueprint $table) {
            // Rollback implementation...
        });
    }
};
