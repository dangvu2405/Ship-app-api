<?php

declare(strict_types=1);

namespace Src\Application\Shared\DTOs;

final readonly class PaginatedResult
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        public array $items,
        public PaginationMeta $meta
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => $this->meta->toArray(),
        ];
    }
}
