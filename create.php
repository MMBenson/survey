<!-- 在 <head> 或頁尾引入 TinyMCE -->
<script src="https://cdn.tiny.cloud/1/您的API金鑰/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<?php
// survey/create.php
if (!defined('IN_SYSTEM')) {
    die('Access Denied');
}

// 引入資料庫類別
require_once dirname(dirname(dirname(__FILE__))) . '/includes/database.php';

// 取得 URL 參數
$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 建立資料庫連線
$database = new Database();
$db = $database->getConnection();

// 初始化變數
$survey = [
    'title' => '',
    'description' => '',
    'welcome_text' => '',
    'end_text' => '',
    'status' => 'draft',
    'is_anonymous' => 1,
    'start_date' => '',
    'end_date' => ''
];

$questions = [];
$page_title = '建立新問卷';
$is_edit_mode = false;

// 如果是編輯模式，載入問卷資料
if ($survey_id > 0) {
    try {
        // 取得問卷資訊
        $sql = "SELECT * FROM surveys WHERE survey_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$survey_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $survey = $result;
            $page_title = '編輯問卷';
            $is_edit_mode = true;

            // 取得問卷問題
            $sql = "SELECT * FROM surveys_questions WHERE survey_id = ? ORDER BY order_num";
            $stmt = $db->prepare($sql);
            $stmt->execute([$survey_id]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 取得問題選項和子問題
            if (!empty($questions)) {
                foreach ($questions as &$question) {
                    $q_id = $question['question_id'];

                    // 取得問題選項
                    $sql = "SELECT * FROM surveys_options WHERE question_id = ? ORDER BY order_num";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$q_id]);
                    $question['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // 在這裡插入取得矩陣項目的代碼
                    if ($question['question_type'] === 'matrix_single') {
                        $sql = "SELECT * FROM surveys_matrix_items WHERE question_id = ? ORDER BY order_num";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$q_id]);
                        $question['matrix_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }

                    // 取得子問題
                    $sql = "SELECT * FROM surveys_subquestions WHERE question_id = ? ORDER BY order_num";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$q_id]);
                    $question['subquestions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                unset($question); // 避免最後一個引用持續存在
            }
        } else {
            echo '<div class="alert alert-danger">找不到此問卷</div>';
            exit;
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">資料庫錯誤：' . $e->getMessage() . '</div>';
        exit;
    }
}

// 取得可用的問卷範本列表
try {
    $sql = "SELECT template_id, template_name, category FROM surveys_templates WHERE is_public = 1 ORDER BY template_name";
    $stmt = $db->query($sql);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}

// 處理問卷存檔或發布
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_survey'])) {
    try {
        $db->beginTransaction();

        // 取得問卷基本資料
        $survey_data = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'welcome_text' => $_POST['welcome_text'] ?? '',
            'end_text' => $_POST['end_text'] ?? '',
            'is_anonymous' => isset($_POST['is_anonymous']) ? 1 : 0,
            'status' => isset($_POST['publish']) ? 'published' : 'draft',
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] . ' 00:00:00' : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] . ' 23:59:59' : null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // 驗證標題
        if (empty($survey_data['title'])) {
            throw new Exception('問卷標題不能為空');
        }

        // 新增或更新問卷
        if ($is_edit_mode) {
            $sql = "UPDATE surveys SET 
                   title = :title, 
                   description = :description, 
                   welcome_text = :welcome_text, 
                   end_text = :end_text, 
                   is_anonymous = :is_anonymous, 
                   status = :status, 
                   start_date = :start_date, 
                   end_date = :end_date, 
                   updated_at = :updated_at 
                   WHERE survey_id = :survey_id";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':survey_id', $survey_id);
        } else {
            $sql = "INSERT INTO surveys 
                   (title, description, welcome_text, end_text, is_anonymous, status, 
                    start_date, end_date, created_by, created_at, updated_at) 
                   VALUES 
                   (:title, :description, :welcome_text, :end_text, :is_anonymous, :status, 
                    :start_date, :end_date, :created_by, :updated_at, :updated_at)";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':created_by', $_SESSION['user_id'] ?? null);
        }

        $stmt->bindValue(':title', $survey_data['title']);
        $stmt->bindValue(':description', $survey_data['description']);
        $stmt->bindValue(':welcome_text', $survey_data['welcome_text']);
        $stmt->bindValue(':end_text', $survey_data['end_text']);
        $stmt->bindValue(':is_anonymous', $survey_data['is_anonymous']);
        $stmt->bindValue(':status', $survey_data['status']);
        $stmt->bindValue(':start_date', $survey_data['start_date']);
        $stmt->bindValue(':end_date', $survey_data['end_date']);
        $stmt->bindValue(':updated_at', $survey_data['updated_at']);

        $stmt->execute();

        if (!$is_edit_mode) {
            $survey_id = $db->lastInsertId();
        }

        // 處理問題資料
        if ($is_edit_mode) {
            // 刪除所有現有問題和選項
            $sql = "DELETE FROM surveys_questions WHERE survey_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$survey_id]);
        }

        // 解析並儲存問題資料
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            $order_num = 1;

            foreach ($_POST['questions'] as $question_data) {
                $question_text = $question_data['text'] ?? '';
                $question_type = $question_data['type'] ?? '';
                $description = $question_data['description'] ?? '';
                $is_required = isset($question_data['required']) ? 1 : 0;
                $has_subquestions = isset($question_data['has_subquestions']) ? 1 : 0;

                if (empty($question_text) || empty($question_type)) {
                    continue; // 跳過空問題
                }

                // 插入問題
                $sql = "INSERT INTO surveys_questions 
                       (survey_id, question_text, question_type, description, is_required, has_subquestions, order_num, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $survey_id,
                    $question_text,
                    $question_type,
                    $description,
                    $is_required,
                    $has_subquestions,
                    $order_num
                ]);

                $question_id = $db->lastInsertId();
                $order_num++;

                // 處理選項（對於需要選項的問題類型）
                if (in_array($question_type, ['single_choice', 'multiple_choice', 'dropdown', 'rating', 'matrix_single'])) {
                    if (isset($question_data['options']) && is_array($question_data['options'])) {
                        $option_order = 1;

                        foreach ($question_data['options'] as $option_data) {
                            $option_text = $option_data['text'] ?? '';
                            $option_value = $option_data['value'] ?? '';
                            $is_other = isset($option_data['is_other']) ? 1 : 0;

                            if (empty($option_text) && !$is_other) {
                                continue; // 跳過空選項
                            }

                            $sql = "INSERT INTO surveys_options 
                           (question_id, option_text, option_value, order_num, is_other, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([
                                $question_id,
                                $option_text,
                                $option_value,
                                $option_order,
                                $is_other
                            ]);

                            $option_order++;
                        }
                    }
                }

                // 在這裡插入處理矩陣項目的代碼
                if ($question_type === 'matrix_single' && isset($question_data['matrix_items']) && is_array($question_data['matrix_items'])) {
                    $item_order = 1;

                    foreach ($question_data['matrix_items'] as $item_data) {
                        $item_text = $item_data['text'] ?? '';

                        if (empty($item_text)) {
                            continue; // 跳過空項目
                        }

                        $sql = "INSERT INTO surveys_matrix_items 
                                               (question_id, item_text, order_num, created_at) 
                                               VALUES (?, ?, ?, NOW())";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            $question_id,
                            $item_text,
                            $item_order
                        ]);

                        $item_order++;
                    }
                }

                // 處理子問題
                if ($has_subquestions && isset($question_data['subquestions']) && is_array($question_data['subquestions'])) {
                    $subquestion_order = 1;

                    foreach ($question_data['subquestions'] as $subquestion_data) {
                        $subquestion_text = $subquestion_data['text'] ?? '';

                        if (empty($subquestion_text)) {
                            continue; // 跳過空子問題
                        }

                        $sql = "INSERT INTO surveys_subquestions (question_id, subquestion_text, order_num, created_at) 
                        VALUES (?, ?, ?, NOW())";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            $question_id,
                            $subquestion_text,
                            $subquestion_order
                        ]);

                        $subquestion_order++;
                    }
                }
            }
        }

        $db->commit();

        // 重定向到問卷列表頁面或編輯頁面
        if (isset($_POST['save_and_continue'])) {
            header("Location: ?page=survey/create&id=$survey_id&saved=1");
        } else {
            header("Location: ?page=survey&saved=1");
        }
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = $e->getMessage();
    }
}

// 問題類型定義（擴充為八種題型）
$question_types = [
    'single_choice' => '單選題',
    'multiple_choice' => '多選題',
    'text' => '文字題',
    'number' => '數字題',
    'date' => '日期題',
    'upload' => '檔案上傳',
    'rating' => '評分題',
    'dropdown' => '下拉選單',
    'matrix_single' => '矩陣單選題'
];
// 獲取通知訊息
$saved_message = '';
if (isset($_GET['saved']) && $_GET['saved'] == 1) {
    $saved_message = '問卷已成功儲存';
}

// 引入問題範本
require_once 'question_templates.php';
?>

<div class="layoutBox">
    <!-- 標題和操作區 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?= htmlspecialchars($page_title) ?></h3>
        <div>
            <a href="?page=survey" class="mainbtn bg_gray">
                <i class="fa fa-arrow-left"></i> 返回列表
            </a>
            <?php if ($is_edit_mode && !empty($questions)): ?>
                <a href="?page=survey/view&id=<?= $survey_id ?>" class="mainbtn bg_green" target="_blank">
                    <i class="fa fa-eye"></i> 預覽問卷
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($saved_message)): ?>
        <div class="alert alert-success">
            <i class="fa fa-check-circle"></i> <?= htmlspecialchars($saved_message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- 問卷表單 -->
    <form id="survey-form" method="POST">
        <div class="row">
            <div class="col-md-9">
                <!-- 問卷基本資訊 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>問卷基本資訊</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">問卷標題 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required
                                value="<?= htmlspecialchars($survey['title']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">問卷描述</label>
                            <textarea class="form-control" id="survey-description" name="description"
                                rows="5"><?= htmlspecialchars($survey['description']) ?></textarea>
                            <small class="text-muted">您可以使用豐富的格式和圖片來描述您的問卷</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">歡迎文字</label>
                            <textarea class="form-control" name="welcome_text"
                                rows="3"><?= htmlspecialchars($survey['welcome_text']) ?></textarea>
                            <small class="text-muted">顯示在問卷開始處的文字</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">結束文字</label>
                            <textarea class="form-control" name="end_text"
                                rows="3"><?= htmlspecialchars($survey['end_text']) ?></textarea>
                            <small class="text-muted">顯示在問卷結束處的文字，例如感謝語</small>
                        </div>
                    </div>
                </div>

                <!-- 問題列表區 -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>問題設計</h5>
                        <div class="dropdown">
                            <button class="mainbtn bg_blue dropdown-toggle" type="button" id="addQuestionDropdown"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-plus"></i> 新增問題
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="addQuestionDropdown">
                                <?php foreach ($question_types as $type => $label): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" data-type="<?= $type ?>">
                                            <?= htmlspecialchars($label) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- 排序提示 -->
                        <div class="bg-light p-3 border-bottom">
                            <i class="fa fa-info-circle text-primary"></i>
                            拖曳問題可調整順序。點擊問題可展開編輯選項。
                        </div>

                        <!-- 問題容器 -->
                        <div id="questions-container" class="questions-list">
                            <!-- 由JavaScript動態生成問題卡片 -->
                            <div class="no-questions text-center py-5 text-muted"
                                style="<?= !empty($questions) ? 'display:none' : '' ?>">
                                <i class="fa fa-clipboard fa-3x mb-3"></i>
                                <p>尚未新增問題。請點擊「新增問題」按鈕開始設計問卷。</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <!-- 問卷設定 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>問卷設定</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_anonymous" name="is_anonymous"
                                <?= $survey['is_anonymous'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_anonymous">匿名填寫</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">開始日期</label>
                            <input type="date" class="form-control" name="start_date"
                                value="<?= $survey['start_date'] ? date('Y-m-d', strtotime($survey['start_date'])) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">結束日期</label>
                            <input type="date" class="form-control" name="end_date"
                                value="<?= $survey['end_date'] ? date('Y-m-d', strtotime($survey['end_date'])) : '' ?>">
                        </div>
                    </div>
                </div>

                <!-- 問卷範本 -->
                <?php if (!$is_edit_mode && !empty($templates)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>使用範本</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">選擇問卷範本</label>
                                <select class="form-select" id="template-select">
                                    <option value="">-- 選擇範本 --</option>
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?= $template['template_id'] ?>">
                                            <?= htmlspecialchars($template['template_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="mainbtn bg_gray w-100" id="load-template-btn">
                                <i class="fa fa-file-text-o"></i> 載入範本
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 儲存按鈕 -->
                <div class="card">
                    <div class="card-header">
                        <h5>儲存問卷</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="mainbtn bg_blue" name="save_survey">
                                <i class="fa fa-save"></i> 儲存問卷
                            </button>
                            <button type="submit" class="mainbtn bg_green" name="save_and_continue">
                                <i class="fa fa-save"></i> 儲存並繼續編輯
                            </button>
                            <button type="submit" class="mainbtn bg_red" name="publish">
                                <i class="fa fa-paper-plane"></i> 儲存並發布
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- 引入 Sortable.js 用於拖曳排序 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>

<!-- 將現有問題數據傳遞給 JavaScript -->
<script>
    const existingQuestions = <?= json_encode($questions) ?>;
</script>

<!-- 引入問卷創建的JavaScript和CSS -->
<link rel="stylesheet" href="survey/create.css">
<script src="survey/create.js"></script>