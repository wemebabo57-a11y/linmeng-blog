<?php
/**
 * 分类管理 v2.0
 * 完整的分类增删改查功能
 */
define('LM_ROOT', dirname(__DIR__));

require_once LM_ROOT . '/includes/config.php';
require_once LM_ROOT . '/includes/Security.php';
require_once LM_ROOT . '/includes/Database.php';
require_once LM_ROOT . '/includes/functions.php';

session_start();
requireAdmin();

$pageTitle = '分类管理';
$currentPage = 'categories';

$error = '';
$success = '';

// 处理删除
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $token = $_GET['token'] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $id = (int)$_GET['id'];
        try {
            // 检查该分类下是否有文章
            $articleCount = db()->fetchColumn("SELECT COUNT(*) FROM lm_article WHERE category_id = ?", [$id]);
            if ($articleCount > 0) {
                $error = '该分类下还有文章，无法删除。请先将文章移到其他分类。';
            } else {
                db()->delete('lm_category', 'id = ?', [$id]);
                $success = '分类已删除';
            }
        } catch (Exception $e) {
            $error = '删除失败: ' . $e->getMessage();
        }
    }
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_category') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!Security::validateToken($token)) {
        $error = 'CSRF验证失败';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        
        if (empty($name)) {
            $error = '请填写分类名称';
        } else {
            try {
                // 生成slug
                if (empty($slug)) {
                    $slug = generateSlug($name);
                }
                
                // 检查名称是否已存在
                $exists = db()->fetchColumn(
                    "SELECT COUNT(*) FROM lm_category WHERE name = ? AND id != ?",
                    [$name, $categoryId]
                );
                
                if ($exists) {
                    $error = '分类名称已存在';
                } else {
                    $data = [
                        'name' => Security::xssClean($name),
                        'slug' => Security::xssClean($slug),
                        'description' => Security::xssClean($description),
                        'sort_order' => $sortOrder
                    ];
                    
                    if ($categoryId > 0) {
                        db()->update('lm_category', $data, 'id = ?', [$categoryId]);
                        $success = '分类已更新';
                    } else {
                        db()->insert('lm_category', $data);
                        $success = '分类已添加';
                    }
                }
            } catch (Exception $e) {
                $error = '保存失败: ' . $e->getMessage();
            }
        }
    }
}

// 获取分类列表（带文章数量统计）
try {
    $categories = db()->fetchAll(
        "SELECT c.*, COUNT(a.id) as article_count 
         FROM lm_category c 
         LEFT JOIN lm_article a ON c.id = a.category_id 
         GROUP BY c.id 
         ORDER BY c.sort_order DESC, c.id ASC"
    );
} catch (Exception $e) {
    $categories = [];
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
        <div class="card-title"><?php echo isset($_GET['edit']) ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>编辑分类' : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M5 12h14"/><path d="M12 5v14"/></svg>添加分类'; ?></div>
    </div>
    <div class="card-body">
        <?php
        $editCategory = null;
        if (isset($_GET['edit'])) {
            $editId = (int)$_GET['edit'];
            foreach ($categories as $cat) {
                if ($cat['id'] == $editId) {
                    $editCategory = $cat;
                    break;
                }
            }
        }
        ?>
        <form method="POST" action="" data-validate>
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="category_id" id="category_id" value="<?php echo $editCategory ? $editCategory['id'] : 0; ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">分类名称 *</label>
                    <input type="text" name="name" class="form-input" placeholder="分类名称" required 
                           value="<?php echo $editCategory ? e($editCategory['name']) : ''; ?>" id="cat_name">
                </div>
                
                <div class="form-group">
                    <label class="form-label">URL别名</label>
                    <input type="text" name="slug" class="form-input" placeholder="留空自动生成" 
                           value="<?php echo $editCategory ? e($editCategory['slug']) : ''; ?>" id="cat_slug">
                    <div class="form-hint">用于URL显示</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">描述</label>
                    <input type="text" name="description" class="form-input" placeholder="分类描述" 
                           value="<?php echo $editCategory ? e($editCategory['description'] ?? '') : ''; ?>" id="cat_desc">
                </div>
                
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" placeholder="数字越大越靠前" 
                           value="<?php echo $editCategory ? $editCategory['sort_order'] : 0; ?>" id="cat_sort">
                </div>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" id="cat_submit_btn"><?php echo $editCategory ? '保存修改' : '添加分类'; ?></button>
                <?php if ($editCategory): ?>
                <a href="categories.php" class="btn btn-secondary">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 6px;"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>分类列表</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>别名</th>
                    <th>描述</th>
                    <th>文章数</th>
                    <th>排序</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?php echo $category['id']; ?></td>
                    <td><?php echo e($category['name']); ?></td>
                    <td><?php echo e($category['slug'] ?: '-'); ?></td>
                    <td><?php echo e(truncate($category['description'] ?: '-', 30)); ?></td>
                    <td>
                        <span class="badge <?php echo $category['article_count'] > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                            <?php echo $category['article_count']; ?> 篇
                        </span>
                    </td>
                    <td><?php echo $category['sort_order']; ?></td>
                    <td>
                        <div style="display: flex; gap: 4px;">
                            <a href="?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                            <a href="?action=delete&id=<?php echo $category['id']; ?>&token=<?php echo Security::generateToken(); ?>"
                               class="btn btn-sm btn-danger"
                               data-confirm="确定要删除该分类吗？">删除</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-light); padding: 40px;">暂无分类</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once LM_ROOT . '/admin/template/footer.php'; ?>
