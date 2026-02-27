<?php

declare(strict_types=1);

namespace Src\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Contracts\TransactionManagerInterface;

final class TransactionManager implements TransactionManagerInterface
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function execute(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function begin(): void
    {
        DB::beginTransaction();
    }

    public function commit(): void
    {
        DB::commit();
    }

    public function rollback(): void
    {
        DB::rollBack();
    }
}
