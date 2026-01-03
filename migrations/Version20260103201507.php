<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103201507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_911533C8989D9B62 ON conference (slug)');
    }

    public function down(Schema $schema): void
    {
        $conferenceTable = $schema->getTable('conference');

        if ($conferenceTable->hasIndex('uniq_911533c8989d9b62')) {
            $conferenceTable->dropIndex('uniq_911533c8989d9b62');
        }
    }
}
