<?php

declare(strict_types=1);

namespace App\Http\Resources\Generated;

use Illuminate\Http\Resources\Json\JsonResource;

class DispatchBoardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            // TODO: map resource fields
            'data' => parent::toArray($request),
        ];
    }
}
