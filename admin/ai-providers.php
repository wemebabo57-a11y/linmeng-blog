<?php
/**
 * AI Provider 管理
 * 支持多 AI 配置的新增、编辑、删除
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = 'AI 管理';
$currentPage = 'ai-providers';

$error = '';
$success = '';

// 处理删除
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $token = $_GET['token'] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF 验证失败';
    } else {
        $id = (int)$_GET['id'];
        try {
            db()->delete('lm_ai_provider', 'id = ?', [$id]);
            db()->delete('lm_ai_summary_cache', 'provider_id = ?', [$id]);
            $success = 'AI Provider 已删除，相关缓存已清空';
        } catch (Exception $e) {
            $error = '删除失败: ' . $e->getMessage();
        }
    }
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_provider') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF 验证失败';
    } else {
        $providerId = isset($_POST['provider_id']) ? (int)$_POST['provider_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $apiUrl = trim($_POST['api_url'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $compatibility = in_array($_POST['compatibility'] ?? '', ['openai', 'claude', 'gemini', 'custom']) ? $_POST['compatibility'] : 'openai';
        $requestTemplate = trim($_POST['request_template'] ?? '');
        $responsePath = trim($_POST['response_path'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        // 校验
        if ($name === '' || $apiUrl === '' || $model === '') {
            $error = '名称、API URL、模型名称均为必填项';
        } elseif (!filter_var($apiUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($apiUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $error = 'API URL 格式不正确';
        } elseif ($providerId === 0 && $apiKey === '') {
            $error = '新增 Provider 时必须填写 API Key';
        } elseif ($compatibility === 'custom' && ($requestTemplate === '' || $responsePath === '')) {
            $error = '自定义模式下请求模板和响应提取路径均为必填';
        } else {
            try {
                $data = [
                    'name' => Security::xssClean($name),
                    'api_url' => Security::xssClean($apiUrl),
                    'model' => Security::xssClean($model),
                    'compatibility' => $compatibility,
                    'enabled' => $enabled,
                    'sort_order' => $sortOrder
                ];

                if ($compatibility === 'custom') {
                    $data['request_template'] = $requestTemplate;
                    $data['response_path'] = Security::xssClean($responsePath);
                } else {
                    $data['request_template'] = null;
                    $data['response_path'] = null;
                }

                // 编辑时留空表示不修改 Key
                if ($apiKey !== '') {
                    $data['api_key'] = Security::encrypt($apiKey);
                }

                if ($providerId > 0) {
                    db()->update('lm_ai_provider', $data, 'id = ?', [$providerId]);
                    $success = 'AI Provider 已更新';
                } else {
                    db()->insert('lm_ai_provider', $data);
                    $success = 'AI Provider 已添加';
                }
            } catch (Exception $e) {
                $error = '保存失败: ' . $e->getMessage();
            }
        }
    }
}

// 获取 Provider 列表
$providers = [];
$editProvider = null;
try {
    $providers = db()->fetchAll("SELECT * FROM lm_ai_provider ORDER BY sort_order DESC, id ASC");

    if (isset($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        foreach ($providers as $p) {
            if ((int)$p['id'] === $editId) {
                $editProvider = $p;
                break;
            }
        }
    }
} catch (Exception $e) {
    $error = '加载失败: ' . $e->getMessage();
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;">
                <path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/>
                <path d="M8.5 8.5v.01"/>
                <path d="M16 15.5v.01"/>
                <path d="M12 12v.01"/>
                <path d="M11 17v.01"/>
                <path d="M7 14v.01"/>
            </svg>
            <?php echo $editProvider ? '编辑 AI Provider' : '添加 AI Provider'; ?>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_provider">
            <input type="hidden" name="provider_id" value="<?php echo $editProvider ? (int)$editProvider['id'] : 0; ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">显示名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="如 OpenAI / 硅基流动 / DeepSeek" required
                           value="<?php echo $editProvider ? e($editProvider['name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">兼容模式 *</label>
                    <select name="compatibility" class="form-select" id="provider-compatibility">
                        <option value="openai" <?php echo ($editProvider && $editProvider['compatibility'] === 'openai') ? 'selected' : ''; ?>>OpenAI 兼容</option>
                        <option value="claude" <?php echo ($editProvider && $editProvider['compatibility'] === 'claude') ? 'selected' : ''; ?>>Anthropic Claude</option>
                        <option value="gemini" <?php echo ($editProvider && $editProvider['compatibility'] === 'gemini') ? 'selected' : ''; ?>>Google Gemini</option>
                        <option value="custom" <?php echo ($editProvider && $editProvider['compatibility'] === 'custom') ? 'selected' : ''; ?>>自定义模板</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">API URL *</label>
                    <input type="url" name="api_url" class="form-input" placeholder="https://api.example.com/v1/chat/completions" required
                           value="<?php echo $editProvider ? e($editProvider['api_url']) : ''; ?>">
                    <div class="form-hint">必须以 http:// 或 https:// 开头</div>
                </div>

                <div class="form-group">
                    <label class="form-label">模型名称 *</label>
                    <input type="text" name="model" class="form-input" placeholder="如 gpt-4o-mini / deepseek-chat" required
                           value="<?php echo $editProvider ? e($editProvider['model']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">API Key <?php echo $editProvider ? '' : '*'; ?></label>
                <input type="password" name="api_key" class="form-input"
                       placeholder="<?php echo $editProvider ? '已保存，留空不修改' : 'sk-xxxxxxxxxxxxxxxxxxxx'; ?>"
                       <?php echo $editProvider ? '' : 'required'; ?>>
                <div class="form-hint">数据库中加密存储，页面不会回显</div>
            </div>

            <div id="custom-fields" style="display: <?php echo ($editProvider && $editProvider['compatibility'] === 'custom') ? 'block' : 'none'; ?>;">
                <div class="form-group">
                    <label class="form-label">自定义请求 JSON 模板</label>
                    <textarea name="request_template" class="form-textarea" style="min-height: 120px; font-family: monospace;" placeholder='{"model":"{model}","messages":[{"role":"user","content":"{prompt}\n\n{content}"}]}'><?php echo $editProvider ? e($editProvider['request_template'] ?? '') : ''; ?></textarea>
                    <div class="form-hint">可用占位符：{model}、{api_key}、{prompt}、{content}</div>
                </div>

                <div class="form-group">
                    <label class="form-label">响应提取路径</label>
                    <input type="text" name="response_path" class="form-input" placeholder="choices.0.message.content"
                           value="<?php echo $editProvider ? e($editProvider['response_path'] ?? '') : ''; ?>">
                    <div class="form-hint">按点号分隔的路径，用于从 JSON 响应中提取总结文本</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" placeholder="数字越大越靠前"
                           value="<?php echo $editProvider ? (int)$editProvider['sort_order'] : 0; ?>">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-top: 28px;">
                    <input type="checkbox" name="enabled" value="1" id="provider_enabled"
                           <?php echo (!$editProvider || (int)$editProvider['enabled'] === 1) ? 'checked' : ''; ?> style="width: auto;">
                    <label for="provider_enabled" style="margin-bottom: 0;">启用</label>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary"><?php echo $editProvider ? '保存修改' : '添加 Provider'; ?></button>
                <?php if ($editProvider): ?>
                <a href="ai-providers.php" class="btn btn-secondary">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
            AI Provider 列表
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>模型</th>
                    <th>兼容模式</th>
                    <th>启用</th>
                    <th>排序</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($providers as $p): ?>
                <tr>
                    <td><?php echo (int)$p['id']; ?></td>
                    <td><?php echo e($p['name']); ?></td>
                    <td><?php echo e($p['model']); ?></td>
                    <td><?php echo e($p['compatibility']); ?></td>
                    <td>
                        <span class="badge <?php echo (int)$p['enabled'] === 1 ? 'badge-primary' : 'badge-secondary'; ?>">
                            <?php echo (int)$p['enabled'] === 1 ? '启用' : '禁用'; ?>
                        </span>
                    </td>
                    <td><?php echo (int)$p['sort_order']; ?></td>
                    <td>
                        <div style="display: flex; gap: 4px;">
                            <a href="?edit=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                            <a href="?action=delete&id=<?php echo (int)$p['id']; ?>&token=<?php echo Security::generateToken(); ?>"
                               class="btn btn-sm btn-danger"
                               data-confirm="确定要删除该 Provider 吗？">删除</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($providers)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-light); padding: 40px;">暂无 Provider，请先添加</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    var select = document.getElementById('provider-compatibility');
    var customFields = document.getElementById('custom-fields');
    if (select && customFields) {
        function toggle() {
            customFields.style.display = select.value === 'custom' ? 'block' : 'none';
        }
        select.addEventListener('change', toggle);
        toggle();
    }
})();
</script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
