<?php
declare(strict_types = 1);

use Phinx\Migration\AbstractMigration;

final class Packages extends AbstractMigration {
  public function change(): void {
    $packages = $this->table('packages');
    $packages
      ->addColumn('name', 'text', ['null' => false])
      ->addColumn('description', 'text', ['null' => false])
      ->addColumn('latest_version', 'text', ['null' => false])
      ->addColumn('url', 'text', ['null' => false])
      ->addTimestampsWithTimezone()
      ->addIndex('name')
      ->addIndex('created_at')
      ->create();
  }
}
