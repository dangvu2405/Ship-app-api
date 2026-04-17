<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MVP payroll constants (driver-centric)
    |--------------------------------------------------------------------------
    */
    'default_working_days' => 22,

    /** BHXH NLĐ phần đơn giản: % trên lương cơ bản (0.105 = 10.5%) */
    'insurance_percent_of_base' => (float) env('PAYROLL_INSURANCE_PERCENT', 0.105),

    /** Thuế TNCN MVP: % trên lương cơ bản (0 = tắt) */
    'tax_percent_of_base' => (float) env('PAYROLL_TAX_PERCENT', 0.0),

    /** Phụ cấp cố định MVP / tài xế / kỳ (VND) */
    'default_allowance_per_driver' => (float) env('PAYROLL_DEFAULT_ALLOWANCE', 0),

    /*
    |--------------------------------------------------------------------------
    | Fuel quota & saving bonus
    |--------------------------------------------------------------------------
    | fuel_monthly_quota   : Mức xăng được duyệt / tài xế / tháng (VND).
    |                        Chi phí vượt quota sẽ bị trừ vào lương.
    |                        0 = tắt tính năng quota.
    | fuel_saving_bonus_rate : Tỷ lệ thưởng tiết kiệm xăng (0.5 = 50% số tiền
    |                          tiết kiệm được so với quota).
    |                          0 = tắt thưởng tiết kiệm.
    */
    'fuel_monthly_quota'      => (float) env('PAYROLL_FUEL_MONTHLY_QUOTA', 0.0),
    'fuel_saving_bonus_rate'  => (float) env('PAYROLL_FUEL_SAVING_BONUS_RATE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | OT cap (Vietnamese Labor Code: 40 h/month, 200 h/year)
    |--------------------------------------------------------------------------
    | Payroll calculation caps approved OT hours at this value to ensure
    | salary is never computed on OT that exceeds the legal limit, even if
    | individual OT requests somehow slipped through the per-request guard.
    */
    'max_ot_hours_per_month'  => (float) env('PAYROLL_MAX_OT_HOURS_PER_MONTH', 40.0),
];
