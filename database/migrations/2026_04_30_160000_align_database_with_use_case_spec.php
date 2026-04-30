<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createCatalogTables();
        $this->createCustomerGroupTable();
        $this->createDriverTeamTable();
        $this->alterUsersTable();
        $this->createUserPermissions();
        $this->alterCustomersTable();
        $this->createPriceListTables();
        $this->alterVehiclesTable();
        $this->createVehicleDocuments();
        $this->createSpareParts();
        $this->createMaintenanceTables();
        $this->alterVehicleAssignments();
        $this->alterDriversTable();
        $this->createDriverDocuments();
        $this->alterTripsTable();
        $this->alterLeaveRequestsTable();
        $this->createOrderTables();
        $this->createAccountingTables();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliation_sessions');
        Schema::dropIfExists('cost_approval_requests');
        Schema::dropIfExists('trip_costs');
        Schema::dropIfExists('trip_documents');
        Schema::dropIfExists('trip_surcharges');
        Schema::dropIfExists('trip_stops');

        Schema::table('leave_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('leave_requests', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });

        Schema::table('trips', function (Blueprint $table): void {
            foreach ([
                'contact_name', 'contact_phone', 'cargo_type_id', 'cargo_description',
                'cargo_quantity', 'cargo_unit', 'cargo_weight_ton', 'cargo_notes',
                'dispatcher_id', 'assigned_at', 'route_template_id',
                'origin_location_id', 'destination_location_id',
                'received_date', 'scheduled_date', 'scheduled_time_from', 'scheduled_time_to',
                'actual_distance_km', 'actual_pickup_at', 'actual_delivered_at',
                'base_price', 'surcharge_amount', 'total_revenue',
                'payment_method', 'payment_status',
                'cancellation_reason', 'cancelled_at', 'cancelled_by', 'internal_notes',
            ] as $column) {
                if (Schema::hasColumn('trips', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('driver_documents');

        Schema::table('drivers', function (Blueprint $table): void {
            foreach (['team_id', 'annual_leave_days', 'license_alert_days'] as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('vehicle_assignments', function (Blueprint $table): void {
            foreach (['release_reason', 'notes', 'created_by'] as $column) {
                if (Schema::hasColumn('vehicle_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('vehicle_documents');

        Schema::table('vehicles', function (Blueprint $table): void {
            foreach ([
                'vehicle_type_id', 'max_load_ton', 'volume_m3',
                'fuel_type', 'fuel_consumption', 'current_odometer_km',
            ] as $column) {
                if (Schema::hasColumn('vehicles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');

        Schema::table('customers', function (Blueprint $table): void {
            foreach ([
                'code', 'company_name', 'full_name', 'extra_contact_name', 'extra_contact_phone',
                'group_id', 'assigned_dispatcher_id', 'credit_limit', 'payment_terms_days',
                'contract_file_url', 'contract_start_date', 'contract_end_date', 'notes', 'is_active',
            ] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('user_permissions');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['full_name', 'phone', 'role', 'must_change_password'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('driver_teams');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('order_status_configs');
        Schema::dropIfExists('route_templates');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('cost_categories');
        Schema::dropIfExists('cargo_types');
        Schema::dropIfExists('vehicle_types');
    }

    // ─── CATALOG ──────────────────────────────────────────────────────────────

    private function createCatalogTables(): void
    {
        if (! Schema::hasTable('vehicle_types')) {
            Schema::create('vehicle_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->decimal('max_load_ton', 6, 2)->nullable();
                $table->decimal('volume_m3', 8, 2)->nullable();
                $table->string('required_license_class', 10)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('cargo_types')) {
            Schema::create('cargo_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->boolean('requires_special_vehicle')->default(false);
                $table->text('special_requirements')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 200);
                $table->text('address');
                $table->string('province', 100)->nullable();
                $table->string('district', 100)->nullable();
                $table->decimal('lat', 10, 8)->nullable();
                $table->decimal('lng', 11, 8)->nullable();
                $table->string('contact_name', 200)->nullable();
                $table->string('contact_phone', 20)->nullable();
                $table->time('open_time')->nullable();
                $table->time('close_time')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('route_templates')) {
            Schema::create('route_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 200);
                $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->decimal('distance_km', 8, 2)->nullable();
                $table->decimal('estimated_hours', 4, 1)->nullable();
                $table->decimal('default_price', 15, 2)->nullable();
                $table->decimal('fuel_norm_liter', 6, 2)->nullable();
                $table->decimal('toll_norm', 12, 2)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('cost_categories')) {
            Schema::create('cost_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name', 100);
                $table->boolean('requires_receipt')->default(false);
                $table->decimal('approval_threshold', 15, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('order_status_configs')) {
            Schema::create('order_status_configs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name', 100);
                $table->string('color', 7)->nullable();
                $table->boolean('is_terminal')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'code']);
            });
        }
    }

    // ─── CUSTOMER ─────────────────────────────────────────────────────────────

    private function createCustomerGroupTable(): void
    {
        if (! Schema::hasTable('customer_groups')) {
            Schema::create('customer_groups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->foreignId('assigned_dispatcher_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'is_active']);
            });
        }
    }

    private function alterCustomersTable(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'code')) {
                $table->string('code', 50)->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('customers', 'company_name')) {
                $table->string('company_name', 300)->nullable()->after('type');
            }
            if (! Schema::hasColumn('customers', 'full_name')) {
                $table->string('full_name', 200)->nullable()->after('name');
            }
            if (! Schema::hasColumn('customers', 'extra_contact_name')) {
                $table->string('extra_contact_name', 200)->nullable()->after('full_name');
            }
            if (! Schema::hasColumn('customers', 'extra_contact_phone')) {
                $table->string('extra_contact_phone', 20)->nullable()->after('extra_contact_name');
            }
            if (! Schema::hasColumn('customers', 'group_id')) {
                $table->foreignId('group_id')->nullable()->after('address')->constrained('customer_groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'assigned_dispatcher_id')) {
                $table->foreignId('assigned_dispatcher_id')->nullable()->after('group_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'credit_limit')) {
                $table->decimal('credit_limit', 15, 2)->nullable()->after('assigned_dispatcher_id');
            }
            if (! Schema::hasColumn('customers', 'payment_terms_days')) {
                $table->unsignedInteger('payment_terms_days')->nullable()->after('credit_limit');
            }
            if (! Schema::hasColumn('customers', 'contract_file_url')) {
                $table->string('contract_file_url', 500)->nullable()->after('payment_terms_days');
            }
            if (! Schema::hasColumn('customers', 'contract_start_date')) {
                $table->date('contract_start_date')->nullable()->after('contract_file_url');
            }
            if (! Schema::hasColumn('customers', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable()->after('contract_start_date');
            }
            if (! Schema::hasColumn('customers', 'notes')) {
                $table->text('notes')->nullable()->after('contract_end_date');
            }
            if (! Schema::hasColumn('customers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }
        });
    }

    private function createPriceListTables(): void
    {
        if (! Schema::hasTable('price_lists')) {
            Schema::create('price_lists', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('name', 200);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'customer_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('price_list_items')) {
            Schema::create('price_list_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
                $table->foreignId('route_template_id')->nullable()->constrained('route_templates')->nullOnDelete();
                $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->nullOnDelete();
                $table->foreignId('cargo_type_id')->nullable()->constrained('cargo_types')->nullOnDelete();
                $table->decimal('price', 15, 2);
                $table->enum('price_unit', ['per_trip', 'per_km', 'per_ton'])->default('per_trip');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['price_list_id', 'route_template_id']);
            });
        }
    }

    // ─── PLATFORM ─────────────────────────────────────────────────────────────

    private function alterUsersTable(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name', 200)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('full_name');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['super_admin', 'admin', 'dispatcher', 'accountant', 'viewer'])
                    ->default('dispatcher')
                    ->after('avatar_url');
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('role');
            }
        });
    }

    private function createUserPermissions(): void
    {
        if (! Schema::hasTable('user_permissions')) {
            Schema::create('user_permissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('module', 50);
                $table->boolean('can_view')->default(false);
                $table->boolean('can_create')->default(false);
                $table->boolean('can_edit')->default(false);
                $table->boolean('can_delete')->default(false);
                $table->boolean('can_approve')->default(false);
                $table->boolean('can_export')->default(false);
                $table->timestamps();
                $table->unique(['company_id', 'user_id', 'module']);
            });
        }
    }

    // ─── VEHICLE ──────────────────────────────────────────────────────────────

    private function alterVehiclesTable(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicles', 'vehicle_type_id')) {
                $table->foreignId('vehicle_type_id')->nullable()->after('company_id')->constrained('vehicle_types')->nullOnDelete();
            }
            if (! Schema::hasColumn('vehicles', 'max_load_ton')) {
                $table->decimal('max_load_ton', 6, 2)->nullable()->after('capacity');
            }
            if (! Schema::hasColumn('vehicles', 'volume_m3')) {
                $table->decimal('volume_m3', 8, 2)->nullable()->after('max_load_ton');
            }
            if (! Schema::hasColumn('vehicles', 'fuel_type')) {
                $table->enum('fuel_type', ['gasoline', 'diesel', 'electric', 'hybrid'])->nullable()->after('volume_m3');
            }
            if (! Schema::hasColumn('vehicles', 'fuel_consumption')) {
                $table->decimal('fuel_consumption', 5, 2)->nullable()->after('fuel_type');
            }
            if (! Schema::hasColumn('vehicles', 'current_odometer_km')) {
                $table->decimal('current_odometer_km', 10, 2)->nullable()->after('fuel_consumption');
            }
        });

        // Extend status enum to include 'broken'
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement("ALTER TABLE vehicles MODIFY COLUMN status ENUM('active','maintenance','inactive','broken') NOT NULL DEFAULT 'active'");
            } catch (\Throwable) {
            }
        }
    }

    private function createVehicleDocuments(): void
    {
        if (! Schema::hasTable('vehicle_documents')) {
            Schema::create('vehicle_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->enum('doc_type', [
                    'registration', 'inspection', 'liability_insurance',
                    'vehicle_insurance', 'badge', 'photo', 'other',
                ]);
                $table->string('doc_name', 200);
                $table->string('doc_number', 100)->nullable();
                $table->date('issued_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('issuer', 200)->nullable();
                $table->string('file_url', 500)->nullable();
                $table->unsignedInteger('alert_before_days')->default(30);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['vehicle_id', 'doc_type']);
                $table->index(['company_id', 'expiry_date']);
            });
        }
    }

    private function createSpareParts(): void
    {
        if (! Schema::hasTable('spare_parts')) {
            Schema::create('spare_parts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 200);
                $table->string('unit', 50)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['company_id', 'is_active']);
            });
        }
    }

    private function createMaintenanceTables(): void
    {
        if (! Schema::hasTable('maintenance_schedules')) {
            Schema::create('maintenance_schedules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('spare_part_id')->nullable()->constrained('spare_parts')->nullOnDelete();
                $table->string('task_name', 200);
                $table->enum('interval_type', ['by_km', 'by_days', 'both']);
                $table->unsignedInteger('interval_km')->nullable();
                $table->unsignedInteger('interval_days')->nullable();
                $table->decimal('last_done_km', 10, 2)->nullable();
                $table->date('last_done_date')->nullable();
                $table->decimal('next_due_km', 10, 2)->nullable();
                $table->date('next_due_date')->nullable();
                $table->unsignedInteger('alert_before_km')->nullable();
                $table->unsignedInteger('alert_before_days')->default(7);
                $table->decimal('estimated_cost', 12, 2)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['vehicle_id', 'is_active']);
                $table->index(['company_id', 'next_due_date']);
            });
        }

        if (! Schema::hasTable('maintenance_records')) {
            Schema::create('maintenance_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('maintenance_schedule_id')->nullable()->constrained('maintenance_schedules')->nullOnDelete();
                $table->enum('type', ['scheduled', 'unscheduled']);
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->decimal('odometer_km', 10, 2)->nullable();
                $table->date('started_date');
                $table->date('completed_date')->nullable();
                $table->string('garage_name', 200)->nullable();
                $table->decimal('total_cost', 15, 2)->nullable();
                $table->string('invoice_number', 100)->nullable();
                $table->string('file_url', 500)->nullable();
                $table->enum('status', ['open', 'in_progress', 'completed'])->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['vehicle_id', 'status']);
                $table->index(['company_id', 'started_date']);
            });
        }
    }

    private function alterVehicleAssignments(): void
    {
        Schema::table('vehicle_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicle_assignments', 'release_reason')) {
                $table->text('release_reason')->nullable()->after('to_date');
            }
            if (! Schema::hasColumn('vehicle_assignments', 'notes')) {
                $table->text('notes')->nullable()->after('release_reason');
            }
            if (! Schema::hasColumn('vehicle_assignments', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            }
        });
    }

    // ─── DRIVER ───────────────────────────────────────────────────────────────

    private function createDriverTeamTable(): void
    {
        if (! Schema::hasTable('driver_teams')) {
            Schema::create('driver_teams', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'is_active']);
            });
        }
    }

    private function alterDriversTable(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            if (! Schema::hasColumn('drivers', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('company_id')->constrained('driver_teams')->nullOnDelete();
            }
            if (! Schema::hasColumn('drivers', 'annual_leave_days')) {
                $table->unsignedSmallInteger('annual_leave_days')->default(12)->after('resign_date');
            }
            if (! Schema::hasColumn('drivers', 'license_alert_days')) {
                $table->unsignedSmallInteger('license_alert_days')->default(30)->after('expired_date');
            }
        });
    }

    private function createDriverDocuments(): void
    {
        if (! Schema::hasTable('driver_documents')) {
            Schema::create('driver_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->enum('doc_type', [
                    'driver_license', 'international_license', 'health_certificate',
                    'skill_certificate', 'id_card', 'other',
                ]);
                $table->string('doc_name', 200);
                $table->string('doc_number', 100)->nullable();
                $table->date('issued_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('issuer', 200)->nullable();
                $table->string('file_url', 500)->nullable();
                $table->unsignedInteger('alert_before_days')->default(30);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['driver_id', 'doc_type']);
                $table->index(['company_id', 'expiry_date']);
            });
        }
    }

    // ─── ORDER ────────────────────────────────────────────────────────────────

    private function alterTripsTable(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'contact_name')) {
                $table->string('contact_name', 200)->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('trips', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable()->after('contact_name');
            }
            if (! Schema::hasColumn('trips', 'cargo_type_id')) {
                $table->foreignId('cargo_type_id')->nullable()->after('contact_phone')->constrained('cargo_types')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'cargo_description')) {
                $table->text('cargo_description')->nullable()->after('cargo_type_id');
            }
            if (! Schema::hasColumn('trips', 'cargo_quantity')) {
                $table->decimal('cargo_quantity', 10, 2)->nullable()->after('cargo_description');
            }
            if (! Schema::hasColumn('trips', 'cargo_unit')) {
                $table->string('cargo_unit', 50)->nullable()->after('cargo_quantity');
            }
            if (! Schema::hasColumn('trips', 'cargo_weight_ton')) {
                $table->decimal('cargo_weight_ton', 8, 2)->nullable()->after('cargo_unit');
            }
            if (! Schema::hasColumn('trips', 'cargo_notes')) {
                $table->text('cargo_notes')->nullable()->after('cargo_weight_ton');
            }
            if (! Schema::hasColumn('trips', 'dispatcher_id')) {
                $table->foreignId('dispatcher_id')->nullable()->after('vehicle_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'assigned_at')) {
                $table->dateTime('assigned_at')->nullable()->after('dispatcher_id');
            }
            if (! Schema::hasColumn('trips', 'route_template_id')) {
                $table->foreignId('route_template_id')->nullable()->after('assigned_at')->constrained('route_templates')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'origin_location_id')) {
                $table->foreignId('origin_location_id')->nullable()->after('route_template_id')->constrained('locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'destination_location_id')) {
                $table->foreignId('destination_location_id')->nullable()->after('origin_location_id')->constrained('locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'received_date')) {
                $table->date('received_date')->nullable()->after('end_point');
            }
            if (! Schema::hasColumn('trips', 'scheduled_date')) {
                $table->date('scheduled_date')->nullable()->after('received_date');
            }
            if (! Schema::hasColumn('trips', 'scheduled_time_from')) {
                $table->time('scheduled_time_from')->nullable()->after('scheduled_date');
            }
            if (! Schema::hasColumn('trips', 'scheduled_time_to')) {
                $table->time('scheduled_time_to')->nullable()->after('scheduled_time_from');
            }
            if (! Schema::hasColumn('trips', 'actual_distance_km')) {
                $table->decimal('actual_distance_km', 8, 2)->nullable()->after('distance_km');
            }
            if (! Schema::hasColumn('trips', 'actual_pickup_at')) {
                $table->dateTime('actual_pickup_at')->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('trips', 'actual_delivered_at')) {
                $table->dateTime('actual_delivered_at')->nullable()->after('actual_pickup_at');
            }
            if (! Schema::hasColumn('trips', 'base_price')) {
                $table->decimal('base_price', 15, 2)->default(0)->after('price');
            }
            if (! Schema::hasColumn('trips', 'surcharge_amount')) {
                $table->decimal('surcharge_amount', 15, 2)->default(0)->after('base_price');
            }
            if (! Schema::hasColumn('trips', 'total_revenue')) {
                $table->decimal('total_revenue', 15, 2)->nullable()->after('surcharge_amount');
            }
            if (! Schema::hasColumn('trips', 'payment_method')) {
                $table->enum('payment_method', ['bank_transfer', 'cash', 'credit'])->nullable()->after('total_revenue');
            }
            if (! Schema::hasColumn('trips', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'invoiced', 'paid'])->default('unpaid')->after('payment_method');
            }
            if (! Schema::hasColumn('trips', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('trips', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
            }
            if (! Schema::hasColumn('trips', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('cancelled_by');
            }
        });
    }

    private function alterLeaveRequestsTable(): void
    {
        if (Schema::hasTable('leave_requests') && ! Schema::hasColumn('leave_requests', 'company_id')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('driver_id')->constrained('companies')->nullOnDelete();
            });
        }
    }

    private function createOrderTables(): void
    {
        if (! Schema::hasTable('trip_stops')) {
            Schema::create('trip_stops', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->enum('stop_type', ['pickup', 'delivery']);
                $table->unsignedSmallInteger('sequence');
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->text('address');
                $table->string('contact_name', 200)->nullable();
                $table->string('contact_phone', 20)->nullable();
                $table->dateTime('scheduled_time')->nullable();
                $table->dateTime('actual_time')->nullable();
                $table->enum('status', ['pending', 'arrived', 'completed'])->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['trip_id', 'stop_type', 'sequence']);
            });
        }

        if (! Schema::hasTable('trip_surcharges')) {
            Schema::create('trip_surcharges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->string('name', 200);
                $table->decimal('amount', 15, 2);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trip_documents')) {
            Schema::create('trip_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->enum('doc_type', ['dispatch_note', 'delivery_receipt', 'epod', 'invoice', 'other']);
                $table->string('doc_name', 200);
                $table->string('file_url', 500);
                $table->unsignedInteger('file_size_kb')->nullable();
                $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('trip_costs')) {
            Schema::create('trip_costs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->foreignId('cost_category_id')->constrained('cost_categories')->restrictOnDelete();
                $table->decimal('amount', 15, 2);
                $table->decimal('norm_amount', 15, 2)->nullable();
                $table->text('description')->nullable();
                $table->string('receipt_file_url', 500)->nullable();
                $table->date('incurred_date');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->boolean('approval_required')->default(false);
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_note')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['trip_id', 'status']);
            });
        }

        if (! Schema::hasTable('cost_approval_requests')) {
            Schema::create('cost_approval_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
                $table->decimal('total_amount', 15, 2);
                $table->text('reason');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->index(['trip_id', 'status']);
            });
        }
    }

    // ─── ACCOUNTING ───────────────────────────────────────────────────────────

    private function createAccountingTables(): void
    {
        if (! Schema::hasTable('reconciliation_sessions')) {
            Schema::create('reconciliation_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->date('period_from');
                $table->date('period_to');
                $table->unsignedInteger('total_trips')->default(0);
                $table->decimal('total_revenue', 15, 2)->default(0);
                $table->decimal('adjusted_amount', 15, 2)->default(0);
                $table->decimal('final_amount', 15, 2)->default(0);
                $table->enum('status', ['draft', 'confirmed', 'locked'])->default('draft');
                $table->timestamp('confirmed_at')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'customer_id', 'status']);
            });
        }

        if (! Schema::hasTable('reconciliation_items')) {
            Schema::create('reconciliation_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('session_id')->constrained('reconciliation_sessions')->cascadeOnDelete();
                $table->foreignId('trip_id')->constrained('trips')->restrictOnDelete();
                $table->decimal('original_amount', 15, 2);
                $table->decimal('adjusted_amount', 15, 2)->nullable();
                $table->text('adjustment_reason')->nullable();
                $table->boolean('is_disputed')->default(false);
                $table->text('dispute_note')->nullable();
                $table->timestamps();
                $table->unique(['session_id', 'trip_id']);
            });
        }

        if (! Schema::hasTable('payment_records')) {
            Schema::create('payment_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('reconciliation_session_id')->nullable()->constrained('reconciliation_sessions')->nullOnDelete();
                $table->date('payment_date');
                $table->decimal('amount', 15, 2);
                $table->enum('payment_method', ['bank_transfer', 'cash', 'check']);
                $table->string('bank_reference', 200)->nullable();
                $table->string('receipt_url', 500)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'customer_id', 'payment_date']);
            });
        }
    }
};
