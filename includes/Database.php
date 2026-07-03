<?php
/**
 * 数据库操作类
 * 使用PDO预处理语句防止SQL注入
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $lastQuery = '';
    private $queryCount = 0;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // 禁用模拟预处理，使用真实预处理语句
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die('数据库连接失败，请检查配置');
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 获取PDO实例
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * 执行预处理查询
     */
    public function query($sql, $params = []) {
        $this->lastQuery = $sql;
        $this->queryCount++;
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $sql . " Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取单行
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 获取多行
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取单个值
     */
    public function fetchColumn($sql, $params = [], $column = 0) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }
    
    /**
     * 插入数据
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, $values);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 更新数据
     */
    public function update($table, $data, $where, $whereParams = []) {
        $sets = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $sets[] = "`{$column}` = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE " . $where;
        $values = array_merge($values, $whereParams);
        
        $stmt = $this->query($sql, $values);
        return $stmt->rowCount();
    }
    
    /**
     * 删除数据
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `{$table}` WHERE " . $where;
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * 获取最后插入ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 获取查询次数
     */
    public function getQueryCount() {
        return $this->queryCount;
    }
    
    /**
     * 获取最后执行的SQL
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }
    
    /**
     * 检查表是否存在
     */
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->fetchColumn($sql, [$table]);
        return !empty($result);
    }
}
