<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Text-to-SQL agent — converts a Vietnamese business question into a safe
 * SELECT query and executes it on a read-only MySQL connection.
 *
 * SAFETY CONTRACT
 * ───────────────
 * • Only SELECT statements are allowed; any DML/DDL keyword causes rejection.
 * • Queries run on the `mysql_readonly` connection (configure a DB user with
 *   only SELECT grants: GRANT SELECT ON ship_db.* TO 'rag_ro'@'%').
 * • Result rows are capped at 100.
 *
 * Add DB_READONLY_USER / DB_READONLY_PASS to .env to enable a dedicated
 * read-only user; falls back to main credentials if not set.
 */
class SqlAgentService
{
    private const READ_ONLY_CONNECTION = 'mysql_readonly';

    private const ROW_LIMIT = 100;

    /** @var string[] DML/DDL keywords that are never allowed */
    private const BLOCKED_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE',
        'GRANT', 'REVOKE', 'CREATE', 'COPY', 'EXECUTE', 'REPLACE',
        'CALL', 'LOAD', 'HANDLER', 'LOCK', 'UNLOCK', 'SET',
    ];

    /**
     * Whitelist of tables the SQL agent is allowed to query.
     * Any generated SQL referencing tables outside this list is rejected.
     *
     * @var string[]
     */
    private const ALLOWED_TABLES = [
        'drivers', 'trips', 'vehicles', 'invoices',
        'payrolls', 'payroll_lines', 'violations',
        'offices', 'customers', 'vehicle_expenses',
        'leave_requests', 'overtime_requests',
        'driver_work_schedules', 'trip_bonus_rules',
        'positions', 'departments', 'companies',
    ];

    public function __construct(
        private readonly GeminiService $geminiService,
    ) {}

    /**
     * Answer a quantitative Vietnamese question using generated SQL.
     *
     * Returns a human-readable string (suitable for the agent's tool result).
     */
    public function answer(string $question, ?int $companyId = null): string
    {
        if (trim($question) === '') {
            return 'Câu hỏi trống.';
        }

        // Refuse to run if readonly credentials are not separately configured —
        // falling back to the main (write-capable) user is never acceptable.
        if (! $this->isReadonlyConnectionConfigured()) {
            Log::error('SqlAgentService: readonly connection not configured, refusing query');

            return 'Tính năng SQL agent chưa được cấu hình đúng (thiếu DB_READONLY_USER).';
        }

        try {
            $sql = $this->generateSql($question, $companyId);

            if (! $this->isSafeSql($sql)) {
                Log::warning('SqlAgentService: unsafe SQL rejected', ['sql' => $sql, 'company_id' => $companyId]);

                return 'Query không an toàn, đã bị từ chối.';
            }

            if (! $this->isAllowedTables($sql)) {
                Log::warning('SqlAgentService: disallowed table reference rejected', ['sql' => $sql, 'company_id' => $companyId]);

                return 'Query tham chiếu bảng không được phép, đã bị từ chối.';
            }

            if ($companyId !== null && ! $this->hasTenantFilter($sql, $companyId)) {
                Log::error('SqlAgentService: generated SQL missing tenant filter — rejecting', ['sql' => $sql, 'company_id' => $companyId]);

                return 'Query thiếu điều kiện tenant, đã bị từ chối.';
            }

            Log::info('SqlAgentService: executing query', ['sql' => $sql, 'company_id' => $companyId]);

            // Wrap in a subquery so LIMIT 100 can never be comment-stripped by the LLM
            $safeQuery = sprintf('SELECT * FROM (%s) AS __safe_wrap LIMIT %d', $sql, self::ROW_LIMIT);

            $rows = DB::connection(self::READ_ONLY_CONNECTION)->select($safeQuery);

            if (empty($rows)) {
                return "Không có dữ liệu phù hợp với câu hỏi.\nQuery đã chạy: {$sql}";
            }

            $rowsArray = array_map(fn ($row) => (array) $row, $rows);

            return sprintf(
                "Query: %s\n\nKết quả (%d dòng):\n%s",
                $sql,
                count($rowsArray),
                json_encode($rowsArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            );
        } catch (Throwable $e) {
            Log::warning('SqlAgentService: error', ['question' => $question, 'error' => $e->getMessage()]);

            return 'Lỗi khi truy vấn dữ liệu.';  // Never expose DB error details to the agent output
        }
    }

    private function isReadonlyConnectionConfigured(): bool
    {
        $roUser = (string) config('database.connections.mysql_readonly.username', '');
        $mainUser = (string) config('database.connections.mysql.username', '');

        // If both are empty or identical, the readonly connection was never configured
        return $roUser !== '' && $roUser !== $mainUser;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function generateSql(string $question, ?int $companyId): string
    {
        $schema = $this->getSchemaDescription();

        $systemPrompt = implode("\n", [
            'Bạn là chuyên gia MySQL. Sinh DUY NHẤT một câu SELECT hợp lệ.',
            'Không giải thích. Không dùng markdown. Chỉ trả về câu SQL thuần túy.',
            'Không dùng INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE/GRANT/SET.',
            $companyId !== null ? "QUAN TRỌNG: Luôn thêm điều kiện company_id = {$companyId} vào mọi bảng để đảm bảo tenant isolation." : '',
            '',
            'Schema hệ thống Company Ship (vận tải):',
            $schema,
        ]);

        $result = $this->geminiService->generateContent(
            [
                'system' => $systemPrompt,
                'user' => $question,
                'turns' => [],
            ],
            [
                'generation_config' => [
                    'temperature' => 0.0,
                    'topP' => 0.1,
                    'maxOutputTokens' => 300,
                ],
            ],
        );

        $sql = trim((string) ($result['text'] ?? ''));

        // Strip markdown fences if model wraps output
        $sql = (string) preg_replace('/^```(?:sql)?\s*/i', '', $sql);
        $sql = (string) preg_replace('/\s*```$/m', '', $sql);

        return trim($sql, " \n\t;");
    }

    /**
     * Verify that all table references in the SQL are in the allowed whitelist.
     * Prevents the LLM from querying sensitive tables (users, audit_logs, etc.).
     */
    private function isAllowedTables(string $sql): bool
    {
        preg_match_all('/\b(?:FROM|JOIN)\s+`?(\w+)`?/i', $sql, $matches);
        foreach ($matches[1] as $table) {
            $table = strtolower(trim($table, '`'));
            if ($table === '__safe_wrap') {
                continue;
            }
            if (! in_array($table, self::ALLOWED_TABLES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Post-generation check: the SQL must contain a company_id = X filter.
     * This is a hard guard against prompt injection bypassing the system prompt instruction.
     */
    private function hasTenantFilter(string $sql, int $companyId): bool
    {
        return preg_match('/\bcompany_id\s*=\s*'.$companyId.'\b/i', $sql) === 1;
    }

    private function isSafeSql(string $sql): bool
    {
        // Reject SQL that contains any comment syntax — comments can be used to bypass
        // keyword checks or comment-out the LIMIT we append.
        if (preg_match('/--|\/\*|\*\/|#/', $sql) === 1) {
            return false;
        }

        // Strip any remaining whitespace noise before keyword scan
        $upper = strtoupper(ltrim($sql));

        if (! str_starts_with($upper, 'SELECT')) {
            return false;
        }

        foreach (self::BLOCKED_KEYWORDS as $kw) {
            // Word-boundary check to avoid false positives (e.g. 'SET' inside 'RESET')
            if (preg_match('/\b'.preg_quote($kw, '/').'\b/', $upper) === 1) {
                return false;
            }
        }

        // No semicolons — prevents stacked/multi-statement attacks
        if (str_contains($sql, ';')) {
            return false;
        }

        return true;
    }

    /**
     * Schema description — written in Vietnamese for better accuracy.
     * Cache forever; clear with Cache::forget('sql_agent_schema').
     */
    private function getSchemaDescription(): string
    {
        return Cache::rememberForever('sql_agent_schema', fn (): string => <<<'SCHEMA'
Bảng drivers: tài xế
  - id, code (mã tài xế), name (tên), phone, email, status (active/inactive)
  - company_id, office_id (văn phòng), license_class (hạng bằng lái)
  - expired_date (hạn bằng lái), driver_insurance_expired_date, health_certificate_expired_date
  - available_status (available/on_trip/off_duty)

Bảng trips: chuyến xe
  - id, code (mã chuyến), company_id, driver_id (FK drivers), vehicle_id (FK vehicles)
  - status (pending/in_progress/completed/cancelled)
  - start_point, end_point, distance_km, price
  - start_time, end_time

Bảng vehicles: xe
  - id, plate_number (biển số), type, brand, status (active/maintenance/inactive)
  - company_id, capacity (tải trọng tấn)

Bảng invoices: hóa đơn
  - id, trip_id (FK trips), total_amount (số tiền), status (pending/paid/cancelled)
  - issued_at, paid_at

Bảng payrolls: bảng lương (cấp độ tháng)
  - id, company_id, month (1-12), year, status (draft/locked/approved/paid)

Bảng payroll_lines: chi tiết lương từng tài xế
  - id, payroll_id (FK payrolls), driver_id (FK drivers), company_id
  - base_salary, trip_bonus, allowance, deduction, tax, net_salary
  - working_days, trips_completed_count, total_distance_km

Bảng violations: vi phạm
  - id, company_id, driver_id (FK drivers), type, description
  - penalty_amount, status (pending/confirmed/resolved/disputed)
  - occurred_at

Bảng offices: văn phòng / chi nhánh
  - id, code, name, company_id, address

Bảng customers: khách hàng
  - id, name, phone, email, company_id

Bảng vehicle_expenses: chi phí xe
  - id, company_id, vehicle_id, driver_id, type (fuel/maintenance/other)
  - amount, expense_date, note

Ví dụ:
-- Tổng doanh thu tháng 4/2026:
SELECT SUM(i.total_amount) AS doanh_thu FROM invoices i JOIN trips t ON t.id = i.trip_id WHERE t.company_id = 1 AND t.status = 'completed' AND YEAR(t.end_time) = 2026 AND MONTH(t.end_time) = 4

-- Top 5 tài xế nhiều chuyến nhất tháng này:
SELECT d.name, COUNT(t.id) AS so_chuyen FROM drivers d JOIN trips t ON t.driver_id = d.id WHERE d.company_id = 1 AND t.status = 'completed' AND YEAR(t.end_time) = YEAR(NOW()) AND MONTH(t.end_time) = MONTH(NOW()) GROUP BY d.id, d.name ORDER BY so_chuyen DESC LIMIT 5

-- Vi phạm chưa xử lý:
SELECT d.name, v.type, v.penalty_amount, v.occurred_at FROM violations v JOIN drivers d ON d.id = v.driver_id WHERE v.company_id = 1 AND v.status = 'pending' ORDER BY v.occurred_at DESC
SCHEMA);
    }
}
