<?php

declare(strict_types=1);

namespace App\Tenancy;

final class TenantContext
{
    private ?int $companyId = null;

    public function setCompanyId(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function reset(): void
    {
        $this->companyId = null;
    }
}
