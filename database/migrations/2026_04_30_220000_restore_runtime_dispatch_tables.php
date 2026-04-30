<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pricing_rules')) {
            Schema::create('pricing_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('name', 120);
                $table->decimal('base_freight', 15, 2)->default(0);
                $table->decimal('rate_per_km', 12, 4)->default(0);
                $table->decimal('fuel_adjustment', 12, 2)->default(0);
                $table->decimal('max_discount_percent', 5, 2)->default(0);
                $table->decimal('minimum_margin_percent', 5, 2)->default(0);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('transport_requests')) {
            Schema::create('transport_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('code', 40)->unique();
                $table->string('pickup_location');
                $table->string('delivery_location');
                $table->string('cargo_type', 120)->nullable();
                $table->decimal('cargo_weight', 12, 2);
                $table->decimal('cargo_volume', 12, 2)->nullable();
                $table->date('requested_delivery_date');
                $table->string('service_type', 60)->nullable();
                $table->string('payment_term', 60)->nullable();
                $table->text('special_requirement')->nullable();
                $table->boolean('fragile_flag')->default(false);
                $table->boolean('cold_chain_flag')->default(false);
                $table->boolean('dangerous_goods_flag')->default(false);
                $table->boolean('loading_support_required')->default(false);
                $table->boolean('insurance_required')->default(false);
                $table->enum('status', ['draft', 'pending_pricing', 'pending_approval', 'approved', 'rejected', 'expired'])->default('draft');
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('transport_request_id')->constrained('transport_requests')->cascadeOnDelete();
                $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->nullOnDelete();
                $table->string('code', 40)->unique();
                $table->decimal('distance_km', 12, 2)->default(0);
                $table->decimal('base_freight', 15, 2)->default(0);
                $table->decimal('surcharges_total', 15, 2)->default(0);
                $table->decimal('special_fees_total', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('cost_amount', 15, 2)->default(0);
                $table->decimal('selling_price', 15, 2)->default(0);
                $table->decimal('margin_percent', 8, 4)->default(0);
                $table->enum('status', ['pending_pricing', 'need_manual_pricing', 'pending_approval', 'approved', 'rejected'])->default('pending_pricing');
                $table->text('notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['transport_request_id']);
            });
        }

        if (! Schema::hasTable('quotation_pricing_items')) {
            Schema::create('quotation_pricing_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
                $table->enum('type', ['surcharge', 'special_fee', 'discount', 'vat']);
                $table->string('code', 80)->nullable();
                $table->string('label', 120);
                $table->decimal('amount', 15, 2);
                $table->boolean('is_mandatory')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quotation_approvals')) {
            Schema::create('quotation_approvals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('action', ['submitted', 'approved', 'rejected', 'recalculated']);
                $table->text('reason')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'office_id')) {
                $table->foreignId('office_id')->nullable()->after('company_id')->constrained('offices')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'transport_request_id')) {
                $table->foreignId('transport_request_id')->nullable()->after('customer_id')->constrained('transport_requests')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'quotation_id')) {
                $table->foreignId('quotation_id')->nullable()->after('transport_request_id')->constrained('quotations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // runtime compatibility migration, no destructive rollback
    }
};
