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
];
