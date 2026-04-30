<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PriceListItem extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'price_list_id',
        'route_template_id',
        'vehicle_type_id',
        'cargo_type_id',
        'price',
        'price_unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'price_list_id' => 'integer',
            'route_template_id' => 'integer',
            'vehicle_type_id' => 'integer',
            'cargo_type_id' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
