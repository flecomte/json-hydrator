<?php

namespace FLE\JsonHydrator\Database;

use PDO;
use PDOException;

/**
 * This trait extends native PDO one but allow nested transactions
 * by using the SQL statements `SAVEPOINT', 'RELEASE SAVEPOINT' AND 'ROLLBACK SAVEPOINT'.
 */
trait NestedTransactionPDO
{
    /**
     * @var array database drivers that support SAVEPOINT * statements
     */
    protected static array $_supportedDrivers = ['pgsql', 'mysql'];

    /**
     * @var int the current transaction depth
     */
    protected int $transactionDepth = 0;

    abstract public function getAttribute($attribute);

    /**
     * Test if database driver support savepoints.
     *
     * @return bool
     */
    protected function hasSavepoint()
    {
        return in_array($this->getAttribute(PDO::ATTR_DRIVER_NAME),
            self::$_supportedDrivers);
    }

    /**
     * Start transaction.
     *
     * @return bool|void
     */
    public function beginTransaction()
    {
        if ($this->transactionDepth == 0 || !$this->hasSavepoint()) {
            parent::beginTransaction();
        } else {
            $this->exec("SAVEPOINT LEVEL{$this->transactionDepth}");
        }

        ++$this->transactionDepth;
    }

    /**
     * Commit current transaction.
     *
     * @return bool|void
     */
    public function commit()
    {
        --$this->transactionDepth;

        if ($this->transactionDepth == 0 || !$this->hasSavepoint()) {
            parent::commit();
        } else {
            $this->exec("RELEASE SAVEPOINT LEVEL{$this->transactionDepth}");
        }
    }

    /**
     * Rollback current transaction,.
     *
     * @throws PDOException if there is no transaction started
     *
     * @return bool|void
     */
    public function rollBack()
    {
        if ($this->transactionDepth == 0) {
            throw new PDOException('Rollback error : There is no transaction started');
        }

        --$this->transactionDepth;

        if ($this->transactionDepth == 0 || !$this->hasSavepoint()) {
            parent::rollBack();
        } else {
            $this->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->transactionDepth}");
        }
    }
}
