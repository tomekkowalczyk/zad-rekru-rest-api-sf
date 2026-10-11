<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008180302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: catalog (product, category), security (app_user), notification audit trail (operation_log) and the Doctrine based message queue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'Creation timestamp (UTC)\', updated_at DATETIME NOT NULL COMMENT \'Last modification timestamp (UTC)\', UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id BINARY(16) NOT NULL, code VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL COMMENT \'Creation timestamp (UTC)\', updated_at DATETIME NOT NULL COMMENT \'Last modification timestamp (UTC)\', UNIQUE INDEX UNIQ_64C19C177153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE operation_log (id BINARY(16) NOT NULL, type VARCHAR(100) NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, context JSON NOT NULL, logged_at DATETIME NOT NULL, INDEX idx_operation_log_type (type), INDEX idx_operation_log_logged_at (logged_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'Creation timestamp (UTC)\', updated_at DATETIME NOT NULL COMMENT \'Last modification timestamp (UTC)\', price_amount INT UNSIGNED NOT NULL COMMENT \'Amount in minor units\', price_currency VARCHAR(3) NOT NULL COMMENT \'ISO 4217 currency code\', PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_category (product_id BINARY(16) NOT NULL, category_id BINARY(16) NOT NULL, INDEX IDX_CDFC73564584665A (product_id), INDEX IDX_CDFC735612469DE2 (category_id), PRIMARY KEY (product_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC73564584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC735612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC73564584665A');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC735612469DE2');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE operation_log');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
