<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

trait DoctrineConnection
{
    protected function getConnection(): Connection
    {
        return $this->conn ??= $this->createConnection();
    }

    protected function createConnection(): Connection
    {
        $connectionParams = [
            //'dbname' => 'rekodi',
            'user' => 'root',
            'password' => 'test',
            'host' => 'db',
            'driver' => 'pdo_mysql',
        ];
        return DriverManager::getConnection($connectionParams);
    }

    public function resetDatabase(string $name): void
    {
        $conn = $this->getConnection();

        $sm = $conn->createSchemaManager();
        $sm->dropAndCreateDatabase($name);

        // This line may be MySQL-specific.
        $conn->executeQuery("USE " . $name);
    }
}
