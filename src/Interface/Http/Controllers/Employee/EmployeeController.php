<?php

declare(strict_types=1);

namespace Src\Interface\Http\Controllers\Employee;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Application\Employee\DTOs\CreateEmployeeData;
use Src\Application\Employee\UseCases\CreateEmployeeUseCase;
use Src\Application\Employee\UseCases\DeleteEmployeeUseCase;
use Src\Application\Employee\UseCases\GetEmployeeUseCase;
use Src\Application\Employee\UseCases\ListEmployeesUseCase;
use Src\Application\Employee\UseCases\UpdateEmployeeUseCase;
use Src\Interface\Http\Controllers\Controller;
use Src\Interface\Http\Requests\Employee\CreateEmployeeRequest;
use Src\Interface\Http\Requests\Employee\ListEmployeesRequest;
use Src\Interface\Http\Requests\Employee\UpdateEmployeeRequest;
use Src\Interface\Http\Resources\Employee\EmployeeResource;

final class EmployeeController extends Controller
{
    public function __construct(
        private readonly CreateEmployeeUseCase $createEmployeeUseCase,
        private readonly GetEmployeeUseCase $getEmployeeUseCase,
        private readonly ListEmployeesUseCase $listEmployeesUseCase,
        private readonly UpdateEmployeeUseCase $updateEmployeeUseCase,
        private readonly DeleteEmployeeUseCase $deleteEmployeeUseCase
    ) {}

    public function index(ListEmployeesRequest $request): JsonResponse
    {
        $result = $this->listEmployeesUseCase->execute(
            $request->toListCriteria()
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $result->items,
            'meta' => $result->meta->toArray(),
        ]);
    }

    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        $result = $this->createEmployeeUseCase->execute(
            CreateEmployeeData::fromArray($request->validated())
        );

        return EmployeeResource::make($result)
            ->additional([
                'success' => true,
                'message' => 'Employee created successfully',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $employee): JsonResponse
    {
        $result = $this->getEmployeeUseCase->execute($employee);

        return EmployeeResource::make($result)
            ->additional([
                'success' => true,
                'message' => 'OK',
            ])
            ->response();
    }

    public function update(UpdateEmployeeRequest $request, string $employee): JsonResponse
    {
        $result = $this->updateEmployeeUseCase->execute(
            $employee,
            $request->toUpdateData()
        );

        return EmployeeResource::make($result)
            ->additional([
                'success' => true,
                'message' => 'Employee updated successfully',
            ])
            ->response();
    }

    public function destroy(string $employee): JsonResponse
    {
        $this->deleteEmployeeUseCase->execute($employee);

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
        ]);
    }
}
