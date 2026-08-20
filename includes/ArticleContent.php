<?php
require_once __DIR__ . '/Markdown.php';

/**
 * Article body storage facade.
 * Existing database HTML remains untouched. Markdown articles keep a rendered
 * snapshot in the database and a source file under includes/articles.
 */
class ArticleContent {
    const REFERENCE_PREFIX = '<!--lm-markdown:';
    const REFERENCE_SUFFIX = '-->';

    public static function storageDirectory() {
        return LM_ROOT . '/includes/articles';
    }

    public static function isMarkdownArticle(array $article) {
        return self::parseReference($article['content'] ?? '') !== null;
    }

    public static function source(array $article) {
        $reference = self::parseReference($article['content'] ?? '');
        if ($reference === null) {
            return (string)($article['content'] ?? '');
        }

        $path = self::path($reference['file']);
        $source = is_readable($path) ? file_get_contents($path) : false;
        if ($source === false || ($reference['hash'] !== '' && hash('sha256', $source) !== $reference['hash'])) {
            return '';
        }
        return $source;
    }

    public static function sourceAvailable(array $article) {
        if (!self::isMarkdownArticle($article)) {
            return true;
        }
        return self::source($article) !== '';
    }

    public static function render(array $article) {
        $reference = self::parseReference($article['content'] ?? '');
        if ($reference === null) {
            return formatArticleContent((string)($article['content'] ?? ''));
        }

        $source = self::source($article);
        if ($source !== '') {
            return Markdown::render($source);
        }

        return (string)$reference['snapshot'];
    }

    public static function plainText(array $article) {
        if (self::isMarkdownArticle($article)) {
            $source = self::source($article);
            if ($source !== '') {
                $source = preg_replace('/```[\s\S]*?```/u', ' ', $source);
                $source = preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $source);
                $source = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $source);
                $source = preg_replace('/[#>*_`~\-]+/u', ' ', $source);
                return trim(preg_replace('/\s+/u', ' ', $source));
            }
        }

        return trim(preg_replace('/\s+/u', ' ', strip_tags((string)($article['content'] ?? ''))));
    }

    public static function databaseValue($markdown, $slug) {
        $file = self::fileName($slug);
        $snapshot = Markdown::render($markdown);
        $normalized = rtrim((string)$markdown) . "\n";
        return self::REFERENCE_PREFIX . $file . ':' . hash('sha256', $normalized) . self::REFERENCE_SUFFIX . "\n" . $snapshot;
    }

    public static function prepareMarkdown($markdown, $slug) {
        $directory = self::storageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建 Markdown 文章目录');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('Markdown 文章目录不可写');
        }

        $file = self::fileName($slug);
        $pending = self::path($file) . '.pending-' . bin2hex(random_bytes(6));
        if (file_put_contents($pending, rtrim((string)$markdown) . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Markdown 临时文件写入失败');
        }

        return [
            'content' => self::databaseValue($markdown, $slug),
            'file' => $file,
            'pending' => $pending
        ];
    }

    public static function commitPrepared(array $prepared) {
        $pending = (string)($prepared['pending'] ?? '');
        $file = (string)($prepared['file'] ?? '');
        if ($pending === '' || !is_file($pending)) {
            throw new RuntimeException('Markdown 临时文件不存在');
        }
        $source = file_get_contents($pending);
        $path = self::path($file);
        if ($source === false || file_put_contents($path, $source, LOCK_EX) === false) {
            @unlink($pending);
            @unlink($path);
            throw new RuntimeException('Markdown 文件发布失败');
        }
        @unlink($pending);
    }

    public static function discardPrepared(array $prepared) {
        $pending = (string)($prepared['pending'] ?? '');
        if ($pending !== '' && is_file($pending)) {
            @unlink($pending);
        }
    }

    public static function cleanupPrevious($previousContent, $currentContent) {
        $previous = self::parseReference($previousContent);
        $current = self::parseReference($currentContent);
        if ($previous !== null && ($current === null || $previous['file'] !== $current['file'])) {
            self::deleteFile($previous['file']);
        }
    }

    public static function deleteForContent($content) {
        $reference = self::parseReference($content);
        if ($reference !== null) {
            self::deleteFile($reference['file']);
        }
    }

    private static function parseReference($content) {
        $content = (string)$content;
        if (!preg_match('/^<!--lm-markdown:([a-z0-9][a-z0-9._-]*\.md)(?::([a-f0-9]{64}))?-->(?:\r?\n)?/i', $content, $match)) {
            return null;
        }
        return [
            'file' => strtolower($match[1]),
            'hash' => strtolower($match[2] ?? ''),
            'snapshot' => substr($content, strlen($match[0]))
        ];
    }

    private static function fileName($slug) {
        $originalSlug = trim((string)$slug);
        $slug = strtolower($originalSlug);
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim($slug, '-_');
        if ($slug === '') {
            $slug = 'article';
        }
        return substr($slug, 0, 100) . '-' . substr(sha1($originalSlug), 0, 12) . '.md';
    }

    private static function path($file) {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*\.md$/i', (string)$file)) {
            throw new InvalidArgumentException('非法 Markdown 文件名');
        }
        return self::storageDirectory() . DIRECTORY_SEPARATOR . $file;
    }

    private static function deleteFile($file) {
        $path = self::path($file);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
