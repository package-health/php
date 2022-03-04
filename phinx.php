<?php

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
    'default_environment' => 'development',
    'production' => [
      'dsn' => "pgsql://${_ENV['POSTGRES_USER']}:${_ENV['POSTGRES_PASSWORD']}@${_ENV['POSTGRES_HOST']}/${_ENV['POSTGRES_DB']}"
    ],
    'development' => [
      'dsn' => "pgsql://${_ENV['POSTGRES_USER']}:${_ENV['POSTGRES_PASSWORD']}@${_ENV['POSTGRES_HOST']}/${_ENV['POSTGRES_DB']}"
    ],
    'testing' => []
  ],
  'version_order' => 'creation'
];
