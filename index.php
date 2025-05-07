<?php
// survey/index.php
if (!defined('IN_SYSTEM')) {
    die('Access Denied');
}

// 引入資料庫類別
require_once dirname(dirname(dirname(__FILE__))) . '/includes/database.php';

// 取得 URL 參數
$search_title = $_GET['title'] ?? '';
$search_status = $_GET['status'] ?? '';
$search_date = $_GET['date'] ?? '';
$currentPage = isset($_GET['current_page']) ? (int) $_GET['current_page'] : 1; // 分頁用
$page = $_GET['page'] ?? 'survey';  // 路由用
$limit = 10; // 每頁顯示數量
$offset = ($currentPage - 1) * $limit;

// 建立資料庫連線
$database = new Database();
$db = $database->getConnection();

// API 請求函數
function callAPI($url, $params = [], $method = 'GET')
{
    // 動態構建完整的 URL
    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
    $fullUrl = $baseUrl . $url;

    if (!empty($params) && $method === 'GET') {
        $fullUrl .= '?' . http_build_query($params);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("CURL Error: " . $error);
    }

    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: " . $httpCode);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Error: " . json_last_error_msg());
    }

    return $data;
}

// 嘗試獲取問卷資料
try {
    // 構建參數
    $params = [
        'page' => $currentPage,
        'limit' => $limit
    ];

    if (!empty($search_title))
        $params['title'] = $search_title;
    if (!empty($search_status))
        $params['status'] = $search_status;
    if (!empty($search_date))
        $params['date'] = $search_date;

    // 從 API 獲取問卷列表資料
    $result = callAPI('survey/api/get_surveys.php', $params);
    
    $surveys = $result['data']['surveys'] ?? [];
    $pagination = $result['data']['pagination'] ?? [
        'total' => 0,
        'page' => 1,
        'per_page' => $limit,
        'total_pages' => 1
    ];

} catch (Exception $e) {
    // 如果 API 調用失敗，則直接從資料庫取得數據作為備用方案
    
    // 組合SQL查詢條件
    $where_conditions = [];
    $params = [];

    if (!empty($search_title)) {
        $where_conditions[] = "title LIKE ?";
        $params[] = "%{$search_title}%";
    }

    if (!empty($search_status)) {
        $where_conditions[] = "status = ?";
        $params[] = $search_status;
    }

    if (!empty($search_date)) {
        $where_conditions[] = "DATE(created_at) = ?";
        $params[] = $search_date;
    }

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // 取得問卷總數
    $count_sql = "SELECT COUNT(*) FROM surveys {$where_clause}";
    $stmt = $db->prepare($count_sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $total_surveys = $stmt->fetchColumn();
    $total_pages = ceil($total_surveys / $limit);

    // 取得問卷列表
    $sql = "SELECT s.*, 
                u.full_name as creator_name,
                (SELECT COUNT(*) FROM surveys_responses WHERE survey_id = s.survey_id) as response_count
            FROM surveys s
            LEFT JOIN users u ON s.created_by = u.id
            {$where_clause}
            ORDER BY s.created_at DESC
            LIMIT {$offset}, {$limit}";

    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pagination = [
        'total' => $total_surveys,
        'page' => $currentPage,
        'per_page' => $limit,
        'total_pages' => $total_pages
    ];
}

// 取得問卷狀態對應的中文名稱和樣式
$status_mapping = [
    'draft' => ['text' => '草稿', 'class' => 'text-secondary'],
    'published' => ['text' => '已發布', 'class' => 'text-success'],
    'closed' => ['text' => '已關閉', 'class' => 'text-danger']
];
?>

<div class="layoutBox">
    <!-- 標題和操作區 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>問卷總覽</h3>
        <div>
            <a href="?page=survey/create" class="mainbtn bg_blue">
                <i class="fa fa-plus"></i> 建立新問卷
            </a>
        </div>
    </div>

    <!-- 搜尋/篩選區 -->
    <form method="GET" class="search-form">
        <!-- 確保 page 參數永遠存在且值正確 -->
        <input type="hidden" name="page" value="survey">

        <div class="row g-3">
            <!-- 問卷標題搜尋 -->
            <div class="col-md-4">
                <div class="form-group">
                    <input type="text" class="form-control" name="title" value="<?= htmlspecialchars($search_title) ?>" placeholder="搜尋問卷標題...">
                </div>
            </div>

            <!-- 狀態篩選 -->
            <div class="col-md-4">
                <div class="form-group">
                    <select class="form-control" name="status">
                        <option value="">全部狀態</option>
                        <option value="draft" <?= $search_status === 'draft' ? 'selected' : '' ?>>草稿</option>
                        <option value="published" <?= $search_status === 'published' ? 'selected' : '' ?>>已發布</option>
                        <option value="closed" <?= $search_status === 'closed' ? 'selected' : '' ?>>已關閉</option>
                    </select>
                </div>
            </div>

            <!-- 建立日期搜尋 -->
            <div class="col-md-4">
                <div class="form-group">
                    <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($search_date) ?>" placeholder="建立日期">
                </div>
            </div>
        </div>

        <!-- 按鈕區 -->
        <div class="d-flex justify-content-center gap-2 mt-4">
            <button type="submit" class="mainbtn bg_blue px-4">
                <i class="fa fa-search me-1"></i> 搜尋
            </button>
            <button type="reset" class="mainbtn bg_gray px-4" onclick="resetForm(this.form)">
                <i class="fa fa-refresh me-1"></i> 重置
            </button>
            <button type="button" class="mainbtn bg_green" onclick="window.location.href='?page=survey/create'">
                <i class="fa fa-plus me-1"></i> 新增問卷
            </button>
        </div>
    </form>
</div>

    <!-- 問卷列表 -->
    <div class="layoutBox">
        <table class="main_Table">
            <thead>
                <tr>
                    <th>問卷標題</th>
                    <th>狀態</th>
                    <th>建立者</th>
                    <th>建立時間</th>
                    <th>回應數</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($surveys)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4">尚無問卷資料</td>
                </tr>
                <?php else: ?>
                <?php foreach ($surveys as $survey): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($survey['title']) ?></div>
                        <?php if (!empty($survey['description'])): ?>
                        <div class="text-muted small mt-1"><?= htmlspecialchars(mb_substr($survey['description'], 0, 50)) ?><?= mb_strlen($survey['description']) > 50 ? '...' : '' ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $survey['status'] === 'draft' ? 'bg-warning' : ($survey['status'] === 'published' ? 'bg-success' : 'bg-danger') ?>">
                            <?= $status_mapping[$survey['status']]['text'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($survey['creator_name'] ?? '未知') ?></td>
                    <td><?= date('Y/m/d H:i', strtotime($survey['created_at'])) ?></td>
                    <td>
                        <span class="badge bg-info"><?= $survey['response_count'] ?></span>
                    </td>
                    <td>
                        <button class="mainbtn bg_gray" onclick="editSurvey('<?= $survey['survey_id'] ?>')">
                            編輯
                        </button>
                        <?php if ($survey['status'] === 'draft'): ?>
                        <button class="mainbtn bg_blue" onclick="publishSurvey(<?= $survey['survey_id'] ?>)">
                            發布
                        </button>
                        <?php elseif ($survey['status'] === 'published'): ?>
                        <button class="mainbtn bg_green" onclick="window.open('/EIP/Meeting/Webpage/survey/view.php?id=<?= $survey['survey_id'] ?>', '_blank')">
                            填報
                        </button>
                        <button class="mainbtn bg_orange" onclick="closeSurvey(<?= $survey['survey_id'] ?>)">
                            關閉
                        </button>
                        <?php endif; ?>
                        <button class="mainbtn bg_red" onclick="deleteSurvey(<?= $survey['survey_id'] ?>, '<?= htmlspecialchars(addslashes($survey['title'])) ?>')">
                            刪除
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<!-- 分頁 -->
<?php if ($pagination['total_pages'] > 1): ?>
    <div class="d-flex justify-content-center mt-3">
        <?php if ($pagination['page'] > 1): ?>
            <button class="mainbtn bg_gray" onclick="changePage(<?= $pagination['page'] - 1 ?>)">上一頁</button>
        <?php endif; ?>

        <?php 
        $start_page = max(1, $pagination['page'] - 2);
        $end_page = min($pagination['total_pages'], $pagination['page'] + 2);

        for ($i = $start_page; $i <= $end_page; $i++): 
        ?>
            <button class="mainbtn <?= $i == $pagination['page'] ? 'bg_blue' : 'bg_gray' ?> mx-1" onclick="changePage(<?= $i ?>)">
                <?= $i ?>
            </button>
        <?php endfor; ?>

        <?php if ($pagination['page'] < $pagination['total_pages']): ?>
            <button class="mainbtn bg_gray" onclick="changePage(<?= $pagination['page'] + 1 ?>)">下一頁</button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// 分頁函數
function changePage(pageNum) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', 'survey');  // 保持路由參數
    urlParams.set('current_page', pageNum);  // 設定分頁參數
    
    // 保持其他搜尋參數
    const title = document.querySelector('input[name="title"]')?.value;
    if (title) urlParams.set('title', title);
    
    const status = document.querySelector('select[name="status"]')?.value;
    if (status) urlParams.set('status', status);
    
    const date = document.querySelector('input[name="date"]')?.value;
    if (date) urlParams.set('date', date);
    
    window.location.search = urlParams.toString();
}

// 編輯問卷
function editSurvey(id) {
    window.location.href = `?page=survey/create&id=${id}`;
}

// 發布問卷
function publishSurvey(id) {
    if (confirm('確定要發布此問卷嗎？')) {
        // 使用 AJAX 更新問卷狀態
        updateSurveyStatus(id, 'published');
    }
}

// 關閉問卷
function closeSurvey(id) {
    if (confirm('確定要關閉此問卷嗎？')) {
        // 使用 AJAX 更新問卷狀態
        updateSurveyStatus(id, 'closed');
    }
}

// 刪除問卷
function deleteSurvey(id, title) {
    if (confirm(`確定要刪除問卷 "${title}" 嗎？此操作無法恢復！`)) {
        // 使用 AJAX 刪除問卷
        fetch(`survey/api/delete_survey.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('問卷已刪除');
                window.location.reload();
            } else {
                alert('刪除失敗: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Delete Error:', error);
            alert('刪除失敗: ' + error.message);
        });
    }
}

// 更新問卷狀態
function updateSurveyStatus(id, status) {
    fetch('survey/api/update_survey_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            survey_id: id,
            status: status
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('問卷狀態已更新');
            window.location.reload();
        } else {
            alert('更新失敗: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Update Error:', error);
        alert('更新失敗: ' + error.message);
    });
}

// 重置表單
function resetForm(form) {
    // 重置所有輸入欄位
    form.querySelectorAll('input:not([type=hidden]), select').forEach(input => {
        input.value = '';
    });
    // 提交表單
    form.submit();
}
</script>
