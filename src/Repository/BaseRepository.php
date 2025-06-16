<?php

namespace App\Repository;

use App\Exceptions\DatabaseException;
use App\Logs\Logger;
use App\Providers\DatabaseServiceProvider;
use App\Utils\DBConnectionPool;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityRepository;

abstract class BaseRepository extends EntityRepository
{
    protected DBConnectionPool $connectionPool;
    private int $maxRetries = 3;
    private Logger $logger;

    public function __construct($em, $class)
    {
        parent::__construct($em, $class);
        $this->connectionPool = DBConnectionPool::getInstance();
        $this->logger = Logger::getInstance('base-repository');
    }

    protected function executeInTransaction(callable $callback)
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            $connection = null;
            $em = null;

            try {
                // 获取连接
                $connection = $this->connectionPool->getConnection();

                // 获取新的 EntityManager，并确保使用正确的连接
                $em = DatabaseServiceProvider::createEntityManager($connection);

                // 开始事务
                if (! $connection->isTransactionActive()) {
                    $connection->beginTransaction();
                }

                // 执行回调
                $result = $callback($em);

                // 提交事务
                if ($connection->isTransactionActive()) {
                    $em->flush();
                    $connection->commit();
                }

                return $result;
            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;

                // 回滚事务
                if ($connection && $connection->isTransactionActive()) {
                    try {
                        $connection->rollBack();
                    } catch (\Exception $rollbackException) {
                        // 记录回滚失败，但继续处理原始异常
                        $this->logger->error('Failed to roll back transaction: ' . $rollbackException->getMessage());
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
                // 清理资源
                if ($em) {
                    $em->clear();
                    $em->close();
                }

                // 归还连接
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
        $connection = null;
        $em = null;

        try {
            // 获取连接
            $connection = $this->connectionPool->getConnection();

            // 创建新的 EntityManager
            $em = DatabaseServiceProvider::createEntityManager($connection);

            return $callback($em);
        } catch (DBALException $e) {
            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        } finally {
            if ($em) {
                $em->clear();
                $em->close();
            }
            if ($connection) {
                $this->connectionPool->releaseConnection($connection);
            }
        }
    }
}
