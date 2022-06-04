<?php
declare(strict_types = 1);

if (is_file(__DIR__ . '/.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
  $dotenv->safeLoad();
}

return [
  'paths' => [
    'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
    'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
  ],
  'environments' => [
    'default_migration_table' => 'phinxlog',
    'default_environment' => 'dev',
    'prod' => [
      'dsn' => "pgsql://{$_ENV['POSTGRES_USER']}:{$_ENV['POSTGRES_PASSWORD']}@{$_ENV['POSTGRES_HOST']}/{$_ENV['POSTGRES_DB']}"
    ],
    'dev' => [
      'dsn' => "pgsql://{$_ENV['POSTGRES_USER']}:{$_ENV['POSTGRES_PASSWORD']}@{$_ENV['POSTGRES_HOST']}/{$_ENV['POSTGRES_DB']}"
    ],
    'test' => []
  ],
  'version_order' => 'creation'
];
