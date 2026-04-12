#!/usr/bin/env bash
# One-shot removal of Lark/Feishu integration code (already applied in repo).
# Safe to re-run: ignores missing paths.

set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

rm -rf \
  app/Services/Lark \
  app/Jobs/Lark \
  app/Listeners/Lark \
  app/Events/Lark

rm -f \
  app/Http/Controllers/Api/LarkWebhookController.php \
  app/Http/Requests/Lark/LarkWebhookRequest.php \
  app/Models/LarkEventLog.php \
  app/Console/Commands/LarkBaseReconcileCommand.php \
  app/Console/Commands/LarkBaseReverseSyncCommand.php \
  config/lark.php \
  database/migrations/2026_04_10_210000_add_lark_user_id_to_users_table.php \
  database/migrations/2026_04_10_210100_create_lark_event_logs_table.php \
  tests/Feature/Api/LarkWebhookTest.php \
  tests/Feature/Services/SendLarkMessageJobTest.php \
  tests/Feature/Services/LarkCommandRouterServiceTest.php \
  tests/Feature/Services/LarkBaseSyncServiceTest.php

rmdir app/Http/Requests/Lark 2>/dev/null || true

echo "Lark PHP files removed. Manual follow-up (if not already done):"
echo "  - routes/api.php: remove POST /lark/webhook routes"
echo "  - app/Providers/AppServiceProvider.php: remove Lark listeners + model observers"
echo "  - app/Models/User.php: remove lark_user_id from \$fillable"
echo "  - .env.example: remove LARK_* variables"
echo "  - php artisan migrate   # runs database/migrations/2026_04_16_000000_remove_lark_integration.php"
