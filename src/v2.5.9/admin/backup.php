<?php
/**
 * 数据备份 / 还原 v2.0
 * - 一键导出：数据库 SQL + 文章 Markdown 打包为 zip 下载
 * - 一键导入：上传上面导出的原格式 zip，完全覆盖还原
 */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/Backup.php';

session_start();
requireAdmin();

$pageTitle = '数据备份';
$currentPage = 'backup';

$error = '';
$success = '';
$importResult = null;

// 单次上传上限（upload_max_filesize 与 post_max_size 取较小值，仅用于提示）
$effectiveUploadMax = min(
    appParseSize(ini_get('upload_max_filesize')),
    appParseSize(ini_get('post_max_size'))
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $action = $_POST['action'] ?? 'export';

        if ($action === 'export') {
            $tmpFile = null;
            try {
                if (!class_exists('ZipArchive')) {
                    throw new RuntimeException('当前 PHP 未启用 ZipArchive 扩展，无法生成压缩包。');
                }
                $tmpFile = Backup::exportZip(db()->getPdo(), APP_ROOT, DB_NAME);

                // 推送下载
                $downloadName = 'backup-' . date('Ymd-His') . '.zip';
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $downloadName . '"');
                header('Content-Length: ' . filesize($tmpFile));
                header('Cache-Control: no-store');
                readfile($tmpFile);
                @unlink($tmpFile);
                exit;
            } catch (Exception $e) {
                if ($tmpFile) {
                    @unlink($tmpFile);
                }
                $error = '备份失败：' . $e->getMessage();
            }
        } elseif ($action === 'import') {
            // 完全覆盖还原，可能耗时较长
            @set_time_limit(0);

            if (($_POST['confirm_overwrite'] ?? '') !== '1') {
                $error = '请先勾选「我已知晓：导入会完全覆盖现有数据」';
            } elseif (empty($_FILES['backup_zip']) || !isset($_FILES['backup_zip']['tmp_name'])) {
                $error = '请选择要导入的备份 zip 文件'
                    . ($effectiveUploadMax < PHP_INT_MAX ? '（单次上传上限约 ' . appFormatSize($effectiveUploadMax) . '）' : '');
            } else {
                $file = $_FILES['backup_zip'];
                $tmpFile = null;

                try {
                    if (!class_exists('ZipArchive')) {
                        throw new RuntimeException('当前 PHP 未启用 ZipArchive 扩展，无法读取压缩包。');
                    }
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException(appUploadErrorMessage((int)$file['error']));
                    }
                    if (!is_uploaded_file($file['tmp_name'])) {
                        throw new RuntimeException('上传文件校验失败，请重试。');
                    }

                    // 落盘到临时目录，交由 Backup 统一处理
                    $tmpFile = tempnam(sys_get_temp_dir(), 'imp_');
                    if ($tmpFile === false || !move_uploaded_file($file['tmp_name'], $tmpFile)) {
                        throw new RuntimeException('无法保存上传的压缩包，请检查系统临时目录权限。');
                    }

                    $info = Backup::inspectZip($tmpFile);
                    if (!$info['valid']) {
                        throw new RuntimeException($info['message'] ?: '压缩包内容无法识别。');
                    }

                    $importResult = Backup::importZip($tmpFile, db()->getPdo(), APP_ROOT, true);
                    $success = sprintf(
                        '还原完成：数据库执行 %d 条语句（重建 %d 张表），文章 Markdown 还原 %d 个。',
                        $importResult['statements'],
                        $importResult['tables'],
                        $importResult['articles']
                    );
                    if (!empty($importResult['warnings'])) {
                        $success .= ' 注意：' . implode('；', array_slice($importResult['warnings'], 0, 5));
                    }
                } catch (Exception $e) {
                    $error = '导入失败：' . $e->getMessage();
                } finally {
                    if ($tmpFile) {
                        @unlink($tmpFile);
                    }
                }
            }
        }
    }
}

/**
 * 解析 php.ini 的 size 写法（如 8M / 2G）为字节数；0 或空表示不限
 */
function appParseSize($value): int
{
    $value = trim((string)$value);
    if ($value === '') {
        return PHP_INT_MAX;
    }
    $unit = strtolower(substr($value, -1));
    $num = (float)$value;
    switch ($unit) {
        case 'g':
            $num *= 1024 * 1024 * 1024;
            break;
        case 'm':
            $num *= 1024 * 1024;
            break;
        case 'k':
            $num *= 1024;
            break;
    }
    if ($num <= 0) {
        // php.ini 中 0 代表不限制
        return PHP_INT_MAX;
    }
    return (int)$num;
}

function appFormatSize(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) {
        return round($bytes / 1024 / 1024 / 1024, 1) . ' GB';
    }
    if ($bytes >= 1024 * 1024) {
        return round($bytes / 1024 / 1024, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function appUploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return '上传文件超过服务器 upload_max_filesize 限制。';
        case UPLOAD_ERR_FORM_SIZE:
            return '上传文件超过表单限制。';
        case UPLOAD_ERR_PARTIAL:
            return '文件只上传了一部分，请重试。';
        case UPLOAD_ERR_NO_FILE:
            return '没有选择文件。';
        case UPLOAD_ERR_NO_TMP_DIR:
            return '服务器缺少临时目录。';
        case UPLOAD_ERR_CANT_WRITE:
            return '服务器写入磁盘失败。';
        case UPLOAD_ERR_EXTENSION:
            return '上传被 PHP 扩展中断。';
        default:
            return '上传失败（错误码 ' . $code . '）。';
    }
}

require_once APP_ROOT . '/admin/template/header.php';
?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/></svg>数据备份</div>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 8px;">点击下方按钮即可生成并下载全站备份压缩包，内容包含：</p>
        <ul style="margin-bottom: 16px; padding-left: 20px; line-height: 1.8;">
            <li>数据库完整导出（表结构 + 数据，SQL 格式）</li>
            <li>全部文章 Markdown 源文件</li>
            <li>站点配置清单说明（敏感信息已打码，.env 文件本身不会打包）</li>
        </ul>
        <p style="margin-bottom: 16px;">数据较多时生成可能需要几秒钟，请耐心等待，不要重复点击。</p>
        <form method="POST" action="">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="export">
            <button type="submit" class="btn btn-primary">下载备份</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M3 21h18"/></svg>一键导入（还原）</div>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 8px;">上传由本页导出的备份 zip，即可原格式还原数据库与文章。支持的格式为：</p>
        <ul style="margin-bottom: 16px; padding-left: 20px; line-height: 1.8;">
            <li><code>database.sql</code> — 数据库结构与数据</li>
            <li><code>articles/*.md</code> — 文章 Markdown 源文件</li>
        </ul>
        <div class="alert alert-warning" style="margin-bottom: 16px;">
            <strong>⚠️ 导入为完全覆盖式还原</strong>：同名数据表会先被删除再重建，文章文件按文件名覆盖。此操作不可撤销，建议先「下载备份」留存当前数据。
        </div>
        <?php if ($importResult && !empty($importResult['warnings'])): ?>
        <div class="alert alert-error" style="margin-bottom: 16px;">
            导入告警：<?php echo e(implode('；', $importResult['warnings'])); ?>
        </div>
        <?php endif; ?>
        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return confirm('确认要完全覆盖现有数据吗？此操作不可撤销！');">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="import">
            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">选择备份 zip 文件</label>
                <input type="file" name="backup_zip" accept=".zip,application/zip" class="form-input" required>
                <div class="form-hint">单次上传上限约 <?php echo e($effectiveUploadMax >= PHP_INT_MAX ? '不限' : appFormatSize($effectiveUploadMax)); ?>，若超出请调整 php.ini 的 upload_max_filesize / post_max_size。</div>
            </div>
            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px; cursor: pointer;">
                <input type="checkbox" name="confirm_overwrite" value="1" style="width: auto;" required>
                <span>我已知晓：导入会完全覆盖现有数据</span>
            </label>
            <button type="submit" class="btn btn-danger">上传并还原</button>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/admin/template/footer.php'; ?>
