<?php

declare(strict_types=1);

namespace App\Tenancy;

/**
 * Holds the resolved tenant scope for the current request.
 *
 * company_id: always set for authenticated requests (sentinel -1 when unresolvable).
 * office_id:  set only for office_admin users — restricts queries to one office.
 */
final class TenantContext
{
    private ?int $companyId = null;

    private ?int $officeId = null;

    public function setCompanyId(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function setOfficeId(?int $officeId): void
    {
        $this->officeId = $officeId;
    }

    public function getOfficeId(): ?int
    {
        return $this->officeId;
    }

    public function reset(): void
    {
        $this->companyId = null;
        $this->officeId  = null;
    }
}
