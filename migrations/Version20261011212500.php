<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * DATETIME columns carry no zone. Until now every process wrote its own PHP
 * default zone into them; from here on `UtcDateTimeImmutableType` always writes
 * UTC. Rows written by the web/CLI container are rewritten from that
 * container's zone — the one this migration runs in. `operation_log` is left
 * alone: only the worker, which already ran in UTC, writes it.
 */
final class Version20261011212500 extends AbstractMigration
{
    /** @var array<string, list<string>> */
    private const array COLUMNS = [
        'app_user' => ['created_at', 'updated_at'],
        'category' => ['created_at', 'updated_at'],
        'product' => ['created_at', 'updated_at'],
        'messenger_messages' => ['created_at', 'available_at'],
    ];

    public function getDescription(): string
    {
        return 'Store every timestamp in UTC: convert rows written in the web container\'s local time zone.';
    }

    public function up(Schema $schema): void
    {
        $this->convert(date_default_timezone_get(), 'UTC');
    }

    public function down(Schema $schema): void
    {
        $this->convert('UTC', date_default_timezone_get());
    }

    private function convert(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $this->abortIf(
            null === $this->connection->fetchOne('SELECT CONVERT_TZ(NOW(), ?, ?)', [$from, $to]),
            \sprintf('MySQL time zone tables do not know "%s"; load them (mysql_tzinfo_to_sql) and retry.', $from),
        );

        foreach (self::COLUMNS as $table => $columns) {
            $assignments = implode(', ', array_map(
                static fn (string $column): string => \sprintf('%1$s = CONVERT_TZ(%1$s, :from, :to)', $column),
                $columns,
            ));
            // Messages already handed to a worker were re-stamped by it in UTC.
            $where = 'messenger_messages' === $table ? ' WHERE delivered_at IS NULL' : '';

            $this->addSql(\sprintf('UPDATE %s SET %s%s', $table, $assignments, $where), ['from' => $from, 'to' => $to]);
        }
    }
}
