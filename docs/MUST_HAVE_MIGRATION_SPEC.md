# MUST HAVE Migration Spec (Production Roadmap)

This document converts the MUST HAVE architecture items into concrete Laravel migration specs.

## Scope
- Payroll compliance core
- Leave workflow
- Accounting foundation
- Status history/audit
- Critical indexing pass

---

## 1) Deployment Strategy (Order matters)

1. **Phase A (new master tables):**
   - `leave_types`
   - `tax_brackets`
   - `insurance_rates`
   - `chart_of_accounts`
2. **Phase B (transaction tables without risky FK):**
   - `leave_requests`
   - `leave_balances`
   - `payroll_earnings`
   - `payroll_deductions`
   - `payslips`
   - `journal_entries`
   - `journal_entry_lines`
   - `payroll_status_histories`
   - `trip_status_histories`
   - `invoice_status_histories`
3. **Phase C (add FK/index constraints and backfill):**
   - FK add-on migrations for any ordering-sensitive references
   - Backfill scripts for old payroll/invoice/trip data
4. **Phase D (validation + service guards + rollout):**
   - Enable write paths in services
   - Turn on strict business guards in workflow services

---

## 2) Migration File Plan (Laravel)

## 2.1 Leave module

### `2026_04_15_100000_create_leave_types_table.php`
```php
Schema::create('leave_types', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique(); // annual, sick, unpaid...
    $table->string('name', 100);
    $table->boolean('is_paid')->default(true);
    $table->decimal('annual_quota_days', 6, 2)->default(0);
    $table->boolean('allow_carry_forward')->default(false);
    $table->boolean('requires_attachment')->default(false);
    $table->string('status', 20)->default('active');
    $table->timestamps();
    $table->softDeletes();
});
```

### `2026_04_15_100100_create_leave_requests_table.php`
```php
Schema::create('leave_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
    $table->date('from_date');
    $table->date('to_date');
    $table->decimal('total_days', 6, 2);
    $table->text('reason')->nullable();
    $table->string('status', 20)->default('draft'); // draft/submitted/approved/rejected/cancelled
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->json('attachment_urls')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['employee_id', 'status']);
    $table->index(['from_date', 'to_date']);
});
```

### `2026_04_15_100200_create_leave_balances_table.php`
```php
Schema::create('leave_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
    $table->smallInteger('year');
    $table->decimal('opening_days', 6, 2)->default(0);
    $table->decimal('earned_days', 6, 2)->default(0);
    $table->decimal('used_days', 6, 2)->default(0);
    $table->decimal('adjusted_days', 6, 2)->default(0);
    $table->decimal('closing_days', 6, 2)->default(0);
    $table->timestamps();
    $table->unique(['employee_id', 'leave_type_id', 'year']);
});
```

---

## 2.2 Payroll compliance module

### `2026_04_15_101000_create_tax_brackets_table.php`
```php
Schema::create('tax_brackets', function (Blueprint $table) {
    $table->id();
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->integer('level');
    $table->decimal('income_from', 15, 2)->default(0);
    $table->decimal('income_to', 15, 2)->nullable();
    $table->decimal('tax_rate', 5, 2); // %
    $table->decimal('quick_deduction', 15, 2)->default(0);
    $table->string('status', 20)->default('active');
    $table->timestamps();
    $table->index(['effective_from', 'effective_to']);
    $table->unique(['effective_from', 'level']);
});
```

### `2026_04_15_101100_create_insurance_rates_table.php`
```php
Schema::create('insurance_rates', function (Blueprint $table) {
    $table->id();
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->decimal('social_employee_rate', 5, 2)->default(0);
    $table->decimal('social_company_rate', 5, 2)->default(0);
    $table->decimal('health_employee_rate', 5, 2)->default(0);
    $table->decimal('health_company_rate', 5, 2)->default(0);
    $table->decimal('unemployment_employee_rate', 5, 2)->default(0);
    $table->decimal('unemployment_company_rate', 5, 2)->default(0);
    $table->decimal('salary_cap_amount', 15, 2)->nullable();
    $table->string('status', 20)->default('active');
    $table->timestamps();
    $table->index(['effective_from', 'effective_to']);
});
```

### `2026_04_15_101200_create_payroll_earnings_table.php`
```php
Schema::create('payroll_earnings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payroll_detail_id')->constrained('payroll_details')->cascadeOnDelete();
    $table->string('type_code', 50); // BASE, OT, BONUS...
    $table->string('name', 150);
    $table->decimal('amount', 15, 2)->default(0);
    $table->boolean('taxable')->default(true);
    $table->boolean('insurable')->default(false);
    $table->string('source', 30)->default('system'); // system/manual/import
    $table->json('meta_json')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['payroll_detail_id', 'type_code']);
});
```

### `2026_04_15_101300_create_payroll_deductions_table.php`
```php
Schema::create('payroll_deductions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payroll_detail_id')->constrained('payroll_details')->cascadeOnDelete();
    $table->string('type_code', 50); // TAX, BHXH, BHYT...
    $table->string('name', 150);
    $table->decimal('amount', 15, 2)->default(0);
    $table->boolean('pre_tax')->default(false);
    $table->string('source', 30)->default('system');
    $table->json('meta_json')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['payroll_detail_id', 'type_code']);
});
```

### `2026_04_15_101400_create_payslips_table.php`
```php
Schema::create('payslips', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
    $table->foreignId('payroll_detail_id')->constrained('payroll_details')->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->string('issue_number', 50)->unique();
    $table->timestamp('issued_at')->nullable();
    $table->string('status', 20)->default('draft'); // draft/issued/void
    $table->json('snapshot_json'); // immutable payload
    $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['employee_id', 'issued_at']);
    $table->unique(['payroll_detail_id']); // 1 detail -> 1 payslip
});
```

---

## 2.3 Accounting module (minimum double-entry)

### `2026_04_15_102000_create_chart_of_accounts_table.php`
```php
Schema::create('chart_of_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique();
    $table->string('name', 150);
    $table->string('type', 30); // asset/liability/equity/revenue/expense
    $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
    $table->boolean('is_postable')->default(true);
    $table->string('status', 20)->default('active');
    $table->timestamps();
    $table->softDeletes();
    $table->index(['type', 'status']);
});
```

### `2026_04_15_102100_create_journal_entries_table.php`
```php
Schema::create('journal_entries', function (Blueprint $table) {
    $table->id();
    $table->string('entry_no', 50)->unique();
    $table->date('entry_date');
    $table->string('source_type', 50)->nullable(); // payroll/invoice/payment/manual
    $table->unsignedBigInteger('source_id')->nullable();
    $table->string('status', 20)->default('draft'); // draft/posted/reversed
    $table->text('description')->nullable();
    $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('posted_at')->nullable();
    $table->timestamps();
    $table->index(['source_type', 'source_id']);
    $table->index(['entry_date', 'status']);
});
```

### `2026_04_15_102200_create_journal_entry_lines_table.php`
```php
Schema::create('journal_entry_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
    $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
    $table->decimal('debit', 15, 2)->default(0);
    $table->decimal('credit', 15, 2)->default(0);
    $table->text('line_description')->nullable();
    $table->unsignedInteger('line_no')->default(1);
    $table->timestamps();
    $table->index(['journal_entry_id', 'line_no']);
});
```

---

## 2.4 Status history / audit strengthening

### `2026_04_15_103000_create_payroll_status_histories_table.php`
```php
Schema::create('payroll_status_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
    $table->string('from_status', 20)->nullable();
    $table->string('to_status', 20);
    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('changed_at');
    $table->text('note')->nullable();
    $table->timestamps();
    $table->index(['payroll_id', 'changed_at']);
});
```

### `2026_04_15_103100_create_trip_status_histories_table.php`
```php
Schema::create('trip_status_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
    $table->string('from_status', 30)->nullable();
    $table->string('to_status', 30);
    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('changed_at');
    $table->text('note')->nullable();
    $table->timestamps();
    $table->index(['trip_id', 'changed_at']);
});
```

### `2026_04_15_103200_create_invoice_status_histories_table.php`
```php
Schema::create('invoice_status_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
    $table->string('from_status', 30)->nullable();
    $table->string('to_status', 30);
    $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('changed_at');
    $table->text('note')->nullable();
    $table->timestamps();
    $table->index(['invoice_id', 'changed_at']);
});
```

---

## 2.5 Critical indexing pass (existing tables)

### `2026_04_15_104000_add_critical_indexes_for_scale.php`
```php
Schema::table('attendances', function (Blueprint $table) {
    $table->index(['employee_id', 'status', 'date'], 'idx_att_emp_status_date');
});

Schema::table('trips', function (Blueprint $table) {
    $table->index(['driver_id', 'status', 'start_time'], 'idx_trips_driver_status_start');
    $table->index(['vehicle_id', 'status', 'start_time'], 'idx_trips_vehicle_status_start');
});

Schema::table('vehicle_expenses', function (Blueprint $table) {
    $table->index(['vehicle_id', 'expense_date', 'type'], 'idx_vehicle_exp_vehicle_date_type');
});

Schema::table('payroll_details', function (Blueprint $table) {
    $table->index(['employee_id', 'payroll_id'], 'idx_payroll_details_emp_payroll');
});
```

---

## 3) Backfill & Data Migration Notes

1. `payslips.snapshot_json`: backfill from current `payroll_details + payrolls + employees`.
2. `payroll_earnings/payroll_deductions`: derive initial rows from summary columns:
   - earnings: `BASE`, `OT`, `BONUS`, `ALLOWANCE`
   - deductions: `DEDUCTION`, `FUEL`, `TAX`
3. status history tables:
   - create initial row from current status with `from_status = null`.

---

## 4) Laravel Code Follow-ups (after migrations)

- **Models:** add `$fillable`, `$casts`, relationships for all new tables.
- **Services:** enforce state machine + idempotency at service layer.
- **FormRequests:** add strict validation for new payloads.
- **Arch tests:** ensure controllers remain thin; no heavy DB logic in controller.

---

## 5) Rollout Recommendations

1. Deploy schema first (read/write compatible mode).
2. Backfill data in queue jobs.
3. Release service changes behind feature flags.
4. Enable strict validation after backfill complete.

