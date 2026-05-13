<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Add new columns safely
            if (!Schema::hasColumn('trips', 'company_id')) {
                $table->foreignId('company_id')->constrained('companies')->after('id');
            }
            if (!Schema::hasColumn('trips', 'contact_name')) {
                $table->string('contact_name', 200)->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('trips', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable()->after('contact_name');
            }
            if (!Schema::hasColumn('trips', 'cargo_type_id')) {
                $table->foreignId('cargo_type_id')->nullable()->constrained('cargo_types')->after('contact_phone');
            }
            if (!Schema::hasColumn('trips', 'cargo_description')) {
                $table->text('cargo_description')->nullable()->after('cargo_type_id');
            }
            if (!Schema::hasColumn('trips', 'cargo_quantity')) {
                $table->decimal('cargo_quantity', 10, 2)->nullable()->after('cargo_description');
            }
            if (!Schema::hasColumn('trips', 'cargo_unit')) {
                $table->string('cargo_unit', 50)->nullable()->after('cargo_quantity');
            }
            if (!Schema::hasColumn('trips', 'cargo_weight_ton')) {
                $table->decimal('cargo_weight_ton', 8, 2)->nullable()->after('cargo_unit');
            }
            if (!Schema::hasColumn('trips', 'cargo_notes')) {
                $table->text('cargo_notes')->nullable()->after('cargo_weight_ton');
            }
            if (!Schema::hasColumn('trips', 'dispatcher_id')) {
                $table->foreignId('dispatcher_id')->nullable()->constrained('users')->after('vehicle_id');
            }
            if (!Schema::hasColumn('trips', 'assigned_at')) {
                $table->dateTime('assigned_at')->nullable()->after('dispatcher_id');
            }
            if (!Schema::hasColumn('trips', 'route_template_id')) {
                $table->foreignId('route_template_id')->nullable()->constrained('route_templates')->after('assigned_at');
            }
            if (!Schema::hasColumn('trips', 'origin_location_id')) {
                $table->foreignId('origin_location_id')->nullable()->constrained('locations')->after('route_template_id');
            }
            if (!Schema::hasColumn('trips', 'destination_location_id')) {
                $table->foreignId('destination_location_id')->nullable()->constrained('locations')->after('origin_location_id');
            }
            if (!Schema::hasColumn('trips', 'received_date')) {
                $table->date('received_date')->nullable()->after('end_point');
            }
            if (!Schema::hasColumn('trips', 'scheduled_date')) {
                $table->date('scheduled_date')->nullable()->after('received_date');
            }
            if (!Schema::hasColumn('trips', 'scheduled_time_from')) {
                $table->time('scheduled_time_from')->nullable()->after('scheduled_date');
            }
            if (!Schema::hasColumn('trips', 'scheduled_time_to')) {
                $table->time('scheduled_time_to')->nullable()->after('scheduled_time_from');
            }
            if (!Schema::hasColumn('trips', 'actual_distance_km')) {
                $table->decimal('actual_distance_km', 8, 2)->nullable()->after('distance_km');
            }
            if (!Schema::hasColumn('trips', 'actual_pickup_at')) {
                $table->dateTime('actual_pickup_at')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('trips', 'actual_delivered_at')) {
                $table->dateTime('actual_delivered_at')->nullable()->after('actual_pickup_at');
            }
            if (!Schema::hasColumn('trips', 'base_price')) {
                $table->decimal('base_price', 15, 2)->default(0)->after('price');
            }
            if (!Schema::hasColumn('trips', 'surcharge_amount')) {
                $table->decimal('surcharge_amount', 15, 2)->default(0)->after('base_price');
            }
            if (!Schema::hasColumn('trips', 'total_revenue')) {
                $table->decimal('total_revenue', 15, 2)->nullable()->after('surcharge_amount');
            }
            if (!Schema::hasColumn('trips', 'payment_method')) {
                $table->enum('payment_method', ['bank_transfer', 'cash', 'credit'])->nullable()->after('total_revenue');
            }
            if (!Schema::hasColumn('trips', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'invoiced', 'paid'])->default('unpaid')->after('payment_method');
            }
            if (!Schema::hasColumn('trips', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('trips', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
            }
            if (!Schema::hasColumn('trips', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->after('cancelled_at');
            }
            if (!Schema::hasColumn('trips', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('cancelled_by');
            }

            $table->foreignId('driver_id')->nullable()->change();
            $table->foreignId('vehicle_id')->nullable()->change();
            $table->string('status', 50)->default('new')->change();
        });

        // Per spec.md [O2], create trip_stops table
        if (!Schema::hasTable('trip_stops')) {
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
        }

        // Per spec.md [O3], create trip_surcharges table
        if (!Schema::hasTable('trip_surcharges')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_surcharges');
        Schema::dropIfExists('trip_stops');

        Schema::table('trips', function (Blueprint $table) {
            // Rollback implementation...
        });
    }
};
