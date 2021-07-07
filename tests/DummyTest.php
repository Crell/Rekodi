<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use \PHPUnit\Framework\TestCase;

/**
 * Scratch space before I know what the real architecture is.
 */
class DummyTest extends TestCase
{
    /**
     * @test
     */
    public function stuff(): void
    {

        $dbh = new \PDO('mysql:host=db;port=3306;dbname=rekodi', 'root', 'test');

        $result = $dbh->query("SELECT 1");
        $one = $result->fetchColumn();

        /*
        $connectionParams = array(
            'dbname' => 'mydb',
            'user' => 'root',
            'password' => 'test',
            'host' => 'db',
            'driver' => 'pdo_mysql',
        );
        $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams);
        $result = $conn->executeQuery("SELECT 1");
        $one = $result->fetchOne();
        */
        self::assertSame(1, $one);
    }
}
