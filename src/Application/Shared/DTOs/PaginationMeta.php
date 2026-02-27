<?php

declare(strict_types=1);

namespace Src\Application\Shared\DTOs;

final readonly class PaginationMeta
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        public int $from,
        public int $to
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
