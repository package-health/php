<?php
declare(strict_types = 1);

use Phinx\Migration\AbstractMigration;

final class Preferences extends AbstractMigration {
  public function change(): void {
    $preferences = $this->table('preferences');
    $preferences
      ->addColumn('category', 'text', ['null' => false])
      ->addColumn('property', 'text', ['null' => false])
      ->addColumn('value', 'text', ['null' => false])
      ->addColumn('type', 'text', ['null' => false])
      ->addTimestampsWithTimezone()
      ->addIndex(['category', 'property'], ['unique' => true])
      ->addIndex('created_at')
      ->create();
  }
}
