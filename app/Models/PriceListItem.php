<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PriceListItem extends CetaModel
{
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function routeTemplate(): BelongsTo
    {
        return $this->belongsTo(RouteTemplate::class);
    }
}
