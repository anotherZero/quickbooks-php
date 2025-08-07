<?php

/**
 * PDO backend for the QuickBooks SOAP server
 *
 * This driver allows using an existing PDO instance as the database
 * connection for QuickBooks. It can also create its own PDO connection
 * from a DSN string.
 */
QuickBooks_Loader::load('/QuickBooks/Driver.php');
QuickBooks_Loader::load('/QuickBooks/Driver/Sql.php', false);
QuickBooks_Loader::load('/QuickBooks/Utilities.php');

class QuickBooks_Driver_Pdo extends QuickBooks_Driver_Sql
{
    /**
     * PDO connection instance
     *
     * @var PDO
     */
    protected $_conn;

    /**
     * Last PDOStatement result
     *
     * @var PDOStatement
     */
    protected $_res;

    /**
     * Log level
     *
     * @var int
     */
    protected $_log_level;

    /**
     * Create a new PDO back-end driver
     *
     * @param  mixed  $dsn_or_conn  A DSN string or existing PDO instance
     * @param  array  $config  Driver configuration
     */
    public function __construct($dsn_or_conn, $config)
    {
        $config = $this->_defaults($config);
        $this->_log_level = (int) $config['log_level'];

        if ($dsn_or_conn instanceof PDO) {
            $this->_conn = $dsn_or_conn;
        } else {
            $defaults = [
                'scheme' => 'mysql',
                'host' => 'localhost',
                'port' => 3306,
                'user' => 'root',
                'pass' => '',
                'path' => '/quickbooks',
            ];

            $parse = QuickBooks_Utilities::parseDSN($dsn_or_conn, $defaults);

            $dsn = $parse['scheme'] . ':host=' . $parse['host'];
            if ($parse['port']) {
                $dsn .= ';port=' . $parse['port'];
            }
            $dsn .= ';dbname=' . ltrim($parse['path'], '/');

            $options = isset($config['pdo_options']) ? $config['pdo_options'] : [];
            $this->_conn = new PDO($dsn, $parse['user'], $parse['pass'], $options);
            $this->_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        parent::__construct($dsn_or_conn, $config);
    }

    /**
     * Merge configuration options with defaults
     *
     * @param  array  $config
     * @return array
     */
    protected function _defaults($config)
    {
        $defaults = [
            'log_level' => QUICKBOOKS_LOG_NORMAL,
            'pdo_options' => [],
        ];

        return array_merge($defaults, $config);
    }

    /**
     * Tell whether or not the SQL driver has been initialized
     *
     * @return bool
     */
    protected function _initialized()
    {
        $required = [
            $this->_mapTableName(QUICKBOOKS_DRIVER_SQL_TICKETTABLE) => false,
            $this->_mapTableName(QUICKBOOKS_DRIVER_SQL_USERTABLE) => false,
            $this->_mapTableName(QUICKBOOKS_DRIVER_SQL_RECURTABLE) => false,
            $this->_mapTableName(QUICKBOOKS_DRIVER_SQL_QUEUETABLE) => false,
            $this->_mapTableName(QUICKBOOKS_DRIVER_SQL_LOGTABLE) => false,
            $this->_mapTableName(QUICKBOOKS_DRIVER_SQL_CONFIGTABLE) => false,
        ];

        $errnum = 0;
        $errmsg = '';
        $res = $this->_query('SHOW TABLES', $errnum, $errmsg);
        if ($res) {
            while ($arr = $this->_fetch($res)) {
                $table = current($arr);
                if (isset($required[$table])) {
                    $required[$table] = true;
                }
            }
        }

        foreach ($required as $exists) {
            if (! $exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute an SQL query
     */
    protected function _query($sql, &$errnum, &$errmsg, $offset = 0, $limit = null)
    {
        if ($limit) {
            $sql .= ' LIMIT ' . (int) $limit;
            if ($offset) {
                $sql .= ' OFFSET ' . (int) $offset;
            }
        } elseif ($offset) {
            $sql .= ' OFFSET ' . (int) $offset;
        }

        $errnum = 0;
        $errmsg = '';

        try {
            $this->_res = $this->_conn->query($sql);

            return $this->_res;
        } catch (PDOException $e) {
            $errnum = (int) $e->getCode();
            $errmsg = $e->getMessage();

            return false;
        }
    }

    /**
     * Fetch a row from a result set
     */
    protected function _fetch($res)
    {
        return $res->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Escape a string for use in SQL queries
     */
    public function escape($str)
    {
        return substr($this->_conn->quote($str), 1, -1);
    }

    /**
     * Fetch a row from a result set
     */
    public function fetch($res)
    {
        return $this->_fetch($res);
    }

    /**
     * Number of rows affected by last query
     */
    public function affected()
    {
        if ($this->_res instanceof PDOStatement) {
            return $this->_res->rowCount();
        }

        return 0;
    }

    /**
     * Last insert ID
     */
    public function last()
    {
        return $this->_conn->lastInsertId();
    }

    /**
     * Count rows in a result set
     */
    public function count($res)
    {
        return $res->rowCount();
    }

    /**
     * Rewind a result set
     */
    public function rewind($res)
    {
        // PDO does not support rewinding result sets without requerying
        return false;
    }

    /**
     * Get a list of fields within a table
     */
    protected function _fields($table)
    {
        $errnum = 0;
        $errmsg = '';
        $res = $this->_query('DESCRIBE ' . $table, $errnum, $errmsg);
        $list = [];
        if ($res) {
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['Field'];
            }
        }

        return $list;
    }
}
