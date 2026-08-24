<?php
/**
 * 文章编辑/发布 v2.1
 * 支持多图片上传，增加防重复提交
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';
require_once LM_ROOT . '/includes/PageCache.php';

session_start();
requireAdmin();

$pageTitle = '写文章';
$currentPage = 'article-edit';

$article = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'content' => '',
    'excerpt' => '',
    'cover_image' => '',
    'category_id' => 0,
    'tags' => '',
    'status' => 'published',
    'is_top' => 0
];
$articleStorage = 'database';
$originalArticleContent = '';
$preparedMarkdown = null;
$articleDatabaseSaved = false;
$markdownSourceUnavailable = false;

$articleImages = [];
$error = '';
$success = '';

// PRG后显示成功消息（依据请求参数区分新建/编辑：此处 $article 尚未从DB加载，不能用 $article['id']）
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['id'])) {
    $success = isset($_GET['created']) ? '文章已发布' : '文章已更新';
}

// 获取分类列表
$categories = getCategories();

// 编辑模式
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $existing = db()->fetchOne("SELECT * FROM lm_article WHERE id = ?", [$id]);
        if ($existing) {
            $article = $existing;
            $originalArticleContent = (string)$existing['content'];
            $articleStorage = ArticleContent::isMarkdownArticle($article) ? 'markdown' : 'database';
            if ($articleStorage === 'markdown') {
                $markdownSource = ArticleContent::source($article);
                if ($markdownSource === '') {
                    $markdownSourceUnavailable = true;
                    $article['content'] = '';
                    $error = 'Markdown 源文件缺失或校验失败。前台仍使用数据库快照展示，但当前禁止保存，请先从备份恢复 includes/articles 中的源文件。';
                } else {
                    $article['content'] = $markdownSource;
                }
            }
            $pageTitle = '编辑文章';

            // 获取文章图片
            $articleImages = db()->fetchAll(
                "SELECT * FROM lm_article_image WHERE article_id = ? ORDER BY sort_order ASC, id ASC",
                [$id]
            );
        }
    } catch (Exception $e) {
        $error = '文章不存在';
    }
}

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } elseif ($markdownSourceUnavailable) {
        $error = 'Markdown 源文件不可用，已阻止覆盖保存。请先恢复源文件。';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $requestedStorage = ($_POST['content_storage'] ?? 'database') === 'markdown' ? 'markdown' : 'database';
        // Existing articles keep their original storage mode. This prevents implicit,
        // lossy conversion between legacy HTML and Markdown source.
        $articleStorage = $article['id'] > 0
            ? (ArticleContent::isMarkdownArticle(['content' => $originalArticleContent]) ? 'markdown' : 'database')
            : $requestedStorage;
        $submittedContent = (string)($_POST['content'] ?? '');
        $content = $articleStorage === 'markdown'
            ? str_replace(["\r\n", "\r"], "\n", $submittedContent)
            : Security::xssCleanHtml($submittedContent);
        $excerpt = trim($_POST['excerpt'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $tags = trim($_POST['tags'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';
        $isTop = isset($_POST['pinned']) ? 1 : 0;
        $coverImage = trim($_POST['cover_image'] ?? '');

        $maxImages = 10;

        // 验证封面图片URL
        if (!empty($coverImage) && !isValidImageUrl($coverImage)) {
            $error = '封面图片链接格式不正确';
        }

        if (empty($title)) {
            $error = '请输入文章标题';
        } elseif (empty($content)) {
            $error = '请输入文章内容';
        } else {
            // 防重复提交：检查短时间内是否已发布过相同标题的文章
            $submitKey = 'article_submit_' . md5($title . $content);
            if ($article['id'] == 0 && isset($_SESSION[$submitKey]) && (time() - $_SESSION[$submitKey]) < 10) {
                $error = '文章正在发布中，请勿重复提交';
            } else {
                if ($article['id'] == 0) {
                    $_SESSION[$submitKey] = time();
                }

                // 处理封面上传（优先使用上传覆盖直链输入）
                if (isset($_FILES['cover_upload']) && $_FILES['cover_upload']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = saveUploadedImage($_FILES['cover_upload'], 'cover_');
                    if ($uploadResult['success']) {
                        $coverImage = $uploadResult['url'];
                    } else {
                        $error = '封面上传失败：' . $uploadResult['message'];
                    }
                }

                // 生成slug
                if (empty($slug)) {
                    $slug = generateSlug($title);
                }

                try {
                    $baseSlug = $slug;
                    $counter = 1;
                    while (db()->fetchColumn(
                        "SELECT COUNT(*) FROM lm_article WHERE slug = ? AND id <> ?",
                        [$slug, $article['id']]
                    ) > 0) {
                        $slug = $baseSlug . '-' . $counter;
                        $counter++;
                    }

                    if ($articleStorage === 'markdown') {
                        $preparedMarkdown = ArticleContent::prepareMarkdown($content, $slug);
                        $content = $preparedMarkdown['content'];
                        $excerptSource = getArticlePlainText(['content' => $content]);
                    } else {
                        $excerptSource = strip_tags($content);
                    }

                    $data = [
                        'title' => $title,
                        'slug' => $slug,
                        'content' => $content,
                        'excerpt' => $excerpt ?: getExcerpt($excerptSource, 80),
                        'cover_image' => $coverImage,
                        'category_id' => $categoryId,
                        'tags' => $tags,
                        'status' => $status,
                        'is_top' => $isTop
                    ];

                    if ($article['id'] > 0) {
                        // 更新：不覆盖原作者，仅刷新 updated_at
                        $data['updated_at'] = date('Y-m-d H:i:s');
                        db()->update('lm_article', $data, 'id = ?', [$article['id']]);
                        $articleDatabaseSaved = true;
                        $articleId = $article['id'];
                        $success = '文章已更新';
                    } else {
                        // 新建前再次检查是否已有相同标题+slug的已发布文章（防并发重复）
                        $exists = db()->fetchColumn(
                            "SELECT COUNT(*) FROM lm_article WHERE title = ? AND slug = ? AND status = 'published'",
                            [$title, $slug]
                        );
                        if ($exists > 0) {
                            $error = '已存在相同标题的文章，请勿重复发布';
                        } else {
                            // 新建：仅新建时设置作者，避免覆盖原文章作者
                            $data['author_id'] = $_SESSION['user_id'];
                            $articleId = db()->insert('lm_article', $data);
                            $articleDatabaseSaved = true;
                            $article['id'] = $articleId;
                            $success = '文章已发布';
                        }
                    }

                    // 只有成功保存后才处理图片和跳转
                    if (empty($error)) {
                        if ($preparedMarkdown !== null) {
                            ArticleContent::commitPrepared($preparedMarkdown);
                        }
                        ArticleContent::cleanupPrevious($originalArticleContent, $content);
                        // ------- 图片总数管理 -------
                        // 现有图片
                        $existingImages = [];
                        if ($articleId > 0) {
                            $rows = db()->fetchAll(
                                "SELECT id, image_url FROM lm_article_image WHERE article_id = ? ORDER BY sort_order ASC, id ASC",
                                [$articleId]
                            );
                            foreach ($rows as $r) {
                                $existingImages[] = $r;
                            }
                        }

                        // 标记要删除的
                        $deleteIds = [];
                        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                            foreach ($_POST['delete_images'] as $deleteId) {
                                $deleteIds[] = (int)$deleteId;
                            }
                        }

                        // 保留的数量
                        $kept = 0;
                        foreach ($existingImages as $ei) {
                            if (!in_array((int)$ei['id'], $deleteIds, true)) {
                                $kept++;
                            }
                        }

                        // 处理直链图片（textarea 每行一个，兼容 $_POST['article_image_urls'][]）
                        $directUrls = [];
                        if (!empty($_POST['article_image_urls_text'])) {
                            $lines = preg_split('/\r\n|\r|\n/', $_POST['article_image_urls_text']);
                            foreach ($lines as $line) {
                                $url = trim($line ?? '');
                                if ($url !== '' && isValidImageUrl($url)) {
                                    $directUrls[] = $url;
                                }
                            }
                        }
                        if (isset($_POST['article_image_urls']) && is_array($_POST['article_image_urls'])) {
                            foreach ($_POST['article_image_urls'] as $url) {
                                $url = trim($url ?? '');
                                if ($url !== '' && isValidImageUrl($url) && !in_array($url, $directUrls, true)) {
                                    $directUrls[] = $url;
                                }
                            }
                        }

                        // 处理上传图片
                        $uploadedUrls = [];
                        if (isset($_FILES['article_images']) && is_array($_FILES['article_images']['name'])) {
                            $fileCount = count($_FILES['article_images']['name']);
                            for ($i = 0; $i < $fileCount; $i++) {
                                if ($_FILES['article_images']['error'][$i] !== UPLOAD_ERR_OK) {
                                    continue;
                                }
                                $file = [
                                    'name' => $_FILES['article_images']['name'][$i],
                                    'type' => $_FILES['article_images']['type'][$i],
                                    'tmp_name' => $_FILES['article_images']['tmp_name'][$i],
                                    'error' => $_FILES['article_images']['error'][$i],
                                    'size' => $_FILES['article_images']['size'][$i]
                                ];
                                $result = saveUploadedImage($file, 'article_');
                                if ($result['success']) {
                                    $uploadedUrls[] = $result['url'];
                                }
                            }
                        }

                        // 合并新增，按剩余名额限制
                        $available = max(0, $maxImages - $kept);
                        $newUrls = [];
                        foreach ($directUrls as $u) {
                            if (count($newUrls) >= $available) break;
                            $newUrls[] = $u;
                        }
                        foreach ($uploadedUrls as $u) {
                            if (count($newUrls) >= $available) break;
                            $newUrls[] = $u;
                        }

                        // 执行删除
                        if (!empty($deleteIds)) {
                            foreach ($deleteIds as $did) {
                                $img = db()->fetchOne("SELECT image_url FROM lm_article_image WHERE id = ? AND article_id = ?", [$did, $articleId]);
                                if ($img) {
                                    $filePath = LM_ROOT . $img['image_url'];
                                    if (strpos($img['image_url'], '/assets/uploads/') === 0 && file_exists($filePath)) {
                                        @unlink($filePath);
                                    }
                                    db()->delete('lm_article_image', 'id = ?', [$did]);
                                }
                            }
                        }

                        // 插入新增图片
                        if (!empty($newUrls)) {
                            // 获取当前最大sort_order
                            $maxOrder = (int)db()->fetchColumn(
                                "SELECT COALESCE(MAX(sort_order), -1) FROM lm_article_image WHERE article_id = ?",
                                [$articleId]
                            );
                            foreach ($newUrls as $idx => $url) {
                                db()->insert('lm_article_image', [
                                    'article_id' => $articleId,
                                    'image_url' => $url,
                                    'sort_order' => $maxOrder + $idx + 1
                                ]);
                            }
                        }

                        // 发布/更新会改变前台列表（含置顶状态），清空页面缓存以立即生效
                        PageCache::purgeAll();

                        // PRG模式防止刷新重复提交；新建时带 created=1 标记以便回显时区分提示语
                        $createdFlag = ($success === '文章已发布') ? '&created=1' : '';
                        header('Location: article-edit.php?id=' . $articleId . '&success=1' . $createdFlag);
                        exit;
                    } elseif ($preparedMarkdown !== null && !$articleDatabaseSaved) {
                        ArticleContent::discardPrepared($preparedMarkdown);
                    }
                } catch (Exception $e) {
                    if ($preparedMarkdown !== null && !$articleDatabaseSaved) {
                        ArticleContent::discardPrepared($preparedMarkdown);
                    }
                    $error = '保存失败: ' . $e->getMessage();
                }
            }
        }
    }
}

require_once LM_ROOT . '/admin/template/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><?php echo $article['id'] > 0 ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>编辑文章' : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>写文章'; ?></div>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <?php echo Security::csrfField(); ?>

            <div class="form-group">
                <label class="form-label">文章标题 *</label>
                <input type="text" name="title" class="form-input" placeholder="请输入标题" required
                       value="<?php echo e($article['title']); ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">URL别名</label>
                    <input type="text" name="slug" class="form-input" placeholder="留空自动生成"
                           value="<?php echo e($article['slug']); ?>">
                    <div class="form-hint">用于URL显示，如: my-first-post</div>
                </div>

                <div class="form-group">
                    <label class="form-label">分类</label>
                    <select name="category_id" class="form-select">
                        <option value="0">未分类</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $article['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo e($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">封面图片</label>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="flex: 1;">
                        <input type="text" name="cover_image" class="form-input" placeholder="图片URL或上传"
                               value="<?php echo e($article['cover_image']); ?>" id="cover-input">
                        <div class="form-hint">支持外部图片链接或本地上传</div>
                    </div>
                    <div>
                        <input type="file" name="cover_upload" class="form-input" accept="image/*" data-preview="cover-preview"
                               style="padding: 8px;">
                    </div>
                </div>
                <?php if ($article['cover_image']): ?>
                <img src="<?php echo e($article['cover_image']); ?>" id="cover-preview" style="max-width: 200px; margin-top: 8px; border-radius: var(--radius);">
                <?php else: ?>
                <img src="" id="cover-preview" style="max-width: 200px; margin-top: 8px; border-radius: var(--radius); display: none;">
                <?php endif; ?>
            </div>

            <!-- 多图上传区域 -->
            <div class="form-group" id="article-images-group">
                <label class="form-label">
                    文章图片（最多10张，支持上传或填写直链）
                    <span id="image-counter" style="color: var(--text-light); font-weight: normal; font-size: 0.85rem;">
                        当前: <?php echo count($articleImages); ?> / 10
                    </span>
                </label>

                <!-- 已有图片 -->
                <?php if (!empty($articleImages)): ?>
                <div class="article-image-list" id="existing-images" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin-bottom: 16px;">
                    <?php foreach ($articleImages as $img): ?>
                    <div class="upload-preview-item" data-id="<?php echo (int)$img['id']; ?>" style="position: relative; border: 1px solid var(--border-color); border-radius: var(--radius); padding: 6px; background: var(--bg-color);">
                        <img src="<?php echo e($img['image_url']); ?>" alt="" style="width: 100%; height: 100px; object-fit: cover; border-radius: calc(var(--radius) - 2px);">
                        <label style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.7); color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                            <input type="checkbox" name="delete_images[]" value="<?php echo (int)$img['id']; ?>" style="width: auto; margin: 0;" class="delete-image-checkbox">
                            删除
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- 新增图片预览区（新增直链/上传预览） -->
                <div id="new-images-preview" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin-bottom: 16px;"></div>

                <!-- 直链输入 -->
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 6px;">添加图片直链（每行一个，可填写多个）：</div>
                    <textarea name="article_image_urls_text" id="article-image-urls-text" class="form-textarea" style="min-height: 70px; font-family: monospace; font-size: 0.85rem;" placeholder="https://example.com/img1.jpg&#10;https://example.com/img2.jpg"></textarea>
                </div>

                <!-- 上传新图片 -->
                <div class="upload-area">
                    <input type="file" name="article_images[]" id="article-images-input" multiple accept="image/*" style="display: none;">
                    <label for="article-images-input" style="cursor: pointer; display: block;">
                        <div><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 4px;"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>点击或拖拽图片到此处上传</div>
                    </label>
                    <div style="font-size: 0.8rem; margin-top: 4px; color: var(--text-light);">支持多张图片同时上传，单张最大5MB</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">标签</label>
                <input type="text" name="tags" class="form-input" placeholder="多个标签用逗号分隔，如: PHP,技术,教程"
                       value="<?php echo e($article['tags']); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">摘要</label>
                <textarea name="excerpt" class="form-textarea" placeholder="文章摘要，留空自动从内容提取" style="min-height: 80px;"><?php echo e($article['excerpt']); ?></textarea>
            </div>

            <div class="form-group">
                <div class="article-storage-heading">
                    <label class="form-label" for="article-content">文章内容 *</label>
                    <div class="article-storage-switch" role="radiogroup" aria-label="正文存储方式">
                        <label class="storage-option<?php echo $articleStorage === 'markdown' ? ' active' : ''; ?>">
                            <input type="radio" name="content_storage" value="markdown" <?php echo $articleStorage === 'markdown' ? 'checked' : ''; ?> <?php echo $article['id'] > 0 ? 'disabled' : ''; ?>>
                            <span>Markdown 文件</span>
                        </label>
                        <label class="storage-option<?php echo $articleStorage === 'database' ? ' active' : ''; ?>">
                            <input type="radio" name="content_storage" value="database" <?php echo $articleStorage === 'database' ? 'checked' : ''; ?> <?php echo $article['id'] > 0 ? 'disabled' : ''; ?>>
                            <span>数据库 HTML</span>
                        </label>
                    </div>
                </div>
                <div class="storage-compat-note">
                    <?php echo $article['id'] > 0 ? '既有文章已锁定原存储方式，避免 HTML 与 Markdown 隐式转换造成内容损坏。' : 'Markdown 模式会在数据库保留渲染快照，评论、点赞、搜索和文章链接继续兼容。'; ?>
                </div>
                <div class="editor-toolbar" style="display: flex; gap: 8px; align-items: center; padding: 8px 10px; margin-bottom: 8px; background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius); border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                    <button type="button" class="btn btn-secondary btn-sm" id="insert-image-btn" title="在光标处插入图片" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; font-size: 0.85rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        插入图片
                    </button>
                    <span style="font-size: 0.78rem; color: var(--text-light);">在光标位置插入图片（上传或直链）</span>
                </div>
                <textarea name="content" id="article-content" class="form-textarea article-source-editor" placeholder="<?php echo $articleStorage === 'markdown' ? '使用 Markdown 编写文章' : '支持 HTML 标签'; ?>" required><?php echo e($article['content']); ?></textarea>
                <div class="form-hint" id="content-storage-hint"><?php echo $articleStorage === 'markdown' ? '支持标题、列表、引用、代码块、链接和图片等 Markdown 语法。' : '支持 p、strong、h1-h6、列表、引用、代码、链接、图片等 HTML 标签。'; ?></div>
            </div>

            <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" name="pinned" value="1" <?php echo $article['is_top'] ? 'checked' : ''; ?> style="width: auto;">
                    <span>置顶文章</span>
                </label>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="margin-bottom: 0;">状态:</label>
                    <select name="status" class="form-select" style="width: auto;">
                        <option value="published" <?php echo $article['status'] === 'published' ? 'selected' : ''; ?>>发布</option>
                        <option value="draft" <?php echo $article['status'] === 'draft' ? 'selected' : ''; ?>>草稿</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="submit-btn" <?php echo $markdownSourceUnavailable ? 'disabled' : ''; ?>><?php echo $markdownSourceUnavailable ? '源文件不可用' : ($article['id'] > 0 ? '保存修改' : '发布文章'); ?></button>
                <a href="articles.php" class="btn btn-secondary">返回列表</a>
                <?php if ($article['id'] > 0): ?>
                <a href="/article.php?slug=<?php echo e($article['slug']); ?>" target="_blank" class="btn btn-secondary">预览</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- 插入图片模态框 -->
<div class="modal-overlay" id="inline-image-modal" role="dialog" aria-modal="true" aria-labelledby="inline-image-modal-title" aria-hidden="true" style="display: none; position: fixed; inset: 0; background: rgba(45,31,42,0.55); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-panel" role="document" style="background: var(--card-bg); border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); max-width: 480px; width: calc(100% - 32px); max-height: 90vh; overflow: auto;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border-subtle);">
            <h3 id="inline-image-modal-title" style="margin: 0; font-size: 1.05rem; color: var(--text-color);">在光标处插入图片</h3>
            <button type="button" class="modal-close-btn" id="inline-image-close" aria-label="关闭" style="background: none; border: none; font-size: 1.5rem; color: var(--text-light); cursor: pointer; padding: 0; width: 32px; height: 32px; line-height: 32px; text-align: center; border-radius: var(--radius-sm);">×</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <!-- Tab 切换 -->
            <div class="inline-image-tabs" role="tablist" style="display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle);">
                <button type="button" role="tab" aria-selected="true" id="tab-upload" class="inline-image-tab active" style="background: none; border: none; padding: 8px 16px; cursor: pointer; font-size: 0.9rem; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); margin-bottom: -1px;">上传本地图片</button>
                <button type="button" role="tab" aria-selected="false" id="tab-direct" class="inline-image-tab" style="background: none; border: none; padding: 8px 16px; cursor: pointer; font-size: 0.9rem; color: var(--text-secondary); border-bottom: 2px solid transparent; margin-bottom: -1px;">填写图片直链</button>
            </div>

            <!-- 上传面板 -->
            <div class="inline-image-panel" id="panel-upload" role="tabpanel" aria-labelledby="tab-upload">
                <input type="file" id="inline-image-file" accept="image/jpeg,image/png,image/gif,image/webp" style="display: block; margin-bottom: 12px; width: 100%; padding: 8px; border: 1px dashed var(--border-color); border-radius: var(--radius); background: var(--bg-subtle);">
                <div class="inline-image-preview" id="inline-preview-upload" style="display: none; margin-bottom: 12px; text-align: center; padding: 12px; background: var(--bg-subtle); border-radius: var(--radius); border: 1px solid var(--border-subtle);">
                    <img id="inline-preview-img-upload" src="" alt="预览" style="max-width: 100%; max-height: 220px; border-radius: var(--radius-sm); margin: 0 auto;">
                </div>
                <div class="inline-image-hint" style="font-size: 0.78rem; color: var(--text-light); margin-bottom: 12px;">支持 JPG/PNG/GIF/WEBP，单张最大 5MB</div>
            </div>

            <!-- 直链面板 -->
            <div class="inline-image-panel" id="panel-direct" role="tabpanel" aria-labelledby="tab-direct" style="display: none;">
                <input type="url" id="inline-image-url" placeholder="https://example.com/image.jpg" style="display: block; width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: var(--radius); font-size: 0.9rem; background: var(--input-bg); color: var(--text-color); margin-bottom: 12px;">
                <div class="inline-image-preview" id="inline-preview-direct" style="display: none; margin-bottom: 12px; text-align: center; padding: 12px; background: var(--bg-subtle); border-radius: var(--radius); border: 1px solid var(--border-subtle);">
                    <img id="inline-preview-img-direct" src="" alt="预览" style="max-width: 100%; max-height: 220px; border-radius: var(--radius-sm); margin: 0 auto;">
                </div>
                <div class="inline-image-hint" style="font-size: 0.78rem; color: var(--text-light); margin-bottom: 12px;">请输入完整图片 URL（以 http:// 或 https:// 开头）</div>
            </div>

            <!-- 错误提示 -->
            <div class="inline-image-error" id="inline-image-error" role="alert" style="display: none; margin-bottom: 12px; padding: 8px 12px; background: var(--danger-light); color: var(--danger-color); border-radius: var(--radius); font-size: 0.85rem; border: 1px solid var(--danger-color);"></div>

            <!-- 操作按钮 -->
            <div class="modal-footer" style="display: flex; gap: 8px; justify-content: flex-end; padding-top: 8px; border-top: 1px solid var(--border-subtle); margin-top: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" id="inline-image-cancel" style="padding: 6px 16px;">取消</button>
                <button type="button" class="btn btn-primary btn-sm" id="inline-image-confirm" style="padding: 6px 16px;">插入图片</button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/admin/admin-article-edit.js?v=<?php echo LM_VERSION; ?>"></script>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
