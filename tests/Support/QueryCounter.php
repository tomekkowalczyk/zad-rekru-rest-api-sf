<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Test-only DBAL middleware counting the statements sent to the database, to
 * pin down query budgets (N+1 regressions).
 *
 * The counter is static on purpose: DAMA keeps one physical connection for the
 * whole run, so the wrapper created by the first kernel outlives the others.
 */
final class QueryCounter implements Middleware
{
    private static int $count = 0;

    public static function reset(): void
    {
        self::$count = 0;
    }

    public static function count(): int
    {
        return self::$count;
    }

    public static function increment(): void
    {
        ++self::$count;
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                return new class(parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function prepare(string $sql): Statement
                    {
                        QueryCounter::increment();

                        return parent::prepare($sql);
                    }

                    public function query(string $sql): Result
                    {
                        QueryCounter::increment();

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        QueryCounter::increment();

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
