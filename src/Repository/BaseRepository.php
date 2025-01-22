<?php

namespace App\Repository;

use App\Exceptions\DatabaseException;
use App\Utils\DBConnectionPool;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityRepository;

abstract class BaseRepository extends EntityRepository
{
    protected DBConnectionPool $connectionPool;
    private int $maxRetries = 3;

    public function __construct($em, $class)
    {
        parent::__construct($em, $class);
        $this->connectionPool = DBConnectionPool::getInstance();
    }

    protected function executeInTransaction(callable $callback)
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            $connection = null;

            try {
                $connection = $this->connectionPool->getConnection();

                if (! $connection->isTransactionActive()) {
                    $connection->beginTransaction();
                }

                $result = $callback($this->getEntityManager());

                if ($connection->isTransactionActive()) {
                    $connection->commit();
                }

                return $result;
            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;

                if ($connection && $connection->isTransactionActive()) {
                    try {
                        $connection->rollBack();
                    } catch (\Exception $rollbackException) {
                        // 记录回滚失败，但继续处理原始异常
                    }
                }

                if ($attempts >= $this->maxRetries) {
                    throw new DatabaseException(
                        "Transaction failed after {$this->maxRetries} attempts: " . $e->getMessage(),
                        0,
                        $e
                    );
                }

                // 递增延迟重试
                usleep(100000 * $attempts);
            } finally {
                if ($connection) {
                    $this->connectionPool->releaseConnection($connection);
                }
            }
        }

        throw new DatabaseException(
            "Transaction failed after {$this->maxRetries} attempts: " .
                ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    protected function executeSafely(callable $callback)
    {
        try {
            return $callback($this->getEntityManager());
        } catch (DBALException $e) {
            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
