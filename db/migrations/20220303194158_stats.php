<?php
declare(strict_types = 1);

use Phinx\Migration\AbstractMigration;

final class Stats extends AbstractMigration {
  public function change(): void {
    $stats = $this->table('stats', ['id' => false, 'primary_key' => 'package_name']);
    $stats
      ->addColumn('package_name', 'text', ['null' => false])
      ->addColumn('github_stars', 'integer', ['null' => false, 'default' => 0])
      ->addColumn('github_watchers', 'integer', ['null' => false, 'default' => 0])
      ->addColumn('github_forks', 'integer', ['null' => false, 'default' => 0])
      ->addColumn('dependents', 'integer', ['null' => false, 'default' => 0])
      ->addColumn('suggesters', 'integer', ['null' => false, 'default' => 0])
      ->addColumn('favers', 'integer', ['null' => false, 'default' => 0])
      ->addColumn('total_downloads', 'integer', ['null' => false, 'default' => 0])
      ->addColumn('monthly_downloads', 'integer', ['null' => false, 'default' => 0])
      ->addColumn('daily_downloads', 'integer', ['null' => false, 'default' => 0])
      ->addTimestampsWithTimezone()
      ->addIndex('total_downloads')
      ->addIndex('monthly_downloads')
      ->addIndex('daily_downloads')
      ->addIndex('created_at')
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
