<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\Violation;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ChatDataService
{
    /** @var array<string, list<string>> */
    private const INTENT_KEYWORDS = [
        'TRACKING' => [
            'chuyến', 'vị trí', 'đang ở đâu', 'lộ trình', 'giao hàng',
            'hành trình', 'order', 'tracking', 'vận chuyển', 'phân công', 'lịch trình',
        ],
        'DRIVER' => [
            'tài xế', 'driver', 'lái xe', 'nhân viên lái xe', 'danh sách tài xế',
        ],
        'PAYROLL' => [
            'lương', 'bảng lương', 'thu nhập cá nhân', 'thưởng', 'khấu trừ',
            'payroll', 'phụ cấp', 'tổng lương', 'tiền công', 'net',
        ],
        'FUEL' => [
            'nhiên liệu', 'xăng', 'dầu', 'fuel', 'định mức', 'tiêu hao', 'chi phí xe',
        ],
        'COMPLIANCE' => [
            'chứng chỉ', 'bằng lái', 'hết hạn', 'license', 'bảo hiểm',
            'chứng nhận', 'giấy phép', 'giấy tờ', 'expired',
        ],
        'REVENUE' => [
            'doanh thu', 'revenue', 'doanh số', 'thu tiền', 'tổng tiền',
            'cao nhất', 'thấp nhất', 'giá trị chuyến', 'tiền chuyến',
        ],
        'PERFORMANCE' => [
            'hiệu suất', 'nhiều chuyến nhất', 'ít chuyến nhất', 'nhiều km nhất',
            'top tài xế', 'xếp hạng', 'ranking', 'so sánh', 'thống kê tài xế',
        ],
        'VEHICLE' => [
            'xe nào', 'đội xe', 'phương tiện', 'biển số', 'xe trống',
            'xe rảnh', 'xe đang chạy', 'tình trạng xe',
        ],
        'VIOLATION' => [
            'vi phạm', 'phạt', 'sai phạm', 'violation', 'xử phạt', 'khiếu nại vi phạm',
        ],
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function resolve(User $user, string $message, string $task, array $context): array
    {
        if ($this->hasRichContext($context)) {
            return $context;
        }

        $intent = ($task !== 'chat')
            ? strtoupper($task)
            : $this->classifyByKeywords($message);

        $context['_intent'] = $intent;
        $context['_user_message'] = $message;

        $driver = $user->driver_id !== null
            ? Driver::withoutGlobalScope('tenant')->find($user->driver_id)
            : null;

        $companyId = $this->tenantContext->getCompanyId()
            ?? ($driver?->company_id);

        // Admin / manager: pas driver_id
        if ($driver === null) {
            if ($companyId === null) {
                return $context;
            }

            return match ($intent) {
                'REVENUE'                        => $this->enrichAdminRevenue($companyId, $context),
                'DRIVER'                         => $this->enrichAdminDrivers($companyId, $context),
                'PERFORMANCE'                    => $this->enrichAdminPerformance($companyId, $context),
                'VEHICLE'                        => $this->enrichAdminVehicle($companyId, $context),
                'VIOLATION'                      => $this->enrichAdminViolation($companyId, $context),
                'TRACKING', 'ORDER'              => $this->enrichAdminTrips($companyId, $context),
                'PAYROLL', 'PAYROLL_QUERY'       => $this->enrichAdminPayroll($companyId, $context),
                'COMPLIANCE'                     => $this->enrichAdminCompliance($companyId, $context),
                default                          => $this->enrichAdminGeneral($companyId, $context),
            };
        }

        // Driver user
        return match ($intent) {
            'TRACKING', 'ORDER'              => $this->enrichDriverTrips($driver, $context),
            'PAYROLL', 'PAYROLL_QUERY'       => $this->enrichDriverPayroll($driver, $context),
            'FUEL', 'FUEL_CHECK'             => $this->enrichDriverFuel($driver, $context),
            'COMPLIANCE'                     => $this->enrichDriverCompliance($driver, $context),
            'REVENUE'                        => $this->enrichDriverRevenue($driver, $context),
            'VIOLATION'                      => $this->enrichDriverViolation($driver, $context),
            default                          => $this->enrichDriverGeneral($driver, $context),
        };
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function classifyByKeywords(string $message): string
    {
        $lower = mb_strtolower($message);

        foreach (self::INTENT_KEYWORDS as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $intent;
                }
            }
        }

        return 'GENERAL';
    }

    /** @param array<string, mixed> $context */
    private function hasRichContext(array $context): bool
    {
        return isset($context['trip']['from'])
            || isset($context['payroll']['base_salary'])
            || isset($context['fuel']['fuel_quota_l'])
            || isset($context['compliance']['expiry_date']);
    }

    // ─── ADMIN ENRICHMENTS ────────────────────────────────────────────────────

    /**
     * Doanh thu: xếp hạng tài xế + tổng công ty theo tháng hiện tại.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminRevenue(int $companyId, array $context): array
    {
        $now = Carbon::now();
        $officeCode = $this->extractOfficeCodeFromMessage((string) ($context['_user_message'] ?? ''));

        if ($officeCode !== null) {
            $office = Office::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($officeCode)])
                ->first(['id', 'code', 'name']);

            if ($office === null) {
                $context['data']['doanh_thu_văn_phòng'] = sprintf('Không tìm thấy văn phòng %s trong công ty hiện tại.', $officeCode);

                return $context;
            }

            $officeRevenue = DB::table('trips')
                ->join('drivers', 'drivers.id', '=', 'trips.driver_id')
                ->leftJoin('invoices', 'invoices.trip_id', '=', 'trips.id')
                ->where('trips.company_id', $companyId)
                ->where('drivers.office_id', $office->id)
                ->where('trips.status', 'completed')
                ->whereYear('trips.end_time', $now->year)
                ->whereMonth('trips.end_time', $now->month)
                ->selectRaw('COALESCE(SUM(invoices.total_amount), SUM(trips.price), 0) as doanh_thu, COUNT(trips.id) as so_chuyen')
                ->first();

            $revenueAmount = (float) ($officeRevenue->doanh_thu ?? 0);
            $tripCount = (int) ($officeRevenue->so_chuyen ?? 0);

            $context['data']['tháng_phân_tích'] = $now->month.'/'.$now->year;
            $context['data']['văn_phòng'] = sprintf('%s - %s', $office->code, $office->name);
            $context['data']['doanh_thu_văn_phòng'] = $tripCount > 0
                ? number_format($revenueAmount, 0, '.', ',').' VNĐ'
                : sprintf('Chưa có dữ liệu doanh thu cho %s trong tháng này.', $office->code);
            $context['data']['số_chuyến_văn_phòng'] = $tripCount;

            return $context;
        }

        // Doanh thu theo tài xế (qua Invoice.total_amount - thực thanh toán)
        $topDriversByInvoice = DB::table('trips')
            ->join('invoices', 'invoices.trip_id', '=', 'trips.id')
            ->join('drivers', 'drivers.id', '=', 'trips.driver_id')
            ->where('trips.company_id', $companyId)
            ->where('trips.status', 'completed')
            ->whereYear('trips.end_time', $now->year)
            ->whereMonth('trips.end_time', $now->month)
            ->select(
                'drivers.name',
                'drivers.code',
                DB::raw('SUM(invoices.total_amount) as doanh_thu'),
                DB::raw('COUNT(trips.id) as so_chuyen')
            )
            ->groupBy('drivers.id', 'drivers.name', 'drivers.code')
            ->orderByDesc('doanh_thu')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'tài_xế'    => $row->name.' ('.$row->code.')',
                'doanh_thu' => number_format((float) $row->doanh_thu, 0, '.', ',').' VNĐ',
                'số_chuyến' => $row->so_chuyen,
            ])
            ->toArray();

        // Tổng doanh thu công ty tháng này
        $totalRevenue = DB::table('trips')
            ->join('invoices', 'invoices.trip_id', '=', 'trips.id')
            ->where('trips.company_id', $companyId)
            ->where('trips.status', 'completed')
            ->whereYear('trips.end_time', $now->year)
            ->whereMonth('trips.end_time', $now->month)
            ->sum('invoices.total_amount');

        $context['data']['tháng_phân_tích'] = $now->month.'/'.$now->year;
        $context['data']['tổng_doanh_thu_công_ty'] = number_format((float) $totalRevenue, 0, '.', ',').' VNĐ';
        $context['data']['xếp_hạng_doanh_thu_tài_xế'] = $topDriversByInvoice
            ?: 'Chưa có dữ liệu doanh thu tháng này.';

        return $context;
    }

    /**
     * Danh sách tài xế toàn công ty hoặc theo văn phòng.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminDrivers(int $companyId, array $context): array
    {
        $officeCode = $this->extractOfficeCodeFromMessage((string) ($context['_user_message'] ?? ''));
        $office = null;

        if ($officeCode !== null) {
            $office = Office::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($officeCode)])
                ->first(['id', 'code', 'name']);

            if ($office === null) {
                $context['data']['tài_xế_theo_văn_phòng'] = sprintf('Không tìm thấy văn phòng %s trong công ty hiện tại.', $officeCode);

                return $context;
            }
        }

        $query = Driver::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->with(['office:id,code,name'])
            ->orderBy('name');

        if ($office !== null) {
            $query->where('office_id', $office->id);
        }

        $drivers = $query
            ->limit(30)
            ->get(['id', 'code', 'name', 'status', 'office_id'])
            ->map(static fn (Driver $driver): array => [
                'mã_tài_xế' => $driver->code,
                'tên_tài_xế' => $driver->name,
                'trạng_thái' => $driver->status,
                'văn_phòng' => $driver->office?->code ?? 'N/A',
            ])
            ->toArray();

        if ($office !== null) {
            $context['data']['văn_phòng'] = sprintf('%s - %s', $office->code, $office->name);
            $context['data']['tài_xế_theo_văn_phòng'] = $drivers !== []
                ? $drivers
                : sprintf('Không có dữ liệu tài xế cho %s.', $office->code);

            return $context;
        }

        $context['data']['tài_xế_theo_văn_phòng'] = 'Cần mã văn phòng (ví dụ OFF006) để lấy chính xác danh sách tài xế.';
        $context['data']['tổng_tài_xế_đang_hoạt_động'] = Driver::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        return $context;
    }

    private function extractOfficeCodeFromMessage(string $message): ?string
    {
        if ($message === '') {
            return null;
        }

        if (preg_match('/\bOFF\d{3,}\b/i', $message, $matches) !== 1) {
            return null;
        }

        return strtoupper((string) $matches[0]);
    }

    /**
     * Hiệu suất tài xế: top theo số chuyến + km.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminPerformance(int $companyId, array $context): array
    {
        $now = Carbon::now();

        $topDrivers = Driver::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->withCount(['trips as so_chuyen' => fn ($q) => $q
                ->where('status', 'completed')
                ->whereYear('end_time', $now->year)
                ->whereMonth('end_time', $now->month),
            ])
            ->withSum(['trips as tong_km' => fn ($q) => $q
                ->where('status', 'completed')
                ->whereYear('end_time', $now->year)
                ->whereMonth('end_time', $now->month),
            ], 'distance_km')
            ->orderByDesc('so_chuyen')
            ->limit(5)
            ->get(['id', 'name', 'code'])
            ->map(fn (Driver $d): array => [
                'tài_xế'    => $d->name.' ('.$d->code.')',
                'số_chuyến' => (int) ($d->so_chuyen ?? 0),
                'tổng_km'   => number_format((float) ($d->tong_km ?? 0), 0, '.', ',').' km',
            ])
            ->toArray();

        $context['data']['tháng_phân_tích'] = $now->month.'/'.$now->year;
        $context['data']['hiệu_suất_tài_xế_tháng_này'] = $topDrivers
            ?: 'Chưa có dữ liệu hoàn thành chuyến tháng này.';

        return $context;
    }

    /**
     * Tình trạng đội xe.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminVehicle(int $companyId, array $context): array
    {
        $vehicles = Vehicle::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->get(['plate_number', 'type', 'brand', 'status', 'capacity'])
            ->map(fn (Vehicle $v): array => array_filter([
                'biển_số'   => $v->plate_number,
                'loại'      => $v->type,
                'hãng'      => $v->brand,
                'trạng_thái'=> $v->status,
                'tải_trọng' => $v->capacity ? $v->capacity.' tấn' : null,
            ], static fn ($val): bool => $val !== null && $val !== ''))
            ->toArray();

        $statusCounts = collect($vehicles)->groupBy('trạng_thái')
            ->map(fn ($g) => $g->count())
            ->toArray();

        $context['data']['tổng_hợp_xe'] = $statusCounts;
        $context['data']['danh_sách_xe'] = $vehicles ?: 'Chưa có xe nào.';

        return $context;
    }

    /**
     * Vi phạm gần đây của toàn công ty.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminViolation(int $companyId, array $context): array
    {
        $violations = Violation::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->with(['driver:id,name,code'])
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn (Violation $v): array => array_filter([
                'tài_xế'     => $v->driver?->name,
                'loại'       => $v->type,
                'ngày'       => $v->occurred_at?->format('d/m/Y'),
                'tiền_phạt'  => number_format((float) $v->penalty_amount, 0, '.', ',').' VNĐ',
                'trạng_thái' => $v->status,
            ], static fn ($val): bool => $val !== null && $val !== ''))
            ->toArray();

        $pendingCount = Violation::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->pending()
            ->count();

        $context['data']['vi_phạm_chờ_xử_lý'] = $pendingCount;
        $context['data']['vi_phạm_gần_đây'] = $violations ?: 'Không có vi phạm gần đây.';

        return $context;
    }

    /**
     * Chuyến xe gần đây toàn công ty.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminTrips(int $companyId, array $context): array
    {
        $now = Carbon::now();

        $summary = [
            'chuyến_tháng_này' => Trip::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)
                ->whereYear('start_time', $now->year)
                ->whereMonth('start_time', $now->month)
                ->count(),
            'đang_chạy'  => Trip::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)
                ->where('status', 'in_progress')
                ->count(),
            'chờ_xuất_phát' => Trip::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->count(),
            'hoàn_thành_hôm_nay' => Trip::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)
                ->where('status', 'completed')
                ->whereDate('end_time', $now->toDateString())
                ->count(),
        ];

        $recentTrips = Trip::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->with(['driver:id,name', 'vehicle:id,plate_number'])
            ->orderByDesc('start_time')
            ->limit(5)
            ->get()
            ->map(fn (Trip $t): array => array_filter([
                'mã'         => $t->code,
                'tài_xế'     => $t->driver?->name,
                'xe'         => $t->vehicle?->plate_number,
                'từ'         => $t->start_point,
                'đến'        => $t->end_point,
                'trạng_thái' => $t->status,
                'ngày'       => $t->start_time?->format('d/m/Y H:i'),
            ], static fn ($v): bool => $v !== null && $v !== ''))
            ->toArray();

        $context['data']['tổng_hợp_chuyến'] = $summary;
        $context['data']['chuyến_gần_nhất'] = $recentTrips;

        return $context;
    }

    /**
     * Bảng lương mới nhất toàn công ty.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminPayroll(int $companyId, array $context): array
    {
        $latest = Payroll::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        if ($latest === null) {
            $context['data']['ghi_chú'] = 'Chưa có bảng lương nào.';

            return $context;
        }

        $stats = PayrollLine::withoutGlobalScope('tenant')
            ->where('payroll_id', $latest->id)
            ->selectRaw('COUNT(*) as so_tai_xe, SUM(net_salary) as tong_chi_luong, AVG(net_salary) as luong_tb')
            ->first();

        $context['data']['bảng_lương'] = [
            'tháng'          => $latest->month.'/'.$latest->year,
            'trạng_thái'     => $latest->status,
            'số_tài_xế'      => (int) ($stats->so_tai_xe ?? 0),
            'tổng_chi_lương' => number_format((float) ($stats->tong_chi_luong ?? 0), 0, '.', ',').' VNĐ',
            'lương_trung_bình' => number_format((float) ($stats->luong_tb ?? 0), 0, '.', ',').' VNĐ',
        ];

        return $context;
    }

    /**
     * Chứng chỉ sắp hết hạn toàn công ty.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminCompliance(int $companyId, array $context): array
    {
        $threshold = Carbon::today()->addDays(30);

        $expiring = Driver::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->where('expired_date', '<=', $threshold)
                ->orWhere('driver_insurance_expired_date', '<=', $threshold)
                ->orWhere('health_certificate_expired_date', '<=', $threshold)
            )
            ->get(['name', 'code', 'license_class', 'expired_date', 'driver_insurance_expired_date', 'health_certificate_expired_date'])
            ->map(fn (Driver $d): array => array_filter([
                'tài_xế'            => $d->name.' ('.$d->code.')',
                'bằng_lái_hết_hạn'  => $d->expired_date?->format('d/m/Y'),
                'bảo_hiểm_hết_hạn'  => $d->driver_insurance_expired_date?->format('d/m/Y'),
                'sức_khỏe_hết_hạn'  => $d->health_certificate_expired_date?->format('d/m/Y'),
            ], static fn ($v): bool => $v !== null))
            ->toArray();

        $context['data']['tài_xế_cần_gia_hạn_giấy_tờ'] = $expiring
            ?: 'Không có tài xế nào sắp hết hạn trong 30 ngày tới.';

        return $context;
    }

    /**
     * Snapshot tổng quát toàn công ty.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichAdminGeneral(int $companyId, array $context): array
    {
        $now = Carbon::now();

        $context['data']['tổng_quan_công_ty'] = [
            'tài_xế_đang_hoạt_động' => Driver::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)->where('status', 'active')->count(),
            'xe_hoạt_động'          => Vehicle::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)->where('status', 'active')->count(),
            'chuyến_tháng_này'      => Trip::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)
                ->whereYear('start_time', $now->year)
                ->whereMonth('start_time', $now->month)
                ->count(),
            'chuyến_đang_chạy'      => Trip::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)
                ->where('status', 'in_progress')
                ->count(),
            'vi_phạm_chờ_xử_lý'    => Violation::withoutGlobalScope('tenant')
                ->where('company_id', $companyId)
                ->pending()
                ->count(),
        ];

        return $context;
    }

    // ─── DRIVER ENRICHMENTS ───────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichDriverTrips(Driver $driver, array $context): array
    {
        $trips = Trip::withoutGlobalScope('tenant')
            ->where('driver_id', $driver->id)
            ->with(['vehicle:id,plate_number', 'customer:id,name'])
            ->orderByDesc('start_time')
            ->limit(5)
            ->get()
            ->map(fn (Trip $t): array => array_filter([
                'mã'          => $t->code,
                'từ'          => $t->start_point,
                'đến'         => $t->end_point,
                'trạng_thái'  => $t->status,
                'quãng_đường' => $t->distance_km ? $t->distance_km.' km' : null,
                'xe'          => $t->vehicle?->plate_number,
                'khách_hàng'  => $t->customer?->name,
                'bắt_đầu'    => $t->start_time?->format('d/m/Y H:i'),
                'kết_thúc'   => $t->end_time?->format('d/m/Y H:i'),
            ], static fn ($v): bool => $v !== null && $v !== ''))
            ->toArray();

        $context['data']['tài_xế'] = $driver->name;
        $context['data']['chuyến_gần_nhất'] = $trips ?: 'Chưa có chuyến nào.';

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichDriverPayroll(Driver $driver, array $context): array
    {
        $line = PayrollLine::withoutGlobalScope('tenant')
            ->where('driver_id', $driver->id)
            ->with(['payroll:id,month,year,status'])
            ->latest()
            ->first();

        if ($line === null) {
            $context['data']['tài_xế'] = $driver->name;
            $context['data']['ghi_chú'] = 'Chưa có bảng lương nào.';

            return $context;
        }

        $khauTru = (float) $line->deduction
            + (float) $line->violation_deduction
            + (float) $line->tax;

        $context['payroll'] = [
            'driver_name'   => $driver->name,
            'base_salary'   => (float) $line->base_salary,
            'working_days'  => $line->working_days,
            'standard_days' => 22,
            'bonus_km'      => (float) $line->trip_bonus,
            'deductions'    => number_format($khauTru, 0, '.', ',').' VNĐ',
        ];

        $context['data']['chi_tiết_lương'] = [
            'tháng'            => $line->payroll->month.'/'.$line->payroll->year,
            'lương_cơ_bản'    => number_format((float) $line->base_salary, 0, '.', ',').' VNĐ',
            'thưởng_chuyến'   => number_format((float) $line->trip_bonus, 0, '.', ',').' VNĐ',
            'làm_thêm_giờ'    => number_format((float) $line->overtime_pay, 0, '.', ',').' VNĐ',
            'phụ_cấp'         => number_format((float) $line->allowance, 0, '.', ',').' VNĐ',
            'khấu_trừ'        => number_format($khauTru, 0, '.', ',').' VNĐ',
            'thực_lĩnh'       => number_format((float) $line->net_salary, 0, '.', ',').' VNĐ',
            'ngày_công'       => $line->working_days,
            'số_chuyến'       => $line->trips_completed_count,
            'tổng_km'         => $line->total_distance_km.' km',
            'trạng_thái'      => $line->payroll->status,
        ];

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichDriverFuel(Driver $driver, array $context): array
    {
        $expenses = VehicleExpense::withoutGlobalScope('tenant')
            ->where('driver_id', $driver->id)
            ->where('type', 'fuel')
            ->with(['vehicle:id,plate_number'])
            ->orderByDesc('expense_date')
            ->limit(5)
            ->get()
            ->map(fn (VehicleExpense $e): array => array_filter([
                'ngày'    => $e->expense_date?->format('d/m/Y'),
                'xe'      => $e->vehicle?->plate_number,
                'số_tiền' => number_format((float) $e->amount, 0, '.', ',').' VNĐ',
                'ghi_chú' => $e->note,
            ], static fn ($v): bool => $v !== null && $v !== ''))
            ->toArray();

        $context['data']['tài_xế'] = $driver->name;
        $context['data']['chi_phí_nhiên_liệu'] = $expenses ?: 'Không có dữ liệu.';

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichDriverCompliance(Driver $driver, array $context): array
    {
        $today = Carbon::today();
        $licenseExpiry = $driver->expired_date;

        $context['compliance'] = [
            'driver_name' => $driver->name,
            'cert_name'   => 'Bằng lái '.($driver->license_class ?? ''),
            'expiry_date' => $licenseExpiry?->format('d/m/Y') ?? 'Chưa có',
        ];

        $context['data']['tuân_thủ_chứng_chỉ'] = array_filter([
            'tài_xế'            => $driver->name,
            'hạng_bằng_lái'     => $driver->license_class,
            'bằng_lái_hết_hạn'  => $licenseExpiry?->format('d/m/Y') ?? 'Chưa có',
            'còn_lại_ngày'      => $licenseExpiry
                ? max(0, $today->diffInDays($licenseExpiry, false))
                : null,
            'bảo_hiểm_hết_hạn'  => $driver->driver_insurance_expired_date?->format('d/m/Y') ?? 'Chưa có',
            'sức_khỏe_hết_hạn'  => $driver->health_certificate_expired_date?->format('d/m/Y') ?? 'Chưa có',
        ], static fn ($v): bool => $v !== null);

        return $context;
    }

    /**
     * Doanh thu cá nhân: tổng tiền chuyến đã hoàn thành theo tháng.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichDriverRevenue(Driver $driver, array $context): array
    {
        $now = Carbon::now();

        $revenueThisMonth = DB::table('trips')
            ->leftJoin('invoices', 'invoices.trip_id', '=', 'trips.id')
            ->where('trips.driver_id', $driver->id)
            ->where('trips.status', 'completed')
            ->whereYear('trips.end_time', $now->year)
            ->whereMonth('trips.end_time', $now->month)
            ->selectRaw('COALESCE(SUM(invoices.total_amount), SUM(trips.price), 0) as doanh_thu, COUNT(trips.id) as so_chuyen')
            ->first();

        // 3 tháng gần nhất
        $last3Months = collect();
        for ($i = 0; $i < 3; $i++) {
            $month = $now->copy()->subMonths($i);
            $rev = DB::table('trips')
                ->leftJoin('invoices', 'invoices.trip_id', '=', 'trips.id')
                ->where('trips.driver_id', $driver->id)
                ->where('trips.status', 'completed')
                ->whereYear('trips.end_time', $month->year)
                ->whereMonth('trips.end_time', $month->month)
                ->selectRaw('COALESCE(SUM(invoices.total_amount), SUM(trips.price), 0) as doanh_thu')
                ->value('doanh_thu');

            $last3Months->push([
                'tháng'     => $month->month.'/'.$month->year,
                'doanh_thu' => number_format((float) ($rev ?? 0), 0, '.', ',').' VNĐ',
            ]);
        }

        $context['data']['doanh_thu_tài_xế'] = $driver->name;
        $context['data']['tháng_này'] = [
            'doanh_thu' => number_format((float) ($revenueThisMonth->doanh_thu ?? 0), 0, '.', ',').' VNĐ',
            'số_chuyến' => (int) ($revenueThisMonth->so_chuyen ?? 0),
        ];
        $context['data']['3_tháng_gần_nhất'] = $last3Months->toArray();

        return $context;
    }

    /**
     * Vi phạm của tài xế.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichDriverViolation(Driver $driver, array $context): array
    {
        $violations = Violation::withoutGlobalScope('tenant')
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn (Violation $v): array => array_filter([
                'loại'       => $v->type,
                'ngày'       => $v->occurred_at?->format('d/m/Y'),
                'mô_tả'      => mb_substr((string) $v->description, 0, 100),
                'tiền_phạt'  => number_format((float) $v->penalty_amount, 0, '.', ',').' VNĐ',
                'trạng_thái' => $v->status,
            ], static fn ($v): bool => $v !== null && $v !== ''))
            ->toArray();

        $totalPenalty = Violation::withoutGlobalScope('tenant')
            ->where('driver_id', $driver->id)
            ->confirmed()
            ->sum('penalty_amount');

        $context['data']['tài_xế'] = $driver->name;
        $context['data']['vi_phạm'] = $violations ?: 'Không có vi phạm nào đang xử lý.';
        $context['data']['tổng_tiền_phạt_đã_confirmed'] = number_format((float) $totalPenalty, 0, '.', ',').' VNĐ';

        return $context;
    }

    /**
     * Snapshot tổng quát của tài xế.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichDriverGeneral(Driver $driver, array $context): array
    {
        $now = Carbon::now();

        $tripsThisMonth = Trip::withoutGlobalScope('tenant')
            ->where('driver_id', $driver->id)
            ->whereYear('start_time', $now->year)
            ->whereMonth('start_time', $now->month)
            ->count();

        $latestTrip = Trip::withoutGlobalScope('tenant')
            ->where('driver_id', $driver->id)
            ->with(['vehicle:id,plate_number'])
            ->orderByDesc('start_time')
            ->first();

        $context['data']['tài_xế'] = $driver->name;
        $context['data']['trạng_thái_tài_xế'] = $driver->available_status ?? $driver->status;
        $context['data']['chuyến_tháng_này'] = $tripsThisMonth;
        $context['data']['chuyến_gần_nhất'] = $latestTrip !== null ? array_filter([
            'mã'          => $latestTrip->code,
            'từ'          => $latestTrip->start_point,
            'đến'         => $latestTrip->end_point,
            'trạng_thái'  => $latestTrip->status,
            'xe'          => $latestTrip->vehicle?->plate_number,
            'ngày'        => $latestTrip->start_time?->format('d/m/Y'),
        ], static fn ($v): bool => $v !== null && $v !== '') : null;

        return $context;
    }
}
