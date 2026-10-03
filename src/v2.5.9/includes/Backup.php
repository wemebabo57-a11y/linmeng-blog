<?php
/**
 * 备份核心库 v1.0
 *
 * 统一提供「导出 → 打包 zip」与「上传 zip → 原样还原」的双向能力，
 * 导出格式（与后台备份页保持完全一致，即"导出的原格式"）：
 *
 *   database.sql        数据库结构与数据
 *   articles/*.md       全部文章 Markdown 源文件
 *   config-profile.txt  脱敏配置清单（仅说明，不参与还原）
 *
 * 本文件不依赖 .env / config.php，可被安装向导（尚未生成配置时）与后台复用。
 */

class Backup
{
    /** 导出包内文章目录前缀 */
    public const ARTICLE_PREFIX = 'articles/';

    /**
     * 文章 Markdown 目录的绝对路径
     */
    public static function articlesDir(string $root): string
    {
        return rtrim($root, '/\\') . '/includes/articles';
    }

    /* ------------------------------------------------------------------ *
     * 导出
     * ------------------------------------------------------------------ */

    /**
     * 生成完整的备份 zip，返回临时文件绝对路径（调用方负责 unlink）
     *
     * @throws RuntimeException
     */
    public static function exportZip(PDO $pdo, string $root, string $dbName = ''): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('当前 PHP 未启用 ZipArchive 扩展，无法生成压缩包。');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'bak_');
        if ($tmpFile === false) {
            throw new RuntimeException('无法创建临时文件，请检查系统临时目录权限。');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpFile);
            throw new RuntimeException('无法创建 zip 压缩包。');
        }

        // 1. 数据库导出为 SQL
        $zip->addFromString('database.sql', self::dumpDatabase($pdo, $dbName));

        // 2. 文章 Markdown
        $articlesDir = self::articlesDir($root);
        $articleCount = 0;
        if (is_dir($articlesDir)) {
            foreach (glob($articlesDir . '/*.md') ?: [] as $mdFile) {
                $zip->addFile($mdFile, self::ARTICLE_PREFIX . basename($mdFile));
                $articleCount++;
            }
        }

        // 3. 脱敏配置说明（.env 原文不会打包）
        $zip->addFromString('config-profile.txt', self::configProfile($root, $articleCount));

        if (!$zip->close()) {
            @unlink($tmpFile);
            throw new RuntimeException('生成压缩包失败，请稍后重试。');
        }

        return $tmpFile;
    }

    /**
     * 数据库 SQL 导出（结构 + 数据，分批读取避免内存膨胀）
     */
    public static function dumpDatabase(PDO $pdo, string $dbName = ''): string
    {
        $sql = [];
        $sql[] = '-- 站点数据库导出';
        $sql[] = '-- 生成时间：' . date('Y-m-d H:i:s');
        if ($dbName !== '') {
            $sql[] = '-- 数据库：' . $dbName;
        }
        $sql[] = '';
        $sql[] = 'SET NAMES utf8mb4;';
        $sql[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $sql[] = '';

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $escapedTable = str_replace('`', '``', (string)$table);
            $quotedTable = '`' . $escapedTable . '`';

            // 表结构
            $createRow = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
            $createSql = $createRow[1] ?? '';
            $sql[] = '-- ----------------------------';
            $sql[] = '-- 表结构：' . $escapedTable;
            $sql[] = '-- ----------------------------';
            $sql[] = 'DROP TABLE IF EXISTS ' . $quotedTable . ';';
            $sql[] = $createSql . ';';
            $sql[] = '';

            // 表数据，分批读取
            $batchSize = 500;
            $offset = 0;
            $firstBatch = true;
            while (true) {
                $stmt = $pdo->query('SELECT * FROM ' . $quotedTable . ' LIMIT ' . $batchSize . ' OFFSET ' . $offset);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($rows)) {
                    break;
                }

                if ($firstBatch) {
                    $sql[] = '-- 表数据：' . $escapedTable;
                    $firstBatch = false;
                }

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = self::sqlValue($pdo, $value);
                    }
                    $sql[] = 'INSERT INTO ' . $quotedTable . ' VALUES (' . implode(',', $values) . ');';
                }

                $offset += $batchSize;
                if (count($rows) < $batchSize) {
                    break;
                }
            }
            $sql[] = '';
        }

        $sql[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $sql[] = '';

        return implode("\n", $sql);
    }

    /**
     * 将字段值转为安全的 SQL 字面量（处理 NULL 与二进制）
     *
     * @param mixed $value
     */
    public static function sqlValue(PDO $pdo, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        // 非法 UTF-8 视为二进制，用十六进制字面量导出
        if (!preg_match('//u', (string)$value)) {
            return '0x' . bin2hex((string)$value);
        }
        return $pdo->quote((string)$value);
    }

    /**
     * 生成脱敏配置说明（仅列出键与脱敏值，不含任何明文凭据）
     */
    public static function configProfile(string $root, int $articleCount = 0): string
    {
        $envPath = rtrim($root, '/\\') . '/.env';
        $envKeys = [];
        if (is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                $envKeys[$key] = preg_match('/(PASS|SECRET|KEY|TOKEN)/i', $key) ? '********' : $val;
            }
        }

        $lines = [];
        $lines[] = '站点配置清单（脱敏）';
        $lines[] = '生成时间：' . date('Y-m-d H:i:s');
        if (defined('APP_VERSION')) {
            $lines[] = '程序版本：' . APP_VERSION;
        }
        $lines[] = 'PHP 版本：' . PHP_VERSION;
        if (defined('DB_NAME')) {
            $lines[] = '数据库：' . DB_NAME . ' @ ' . (defined('DB_HOST') ? DB_HOST : '');
        }
        if (defined('SITE_URL')) {
            $lines[] = '本站地址：' . SITE_URL;
        }
        $lines[] = '导出文章 Markdown 数：' . $articleCount;
        $lines[] = '';
        $lines[] = '环境变量键值（敏感项已打码）：';
        foreach ($envKeys as $key => $val) {
            $lines[] = '  ' . $key . '=' . $val;
        }
        $lines[] = '';
        $lines[] = '说明：.env 文件本身未包含在备份中，如需还原请按上面的键名重新填写。';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------ *
     * 导入
     * ------------------------------------------------------------------ */

    /**
     * 解析备份 zip，返回内容清单（用于导入前预览校验）
     *
     * @return array{has_sql: bool, sql_size: int, articles: int, has_profile: bool, valid: bool, message: string}
     */
    public static function inspectZip(string $zipPath): array
    {
        $info = [
            'has_sql' => false,
            'sql_size' => 0,
            'articles' => 0,
            'has_profile' => false,
            'valid' => false,
            'message' => '',
        ];

        if (!class_exists('ZipArchive')) {
            $info['message'] = '当前 PHP 未启用 ZipArchive 扩展。';
            return $info;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $info['message'] = '不是有效的 zip 压缩包。';
            return $info;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            $name = str_replace('\\', '/', $name);
            $base = strtolower(basename($name));
            if ($base === 'database.sql') {
                $info['has_sql'] = true;
                $stat = $zip->statIndex($i);
                $info['sql_size'] = (int)($stat['size'] ?? 0);
            } elseif ($base === 'config-profile.txt') {
                $info['has_profile'] = true;
            } elseif (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'md') {
                $info['articles']++;
            }
        }
        $zip->close();

        $info['valid'] = $info['has_sql'] || $info['articles'] > 0;
        if (!$info['valid']) {
            $info['message'] = '压缩包内没有可还原的内容（需要 database.sql 或 articles/*.md）。';
        }
        return $info;
    }

    /**
     * 从备份 zip 原样还原（完全覆盖）：数据库结构+数据、文章 Markdown
     *
     * @param bool $restoreArticles 是否还原文章文件
     * @return array{tables:int, statements:int, articles:int, warnings:array<int,string>}
     * @throws RuntimeException
     */
    public static function importZip(string $zipPath, PDO $pdo, string $root, bool $restoreArticles = true, bool $setupImport = false): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('当前 PHP 未启用 ZipArchive 扩展，无法读取压缩包。');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('无法打开备份压缩包，文件可能已损坏。');
        }

        $result = ['tables' => 0, 'statements' => 0, 'articles' => 0, 'warnings' => []];

        try {
            // ---------- 1. 数据库 ----------
            $sqlName = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name !== false && strtolower(basename(str_replace('\\', '/', $name))) === 'database.sql') {
                    $sqlName = $name;
                    break;
                }
            }

            if ($sqlName === null) {
                $result['warnings'][] = '压缩包内未找到 database.sql，已跳过数据库还原。';
            } else {
                $sqlText = $zip->getFromName($sqlName);
                if ($sqlText === false) {
                    throw new RuntimeException('读取 database.sql 内容失败。');
                }
                $statements = self::splitSql($sqlText);
                if (empty($statements)) {
                    $result['warnings'][] = 'database.sql 中没有可执行的语句。';
                }

                // 会话级设置失败不应中断还原（例如非 MySQL 驱动）
                try {
                    $pdo->exec('SET NAMES utf8mb4');
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                } catch (Throwable $ignored) {
                }
                try {
                    foreach ($statements as $statement) {
                        // 会话级开关交给下面的重设，避免还原中途开启外键检查
                        if (preg_match('/^SET\s+(NAMES|FOREIGN_KEY_CHECKS|SQL_MODE|TIME_ZONE|CHARACTER_SET)/i', $statement)) {
                            continue;
                        }
                        // 拒绝可读写服务器文件或提权的语句：备份包可能来自外部，
                        // 而本站导出的包只包含 CREATE TABLE / INSERT / DROP TABLE 等
                        if (preg_match('/\b(INTO\s+(OUTFILE|DUMPFILE)|LOAD_FILE\s*\(|LOAD\s+DATA\b|GRANT\b|REVOKE\b|CREATE\s+(FUNCTION|PROCEDURE|TRIGGER|USER|EVENT)|ALTER\s+USER\b|SET\s+GLOBAL\b|INSTALL\s+(PLUGIN|COMPONENT)|SHUTDOWN\b|DROP\s+DATABASE\b)/i', $statement)) {
                            $result['warnings'][] = '已跳过存在风险的语句（文件读写/权限或实例级操作）。';
                            continue;
                        }
                        if ($setupImport) {
                            // 安装阶段只接受本站导出器生成的表操作，并兼容旧版 lm_ 表前缀。
                            if (!preg_match('/^(DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`(?:app_|lm_)[A-Za-z0-9_]+`/i', $statement)) {
                                throw new RuntimeException('备份包含有安装向导不支持的 SQL 语句。');
                            }
                            $statement = preg_replace('/^(\s*(?:DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`)lm_/i', '$1app_', $statement);
                            if (preg_match('/^CREATE\s+TABLE/i', $statement)) {
                                $statement = preg_replace('/`lm_([A-Za-z0-9_]+)`/', '`app_$1`', $statement);
                            }
                        }
                        $pdo->exec($statement);
                        $result['statements']++;
                        if (preg_match('/^CREATE\s+TABLE/i', $statement)) {
                            $result['tables']++;
                        }
                    }
                } finally {
                    try {
                        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    } catch (Throwable $ignored) {
                    }
                }
            }

            // ---------- 2. 文章 Markdown ----------
            if ($restoreArticles) {
                $articlesDir = self::articlesDir($root);
                if (!is_dir($articlesDir) && !@mkdir($articlesDir, 0755, true) && !is_dir($articlesDir)) {
                    $result['warnings'][] = '文章目录不存在且无法创建，已跳过文章还原：' . $articlesDir;
                } else {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);
                        if ($name === false) {
                            continue;
                        }
                        $name = str_replace('\\', '/', $name);
                        if (substr($name, -1) === '/') {
                            continue; // 目录项
                        }
                        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'md') {
                            continue;
                        }
                        // 仅取文件名，杜绝路径穿越（zip slip）
                        $safeName = basename($name);
                        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
                            continue;
                        }
                        $content = $zip->getFromName($name);
                        if ($content === false) {
                            $result['warnings'][] = '读取文章失败：' . $name;
                            continue;
                        }
                        if (@file_put_contents($articlesDir . '/' . $safeName, $content) === false) {
                            $result['warnings'][] = '写入文章失败：' . $safeName;
                            continue;
                        }
                        $result['articles']++;
                    }
                }
            }
        } finally {
            $zip->close();
        }

        return $result;
    }

    /**
     * 切分 SQL 文本为单条语句
     *
     * 逐字符扫描，正确处理单引号 / 双引号 / 反引号内的分号、
     * 反斜杠转义、连续的 '' / "" 转义，以及 -- 行注释、# 行注释、块注释。
     *
     * @return array<int,string>
     */
    public static function splitSql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $len = strlen($sql);
        $i = 0;

        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        while ($i < $len) {
            $ch = $sql[$i];
            $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

            // 行注释：吞到行尾（保留换行以便断行）
            if ($inLineComment) {
                if ($ch === "\n") {
                    $inLineComment = false;
                    $buffer .= "\n";
                }
                $i++;
                continue;
            }

            // 块注释：吞到 */
            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i += 2;
                    continue;
                }
                $i++;
                continue;
            }

            // 引号内部
            if ($inSingle || $inDouble || $inBacktick) {
                // 反斜杠转义仅适用于单/双引号（反引号内的标识符不使用反斜杠转义）
                if (($inSingle || $inDouble) && $ch === '\\' && $next !== '') {
                    $buffer .= $ch . $next;
                    $i += 2;
                    continue;
                }

                $quote = $inSingle ? "'" : ($inDouble ? '"' : '`');
                if ($ch === $quote) {
                    // 连续两个引号 = 转义，仍处于字符串内部
                    if ($next === $quote) {
                        $buffer .= $ch . $next;
                        $i += 2;
                        continue;
                    }
                    $buffer .= $ch;
                    $inSingle = $inDouble = $inBacktick = false;
                    $i++;
                    continue;
                }

                $buffer .= $ch;
                $i++;
                continue;
            }

            // 普通状态：识别注释与引号起始
            if ($ch === '-' && $next === '-') {
                $after = ($i + 2 < $len) ? $sql[$i + 2] : '';
                // MySQL 要求 "-- " 后跟空白才算注释
                if ($after === '' || $after === ' ' || $after === "\t" || $after === "\n" || $after === "\r") {
                    $inLineComment = true;
                    $i += 2;
                    continue;
                }
            }
            if ($ch === '#') {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i += 2;
                continue;
            }
            if ($ch === "'") {
                $inSingle = true;
                $buffer .= $ch;
                $i++;
                continue;
            }
            if ($ch === '"') {
                $inDouble = true;
                $buffer .= $ch;
                $i++;
                continue;
            }
            if ($ch === '`') {
                $inBacktick = true;
                $buffer .= $ch;
                $i++;
                continue;
            }
            if ($ch === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                $i++;
                continue;
            }

            $buffer .= $ch;
            $i++;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
