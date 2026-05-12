<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_paid' => $this->is_paid,
            'annual_quota_days' => $this->annual_quota_days,
            'allow_carry_forward' => $this->allow_carry_forward,
            'requires_attachment' => $this->requires_attachment,
            'status' => $this->status,
        ];
    }
}