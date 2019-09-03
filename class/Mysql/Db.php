<?php

namespace Mysql;

class Db
{
    // 定义pdo对象
    private $pdo;
    private $sQuery;
    // 定义数据库配置
    private $settings;
    // 定义数据库连接转态
    private $bConnected = false;
    // 日志
    private $log;
    // 参数
    private $parameters;
    // 定义一个静态变量存储创建的实例
    private static $instances = array();

    /**
     * 使用单例模式创建实例对象
     * @param  string $name [数据库连接名]
     */
    public static function getInstance($name = 'master')
    {
        // 判断是否有存在的实例
        if(isset(self::$instances[$name])){
            return self::$instances[$name];
        }
        // 创建实例
        self::$instances[$name] = new \Mysql\Db($name);
        return self::$instances[$name];
    }

    /**
     * 定义构造函数来连接数据库
     * @param  string $name [连接名]
     */
    private function __contruct($name = 'master')
    {
        // 连接数据库
        $this->Connect($name);
        $this->paramters = array();
    }

    /**
     * 连接数据库
     * @param string $name [description]
     */
    private function Connect($name = 'master')
    {
        // 获取连接的配置信息
        global $config;
        // 定义数据库开始连接时间
        $mtime1 = microtime();
        $this->settings = $config['db'][$name];
        // 定义连接
        $dsn = 'mysql:dbname=' . $this->settings['dbname'] . ';host=' . $this->settings['host'] . '';
        try {
            // 连接数据库，返回格式以utf8的格式
            $this->pdo = new \PDO($dsn, $this->settings["user"], $this->settings["password"], array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8;"));
            // 记录致命错误的任何异常
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
            // 启用预处理
            $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,true);
            // 连接状态
            $this->bConnected = true;
        }catch(\PDOException $e) {
            // 连接失败时打印出错误，输出异常信息
            print_r($e);
            echo $this->ExceptionLog($e->getMessage());
            die();
        }
        // 连接结束的时间
        $mtime2 = microtime();
        // 把创建数据库的时间记录到日志
        \common\DebugLog::_mysql('connect',null,array('host' => $this->settings['host'],'dbname' => $this->settings['dbname']),$mtime1,$mtime2,null);
    }

    /**
     * 关闭数据库连接
     */
    public function CloseConnection()
    {
        $this->pdo = null;
    }

    /**
     *    Every method which needs to execute a SQL query uses this method.
     *
     *    1. If not connected, connect to the database.
     *    2. Prepare Query.
     *    3. Parameterize Query.
     *    4. Execute Query.
     *    5. On exception : Write Exception into the log + SQL query.
     *    6. Reset the Parameters.
     */
    private function Init($query, $parameters = "")
    {
        # Connect to database
        if (!$this->bConnected) {
            $this->Connect();
        }
        try {

            # Prepare query
            $this->sQuery = $this->pdo->prepare($query);

            # Add parameters to the parameter array
            if ($parameters && isset($parameters[0])) {
                // ? 占位符形式
                # Execute SQL
                $this->succes = $this->sQuery->execute($parameters);
            } else {
                // :fieldname 字段名形式
                $this->bindMore($parameters);
                # Bind parameters
                if (!empty($this->parameters)) {
                    foreach ($this->parameters as $param) {
                        $parameters = explode("\x7F", $param);
                        $this->sQuery->bindParam($parameters[0], $parameters[1]);
                    }
                }
                # Execute SQL
                $this->succes = $this->sQuery->execute();
            }
        } catch (PDOException $e) {
            # Write into log and display Exception
            echo $this->ExceptionLog($e->getMessage(), $query);
            die();
        }

        # Reset the parameters
        $this->parameters = array();
    }

    /**
     * @void
     *
     *    Add the parameter to the parameter array
     * @param string $para
     * @param string $value
     */
    public function bind($para, $value)
    {
        if (is_array($para)) {
            $para = json_encode($para);
        }
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $this->parameters[sizeof($this->parameters)] = ":" . $para . "\x7F" . $value;
//        $this->parameters[sizeof($this->parameters)] = ":" . $para . "\x7F" . utf8_encode($value);
    }

    /**
     * @void
     *
     *    Add more parameters to the parameter array
     * @param array $parray
     */
    public function bindMore($parray)
    {
        if (empty($this->parameters) && is_array($parray)) {
            $columns = array_keys($parray);
            foreach ($columns as $i => &$column) {
                $this->bind($column, $parray[$column]);
            }
        }
    }

    /**
     *    If the SQL query  contains a SELECT or SHOW statement it returns an array containing all of the result set row
     *    If the SQL statement is a DELETE, INSERT, or UPDATE statement it returns the number of affected rows
     *
     * @param  string $query
     * @param  array $params
     * @param  int $fetchmode
     * @return mixed
     */
    public function query($query, $params = null, $fetchmode = \PDO::FETCH_ASSOC)
    {
        $mtime1 = microtime();
        $query = trim($query);

        $this->Init($query, $params);

        $rawStatement = explode(" ", $query);

        # Which SQL statement is used
        $statement = strtolower($rawStatement[0]);

        $ret = NULL;
        if ($statement === 'select' || $statement === 'show') {
            $ret = $this->sQuery->fetchAll($fetchmode);
        } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
            $ret = $this->sQuery->rowCount();
        }
        $mtime2 = microtime();
        \common\DebugLog::_mysql('query: ' . $query, $params, array('host' => $this->settings['host'], 'dbname' => $this->settings['dbname']), $mtime1, $mtime2, $ret);
        return $ret;
    }

    /**
     *  Returns the last inserted id.
     * @return string
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     *    Returns an array which represents a column from the result set
     *
     * @param  string $query
     * @param  array $params
     * @return array
     */
    public function column($query, $params = null)
    {
        $mtime1 = microtime();
        $this->Init($query, $params);
        $Columns = $this->sQuery->fetchAll(PDO::FETCH_NUM);

        $column = null;

        foreach ($Columns as $cells) {
            $column[] = $cells[0];
        }

        $mtime2 = microtime();
        \common\DebugLog::_mysql('column: ' . $query, $params, array('host' => $this->settings['host'], 'dbname' => $this->settings['dbname']), $mtime1, $mtime2, $column);
        return $column;

    }

    /**
     *    Returns an array which represents a row from the result set
     *
     * @param  string $query
     * @param  array $params
     * @param  int $fetchmode
     * @return array
     */
    public function row($query, $params = null, $fetchmode = \PDO::FETCH_ASSOC)
    {
        $mtime1 = microtime();
        $this->Init($query, $params);
        $ret = $this->sQuery->fetch($fetchmode);
        $mtime2 = microtime();
        \common\DebugLog::_mysql('row: ' . $query, $params, array('host' => $this->settings['host'], 'dbname' => $this->settings['dbname']), $mtime1, $mtime2, $ret);
        return $ret;
    }

    /**
     *    Returns the value of one single field/column
     *
     * @param  string $query
     * @param  array $params
     * @return string
     */
    public function single($query, $params = null)
    {
        $mtime1 = microtime();
        $this->Init($query, $params);
        $ret = $this->sQuery->fetchColumn();
        $mtime2 = microtime();
        \common\DebugLog::_mysql('single: ' . $query, $params, array('host' => $this->settings['host'], 'dbname' => $this->settings['dbname']), $mtime1, $mtime2, $ret);
        return $ret;
    }

    /**
     * 异常处理
     *
     * @param  string $message
     * @param  string $sql
     * @return string
     */
    private function ExceptionLog($message, $sql = "")
    {
        $exception = 'Unhandled Exception. <br />';
        $exception .= $message;
        $exception .= "<br /> You can find the error back in the log.";

        if (!empty($sql)) {
            # Add the Raw SQL to the Log
            $message .= "\r\nRaw SQL : " . $sql;

            return $exception;
        }
    }


}
