<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExportService
{
    /**
     * @return list<string>
     */
    private function revenueHeaders(): array
    {
        return [
            'ky_thang',
            'ma_hoa_don',
            'trang_thai_hd',
            'tong_tien',
            'vat',
            'tam_tinh',
            'ngay_phat_hanh',
            'ngay_thanh_toan',
            'ma_chuyen',
            'diem_di',
            'diem_den',
            'khach_hang',
            'cong_ty',
        ];
    }

    /**
     * @return list<string>
     */
    private function tripHeaders(): array
    {
        return [
            'ma_chuyen',
            'trang_thai',
            'diem_di',
            'diem_den',
            'km',
            'gia',
            'khach_hang',
            'tai_xe',
            'ma_tx',
            'bat_dau',
            'ket_thuc',
            'tao_luc',
        ];
    }

    /**
     * @return list<string>
     */
    private function payrollHeaders(): array
    {
        return [
            'ky_luong',
            'phong_ban',
            'ma_tai_xe',
            'ten_tai_xe',
            'luong_co_ban',
            'thuong_chuyen',
            'tang_ca',
            'phu_cap_dem',
            'le_cong',
            'phu_cap',
            'khau_tru_bh',
            'nghi_khong_luong',
            'vi_pham',
            'vuot_dinh_muc_xang',
            'thue_tncn',
            'thuc_linh',
            'ngay_cong',
            'gio_tang_ca',
            'so_chuyen',
            'tong_km',
        ];
    }

    public function streamRevenueCsv(?int $companyId, Carbon $from, Carbon $to): StreamedResponse
    {
        $fromBound = $from->copy()->startOfDay();
        $toBound = $to->copy()->endOfDay();
        $slug = sprintf(
            'bao-cao-doanh-thu_%s_%s.csv',
            $fromBound->format('Y-m-d'),
            $toBound->format('Y-m-d'),
        );

        return $this->streamCsv(
            $slug,
            $this->revenueHeaders(),
            function ($writeRow) use ($companyId, $fromBound, $toBound): void {
                Invoice::query()
                    ->with([
                        'trip' => static fn ($q) => $q->withTrashed(),
                        'trip.company',
                        'trip.customer',
                        'customer',
                    ])
                    ->whereBetween('issued_at', [$fromBound, $toBound])
                    ->when($companyId !== null, static function ($q) use ($companyId): void {
                        $q->whereHas('trip', static function ($tq) use ($companyId): void {
                            $tq->withTrashed()->where('company_id', $companyId);
                        });
                    })
                    ->orderBy('id')
                    ->chunkById(500, function ($invoices) use ($writeRow): void {
                        foreach ($invoices as $invoice) {
                            /** @var Invoice $invoice */
                            $trip = $invoice->trip;
                            $issued = $invoice->issued_at;
                            $period = $issued !== null ? $issued->format('Y-m') : '';
                            $customerName = $invoice->customer?->name
                                ?? $trip?->customer?->name
                                ?? '';
                            $writeRow([
                                $period,
                                (string) $invoice->code,
                                (string) $invoice->status,
                                (string) $invoice->total_amount,
                                (string) $invoice->vat_amount,
                                (string) $invoice->subtotal,
                                $invoice->issued_at?->format('Y-m-d H:i:s') ?? '',
                                $invoice->paid_at?->format('Y-m-d H:i:s') ?? '',
                                $trip !== null ? (string) $trip->code : '',
                                $trip !== null ? (string) $trip->start_point : '',
                                $trip !== null ? (string) $trip->end_point : '',
                                $customerName,
                                $trip?->company?->name ?? '',
                            ]);
                        }
                    });
            },
        );
    }

    public function streamRevenueExcel(?int $companyId, Carbon $from, Carbon $to): StreamedResponse
    {
        $fromBound = $from->copy()->startOfDay();
        $toBound = $to->copy()->endOfDay();
        $filename = sprintf(
            'bao-cao-doanh-thu_%s_%s.xls',
            $fromBound->format('Y-m-d'),
            $toBound->format('Y-m-d'),
        );
        $asciiFilename = Str::ascii($filename) ?: 'report.xls';

        return response()->streamDownload(function () use ($companyId, $fromBound, $toBound): void {
            $xml = [];
            $xml[] = '<?xml version="1.0"?>';
            $xml[] = '<?mso-application progid="Excel.Sheet"?>';
            $xml[] = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            $xml[] = '<Worksheet ss:Name="Revenue"><Table>';
            $xml[] = '<Row><Cell><Data ss:Type="String">invoice_code</Data></Cell><Cell><Data ss:Type="String">status</Data></Cell><Cell><Data ss:Type="String">total_amount</Data></Cell><Cell><Data ss:Type="String">trip_code</Data></Cell></Row>';

            Invoice::query()
                ->with('trip')
                ->whereBetween('issued_at', [$fromBound, $toBound])
                ->when($companyId !== null, static function ($q) use ($companyId): void {
                    $q->whereHas('trip', static function ($tq) use ($companyId): void {
                        $tq->withTrashed()->where('company_id', $companyId);
                    });
                })
                ->orderBy('id')
                ->chunkById(300, function ($invoices) use (&$xml): void {
                    foreach ($invoices as $invoice) {
                        $xml[] = sprintf(
                            '<Row><Cell><Data ss:Type="String">%s</Data></Cell><Cell><Data ss:Type="String">%s</Data></Cell><Cell><Data ss:Type="Number">%s</Data></Cell><Cell><Data ss:Type="String">%s</Data></Cell></Row>',
                            htmlspecialchars((string) $invoice->code),
                            htmlspecialchars((string) $invoice->status),
                            (string) ((float) $invoice->total_amount),
                            htmlspecialchars((string) ($invoice->trip?->code ?? '')),
                        );
                    }
                });

            $xml[] = '</Table></Worksheet></Workbook>';
            echo implode('', $xml);
        }, $asciiFilename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>|null  $statuses
     */
    public function streamTripsCsv(?int $companyId, Carbon $from, Carbon $to, ?array $statuses): StreamedResponse
    {
        $fromBound = $from->copy()->startOfDay();
        $toBound = $to->copy()->endOfDay();
        $slug = sprintf(
            'bao-cao-chuyen-di_%s_%s.csv',
            $fromBound->format('Y-m-d'),
            $toBound->format('Y-m-d'),
        );

        return $this->streamCsv(
            $slug,
            $this->tripHeaders(),
            function ($writeRow) use ($companyId, $fromBound, $toBound, $statuses): void {
                Trip::query()
                    ->with(['customer', 'driver'])
                    ->whereBetween('created_at', [$fromBound, $toBound])
                    ->when($companyId !== null, static fn ($q) => $q->where('company_id', $companyId))
                    ->when($statuses !== null && $statuses !== [], static function ($q) use ($statuses): void {
                        $q->whereIn('status', $statuses);
                    })
                    ->orderBy('id')
                    ->chunkById(500, function ($trips) use ($writeRow): void {
                        foreach ($trips as $trip) {
                            /** @var Trip $trip */
                            $writeRow([
                                (string) $trip->code,
                                (string) $trip->status,
                                (string) $trip->start_point,
                                (string) $trip->end_point,
                                (string) $trip->distance_km,
                                (string) $trip->price,
                                $trip->customer?->name ?? '',
                                $trip->driver?->name ?? '',
                                $trip->driver?->code ?? '',
                                $trip->start_time?->format('Y-m-d H:i:s') ?? '',
                                $trip->end_time?->format('Y-m-d H:i:s') ?? '',
                                $trip->created_at?->format('Y-m-d H:i:s') ?? '',
                            ]);
                        }
                    });
            },
        );
    }

    public function streamPayrollCsv(int $companyId, int $month, int $year): StreamedResponse
    {
        $slug = sprintf('bao-cao-luong_%d-%02d.csv', $year, $month);

        return $this->streamCsv(
            $slug,
            $this->payrollHeaders(),
            function ($writeRow) use ($companyId, $month, $year): void {
                $payroll = Payroll::query()
                    ->where('company_id', $companyId)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->with(['lines.driver.department'])
                    ->first();

                if ($payroll === null) {
                    return;
                }

                $period = sprintf('%d-%02d', $year, $month);
                foreach ($payroll->lines as $line) {
                    $driver = $line->driver;
                    $dept = $driver?->department;
                    $writeRow([
                        $period,
                        $dept?->name ?? '',
                        $driver !== null ? (string) $driver->code : '',
                        $driver !== null ? (string) $driver->name : '',
                        (string) $line->base_salary,
                        (string) $line->trip_bonus,
                        (string) $line->overtime_pay,
                        (string) $line->night_shift_allowance,
                        (string) $line->public_holiday_pay,
                        (string) $line->allowance,
                        (string) $line->deduction,
                        (string) $line->leave_unpaid_deduction,
                        (string) $line->violation_deduction,
                        (string) $line->fuel_excess_deduction,
                        (string) $line->tax,
                        (string) $line->net_salary,
                        (string) $line->working_days,
                        (string) $line->overtime_hours,
                        (string) $line->trips_completed_count,
                        (string) $line->total_distance_km,
                    ]);
                }
            },
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  callable(callable(list<string>): void): void  $rowsWriter
     */
    private function streamCsv(string $filename, array $headers, callable $rowsWriter): StreamedResponse
    {
        $asciiFilename = Str::ascii($filename) ?: 'export.csv';

        return response()->streamDownload(function () use ($headers, $rowsWriter): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            $writeRow = static function (array $row) use ($out): void {
                fputcsv($out, $row);
            };
            $rowsWriter($writeRow);
            fclose($out);
        }, $asciiFilename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
