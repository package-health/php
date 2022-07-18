<?php
declare(strict_types = 1);

use Phinx\Db\Table\ForeignKey;
use Phinx\Migration\AbstractMigration;

final class Stats extends AbstractMigration {
  public function change(): void {
    $stats = $this->table('stats');
    $stats
      ->addColumn('package_id', 'integer', ['null' => false])
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
