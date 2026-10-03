<?php
/**
 * 邮件通知（SMTP）
 *
 * 纯 PHP socket 实现，无第三方依赖；支持 SSL(465) / STARTTLS(587) / 明文(25)。
 * 配置存于 app_setting 表，由后台「网站设置 → 邮件提醒」维护：
 *   smtp_enabled / smtp_host / smtp_port / smtp_encryption(ssl|tls|none)
 *   smtp_username / smtp_password / smtp_from_email / smtp_from_name
 *   notify_admin_email / notify_link_apply / notify_comment_reply
 *
 * 所有通知入口（app_send_mail / app_notify_admin / app_notify_link_apply /
 * app_notify_comment_reply）内部均 try/catch，发信失败只写 error_log，
 * 绝不影响评论、友链申请等主流程。
 */

class AppMailer {
    private $host;
    private $port;
    private $encryption; // ssl | tls | none
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $timeout = 20;
    private $skipVerify = false;

    /** @var resource|null */
    private $socket = null;

    public function __construct(array $cfg) {
        $this->encryption = self::normalizeEncryption($cfg['encryption'] ?? 'ssl');
        $parsed           = self::parseHost((string)($cfg['host'] ?? ''));
        $this->host       = $parsed['host'];
        $this->username   = trim((string)($cfg['username'] ?? ''));
        $this->password   = (string)($cfg['password'] ?? '');
        $this->fromEmail  = trim((string)($cfg['from_email'] ?? ''));
        $this->fromName   = trim((string)($cfg['from_name'] ?? ''));
        $this->skipVerify = !empty($cfg['skip_verify']);

        // 端口优先级：显式填写的端口 > 服务器地址里自带的端口 > 加密方式的默认端口
        $port = (int)($cfg['port'] ?? 0);
        if ($port <= 0) {
            $port = (int)($parsed['port'] ?? 0);
        }
        if ($port <= 0) {
            $port = $this->encryption === 'ssl' ? 465 : ($this->encryption === 'tls' ? 587 : 25);
        }
        $this->port = $port;

        if ($this->fromEmail === '') {
            $this->fromEmail = $this->username;
        }
    }

    /**
     * 清洗服务器地址：去掉误填的协议头（ssl:// / https:// …）、空格与路径，并拆出可能写在一起的端口。
     * 例："ssl://smtp.qq.com:465/" → ['host' => 'smtp.qq.com', 'port' => 465]
     * 这类地址直接拼成 "ssl://ssl://host:465:465" 会让连接在传输层就失败。
     */
    public static function parseHost($host) {
        $host = preg_replace('/\s+/', '', (string)$host);
        $host = preg_replace('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', '', $host);
        $slash = strpos($host, '/');
        if ($slash !== false) {
            $host = substr($host, 0, $slash);
        }
        // 去掉 user:pass@ 形式的认证信息，只保留主机名
        $at = strrpos($host, '@');
        if ($at !== false) {
            $host = substr($host, $at + 1);
        }

        $port = null;
        if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $host, $m)) {
            // IPv6 字面量：[::1]:465
            $host = $m[1];
            if (!empty($m[2])) {
                $port = (int)$m[2];
            }
        } elseif (preg_match('/^([^:]+):(\d+)$/', $host, $m)) {
            $host = $m[1];
            $port = (int)$m[2];
        }

        return ['host' => trim($host), 'port' => $port];
    }

    /**
     * 统一加密方式取值，容忍 starttls/STARTTLS 之类写法。
     */
    public static function normalizeEncryption($encryption) {
        $encryption = strtolower(trim((string)$encryption));
        if ($encryption === 'starttls') {
            $encryption = 'tls';
        }
        if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) {
            $encryption = 'ssl';
        }
        return $encryption;
    }

    /**
     * 从站点设置构建实例；未启用或配置不完整时返回 null。
     */
    public static function fromSettings() {
        if (getSetting('smtp_enabled', '0') !== '1') {
            return null;
        }
        $host = trim(getSetting('smtp_host', ''));
        $user = trim(getSetting('smtp_username', ''));
        if ($host === '' || $user === '') {
            return null;
        }
        return new self([
            'host'        => $host,
            'port'        => (int)getSetting('smtp_port', '465'),
            'encryption'  => getSetting('smtp_encryption', 'ssl'),
            'username'    => $user,
            'password'    => getSetting('smtp_password', ''),
            'from_email'  => getSetting('smtp_from_email', ''),
            'from_name'   => getSetting('smtp_from_name', '') !== '' ? getSetting('smtp_from_name', '') : getSetting('site_name', ''),
            'skip_verify' => getSetting('smtp_skip_verify', '0') === '1',
        ]);
    }

    /**
     * 发送邮件（HTML + 纯文本双格式）。
     * @throws Exception 发送失败时抛出，由调用方捕获
     */
    public function send($to, $subject, $htmlBody, $textBody = '') {
        $to = trim((string)$to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('收件人邮箱格式不正确');
        }
        if ($textBody === '') {
            // 由 HTML 生成纯文本兜底
            $textBody = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $htmlBody)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $this->connect();
        try {
            $this->smtpHello();
            if ($this->encryption === 'tls') {
                $this->smtpCommand('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('STARTTLS 加密握手失败');
                }
                $this->smtpHello();
            }
            // 登录
            $this->smtpCommand('AUTH LOGIN', 334);
            $this->smtpCommand(base64_encode($this->username), 334);
            $this->smtpCommand(base64_encode($this->password), 235);
            // 信封
            $this->smtpCommand('MAIL FROM:<' . $this->fromEmail . '>', 250);
            $this->smtpCommand('RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand('DATA', 354);
            $this->sendData($this->buildMessage($to, $subject, $htmlBody, $textBody));
            $this->smtpCommand('QUIT', 221);
        } finally {
            $this->close();
        }
        return true;
    }

    private function connect() {
        if ($this->host === '') {
            throw new Exception('SMTP 服务器地址为空，请在后台「网站设置 → 邮件提醒」中填写');
        }
        if ($this->port <= 0 || $this->port > 65535) {
            throw new Exception('SMTP 端口不合法：' . $this->port);
        }
        // 提前拦截环境问题：否则 fsockopen 只会返回没头没尾的「 (0)」
        if ($this->encryption !== 'none' && !extension_loaded('openssl')) {
            throw new Exception('PHP 未启用 openssl 扩展，无法使用 SSL/TLS 连接 SMTP。请在 php.ini 中启用 extension=openssl，或把加密方式改为「无加密」');
        }
        if ($this->encryption === 'ssl' && !in_array('ssl', stream_get_transports(), true)) {
            throw new Exception('当前 PHP 缺少 ssl 传输层（stream_get_transports 中没有 ssl），请检查 openssl 扩展是否正确安装');
        }

        $remote  = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => !$this->skipVerify,
                'verify_peer_name'  => !$this->skipVerify,
                'allow_self_signed' => $this->skipVerify,
                'SNI_enabled'       => true,
                'peer_name'         => $this->host,
            ],
        ]);

        $errno  = 0;
        $errstr = '';
        $before = error_get_last();
        $this->socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) {
            // fsockopen/stream_socket_client 在传输层或 SSL 握手失败时常把 errno 留 0、errstr 留空，
            // 真正的线索在 PHP 警告里（例如证书校验失败），这里把它取回来。
            $detail = trim((string)$errstr);
            if ($detail === '') {
                $detail = self::latestWarning($before);
            }
            if ($detail === '') {
                $detail = '未知错误（PHP 未提供错误信息）';
            }
            throw new Exception(
                'SMTP 连接失败：' . $detail . ' (errno ' . $errno . ')，目标 ' . $remote
                . '；请检查服务器地址/端口/加密方式，并确认该服务器允许对外访问此端口（部分虚拟主机封禁 25/465/587 出站）'
            );
        }

        stream_set_timeout($this->socket, $this->timeout);
        $this->expectResponse(220);
    }

    /**
     * 取本次操作新产生的 PHP 警告（@ 抑制后 error_get_last 仍会记录），
     * 仅当消息与调用前不同才返回，避免把无关的旧警告当成原因。
     */
    private static function latestWarning($before) {
        $after = error_get_last();
        if (!is_array($after) || empty($after['message'])) {
            return '';
        }
        if (is_array($before)
            && ($before['message'] ?? null) === $after['message']
            && ($before['line'] ?? null) === ($after['line'] ?? null)) {
            return '';
        }
        return (string)$after['message'];
    }

    private function close() {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    private function smtpHello() {
        $hostname = 'localhost';
        if (!empty($_SERVER['SERVER_NAME']) && preg_match('/^[a-zA-Z0-9.\-]+$/', $_SERVER['SERVER_NAME'])) {
            $hostname = $_SERVER['SERVER_NAME'];
        }
        $this->smtpCommand('EHLO ' . $hostname, 250);
    }

    /**
     * 发送一条命令并校验响应码。
     * @param int|int[] $expect 期望的 SMTP 响应码
     */
    private function smtpCommand($command, $expect) {
        $this->writeLine($command);
        return $this->expectResponse($expect);
    }

    /**
     * DATA 阶段：整封邮件 + 结束符，最后校验 250。
     */
    private function sendData($message) {
        // 透明传输：行首的 . 需要双写
        $message = preg_replace('/^\./m', '..', $message);
        $this->writeLine($message . "\r\n.");
        $this->expectResponse(250);
    }

    private function writeLine($line) {
        $data = $line . "\r\n";
        $written = @fwrite($this->socket, $data);
        if ($written === false) {
            throw new Exception('SMTP 写入失败');
        }
    }

    private function expectResponse($expect) {
        $expect = (array)$expect;
        $response = '';
        // SMTP 响应可能多行："250-xxx" 为续行，"250 xxx" 或 "250" 为结束行。
        // 原实现只认 line[3] === ' '，遇到 "250\r\n" 这种短结束行会一直读到超时。
        while (($line = @fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (!isset($line[3]) || $line[3] !== '-') {
                break;
            }
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                throw new Exception('SMTP 响应超时（服务器未在 ' . $this->timeout . ' 秒内响应）');
            }
        }
        if ($response === '') {
            throw new Exception('SMTP 服务器未返回任何数据，连接可能已被对端关闭（常见原因：端口与加密方式不匹配）');
        }
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expect, true)) {
            throw new Exception('SMTP 错误响应：' . trim($response));
        }
        return $response;
    }

    /**
     * 组装 RFC 5322 邮件（multipart/alternative：纯文本 + HTML）。
     */
    private function buildMessage($to, $subject, $htmlBody, $textBody) {
        $boundary = '=_app_' . bin2hex(random_bytes(12));
        $fromName = $this->fromName !== '' ? '=?UTF-8?B?' . base64_encode($this->fromName) . '?= ' : '';

        $headers = [];
        $headers[] = 'From: ' . $fromName . '<' . $this->fromEmail . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '.' . time() . '@' . preg_replace('/^www\./', '', parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $parts = [];
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/plain; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = '';
        $parts[] = chunk_split(base64_encode($textBody));
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = '';
        $parts[] = chunk_split(base64_encode($htmlBody));
        $parts[] = '--' . $boundary . '--';

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }
}

/* ==================== 站点通知封装（全部静默容错） ==================== */

/**
 * 通用发信入口：任何失败都只记录日志，返回 false。
 */
function app_send_mail($to, $subject, $htmlBody, $textBody = '') {
    try {
        $mailer = AppMailer::fromSettings();
        if (!$mailer) {
            return false; // 未启用或未配置
        }
        return $mailer->send($to, $subject, $htmlBody, $textBody);
    } catch (Exception $e) {
        error_log('app_send_mail failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * 给管理员发通知（收件人：notify_admin_email，缺省回退 admin_email）。
 */
function app_notify_admin($subject, $htmlBody, $textBody = '') {
    $to = trim(getSetting('notify_admin_email', ''));
    if ($to === '') {
        $to = trim(getSetting('admin_email', ''));
    }
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return app_send_mail($to, $subject, $htmlBody, $textBody);
}

/**
 * 邮件 HTML 外壳：简洁卡片样式，内容少时一目了然。
 * $contentHtml 调用方负责转义后的安全 HTML。
 */
function app_mail_template($title, $contentHtml) {
    $siteName = getSetting('site_name', '');
    $siteUrl = rtrim(SITE_URL, '/');
    return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:24px;background:#f4f5f7;font-family:-apple-system,\'Microsoft YaHei\',sans-serif;">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">'
        . '<div style="padding:16px 24px;background:#4f7cff;color:#ffffff;font-size:16px;font-weight:600;">' . e($title) . '</div>'
        . '<div style="padding:24px;font-size:14px;line-height:1.8;color:#333333;">' . $contentHtml . '</div>'
        . '<div style="padding:12px 24px;border-top:1px solid #eeeeee;font-size:12px;color:#999999;">'
        . '本邮件由 <a href="' . e($siteUrl) . '" style="color:#4f7cff;text-decoration:none;">' . e($siteName) . '</a> 自动发出，请勿直接回复。'
        . '</div></div></body></html>';
}

/**
 * 引用块样式（展示评论/申请原文）。
 */
function app_mail_quote($text, $maxLen = 500) {
    $text = (string)$text;
    // 库中内容已经过 xssClean（HTML 实体转义），先解码再按长度截断、重新转义，保证输出安全
    $plain = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (mb_strlen($plain, 'UTF-8') > $maxLen) {
        $plain = mb_substr($plain, 0, $maxLen, 'UTF-8') . '……';
    }
    return '<div style="margin:8px 0;padding:12px 16px;background:#f6f7f9;border-left:3px solid #4f7cff;border-radius:6px;word-break:break-all;">'
        . nl2br(e($plain)) . '</div>';
}

/**
 * 友链申请通知管理员。
 * @param array $apply 含 site_name / site_url / site_description / email / site_avatar
 */
function app_notify_link_apply(array $apply) {
    if (getSetting('notify_link_apply', '1') !== '1') {
        return false;
    }
    $siteName = getSetting('site_name', '');
    $subject = '【' . $siteName . '】新的友链申请：' . html_entity_decode((string)($apply['site_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $html = '<p>收到一条新的友链申请，详情如下：</p>'
        . '<p style="margin:4px 0;"><strong>网站名称：</strong>' . $apply['site_name'] . '</p>'
        . '<p style="margin:4px 0;"><strong>网站地址：</strong><a href="' . e($apply['site_url']) . '" style="color:#4f7cff;">' . $apply['site_url'] . '</a></p>';
    if (!empty($apply['site_description'])) {
        $html .= '<p style="margin:4px 0;"><strong>网站描述：</strong></p>' . app_mail_quote($apply['site_description'], 200);
    }
    $html .= '<p style="margin:4px 0;"><strong>联系邮箱：</strong>' . $apply['email'] . '</p>'
        . '<p style="margin-top:16px;"><a href="' . e(rtrim(SITE_URL, '/') . '/admin/link-apply.php') . '" style="display:inline-block;padding:8px 20px;background:#4f7cff;color:#ffffff;border-radius:6px;text-decoration:none;">前往后台处理</a></p>';

    return app_notify_admin($subject, app_mail_template('新的友链申请', $html));
}

/**
 * 评论被回复时通知原评论者。
 *
 * @param array  $parentComment 原评论行（nickname / email / content）
 * @param string $replyContent  回复内容（已 xssClean）
 * @param string $replierName   回复者昵称
 * @param string $contextTitle  所在页面标题（文章标题或“留言板”）
 * @param string $contextUrl    查看链接（绝对地址）
 */
function app_notify_comment_reply(array $parentComment, $replyContent, $replierName, $contextTitle, $contextUrl) {
    if (getSetting('notify_comment_reply', '1') !== '1') {
        return false;
    }
    $to = trim((string)($parentComment['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    // 自己回复自己不通知
    $parentNickname = html_entity_decode((string)($parentComment['nickname'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($parentNickname === $replierName) {
        return false;
    }

    $siteName = getSetting('site_name', '');
    $subject = '【' . $siteName . '】您的评论收到了新回复';

    $html = '<p>您好 ' . e($parentNickname) . '：</p>'
        . '<p>您在「' . e($contextTitle) . '」的评论收到了 <strong>' . e($replierName) . '</strong> 的回复：</p>'
        . app_mail_quote($replyContent)
        . '<p style="color:#777777;font-size:13px;">您的原评论：</p>'
        . app_mail_quote($parentComment['content'] ?? '', 200)
        . '<p style="margin-top:16px;"><a href="' . e($contextUrl) . '" style="display:inline-block;padding:8px 20px;background:#4f7cff;color:#ffffff;border-radius:6px;text-decoration:none;">查看回复</a></p>';

    return app_send_mail($to, $subject, app_mail_template('您的评论收到了新回复', $html));
}

/* ==================== SMTP 连通性自检 ==================== */

/**
 * 逐项检查 SMTP 链路（环境 / 域名解析 / TCP 连通 / SSL 握手），只做检查，不发信、不抛异常。
 * 用于「测试邮件发送失败」时定位到具体环节：网络被墙、端口不对、openssl 缺失还是证书校验失败。
 *
 * @return string[] 可直接展示给管理员的结论行
 */
function app_smtp_diagnose($host = null, $port = null, $encryption = null, $skipVerify = null) {
    $lines = [];

    $rawHost = $host !== null ? (string)$host : getSetting('smtp_host', '');
    $parsed  = AppMailer::parseHost($rawHost);
    $hostName = $parsed['host'];

    $port = $port !== null ? (int)$port : (int)getSetting('smtp_port', '465');
    if ($port <= 0) {
        $port = (int)($parsed['port'] ?? 0);
    }
    $encryption = AppMailer::normalizeEncryption($encryption !== null ? $encryption : getSetting('smtp_encryption', 'ssl'));
    if ($port <= 0) {
        $port = $encryption === 'ssl' ? 465 : ($encryption === 'tls' ? 587 : 25);
    }
    if ($skipVerify === null) {
        $skipVerify = getSetting('smtp_skip_verify', '0') === '1';
    }

    $lines[] = 'PHP 版本：' . PHP_VERSION;
    $lines[] = 'openssl 扩展：' . (extension_loaded('openssl') ? '已启用' : '未启用（SSL/TLS 将无法使用）');
    $lines[] = 'ssl 传输层：' . (in_array('ssl', stream_get_transports(), true) ? '可用' : '不可用');

    if ($rawHost !== $hostName) {
        $lines[] = '服务器地址已清洗：' . $rawHost . ' → ' . $hostName;
    }
    $lines[] = '实际连接目标：' . $hostName . ':' . $port . '（' . strtoupper($encryption) . '）';

    if ($hostName === '') {
        $lines[] = '域名解析：跳过（服务器地址为空）';
        return $lines;
    }

    if (filter_var($hostName, FILTER_VALIDATE_IP)) {
        // 填 IP 时本就不需要 DNS，不能报成“解析失败”误导排查
        $lines[] = '域名解析：跳过（目标本身是 IP 地址）';
    } else {
        $ip = @gethostbyname($hostName);
        $lines[] = '域名解析：' . ($ip !== $hostName ? $ip : '失败，无法解析 ' . $hostName);
    }

    // 1) 明文 TCP：用于区分「网络/防火墙不通」与「仅 SSL 握手失败」
    $errno = 0; $errstr = '';
    $before = error_get_last();
    $tcp = @stream_socket_client('tcp://' . $hostName . ':' . $port, $errno, $errstr, 8);
    if ($tcp) {
        $banner = @fgets($tcp, 512);
        @fclose($tcp);
        $lines[] = 'TCP 直连 ' . $hostName . ':' . $port . '：成功'
            . (is_string($banner) && trim($banner) !== '' ? '，服务器问候：' . trim($banner) : '');
    } else {
        $lines[] = 'TCP 直连 ' . $hostName . ':' . $port . '：失败（' . app_smtp_error_detail($errstr, $before) . '，errno ' . $errno . '）';
    }

    // 2) SSL 握手：仅在 ssl 直连模式下有意义
    if ($encryption === 'ssl') {
        $errno = 0; $errstr = '';
        $before = error_get_last();
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => !$skipVerify,
            'verify_peer_name'  => !$skipVerify,
            'allow_self_signed' => $skipVerify,
            'SNI_enabled'       => true,
            'peer_name'         => $hostName,
        ]]);
        $ssl = @stream_socket_client('ssl://' . $hostName . ':' . $port, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
        if ($ssl) {
            $banner = @fgets($ssl, 512);
            @fclose($ssl);
            $lines[] = 'SSL 握手：成功' . (is_string($banner) && trim($banner) !== '' ? '，服务器问候：' . trim($banner) : '');
        } else {
            $lines[] = 'SSL 握手：失败（' . app_smtp_error_detail($errstr, $before) . '，errno ' . $errno . '）'
                . ($skipVerify ? '' : '；若提示证书校验失败，可勾选「跳过 SSL 证书校验」后重试');
        }
    }

    return $lines;
}

/**
 * 自检内部使用：把 errstr 与本次新增的 PHP 警告拼成一句可读原因。
 */
function app_smtp_error_detail($errstr, $before) {
    $detail = trim((string)$errstr);
    if ($detail !== '') {
        return $detail;
    }
    $after = error_get_last();
    if (is_array($after) && !empty($after['message'])
        && (!is_array($before) || ($before['message'] ?? null) !== $after['message'])) {
        return (string)$after['message'];
    }
    return 'PHP 未提供错误信息';
}
