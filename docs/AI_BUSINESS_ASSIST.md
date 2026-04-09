# AI Business Assist (Gemini)

## Mục tiêu
Endpoint AI giúp phân tích vận hành theo dữ liệu thực tế từ hệ thống (`reports/dashboard`, `payroll summary`) để đưa ra:
- insight ưu tiên cao
- action plan theo mức P1/P2/P3
- cảnh báo rủi ro + phương án giảm thiểu

## Endpoint
- `POST /api/ai/business-assist`
- Middleware: `auth:sanctum`, `role:admin`

## Request body
```json
{
  "task": "dashboard_insight",
  "company_id": 1,
  "month": 4,
  "year": 2026,
  "language": "vi",
  "tone": "executive",
  "question": "Nên ưu tiên tối ưu gì trong 7 ngày tới?",
  "context": {
    "target_trips_completion": 0.92,
    "max_payroll_growth": 0.1
  }
}
```

### task hỗ trợ
- `dashboard_insight`
- `payroll_analysis`
- `trip_optimization`
- `risk_alert`
- `recommendation`

## Biến môi trường
Thêm vào `.env`:

- `GEMINI_API_KEY=...`
- `GEMINI_MODEL=gemini-2.0-flash`
- `GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta`

## Gợi ý vận hành để tận dụng tối đa
1. Lịch chạy định kỳ (cron) gọi endpoint hằng ngày cho từng công ty.
2. Gửi kết quả `actions` vào task board nội bộ (Ops/HR/Finance).
3. Theo dõi KPI thực tế theo `expected_kpi` để feedback loop tuần kế tiếp.
4. Dùng `context` để đưa ngưỡng kinh doanh (chi phí, SLA, năng suất) thay vì hỏi chung chung.
