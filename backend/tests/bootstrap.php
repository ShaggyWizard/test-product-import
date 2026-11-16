<?php

declare(strict_types=1);

use App\Config\Settings;
use Doctrine\DBAL\DriverManager;

require dirname(__DIR__) . '/vendor/autoload.php';

/*
 * Готовит тестовую базу: создаёт её, если нет, и накатывает схему из сущностей.
 * Каждый интеграционный тест сам очищает таблицы (IntegrationTestCase::setUp).
 */
$settings = Settings::fromEnv();

$server = DriverManager::getConnection([
    'driver' => 'pdo_pgsql',
    'host' => $settings->dbHost,
    'port' => $settings->dbPort,
    'dbname' => 'postgres',
    'user' => $settings->dbUser,
    'password' => $settings->dbPassword,
]);

$exists = (bool) $server->fetchOne('SELECT 1 FROM pg_database WHERE datname = ?', [$settings->dbName]);
if (!$exists) {
    $server->executeStatement('CREATE DATABASE ' . $server->quoteSingleIdentifier($settings->dbName));
}
$server->close();

App\Tests\Integration\TestDatabase::recreateSchema();
