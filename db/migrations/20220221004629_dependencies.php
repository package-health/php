<?php
declare(strict_types = 1);

use Phinx\Migration\AbstractMigration;

final class Dependencies extends AbstractMigration {
  public function change(): void {
    // $this->execute(<<<SQL
    //   CREATE TYPE "dependencyStatus"
    //   AS ENUM (
    //     'unknown',
    //     'outdated',
    //     'insecure',
    //     'maybe insecure',
    //     'up to date'
    //   )
    // SQL);

    $dependencies = $this->table('dependencies');
    $dependencies
      ->addColumn('version_id', 'integer', ['null' => false])
      ->addColumn('name', 'text', ['null' => false])
      ->addColumn('constraint', 'text', ['null' => false])
      ->addColumn('development', 'boolean', ['null' => false, 'default' => false])
      // ->addColumn('status', 'dependencyStatus', ['null' => false, 'default' => 'unknown'])
      ->addColumn('status', 'text', ['null' => false, 'default' => 'unknown'])
      ->addTimestampsWithTimezone()
      ->addIndex('version_id')
      ->addIndex('name')
      ->addIndex('development')
      ->addIndex('created_at')
      ->addIndex(['version_id', 'name', 'development'], ['unique' => true])
      ->addForeignKey(
        'version_id',
        'versions',
        'id',
        [
          'delete' => 'CASCADE',
          'update' => 'NO_ACTION'
        ]
      )
      ->create();
  }
}
