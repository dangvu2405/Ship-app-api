<?php

declare(strict_types=1);

/**
 * Bảng lương / kỳ lương — cấu hình môi trường (VN).
 *
 * Tính lương net trong App\Services\DriverPayrollCalculationService dùng
 * TaxCalculatorService (BHXH 8% + BHYT 1,5% + BHTN 1% = 10,5% NLĐ, trần
 * lương đóng BH, khấu trừ gia cảnh, TNCN lũy tiến theo luật).
 *
 * Hai khóa insurance_percent_of_base và tax_percent_of_base là mô hình
 * phẳng MVP (tỷ lệ trên lương cơ bản), chủ yếu phục vụ seeder / tài liệu;
 * không thay thế TaxCalculatorService trên luồng tính lương chính.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Chuẩn kỳ & ngày công (calendar / payroll month)
    |--------------------------------------------------------------------------
    | default_working_days: Số ngày làm việc chuẩn trong tháng để quy đổi
    | lương ngày (thường 22 ≈ trung bình tháng có ~22 ngày làm).
    | Công thực tế + ngày lễ trong PublicHoliday được tính riêng trong service.
    */
    'default_working_days' => (int) env('PAYROLL_DEFAULT_WORKING_DAYS', 22),

    /*
    |--------------------------------------------------------------------------
    | MVP: tỷ lệ phẳng trên lương cơ bản (seeder / snapshot đơn giản)
    |--------------------------------------------------------------------------
    | insurance_percent_of_base: Tổng % khấu trừ “bảo hiểm” đơn giản (0.105 =
    | 10,5% — đúng tổng mức NLĐ BHXH+BHYT+BHTN thông thường).
    | tax_percent_of_base: Thuế phẳng trên lương cơ bản; 0 = tắt (khuyến nghị
    | vì TNCN VN là lũy tiến, đã xử lý trong TaxCalculatorService).
    | default_allowance_per_driver: Phụ cấp cố định VND / tài xế / kỳ (MVP).
    */
    'insurance_percent_of_base' => (float) env('PAYROLL_INSURANCE_PERCENT', 0.105),
    'tax_percent_of_base'       => (float) env('PAYROLL_TAX_PERCENT', 0.0),
    'default_allowance_per_driver' => (float) env('PAYROLL_DEFAULT_ALLOWANCE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | Định mức nhiên liệu & thưởng tiết kiệm
    |--------------------------------------------------------------------------
    | fuel_monthly_quota: Ngân sách nhiên liệu được duyệt / tài xế / tháng (VND).
    |   Chi vượt quota trừ vào lương; 0 = tắt.
    | fuel_saving_bonus_rate: Phần thưởng trên số tiền tiết kiệm so với quota
    |   (0.5 = 50%); 0 = tắt.
    */
    'fuel_monthly_quota'     => (float) env('PAYROLL_FUEL_MONTHLY_QUOTA', 0.0),
    'fuel_saving_bonus_rate' => (float) env('PAYROLL_FUEL_SAVING_BONUS_RATE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | Trần giờ làm thêm (BLLĐ 2019 — Điều 107)
    |--------------------------------------------------------------------------
    | Giờ làm thêm không quá 40 giờ trong một tháng và 200 giờ trong một năm
    | (trừ trường hợp Nhà nước quy định khác). Service dùng mốc tháng để
    | không tính lương OT vượt trần pháp lý dù dữ liệu duyệt lệch.
    */
    'max_ot_hours_per_month' => (float) env('PAYROLL_MAX_OT_HOURS_PER_MONTH', 40.0),

    /*
    |--------------------------------------------------------------------------
    | Meta (snapshot_json, tài liệu API)
    |--------------------------------------------------------------------------
    */
    'currency'     => 'VND',
    'jurisdiction' => 'VN',
];
