<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts;

interface TransactionManagerInterface
{
    /**
     * Execute a callback within a database transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function execute(callable $callback): mixed;

    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;
}
