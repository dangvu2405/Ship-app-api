<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SPEC_TABLES = [
        'companies', 'users', 'user_permissions', 'user_companies', 'audit_logs',
        'offices', 'departments', 'positions', 'employees',
        'vehicle_types', 'cargo_types', 'locations', 'route_templates', 'cost_categories', 'order_status_configs',
        'customer_groups', 'customers', 'price_lists', 'price_list_items',
        'vehicles', 'vehicle_documents', 'vehicle_assignments', 'spare_parts', 'maintenance_schedules', 'maintenance_records',
        'driver_teams', 'drivers', 'driver_documents',
        'leave_types', 'leave_requests', 'overtime_requests', 'driver_work_schedules',
        'trips', 'trip_stops', 'trip_surcharges', 'trip_documents', 'trip_status_histories',
        'trip_costs', 'cost_approval_requests',
        'reconciliation_sessions', 'reconciliation_items', 'payment_records',
        'invoices', 'invoice_status_histories',
        'payrolls', 'payroll_lines', 'payroll_adjustments', 'payroll_status_histories',
        'notifications', 'chat_messages', 'knowledge_articles', 'rag_index', 'report_caches',
    ];

    private const SYSTEM_TABLES = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'sessions', 'personal_access_tokens', 'password_reset_tokens', 'refresh_tokens',
        'login_logs', 'export_logs',
    ];

    public function up(): void
    {

        Schema::disableForeignKeyConstraints();

        $this->ensurePlatformTables();
        $this->ensureWorkforceTables();
        $this->ensureCatalogTables();
        $this->ensureCustomerTables();
        $this->ensureVehicleTables();
        $this->ensureDriverTables();
        $this->ensureScheduleTables();
        $this->ensureOrderAndCostTables();
        $this->ensureAccountingTables();
        $this->ensurePayrollTables();
        $this->ensureLogTables();

        $this->dropTablesOutsideStrictSpec();

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // strict cleanup migration is non-reversible by design
    }

    private function ensurePlatformTables(): void
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

        if (! Schema::hasTable('user_companies')) {
            Schema::create('user_companies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'company_id']);
            });
        }
    }

    private function ensureWorkforceTables(): void
    {
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
    }

    private function ensureCatalogTables(): void
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
            });
        }

        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->nullable();
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

    private function ensureCustomerTables(): void
    {
        if (! Schema::hasTable('customer_groups')) {
            Schema::create('customer_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->nullable();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->foreignId('assigned_dispatcher_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

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
            });
        }
    }

    private function ensureVehicleTables(): void
    {
        if (! Schema::hasTable('vehicle_documents')) {
            Schema::create('vehicle_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->enum('doc_type', ['registration', 'inspection', 'liability_insurance', 'vehicle_insurance', 'badge', 'photo', 'other']);
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
            });
        }

        if (! Schema::hasTable('spare_parts')) {
            Schema::create('spare_parts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 200);
                $table->string('unit', 50)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

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
            });
        }
    }

    private function ensureDriverTables(): void
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
            });
        }

        if (! Schema::hasTable('driver_documents')) {
            Schema::create('driver_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->enum('doc_type', ['driver_license', 'international_license', 'health_certificate', 'skill_certificate', 'id_card', 'other']);
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
            });
        }
    }

    private function ensureScheduleTables(): void
    {
        if (Schema::hasTable('leave_requests') && ! Schema::hasColumn('leave_requests', 'company_id')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('driver_id')->constrained('companies')->nullOnDelete();
            });
        }
    }

    private function ensureOrderAndCostTables(): void
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
            });
        }
    }

    private function ensureAccountingTables(): void
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
            });
        }
    }

    private function ensurePayrollTables(): void
    {
        if (! Schema::hasTable('payrolls')) {
            Schema::create('payrolls', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('month');
                $table->unsignedSmallInteger('year');
                $table->enum('status', ['draft', 'approved', 'locked', 'paid'])->default('draft');
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->text('notes')->nullable();
                $table->json('snapshot_json')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'month', 'year']);
            });
        }

        if (! Schema::hasTable('payroll_lines')) {
            Schema::create('payroll_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
                $table->decimal('base_salary', 15, 2)->default(0);
                $table->decimal('trip_bonus', 15, 2)->default(0);
                $table->decimal('overtime_pay', 15, 2)->default(0);
                $table->decimal('night_shift_allowance', 15, 2)->default(0);
                $table->decimal('public_holiday_pay', 15, 2)->default(0);
                $table->decimal('allowance', 15, 2)->default(0);
                $table->decimal('deduction', 15, 2)->default(0);
                $table->decimal('leave_unpaid_deduction', 15, 2)->default(0);
                $table->decimal('violation_deduction', 15, 2)->default(0);
                $table->decimal('fuel_excess_deduction', 15, 2)->default(0);
                $table->decimal('tax', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);
                $table->unsignedSmallInteger('working_days')->default(22);
                $table->unsignedSmallInteger('leave_days_paid')->default(0);
                $table->unsignedSmallInteger('leave_days_unpaid')->default(0);
                $table->decimal('overtime_hours', 8, 2)->default(0);
                $table->unsignedInteger('trips_completed_count')->default(0);
                $table->decimal('total_distance_km', 12, 2)->default(0);
                $table->json('meta_json')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['payroll_id', 'driver_id']);
            });
        }

        if (! Schema::hasTable('payroll_adjustments')) {
            Schema::create('payroll_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
                $table->unsignedBigInteger('original_payroll_id')->nullable();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->enum('type', ['addition', 'deduction']);
                $table->enum('category', ['violation_refund', 'leave_restore', 'ot_late_approval', 'manual'])->default('manual');
                $table->decimal('amount', 15, 2);
                $table->text('reason');
                $table->string('source_type', 50)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('payroll_status_histories')) {
            Schema::create('payroll_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50);
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('changed_at')->useCurrent();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    private function ensureLogTables(): void
    {
        if (! Schema::hasTable('login_logs')) {
            Schema::create('login_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('device', 255)->nullable();
                $table->timestamp('login_at')->useCurrent();
                $table->timestamp('logout_at')->nullable();
                $table->string('status', 20)->default('active');
                $table->string('action', 50)->default('login');
                $table->string('performed_by', 255)->nullable();
                $table->timestamps();
                $table->index('user_id');
                $table->index('login_at');
            });
        }

        if (! Schema::hasTable('export_logs')) {
            Schema::create('export_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type', 50);
                $table->string('file_name', 255);
                $table->string('file_path', 255);
                $table->integer('record_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->index('user_id');
                $table->index(['type', 'created_at']);
            });
        }
    }

    private function dropTablesOutsideStrictSpec(): void
    {
        $keep = array_fill_keys(array_merge(self::SPEC_TABLES, self::SYSTEM_TABLES), true);

        foreach (Schema::getTableListing() as $table) {
            $normalized = strtolower($table);
            $normalized = str_contains($normalized, '.') ? substr($normalized, strrpos($normalized, '.') + 1) : $normalized;

            if (str_starts_with($normalized, 'sqlite_')) {
                continue;
            }

            if ($normalized === 'migrations') {
                continue;
            }

            if (! isset($keep[$normalized])) {
                Schema::dropIfExists($table);
            }
        }
    }
};
