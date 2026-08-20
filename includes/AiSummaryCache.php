<?php
/**
 * File-backed AI summary cache.
 * Entries are Markdown source files under the protected includes directory.
 */
class AiSummaryCache {
    const TTL = 2592000; // 30 days

    public static function get($articleId, $providerId, $contentHash) {
        $path = self::path($articleId, $providerId, $contentHash);
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $modifiedAt = filemtime($path);
        if ($modifiedAt === false || $modifiedAt < time() - self::TTL) {
            @unlink($path);
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            @unlink($path);
            return null;
        }

        return $content;
    }

    public static function put($articleId, $providerId, $contentHash, $summary) {
        $summary = rtrim((string)$summary);
        if ($summary === '') {
            return false;
        }

        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建 AI 摘要缓存目录');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('AI 摘要缓存目录不可写');
        }

        $path = self::path($articleId, $providerId, $contentHash);
        $pending = $path . '.pending-' . bin2hex(random_bytes(6));
        $payload = $summary . "\n";
        if (file_put_contents($pending, $payload, LOCK_EX) === false) {
            throw new RuntimeException('AI 摘要缓存写入失败');
        }

        // Windows rename cannot replace an existing destination reliably.
        if (is_file($path) && !@unlink($path)) {
            @unlink($pending);
            throw new RuntimeException('AI 摘要旧缓存无法替换');
        }
        if (!@rename($pending, $path)) {
            @unlink($pending);
            throw new RuntimeException('AI 摘要缓存发布失败');
        }

        self::pruneExpired();
        return true;
    }

    public static function deleteProvider($providerId) {
        $providerId = self::positiveInt($providerId, 'providerId');
        self::deletePattern('*-p' . $providerId . '-*.md');
    }

    public static function deleteArticle($articleId) {
        $articleId = self::positiveInt($articleId, 'articleId');
        self::deletePattern('a' . $articleId . '-p*-*.md');
    }

    public static function pruneExpired() {
        $directory = self::directory();
        if (!is_dir($directory)) {
            return;
        }
        $cutoff = time() - self::TTL;
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.md') ?: [] as $path) {
            $modifiedAt = filemtime($path);
            if ($modifiedAt !== false && $modifiedAt < $cutoff) {
                @unlink($path);
            }
        }
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.pending-*') ?: [] as $path) {
            $modifiedAt = filemtime($path);
            if ($modifiedAt !== false && $modifiedAt < time() - 3600) {
                @unlink($path);
            }
        }
    }

    private static function directory() {
        return LM_ROOT . '/includes/cache/ai-summary';
    }

    private static function deletePattern($filePattern) {
        foreach (glob(self::directory() . DIRECTORY_SEPARATOR . $filePattern) ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function path($articleId, $providerId, $contentHash) {
        $articleId = self::positiveInt($articleId, 'articleId');
        $providerId = self::positiveInt($providerId, 'providerId');
        $contentHash = strtolower((string)$contentHash);
        if (!preg_match('/^[a-f0-9]{32,64}$/', $contentHash)) {
            throw new InvalidArgumentException('非法 AI 摘要内容哈希');
        }

        return self::directory() . DIRECTORY_SEPARATOR
            . 'a' . $articleId . '-p' . $providerId . '-' . $contentHash . '.md';
    }

    private static function positiveInt($value, $name) {
        $value = (int)$value;
        if ($value <= 0) {
            throw new InvalidArgumentException('非法参数: ' . $name);
        }
        return $value;
    }
}
