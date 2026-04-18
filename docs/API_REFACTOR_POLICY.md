# API refactor policy (Master Prompt alignment)

## JsonResource

- **Default:** Giữ nguyên cấu trúc `data` JSON hiện tại (model/array/pagination) để không phá FE.
- **JsonResource:** Chỉ thêm khi endpoint mới hoặc khi đã có kế hoạch versioning / đồng bộ ship-app.

## Form Request

- Không dùng `$request->validate([...])` trong controller API; validation nằm trong `app/Http/Requests`.
- Kiểm tra tự động: `tests/Arch/ApiControllersValidationArchitectureTest.php`.

## Checklist: validate inline (đã refactor)

| Module | Ghi chú |
|--------|---------|
| Driver schedules | `IndexScheduleRequest`, `ApproveScheduleRequest`, `OverrideScheduleRequest` + `ScheduleService` |
| Workforce | `Workforce*Request` + `WorkforceService` |
| Chat | `GetChatMessagesRequest`, `GetChatSessionsRequest` (đã có sẵn) |
| Public holidays | `IndexPublicHolidayRequest` |
| Trips assign | `AssignTripRequest` |
| Attendance | `CheckOutAttendanceRequest`, `NotifyLateAttendanceRequest` |
| Leave / Overtime reject | `RejectLeaveRequest`, `RejectOvertimeRequest` |
| Violations | `ResolveViolationDisputeRequest`, `WaiveViolationRequest` |
| Payroll adjustments | `Store/Update/RejectPayrollAdjustmentRequest` |
| Payroll driver monthly | `DriverMonthlySalaryRequest` |
| Auth logs/actions | `AuthLogsRequest`, `AuthActionsRequest` |
