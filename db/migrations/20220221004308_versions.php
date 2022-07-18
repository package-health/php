<?php
declare(strict_types = 1);

use Phinx\Db\Table\ForeignKey;
use Phinx\Migration\AbstractMigration;

final class Versions extends AbstractMigration {
  public function change(): void {
    // $this->execute(<<<SQL
    //   CREATE TYPE "versionStatus"
    //   AS ENUM (
    //     'unknown',
    //     'outdated',
    //     'insecure',
    //     'maybe insecure',
    //     'up to date'
    //   )
    // SQL);

    $versions = $this->table('versions');
    $versions
      ->addColumn('package_id', 'integer', ['null' => false])
      ->addColumn('number', 'text', ['null' => false])
      ->addColumn('normalized', 'text', ['null' => false])
      ->addColumn('release', 'boolean', ['null' => false, 'default' => false])
      // ->addColumn('status', 'versionStatus', ['null' => false, 'default' => 'unknown'])
      ->addColumn('status', 'text', ['null' => false, 'default' => 'unknown'])
      ->addTimestampsWithTimezone()
      ->addIndex('package_id')
      ->addIndex('number')
      ->addIndex('created_at')
      ->addIndex(['package_id', 'number'], ['unique' => true])
      ->addForeignKey(
        'package_id',
        'packages',
        'id',
        [
          'delete' => ForeignKey::CASCADE,
          'update' => ForeignKey::NO_ACTION
        ]
      )
      ->create();
  }
}
