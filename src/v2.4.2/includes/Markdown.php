<?php
/**
 * Small, dependency-free Markdown renderer for trusted article source files.
 * Raw HTML is escaped; generated links and images use an explicit URL allowlist.
 */
class Markdown {
    public static function render($markdown) {
        $markdown = str_replace(["\r\n", "\r"], "\n", (string)$markdown);
        $lines = explode("\n", $markdown);
        $html = [];
        $paragraph = [];
        $listType = null;
        $inCode = false;
        $codeLanguage = '';
        $codeLines = [];

        $flushParagraph = function () use (&$paragraph, &$html) {
            if (!$paragraph) {
                return;
            }
            $text = implode("\n", $paragraph);
            $html[] = '<p>' . self::inline($text) . '</p>';
            $paragraph = [];
        };
        $closeList = function () use (&$listType, &$html) {
            if ($listType !== null) {
                $html[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            if ($inCode) {
                if (preg_match('/^```\s*$/', $line)) {
                    $class = $codeLanguage !== '' ? ' class="language-' . e($codeLanguage) . '"' : '';
                    $html[] = '<pre><code' . $class . '>' . e(implode("\n", $codeLines)) . '</code></pre>';
                    $inCode = false;
                    $codeLanguage = '';
                    $codeLines = [];
                } else {
                    $codeLines[] = $line;
                }
                continue;
            }

            if (preg_match('/^```\s*([A-Za-z0-9_+-]*)\s*$/', $line, $match)) {
                $flushParagraph();
                $closeList();
                $inCode = true;
                $codeLanguage = strtolower($match[1]);
                continue;
            }

            if (trim($line) === '') {
                $flushParagraph();
                $closeList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $match)) {
                $flushParagraph();
                $closeList();
                $level = strlen($match[1]);
                $html[] = '<h' . $level . '>' . self::inline(trim($match[2])) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^\s*(?:---+|___+|\*\*\*+)\s*$/', $line)) {
                $flushParagraph();
                $closeList();
                $html[] = '<hr>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line, $match)) {
                $flushParagraph();
                $closeList();
                $html[] = '<blockquote><p>' . self::inline($match[1]) . '</p></blockquote>';
                continue;
            }

            if (preg_match('/^\s*([-+*])\s+(.+)$/', $line, $match)) {
                $flushParagraph();
                if ($listType !== 'ul') {
                    $closeList();
                    $listType = 'ul';
                    $html[] = '<ul>';
                }
                $html[] = '<li>' . self::inline($match[2]) . '</li>';
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.+)$/', $line, $match)) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $closeList();
                    $listType = 'ol';
                    $html[] = '<ol>';
                }
                $html[] = '<li>' . self::inline($match[1]) . '</li>';
                continue;
            }

            $paragraph[] = $line;
        }

        if ($inCode) {
            $class = $codeLanguage !== '' ? ' class="language-' . e($codeLanguage) . '"' : '';
            $html[] = '<pre><code' . $class . '>' . e(implode("\n", $codeLines)) . '</code></pre>';
        }
        $flushParagraph();
        $closeList();

        return implode("\n", $html);
    }

    private static function inline($text) {
        $tokens = [];
        $store = function ($html) use (&$tokens) {
            $key = "\x1A" . count($tokens) . "\x1A";
            $tokens[$key] = $html;
            return $key;
        };

        $text = preg_replace_callback('/`([^`\n]+)`/', function ($match) use ($store) {
            return $store('<code>' . e($match[1]) . '</code>');
        }, (string)$text);
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^\s)]+)(?:\s+["\']([^"\']*)["\'])?\)/', function ($match) use ($store) {
            $url = self::safeUrl($match[2], true);
            if ($url === '') {
                return $match[0];
            }
            $title = isset($match[3]) && $match[3] !== '' ? ' title="' . e($match[3]) . '"' : '';
            return $store('<img src="' . e($url) . '" alt="' . e($match[1]) . '"' . $title . ' loading="lazy" decoding="async">');
        }, $text);
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^\s)]+)(?:\s+["\']([^"\']*)["\'])?\)/', function ($match) use ($store) {
            $url = self::safeUrl($match[2], false);
            if ($url === '') {
                return $match[0];
            }
            $title = isset($match[3]) && $match[3] !== '' ? ' title="' . e($match[3]) . '"' : '';
            $external = preg_match('#^https?://#i', $url) ? ' target="_blank" rel="noopener noreferrer"' : '';
            return $store('<a href="' . e($url) . '"' . $title . $external . '>' . e($match[1]) . '</a>');
        }, $text);

        $text = e($text);
        $text = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__([^_\n]+)__/', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text);
        $text = preg_replace('/~~([^~\n]+)~~/', '<del>$1</del>', $text);
        $text = str_replace("\n", '<br>', $text);

        return strtr($text, $tokens);
    }

    private static function safeUrl($url, $image) {
        $url = trim(html_entity_decode((string)$url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x20]/', $url)) {
            return '';
        }
        if ($url[0] === '/' && strpos($url, '//') !== 0) {
            return $url;
        }
        if (!$image && $url[0] === '#') {
            return $url;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }
}
