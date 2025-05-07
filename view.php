<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="view.css">
<?php
// survey/view.php
// if (!defined('IN_SYSTEM')) {
//     die('Access Denied');
// }

// 引入資料庫類別
require_once dirname(dirname(dirname(__FILE__))) . '/includes/database.php';

// 取得問卷ID
$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 檢查問卷ID是否有效
if ($survey_id <= 0) {
    echo '<div class="alert alert-danger">無效的問卷ID</div>';
    exit;
}

// 建立資料庫連線
$database = new Database();
$db = $database->getConnection();

// 取得問卷資訊
try {
    $sql = "SELECT * FROM surveys WHERE survey_id = ? AND status = 'published'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$survey_id]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$survey) {
        echo '<div class="alert alert-danger">找不到此問卷或問卷尚未發布</div>';
        exit;
    }

    // 檢查問卷是否在有效期間內
    $now = date('Y-m-d H:i:s');
    if ($survey['start_date'] && $survey['start_date'] > $now) {
        echo '<div class="alert alert-warning">此問卷尚未開始</div>';
        exit;
    }
    if ($survey['end_date'] && $survey['end_date'] < $now) {
        echo '<div class="alert alert-warning">此問卷已經結束</div>';
        exit;
    }

    // 取得問卷問題
    $sql = "SELECT * FROM surveys_questions WHERE survey_id = ? ORDER BY order_num";
    $stmt = $db->prepare($sql);
    $stmt->execute([$survey_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得問題選項
    $options = [];
    if (!empty($questions)) {
        $question_ids = array_column($questions, 'question_id');
        $question_ids_str = implode(',', $question_ids);
        
        if (!empty($question_ids_str)) {
            $sql = "SELECT * FROM surveys_options WHERE question_id IN ($question_ids_str) ORDER BY question_id, order_num";
            $stmt = $db->query($sql);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $options[$row['question_id']][] = $row;
            }
        }
    }
    
    // 取得矩陣項目 (新增部分)
    $matrix_items = [];
    if (!empty($questions)) {
        $question_ids = array_column($questions, 'question_id');
        $question_ids_str = implode(',', $question_ids);
        
        if (!empty($question_ids_str)) {
            $sql = "SELECT * FROM surveys_matrix_items WHERE question_id IN ($question_ids_str) ORDER BY question_id, order_num";
            $stmt = $db->query($sql);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $matrix_items[$row['question_id']][] = $row;
            }
        }
    }
    
    // 取得子問題
    $subquestions = [];
    if (!empty($questions)) {
        $question_ids = array_column($questions, 'question_id');
        $question_ids_str = implode(',', $question_ids);
        
        if (!empty($question_ids_str)) {
            $sql = "SELECT * FROM surveys_subquestions WHERE question_id IN ($question_ids_str) ORDER BY question_id, order_num";
            $stmt = $db->query($sql);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $subquestions[$row['question_id']][] = $row;
            }
        }
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">資料庫錯誤：' . $e->getMessage() . '</div>';
    exit;
}

// 表單提交處理
$form_submitted = false;
$form_errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    $form_submitted = true;
    
    // 驗證必填問題
    foreach ($questions as $question) {
        if ($question['is_required']) {
            $q_id = $question['question_id'];
            $answer_key = 'question_' . $q_id;
            $question_type = $question['question_type'];
            
            // 矩陣單選題的必填驗證 (新增部分)
            if ($question_type === 'matrix_single') {
                $matrix_items_for_question = $matrix_items[$q_id] ?? [];
                $all_answered = true;
                
                foreach ($matrix_items_for_question as $item) {
                    $matrix_answer_key = 'question_' . $q_id . '_item_' . $item['item_id'];
                    if (!isset($_POST[$matrix_answer_key]) || trim($_POST[$matrix_answer_key]) === '') {
                        $all_answered = false;
                        break;
                    }
                }
                
                if (!$all_answered) {
                    $form_errors[$q_id] = '此問題中的所有項目必須回答';
                }
            }
            // 一般問題的必填驗證
            else if (
                !isset($_POST[$answer_key]) || 
                (is_array($_POST[$answer_key]) && empty($_POST[$answer_key])) ||
                (is_string($_POST[$answer_key]) && trim($_POST[$answer_key]) === '')
            ) {
                $form_errors[$q_id] = '此問題必須回答';
            }
        }
    }
    
    // 如果沒有錯誤，處理表單提交
    if (empty($form_errors)) {
        try {
            $db->beginTransaction();
            
            // 建立回應記錄
            $sql = "INSERT INTO surveys_responses 
                   (survey_id, user_id, respondent_name, respondent_email, respondent_phone, 
                    ip_address, user_agent, started_at, completed_at, is_completed, created_at) 
                   VALUES 
                   (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1, NOW())";
            
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $respondent_name = isset($_POST['respondent_name']) ? trim($_POST['respondent_name']) : null;
            $respondent_email = isset($_POST['respondent_email']) ? trim($_POST['respondent_email']) : null;
            $respondent_phone = isset($_POST['respondent_phone']) ? trim($_POST['respondent_phone']) : null;
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $survey_id, 
                $user_id, 
                $respondent_name, 
                $respondent_email, 
                $respondent_phone, 
                $ip_address, 
                $user_agent
            ]);
            
            $response_id = $db->lastInsertId();
            
            // 儲存答案
            foreach ($questions as $question) {
                $q_id = $question['question_id'];
                $answer_key = 'question_' . $q_id;
                $question_type = $question['question_type'];
                
                // 處理矩陣單選題的答案 (新增部分)
                if ($question_type === 'matrix_single') {
                    $matrix_items_for_question = $matrix_items[$q_id] ?? [];
                    
                    foreach ($matrix_items_for_question as $item) {
                        $matrix_answer_key = 'question_' . $q_id . '_item_' . $item['item_id'];
                        if (isset($_POST[$matrix_answer_key])) {
                            $option_id = intval($_POST[$matrix_answer_key]);
                            
                            $sql = "INSERT INTO surveys_answers 
                                   (response_id, question_id, option_id, matrix_item_id, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$response_id, $q_id, $option_id, $item['item_id']]);
                        }
                    }
                }
                // 一般問題的答案處理
                else if (isset($_POST[$answer_key])) {
                    $answer_value = $_POST[$answer_key];
                    
                    // 根據問題類型處理答案
                    switch ($question_type) {
                        case 'single_choice':
                            $option_id = intval($answer_value);
                            
                            $sql = "INSERT INTO surveys_answers 
                                   (response_id, question_id, option_id, created_at) 
                                   VALUES (?, ?, ?, NOW())";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$response_id, $q_id, $option_id]);
                            break;
                            
                        case 'multiple_choice':
                            if (is_array($answer_value)) {
                                foreach ($answer_value as $option_id) {
                                    $sql = "INSERT INTO surveys_answers 
                                           (response_id, question_id, option_id, created_at) 
                                           VALUES (?, ?, ?, NOW())";
                                    $stmt = $db->prepare($sql);
                                    $stmt->execute([$response_id, $q_id, $option_id]);
                                }
                            }
                            break;
                            
                        case 'text':
                            $sql = "INSERT INTO surveys_answers 
                                   (response_id, question_id, answer_text, created_at) 
                                   VALUES (?, ?, ?, NOW())";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$response_id, $q_id, $answer_value]);
                            break;
                            
                        case 'number':
                            $sql = "INSERT INTO surveys_answers 
                                   (response_id, question_id, answer_number, created_at) 
                                   VALUES (?, ?, ?, NOW())";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$response_id, $q_id, floatval($answer_value)]);
                            break;
                            
                        case 'date':
                            $sql = "INSERT INTO surveys_answers 
                                   (response_id, question_id, answer_date, created_at) 
                                   VALUES (?, ?, ?, NOW())";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$response_id, $q_id, $answer_value]);
                            break;
                            
                        case 'rating':
                            $sql = "INSERT INTO surveys_answers 
                                   (response_id, question_id, option_id, answer_number, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$response_id, $q_id, intval($answer_value), floatval($answer_value)]);
                            break;
                            
                        case 'dropdown':
                            $option_id = intval($answer_value);
                            
                            $sql = "INSERT INTO surveys_answers 
                                   (response_id, question_id, option_id, created_at) 
                                   VALUES (?, ?, ?, NOW())";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$response_id, $q_id, $option_id]);
                            break;
                    }
                }
            }
            
            // 儲存子問題答案
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'subquestion_') === 0 && !empty($value)) {
                    $subquestion_id = intval(str_replace('subquestion_', '', $key));
                    
                    // 找出子問題所屬的問題ID
                    $parent_question_id = null;
                    foreach ($questions as $question) {
                        if (isset($subquestions[$question['question_id']])) {
                            foreach ($subquestions[$question['question_id']] as $subquestion) {
                                if ($subquestion['subquestion_id'] == $subquestion_id) {
                                    $parent_question_id = $question['question_id'];
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    if ($parent_question_id) {
                        $sql = "INSERT INTO surveys_answers 
                               (response_id, question_id, subquestion_id, answer_text, created_at) 
                               VALUES (?, ?, ?, ?, NOW())";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$response_id, $parent_question_id, $subquestion_id, $value]);
                    }
                }
            }
            
            // 處理檔案上傳（如果有）
            if (isset($_FILES) && !empty($_FILES)) {
                foreach ($_FILES as $field_name => $file_info) {
                    if (strpos($field_name, 'question_') === 0 && $file_info['error'] == 0) {
                        $q_id = intval(str_replace('question_', '', $field_name));
                        
                        // 檢查問題是否為上傳類型
                        foreach ($questions as $question) {
                            if ($question['question_id'] == $q_id && $question['question_type'] == 'upload') {
                                // 處理檔案上傳
                                $file_name = $file_info['name'];
                                $file_type = $file_info['type'];
                                $file_size = $file_info['size'];
                                $file_tmp = $file_info['tmp_name'];
                                
                                // 建立上傳目錄
                                $upload_dir = dirname(dirname(dirname(__FILE__))) . '/uploads/survey_answers/';
                                if (!file_exists($upload_dir)) {
                                    mkdir($upload_dir, 0777, true);
                                }
                                
                                // 產生唯一檔案名稱
                                $file_path = 'uploads/survey_answers/' . $response_id . '_' . $q_id . '_' . time() . '_' . $file_name;
                                $full_path = dirname(dirname(dirname(__FILE__))) . '/' . $file_path;
                                
                                // 移動檔案
                                move_uploaded_file($file_tmp, $full_path);
                                
                                // 儲存檔案資訊到資料庫
                                $sql = "INSERT INTO surveys_answer_files 
                                       (answer_id, response_id, question_id, original_filename, 
                                        file_path, file_type, file_size, created_at) 
                                       VALUES 
                                       ((SELECT answer_id FROM surveys_answers WHERE response_id = ? AND question_id = ? LIMIT 1), 
                                        ?, ?, ?, ?, ?, ?, NOW())";
                                $stmt = $db->prepare($sql);
                                $stmt->execute([
                                    $response_id, 
                                    $q_id, 
                                    $response_id, 
                                    $q_id, 
                                    $file_name, 
                                    $file_path, 
                                    $file_type, 
                                    $file_size
                                ]);
                            }
                        }
                    }
                }
            }
            
            $db->commit();
            
            // 提交成功後重定向到結果頁面
            header("Location: ?page=survey/detail&survey_id=$survey_id&response_id=$response_id");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $success_message = '';
            $form_errors['general'] = '發生錯誤：' . $e->getMessage();
        }
    }
}
?>

<div class="layoutBox">
    <!-- 問卷標題和描述 -->
    <div class="text-center mb-4">
        <h2><?= htmlspecialchars($survey['title']) ?></h2>
        <?php if (!empty($survey['description'])): ?>
            <!-- 修改此處：移除 htmlspecialchars 和 nl2br，直接輸出富文本內容 -->
            <div class="survey-description">
                <?= $survey['description'] ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 歡迎文字也需要相同處理 -->
    <?php if (!empty($survey['welcome_text'])): ?>
        <div class="card mb-4">
            <div class="card-body">
                <?= $survey['welcome_text'] ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 表單錯誤訊息 -->
    <?php if (!empty($form_errors['general'])): ?>
        <div class="alert alert-danger">
            <?= $form_errors['general'] ?>
        </div>
    <?php endif; ?>

    <!-- 問卷表單 -->
    <form method="POST" id="survey-form" enctype="multipart/form-data">
        <!-- 個人資訊 (如果不是匿名問卷) -->
        <?php if (!$survey['is_anonymous']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5>個人資訊</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">姓名 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="respondent_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">電子郵件 <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="respondent_email" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">電話</label>
                            <input type="tel" class="form-control" name="respondent_phone">
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 問題列表 -->
        <?php foreach ($questions as $index => $question): ?>
            <?php 
            $q_id = $question['question_id'];
            $is_required = $question['is_required'];
            $question_type = $question['question_type'];
            $question_options = isset($options[$q_id]) ? $options[$q_id] : [];
            ?>
            <div class="card mb-4 question-card" id="question-<?= $q_id ?>">
                <div class="card-header">
                    <h5>
                        問題 <?= $index + 1 ?>： <?= htmlspecialchars($question['question_text']) ?>
                        <?php if ($is_required): ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($question['description'])): ?>
                        <div class="question-description mb-3">
                            <?= nl2br(htmlspecialchars($question['description'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($form_errors[$q_id])): ?>
                        <div class="alert alert-danger">
                            <?= $form_errors[$q_id] ?>
                        </div>
                    <?php endif; ?>

                    <?php switch ($question_type): 
                          case 'single_choice': ?>
                        <!-- 單選題 -->
                        <div class="question-options">
                            <?php foreach ($question_options as $option): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" 
                                           name="question_<?= $q_id ?>" 
                                           id="option_<?= $option['option_id'] ?>" 
                                           value="<?= $option['option_id'] ?>"
                                           <?= $is_required ? 'required' : '' ?>>
                                    <label class="form-check-label" for="option_<?= $option['option_id'] ?>">
                                        <?= htmlspecialchars($option['option_text']) ?>
                                    </label>
                                </div>
                                <?php if ($option['is_other']): ?>
                                    <div class="ms-4 mb-2 other-option-input" style="display: none;">
                                        <input type="text" class="form-control" 
                                               name="other_option_<?= $q_id ?>" 
                                               placeholder="請說明其他選項">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php break; ?>
                    
                    <?php case 'multiple_choice': ?>
                        <!-- 多選題 -->
                        <div class="question-options">
                            <?php foreach ($question_options as $option): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" 
                                           name="question_<?= $q_id ?>[]" 
                                           id="option_<?= $option['option_id'] ?>" 
                                           value="<?= $option['option_id'] ?>">
                                    <label class="form-check-label" for="option_<?= $option['option_id'] ?>">
                                        <?= htmlspecialchars($option['option_text']) ?>
                                    </label>
                                </div>
                                <?php if ($option['is_other']): ?>
                                    <div class="ms-4 mb-2 other-option-input" style="display: none;">
                                        <input type="text" class="form-control" 
                                               name="other_option_<?= $q_id ?>" 
                                               placeholder="請說明其他選項">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php break; ?>
                    
                    <?php case 'text': ?>
                        <!-- 文字題 -->
                        <div class="question-input">
                            <textarea class="form-control" 
                                      name="question_<?= $q_id ?>" 
                                      rows="3" 
                                      <?= $is_required ? 'required' : '' ?>></textarea>
                        </div>
                    <?php break; ?>
                    
                    <?php case 'number': ?>
                        <!-- 數字題 -->
                        <div class="question-input">
                            <input type="number" class="form-control" 
                                   name="question_<?= $q_id ?>" 
                                   step="any" 
                                   <?= $is_required ? 'required' : '' ?>>
                        </div>
                    <?php break; ?>
                    
                    <?php case 'date': ?>
                        <!-- 日期題 -->
                        <div class="question-input">
                            <input type="date" class="form-control" 
                                   name="question_<?= $q_id ?>" 
                                   <?= $is_required ? 'required' : '' ?>>
                        </div>
                    <?php break; ?>
                    
                    <?php case 'upload': ?>
                        <!-- 上傳題 -->
                        <div class="question-input">
                            <input type="file" class="form-control" 
                                   name="question_<?= $q_id ?>" 
                                   <?= $is_required ? 'required' : '' ?>>
                        </div>
                    <?php break; ?>
                    
                    <?php case 'rating': ?>
                        <!-- 評分題 -->
                        <div class="question-rating">
                            <div class="rating-container d-flex justify-content-between">
                                <?php 
                                // 假設評分範圍是1-5
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <div class="rating-item text-center">
                                        <input type="radio" 
                                               name="question_<?= $q_id ?>" 
                                               id="rating_<?= $q_id ?>_<?= $i ?>" 
                                               value="<?= $i ?>" 
                                               <?= $is_required ? 'required' : '' ?>>
                                        <label for="rating_<?= $q_id ?>_<?= $i ?>" class="d-block">
                                            <?= $i ?>
                                        </label>
                                        <?php
                                        // 尋找對應的評分選項文字
                                        foreach ($question_options as $option) {
                                            if (intval($option['option_value']) === $i) {
                                                echo '<div class="rating-label small">' . htmlspecialchars($option['option_text']) . '</div>';
                                                break;
                                            }
                                        }
                                        ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php break; ?>
                    
                    <?php case 'dropdown': ?>
                        <!-- 下拉選單 -->
                        <div class="question-input">
                            <select class="form-select" 
                                    name="question_<?= $q_id ?>" 
                                    <?= $is_required ? 'required' : '' ?>>
                                <option value="">請選擇</option>
                                <?php foreach ($question_options as $option): ?>
                                    <option value="<?= $option['option_id'] ?>">
                                        <?= htmlspecialchars($option['option_text']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php break; ?>
                    
                    <?php case 'matrix_single': ?>
    <!-- 矩陣單選題 -->
    <div class="question-matrix">
        <?php
        // 獲取矩陣項目
        $question_matrix_items = isset($matrix_items[$q_id]) ? $matrix_items[$q_id] : [];
        
        // 獲取是否需要隨機排序
        $random_items = isset($question['random_items']) && $question['random_items'] == 1;
        if ($random_items) {
            // 複製一份以避免修改原始數據
            $shuffled_items = $question_matrix_items;
            shuffle($shuffled_items);
            $question_matrix_items = $shuffled_items;
        }
        ?>
        
        <div class="table-responsive">
            <table class="table table-bordered matrix-table">
                <thead>
                    <tr>
                        <th class="matrix-header-empty"></th>
                        <?php foreach ($question_options as $option): ?>
                            <th class="text-center matrix-header-option"><?= htmlspecialchars($option['option_text']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($question_matrix_items as $item): ?>
                        <tr class="<?= $item === reset($question_matrix_items) ? '' : 'matrix-alternating-row' ?>">
                            <td class="matrix-item-text"><?= htmlspecialchars($item['item_text']) ?></td>
                            <?php foreach ($question_options as $option): ?>
                                <td class="text-center matrix-radio-cell">
                                    <label class="matrix-radio-label">
                                        <input type="radio" 
                                               class="matrix-radio-input"
                                               name="question_<?= $q_id ?>_item_<?= $item['item_id'] ?>" 
                                               value="<?= $option['option_id'] ?>"
                                               <?= $is_required ? 'required' : '' ?>>
                                        <span class="matrix-radio-custom"></span>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php break; ?>
                    
                    <?php endswitch; ?>
                    
                    <!-- 子問題部分 -->
                    <?php if ($question['has_subquestions'] == 1 && isset($subquestions[$q_id]) && !empty($subquestions[$q_id])): ?>
                        <div class="subquestions-container mt-4">
                            <h6 class="mb-3">子問題</h6>
                            
                            <?php foreach ($subquestions[$q_id] as $index => $subquestion): ?>
                                <div class="subquestion-item card mb-3">
                                    <div class="card-body">
                                        <p class="mb-2">
                                            <?= htmlspecialchars($subquestion['subquestion_text']) ?>
                                            <?php if ($subquestion['is_required']): ?>
                                                <span class="text-danger">*</span>
                                                <?php endif; ?>
                                        </p>
                    
                                        <?php if (!empty($subquestion['description'])): ?>
                                            <div class="subquestion-description mb-3">
                                                <?= nl2br(htmlspecialchars($subquestion['description'])) ?>
                                            </div>
                                        <?php endif; ?>
                    
                                        <!-- 子問題的答案輸入框 -->
                                        <div class="subquestion-input">
                                            <textarea class="form-control" 
                                                      name="subquestion_<?= $subquestion['subquestion_id'] ?>" 
                                                      rows="2" 
                                                      <?= $subquestion['is_required'] ? 'required' : '' ?>></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- 送出按鈕 -->
        <div class="d-flex justify-content-center gap-2 my-4">
            <button type="submit" name="submit_survey" class="mainbtn bg_blue px-4">
                <i class="fa fa-paper-plane me-1"></i> 提交問卷
            </button>
            <button type="reset" class="mainbtn bg_gray px-4">
                <i class="fa fa-refresh me-1"></i> 重置
            </button>
        </div>
    </form>
</div>

<script>
// 當DOM加載完成後執行
document.addEventListener('DOMContentLoaded', function() {
    // 處理「其他」選項的顯示/隱藏
    const otherOptionHandlers = function() {
        // 單選題其他選項處理
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const parentDiv = this.closest('.question-options');
                if (parentDiv) {
                    const otherInput = parentDiv.querySelector('.other-option-input');
                    if (otherInput) {
                        const isOtherOption = this.closest('.form-check').querySelector('label').textContent.trim().toLowerCase().includes('其他');
                        otherInput.style.display = isOtherOption && this.checked ? 'block' : 'none';
                    }
                }
            });
        });

        // 多選題其他選項處理
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const parentDiv = this.closest('.form-check');
                if (parentDiv) {
                    const isOtherOption = parentDiv.querySelector('label').textContent.trim().toLowerCase().includes('其他');
                    if (isOtherOption) {
                        const otherInput = parentDiv.nextElementSibling;
                        if (otherInput && otherInput.classList.contains('other-option-input')) {
                            otherInput.style.display = this.checked ? 'block' : 'none';
                        }
                    }
                }
            });
        });
    };

    // 增強檔案上傳功能
    const enhanceFileUploads = function() {
        // 獲取所有檔案上傳欄位
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(fileInput => {
            const questionId = fileInput.name.replace('question_', '');
            const filePreviewDiv = document.createElement('div');
            filePreviewDiv.className = 'file-preview mt-2';
            filePreviewDiv.id = `file-preview-${questionId}`;
            
            // 在檔案輸入框後面添加預覽區域
            fileInput.parentNode.appendChild(filePreviewDiv);
            
            // 為檔案輸入框添加change事件
            fileInput.addEventListener('change', function(e) {
                const files = e.target.files;
                
                if (files.length > 0) {
                    const file = files[0];
                    // 檢查檔案大小
                    if (file.size > 5 * 1024 * 1024) { // 5MB
                        alert('檔案大小不能超過5MB');
                        e.target.value = '';
                        return;
                    }
                    
                    // 檢查檔案類型
                    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 
                                         'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                         'application/vnd.ms-excel', 
                                         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                                         
                    if (!allowedTypes.includes(file.type)) {
                        alert('不支援的檔案類型，僅支援 PDF, JPG, PNG, DOC, DOCX, XLS, XLSX');
                        e.target.value = '';
                        return;
                    }
                    
                    // 顯示檔案預覽
                    showFilePreview(file, questionId);
                }
            });
        });
    };

    // 顯示檔案預覽
    function showFilePreview(file, questionId) {
        const previewDiv = document.getElementById(`file-preview-${questionId}`);
        previewDiv.innerHTML = '';
        
        const fileInfoDiv = document.createElement('div');
        fileInfoDiv.className = 'file-info p-2 border rounded';
        
        // 判斷是否為圖片
        const isImage = file.type.startsWith('image/');
        
        if (isImage) {
            // 創建圖片預覽
            const img = document.createElement('img');
            img.className = 'img-thumbnail mb-2';
            img.style.maxHeight = '150px';
            img.style.maxWidth = '100%';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
            
            fileInfoDiv.appendChild(img);
        } else {
            // 創建檔案圖示
            const fileIcon = document.createElement('div');
            fileIcon.className = 'file-icon mb-2 text-center';
            
            let iconClass = 'fa fa-file';
            if (file.type === 'application/pdf') {
                iconClass = 'fa fa-file-pdf-o text-danger';
            } else if (file.type.includes('spreadsheet') || file.type.includes('excel')) {
                iconClass = 'fa fa-file-excel-o text-success';
            } else if (file.type.includes('document') || file.type.includes('word')) {
                iconClass = 'fa fa-file-word-o text-primary';
            }
            
            fileIcon.innerHTML = `<i class="${iconClass}" style="font-size: 48px;"></i>`;
            fileInfoDiv.appendChild(fileIcon);
        }
        
        // 添加檔案資訊
        const fileDetails = document.createElement('div');
        fileDetails.className = 'file-details text-center';
        fileDetails.innerHTML = `
            <div class="file-name">${file.name}</div>
            <div class="file-size text-muted">${formatFileSize(file.size)}</div>
        `;
        
        fileInfoDiv.appendChild(fileDetails);
        
        // 添加移除按鈕
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-danger mt-2 w-100';
        removeBtn.innerHTML = '<i class="fa fa-trash"></i> 移除檔案';
        removeBtn.onclick = function() {
            document.querySelector(`input[name="question_${questionId}"]`).value = '';
            previewDiv.innerHTML = '';
        };
        
        fileInfoDiv.appendChild(removeBtn);
        previewDiv.appendChild(fileInfoDiv);
    }

    // 格式化檔案大小
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // 表單驗證
    function validateForm() {
        let isValid = true;
        
        // 清除所有錯誤訊息
        document.querySelectorAll('.alert-danger').forEach(el => el.remove());
        
        // 檢查必填問題
        document.querySelectorAll('.question-card').forEach(questionCard => {
            const isRequired = questionCard.querySelector('.text-danger') !== null;
            if (isRequired) {
                let answered = false;
                
                // 檢查不同類型的問題
                const radios = questionCard.querySelectorAll('input[type="radio"]');
                const checkboxes = questionCard.querySelectorAll('input[type="checkbox"]');
                const textareas = questionCard.querySelectorAll('textarea');
                const inputs = questionCard.querySelectorAll('input[type="text"], input[type="number"], input[type="date"], input[type="file"]');
                const selects = questionCard.querySelectorAll('select');
                
                // 單選題
                if (radios.length > 0) {
                    answered = Array.from(radios).some(radio => radio.checked);
                }
                // 多選題
                else if (checkboxes.length > 0) {
                    answered = Array.from(checkboxes).some(checkbox => checkbox.checked);
                }
                // 文字題
                else if (textareas.length > 0) {
                    answered = Array.from(textareas).some(textarea => textarea.value.trim() !== '');
                }
                // 其他輸入類型
                else if (inputs.length > 0) {
                    answered = Array.from(inputs).some(input => input.value.trim() !== '');
                }
                // 下拉選單
                else if (selects.length > 0) {
                    answered = Array.from(selects).some(select => select.value !== '');
                }
                // 矩陣題 (新增部分)
                else if (questionCard.querySelector('.matrix-table')) {
                    const matrixTable = questionCard.querySelector('.matrix-table');
                    const matrixRows = matrixTable.querySelectorAll('tbody tr');
                    
                    // 檢查每一行是否有選擇選項
                    let allRowsAnswered = true;
                    matrixRows.forEach(row => {
                        const radios = row.querySelectorAll('input[type="radio"]');
                        const rowAnswered = Array.from(radios).some(radio => radio.checked);
                        if (!rowAnswered) {
                            allRowsAnswered = false;
                        }
                    });
                    
                    answered = allRowsAnswered;
                }
                
                if (!answered) {
                    isValid = false;
                    
                    // 添加錯誤訊息
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.textContent = '此問題必須回答';
                    
                    const cardBody = questionCard.querySelector('.card-body');
                    cardBody.insertBefore(errorDiv, cardBody.firstChild);
                    
                    // 如果是第一個錯誤，滾動到此問題
                    if (isValid === false) {
                        questionCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }
        });
        
        return isValid;
    }

    // 提交表單數據
    async function submitFormData(formData) {
        try {
            const response = await fetch('api/submit_response.php', {
                method: 'POST',
                body: formData
            });
            
            // 檢查回應是否為JSON
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return await response.json();
            } else {
                // 如果回應不是JSON，獲取文本並拋出錯誤
                const text = await response.text();
                console.error("API回應不是JSON格式:", text);
                throw new Error("伺服器回應格式錯誤，請檢查API");
            }
        } catch (error) {
            console.error("提交表單錯誤:", error);
            throw new Error(`表單提交失敗: ${error.message}`);
        }
    }

    // 處理檔案上傳
    async function handleFileUploads(surveyId, responseId) {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        const uploadPromises = [];
        
        for (const fileInput of fileInputs) {
            if (fileInput.files.length > 0) {
                const questionId = fileInput.name.replace('question_', '');
                const file = fileInput.files[0];
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('survey_id', surveyId);
                formData.append('question_id', questionId);
                formData.append('response_id', responseId);
                
                try {
                    const uploadPromise = fetch('api/upload_file.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(async response => {
                        if (!response.ok) {
                            const text = await response.text();
                            console.error("檔案上傳失敗回應:", text);
                            throw new Error(`檔案上傳失敗: ${response.status}`);
                        }
                        
                        // 檢查回應是否為JSON
                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            return response.json();
                        } else {
                            // 如果回應不是JSON，獲取文本並拋出錯誤
                            const text = await response.text();
                            console.error("API回應不是JSON格式:", text);
                            throw new Error("檔案上傳失敗：伺服器回應格式錯誤");
                        }
                    });
                    
                    uploadPromises.push(uploadPromise);
                } catch (error) {
                    console.error("檔案上傳錯誤:", error);
                    throw new Error(`檔案 ${file.name} 上傳失敗: ${error.message}`);
                }
            }
        }
        
        // 等待所有檔案上傳完成
        if (uploadPromises.length > 0) {
            try {
                await Promise.all(uploadPromises);
            } catch (error) {
                console.error("檔案上傳過程中發生錯誤:", error);
                throw error;
            }
        }
    }

    // 顯示載入中訊息
    function showLoadingMessage() {
        // 移除所有現有訊息
        removeAllMessages();
        
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'alert alert-info message-box fixed-top w-50 mx-auto mt-3 text-center';
        loadingDiv.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 正在提交問卷資料，請稍候...';
        document.body.appendChild(loadingDiv);
    }

    // 顯示成功訊息
    function showSuccessMessage() {
        // 移除所有現有訊息
        removeAllMessages();
        
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success message-box fixed-top w-50 mx-auto mt-3 text-center';
        successDiv.innerHTML = '<i class="fa fa-check-circle"></i> 問卷提交成功！正在跳轉...';
        document.body.appendChild(successDiv);
    }

    // 顯示錯誤訊息
    function showErrorMessage(message) {
        // 移除所有現有訊息
        removeAllMessages();
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger message-box fixed-top w-50 mx-auto mt-3 text-center';
        errorDiv.innerHTML = `<i class="fa fa-exclamation-circle"></i> 錯誤: ${message}`;
        document.body.appendChild(errorDiv);
        
        // 3秒後自動移除錯誤訊息
        setTimeout(() => {
            errorDiv.remove();
        }, 3000);
    }

    // 移除所有訊息
    function removeAllMessages() {
        document.querySelectorAll('.message-box').forEach(el => el.remove());
    }

    // 處理表單提交時的路徑修正
    const handleFormSubmit = function() {
        const form = document.getElementById('survey-form');
        
        if (form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // 執行表單驗證
                if (!validateForm()) {
                    return false;
                }
                
                try {
                    // 顯示載入中訊息
                    showLoadingMessage();
                    
                    // 從URL獲取問卷ID
                    const surveyId = new URLSearchParams(window.location.search).get('id');
                    
                    // 準備表單數據
                    const formData = new FormData(form);
                    formData.append('survey_id', surveyId);
                    
                    // 提交表單數據到API
                    const responseResult = await submitFormData(formData);
                    
                    if (responseResult && responseResult.success) {
                        // 上傳檔案（如果有）
                        try {
                            await handleFileUploads(surveyId, responseResult.response_id);
                            
                            // 顯示成功訊息
                            showSuccessMessage();
                            
                            // 延遲後跳轉到問卷結果頁面
                            setTimeout(() => {
                                window.location.href = `?page=survey/detail&survey_id=${surveyId}&response_id=${responseResult.response_id}`;
                            }, 1000);
                        } catch (uploadError) {
                            showErrorMessage(`檔案上傳失敗: ${uploadError.message}`);
                        }
                    } else {
                        throw new Error(responseResult?.message || '提交失敗');
                    }
                } catch (error) {
                    // 顯示錯誤訊息
                    console.error("表單提交過程中發生錯誤:", error);
                    showErrorMessage(error.message);
                }
            });
        }
    };

    // 初始化
    otherOptionHandlers();
    enhanceFileUploads();
    handleFormSubmit();
});
</script>

<style>
/* 矩陣題樣式 */
.question-matrix {
  overflow-x: auto;
  margin-bottom: 20px;
}

.matrix-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 0;
  background-color: #fff;
}

.matrix-table th, 
.matrix-table td {
  border: 1px solid #dee2e6;
  padding: 10px 15px;
  vertical-align: middle;
}

.matrix-header-empty {
  border-bottom: 1px solid #dee2e6;
  background-color: #fff;
  min-width: 180px;
}

.matrix-header-option {
  background-color: #f8f9fa;
  font-weight: 500;
  text-align: center;
  padding: 12px 15px;
}

.matrix-alternating-row {
  background-color: #f9f9f9;
}

.matrix-item-text {
  font-weight: 500;
  min-width: 180px;
  padding-left: 15px;
}

.matrix-radio-cell {
  text-align: center;
  vertical-align: middle;
  padding: 12px;
}

/* 自定義單選按鈕樣式 */
.matrix-radio-label {
  display: inline-block;
  position: relative;
  margin: 0;
  cursor: pointer;
  height: 20px;
  width: 20px;
  vertical-align: middle;
}

.matrix-radio-input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  height: 0;
  width: 0;
}

.matrix-radio-custom {
  position: absolute;
  top: 0;
  left: 0;
  height: 20px;
  width: 20px;
  background-color: #fff;
  border: 2px solid #ddd;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.matrix-radio-input:checked ~ .matrix-radio-custom {
  background-color: #fff;
  border-color: #28a745;
}

.matrix-radio-input:checked ~ .matrix-radio-custom:after {
  content: "";
  position: absolute;
  display: block;
  top: 3px;
  left: 3px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #28a745;
}

.matrix-radio-label:hover .matrix-radio-custom {
  border-color: #28a745;
}

/* 響應式調整 */
@media (max-width: 768px) {
  .matrix-table {
    min-width: 650px;
  }
  
  .matrix-table th, 
  .matrix-table td {
    padding: 8px 10px;
  }
  
  .matrix-item-text {
    min-width: 150px;
    padding-left: 10px;
  }
  
  .matrix-header-option {
    padding: 8px 10px;
  }
  
  .matrix-radio-cell {
    padding: 8px;
  }
}
</style>