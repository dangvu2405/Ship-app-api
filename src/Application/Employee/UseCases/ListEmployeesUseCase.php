<?php

declare(strict_types=1);

namespace Src\Application\Employee\UseCases;

use Src\Application\Employee\DTOs\ListEmployeesCriteria;
use Src\Application\Shared\DTOs\PaginatedResult;
use Src\Domain\Employee\Repositories\EmployeeQueryRepositoryInterface;

final readonly class ListEmployeesUseCase
{
    public function __construct(
        private EmployeeQueryRepositoryInterface $employeeQueryRepository
    ) {}

    public function execute(ListEmployeesCriteria $criteria): PaginatedResult
    {
        return $this->employeeQueryRepository->findAllPaginated($criteria);
    }
}
