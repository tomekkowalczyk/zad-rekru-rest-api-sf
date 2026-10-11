<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011212027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index product.created_at: the collection is ordered by it on every list request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_product_created_at ON product (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_product_created_at ON product');
    }
}
