<?php
declare(strict_types = 1);

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
      ->addColumn('number', 'text', ['null' => false])
      ->addColumn('normalized', 'text', ['null' => false])
      ->addColumn('package_name', 'text', ['null' => false])
      // ->addColumn('status', 'versionStatus', ['null' => false, 'default' => 'unknown'])
      ->addColumn('release', 'boolean', ['null' => false, 'default' => false])
      ->addColumn('status', 'text', ['null' => false, 'default' => 'unknown'])
      ->addTimestampsWithTimezone()
      ->addIndex('number')
      ->addIndex('package_name')
      ->addIndex('created_at')
      ->addIndex(['number', 'package_name'], ['unique' => true])
      ->addForeignKey(
        'package_name',
        'packages',
        'name',
        [
          'delete' => 'CASCADE',
          'update' => 'NO_ACTION'
        ]
      )
      ->create();
  }
}
