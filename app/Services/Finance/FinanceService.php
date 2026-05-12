<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FinanceService extends BaseService
{
    public function priceLookup(Request $request): ?array
    {
        $query = $this->scopedQuery('price_list_items');
        foreach (['route_template_id', 'vehicle_type_id', 'cargo_type_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('customer_id')) {
            $query->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
                ->where('price_lists.customer_id', $request->input('customer_id'));
        }
        $item = $query->select('price_list_items.*')->orderByDesc('price_list_items.id')->first();

        return $item ? [
            'price' => $item->price,
            'price_unit' => $item->price_unit,
            'price_list_id' => $item->price_list_id,
        ] : null;
    }

    public function debtOverview(): array
    {
        $companyId = $this->companyId();
        return DB::table('customers')
            ->leftJoin('trips', 'trips.customer_id', '=', 'customers.id')
            ->where('customers.company_id', $companyId)
            ->groupBy('customers.id', 'customers.name')
            ->selectRaw('customers.id, customers.name, COALESCE(SUM(CASE WHEN trips.payment_status != "paid" THEN trips.price ELSE 0 END),0) as debt')
            ->get()
            ->toArray();
    }

    public function afterStore(string $table, int|string $id, array $payload): void
    {
        if ($table !== 'trip_costs') {
            return;
        }
        $threshold = DB::table('cost_categories')->where('id', $payload['cost_category_id'] ?? null)->value('approval_threshold');
        $amount = (float) ($payload['amount'] ?? 0);
        if ($threshold !== null && $amount > (float) $threshold) {
            DB::table('trip_costs')->where('id', $id)->update(['approval_required' => true, 'status' => 'pending']);
            DB::table('cost_approval_requests')->insert([
                'company_id' => $payload['company_id'] ?? $this->companyId(),
                'trip_id' => $payload['trip_id'] ?? 1,
                'requested_by' => auth()->id() ?? 1,
                'total_amount' => $amount,
                'reason' => $payload['description'] ?? 'Cost exceeds approval threshold',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
