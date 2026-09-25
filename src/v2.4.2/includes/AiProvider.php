<?php
/**
 * AI Provider 适配器
 * 支持 OpenAI、Claude、Gemini 及自定义兼容模式
 */

class AiProvider {
    private $provider;

    public function __construct(array $provider) {
        $this->provider = $provider;
    }

    /**
     * 向 AI 发送请求并返回总结文本
     *
     * @param string $content 待总结的文章内容（已清洗截断）
     * @param string $systemPrompt 系统提示词
     * @return string 总结文本
     * @throws Exception
     */
    public function request($content, $systemPrompt) {
        $mode = $this->provider['compatibility'] ?? 'openai';
        $apiKey = Security::decrypt($this->provider['api_key'] ?? '');
        if ($apiKey === false || $apiKey === '') {
            throw new Exception('API Key 无效');
        }

        switch ($mode) {
            case 'openai':
                return $this->requestOpenAI($content, $systemPrompt, $apiKey);
            case 'claude':
                return $this->requestClaude($content, $systemPrompt, $apiKey);
            case 'gemini':
                return $this->requestGemini($content, $systemPrompt, $apiKey);
            case 'custom':
                return $this->requestCustom($content, $systemPrompt, $apiKey);
            default:
                throw new Exception('不支持的兼容模式');
        }
    }

    /**
     * 流式请求 AI，每收到一段文本增量就回调 $onDelta
     * 返回完整文本（已拼接）
     *
     * @param string $content
     * @param string $systemPrompt
     * @param callable $onDelta function(string $deltaText): void  每次增量回调
     * @return string 完整文本
     * @throws Exception
     */
    public function requestStream($content, $systemPrompt, callable $onDelta) {
        $mode = $this->provider['compatibility'] ?? 'openai';
        $apiKey = Security::decrypt($this->provider['api_key'] ?? '');
        if ($apiKey === false || $apiKey === '') {
            throw new Exception('API Key 无效');
        }

        switch ($mode) {
            case 'openai':
                return $this->streamOpenAI($content, $systemPrompt, $apiKey, $onDelta);
            case 'claude':
                return $this->streamClaude($content, $systemPrompt, $apiKey, $onDelta);
            case 'gemini':
                return $this->streamGemini($content, $systemPrompt, $apiKey, $onDelta);
            case 'custom':
                // 自定义模板模式不支持通用流式解析，降级为一次性请求再整体回放
                $full = $this->requestCustom($content, $systemPrompt, $apiKey);
                if ($full !== '') {
                    $onDelta($full);
                }
                return $full;
            default:
                throw new Exception('不支持的兼容模式');
        }
    }

    /**
     * OpenAI 兼容格式流式请求 /v1/chat/completions (stream=true)
     */
    private function streamOpenAI($content, $systemPrompt, $apiKey, callable $onDelta) {
        $payload = [
            'model' => $this->provider['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $content]
            ],
            'temperature' => 0.7,
            'max_tokens' => 800,
            'stream' => true,
        ];

        $full = '';
        $streamErr = null;
        $this->streamCurl(
            $this->provider['api_url'],
            $payload,
            ["Authorization: Bearer {$apiKey}", 'Accept: text/event-stream'],
            function ($data) use (&$full, &$streamErr, $onDelta) {
                // OpenAI SSE 格式：每行 "data: {json}" 或 "data: [DONE]"
                foreach ($this->parseSseLines($data) as $line) {
                    if ($line === '[DONE]' || $line === '') {
                        continue;
                    }
                    $json = json_decode($line, true);
                    if (!is_array($json)) {
                        continue;
                    }
                    // 错误响应：记录后中断
                    if (isset($json['error'])) {
                        $streamErr = 'AI 返回错误: ' . ($json['error']['message'] ?? '未知错误');
                        return;
                    }
                    $delta = $json['choices'][0]['delta']['content'] ?? '';
                    if ($delta !== '') {
                        $full .= $delta;
                        $onDelta($delta);
                    }
                }
            }
        );

        if ($streamErr !== null) {
            throw new Exception($streamErr);
        }
        if (trim($full) === '') {
            throw new Exception('AI 返回总结为空');
        }
        return $full;
    }

    /**
     * Anthropic Claude 流式请求 /v1/messages (stream=true)
     */
    private function streamClaude($content, $systemPrompt, $apiKey, callable $onDelta) {
        $payload = [
            'model' => $this->provider['model'],
            'max_tokens' => 800,
            'stream' => true,
            'messages' => [
                ['role' => 'user', 'content' => $systemPrompt . "\n\n" . $content]
            ]
        ];

        $full = '';
        $streamErr = null;
        $this->streamCurl(
            $this->provider['api_url'],
            $payload,
            [
                "x-api-key: {$apiKey}",
                'anthropic-version: 2023-06-01',
                'Accept: text/event-stream'
            ],
            function ($data) use (&$full, &$streamErr, $onDelta) {
                // Claude SSE 格式：event: xxx\ndata: {json}
                foreach ($this->parseSseLines($data) as $line) {
                    if ($line === '') {
                        continue;
                    }
                    $json = json_decode($line, true);
                    if (!is_array($json)) {
                        continue;
                    }
                    if (isset($json['type']) && $json['type'] === 'error') {
                        $streamErr = 'AI 返回错误: ' . ($json['error']['message'] ?? '未知错误');
                        return;
                    }
                    // content_block_delta 携带 text 增量
                    if (($json['type'] ?? '') === 'content_block_delta') {
                        $delta = $json['delta']['text'] ?? '';
                        if ($delta !== '') {
                            $full .= $delta;
                            $onDelta($delta);
                        }
                    }
                }
            }
        );

        if ($streamErr !== null) {
            throw new Exception($streamErr);
        }
        if (trim($full) === '') {
            throw new Exception('AI 返回总结为空');
        }
        return $full;
    }

    /**
     * Google Gemini 流式请求 streamGenerateContent?alt=sse
     */
    private function streamGemini($content, $systemPrompt, $apiKey, callable $onDelta) {
        $baseUrl = rtrim($this->provider['api_url'], '/');
        $url = $baseUrl . '/models/' . urlencode($this->provider['model'])
            . ':streamGenerateContent?alt=sse&key=' . urlencode($apiKey);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $systemPrompt . "\n\n" . $content]]]
            ],
            'generationConfig' => ['maxOutputTokens' => 800]
        ];

        $full = '';
        $streamErr = null;
        $this->streamCurl(
            $url,
            $payload,
            ['Accept: text/event-stream'],
            function ($data) use (&$full, &$streamErr, $onDelta) {
                foreach ($this->parseSseLines($data) as $line) {
                    if ($line === '') {
                        continue;
                    }
                    $json = json_decode($line, true);
                    if (!is_array($json)) {
                        continue;
                    }
                    if (isset($json['error'])) {
                        $streamErr = 'AI 返回错误: ' . ($json['error']['message'] ?? '未知错误');
                        return;
                    }
                    $delta = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if ($delta !== '') {
                        $full .= $delta;
                        $onDelta($delta);
                    }
                }
            }
        );

        if ($streamErr !== null) {
            throw new Exception($streamErr);
        }
        if (trim($full) === '') {
            throw new Exception('AI 返回总结为空');
        }
        return $full;
    }

    /**
     * 通用流式 cURL 请求：发送 JSON POST，按 WRITEFUNCTION 回调推送数据
     */
    private function streamCurl($url, $payload, $headers, callable $onData) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL 扩展未启用');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            'Content-Type: application/json; charset=utf-8',
        ], $headers));

        $httpCode = 0;
        $errBuf = '';

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use ($onData, &$errBuf, &$httpCode) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // 非 2xx 响应：累积错误内容
            if ($httpCode >= 400) {
                $errBuf .= $chunk;
                return strlen($chunk);
            }
            $onData($chunk);
            return strlen($chunk);
        });

        curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception('AI 请求失败: ' . $curlErr);
        }
        if ($httpCode >= 400 && $errBuf !== '') {
            throw new Exception('AI 上游错误(HTTP ' . $httpCode . '): ' . $errBuf);
        }
    }

    /**
     * 解析 SSE 数据块（可能包含多行、不完整行）
     * 仅返回 "data:" 后的内容（去掉前缀），按行返回
     */
    private function parseSseLines($chunk) {
        $lines = [];
        foreach (preg_split('/\r\n|\n|\r/', $chunk) as $line) {
            $line = ltrim($line);
            if ($line === '' || $line[0] === ':') {
                continue; // 空行或注释
            }
            if (stripos($line, 'data:') === 0) {
                $data = substr($line, 5);
                $lines[] = trim($data);
            }
        }
        return $lines;
    }

    /**
     * OpenAI 兼容格式 /v1/chat/completions
     */
    private function requestOpenAI($content, $systemPrompt, $apiKey) {
        $payload = [
            'model' => $this->provider['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $content]
            ],
            'temperature' => 0.7,
            'max_tokens' => 800
        ];

        $result = Security::httpPostJson(
            $this->provider['api_url'],
            $payload,
            ["Authorization: Bearer {$apiKey}"]
        );

        if (!$result['success']) {
            throw new Exception('AI 请求失败: ' . $result['error']);
        }

        $data = json_decode($result['response'], true);
        return $this->extract($data, 'choices.0.message.content');
    }

    /**
     * Anthropic Claude /v1/messages
     */
    private function requestClaude($content, $systemPrompt, $apiKey) {
        $payload = [
            'model' => $this->provider['model'],
            'max_tokens' => 800,
            'messages' => [
                ['role' => 'user', 'content' => $systemPrompt . "\n\n" . $content]
            ]
        ];

        $result = Security::httpPostJson(
            $this->provider['api_url'],
            $payload,
            [
                "x-api-key: {$apiKey}",
                "anthropic-version: 2023-06-01"
            ]
        );

        if (!$result['success']) {
            throw new Exception('AI 请求失败: ' . $result['error']);
        }

        $data = json_decode($result['response'], true);
        return $this->extract($data, 'content.0.text');
    }

    /**
     * Google Gemini models/{model}:generateContent
     */
    private function requestGemini($content, $systemPrompt, $apiKey) {
        $baseUrl = rtrim($this->provider['api_url'], '/');
        $url = $baseUrl . '/models/' . urlencode($this->provider['model']) . ':generateContent?key=' . urlencode($apiKey);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $systemPrompt . "\n\n" . $content]]]
            ],
            'generationConfig' => ['maxOutputTokens' => 800]
        ];

        $result = Security::httpPostJson($url, $payload, []);

        if (!$result['success']) {
            throw new Exception('AI 请求失败: ' . $result['error']);
        }

        $data = json_decode($result['response'], true);
        return $this->extract($data, 'candidates.0.content.parts.0.text');
    }

    /**
     * 自定义模板模式
     * 模板中可用占位符：{model} {api_key} {prompt} {content}
     * 通过 response_path 提取结果
     */
    private function requestCustom($content, $systemPrompt, $apiKey) {
        $template = $this->provider['request_template'] ?? '';
        $responsePath = $this->provider['response_path'] ?? '';

        if ($template === '' || $responsePath === '') {
            throw new Exception('自定义模式必须填写请求模板和响应提取路径');
        }

        $json = str_replace(
            ['{model}', '{api_key}', '{prompt}', '{content}'],
            [$this->provider['model'], $apiKey, $systemPrompt, $content],
            $template
        );

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new Exception('自定义请求模板不是有效 JSON');
        }

        $result = Security::httpPostJson(
            $this->provider['api_url'],
            $payload,
            ["Authorization: Bearer {$apiKey}"]
        );

        if (!$result['success']) {
            throw new Exception('AI 请求失败: ' . $result['error']);
        }

        $data = json_decode($result['response'], true);
        return $this->extract($data, $responsePath);
    }

    /**
     * 按点分隔路径从数组中提取值
     */
    private function extract($data, $path) {
        if (!is_array($data)) {
            throw new Exception('AI 返回格式异常');
        }

        $keys = explode('.', $path);
        $value = $data;
        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                throw new Exception('无法从响应中提取总结内容');
            }
        }

        if (!is_string($value) || trim($value) === '') {
            throw new Exception('AI 返回总结为空');
        }

        return trim($value);
    }
}
