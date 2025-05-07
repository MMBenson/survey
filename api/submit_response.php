<?php
// survey/api/submit_response.php

// 確認是系統內部請求
if (!defined('IN_SYSTEM')) {
    // 設定允許的來源網域 (同源網站)
    header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    // 檢查是否為POST請求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '只允許POST請求']);
        exit;
    }

    // 假設在獨立執行API時需要這些包含檔案
    // 請確保以下路徑正確指向您的檔案
    $base_path = dirname(dirname(dirname(__FILE__)));
    require_once '../../../includes/database.php';  // 資料庫連接

    // 定義 IN_SYSTEM 常數，表示已經執行了系統檢查
    define('IN_SYSTEM', true);
}

// 記錄API執行時間和接收參數
error_log('--- submit_response.php 執行開始 ---' . date('Y-m-d H:i:s'));
error_log('接收參數: ' . print_r($_POST, true));

try {
    // 取得表單資料
    $input = $_POST;

    // 確認必要參數
    $survey_id = isset($input['survey_id']) ? intval($input['survey_id']) : 0;

    if ($survey_id <= 0) {
        echo json_encode(['success' => false, 'message' => '無效的問卷ID']);
        exit;
    }

    // 連接資料庫
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        // 在獨立執行API時建立資料庫連接
        $database = new Database();
        $db = $database->getConnection();
    }

    // 檢查問卷是否存在且發布
    $sql = "SELECT survey_id, title, is_anonymous FROM surveys WHERE survey_id = ? AND status = 'published'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$survey_id]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$survey) {
        echo json_encode(['success' => false, 'message' => '找不到此問卷或問卷尚未發布']);
        exit;
    }

    // 取得問卷問題
    $sql = "SELECT question_id, question_type, is_required FROM surveys_questions WHERE survey_id = ? ORDER BY order_num";
    $stmt = $db->prepare($sql);
    $stmt->execute([$survey_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 驗證必填問題 - 特別處理檔案上傳題型
    $errors = [];
    foreach ($questions as $question) {
        if ($question['is_required']) {
            $q_id = $question['question_id'];
            $answer_key = 'question_' . $q_id;
            $question_type = $question['question_type'];

            // 特別處理檔案上傳類型的問題
            if ($question_type === 'upload') {
                // 檢查是否有上傳檔案
                $has_file = false;

                // 方式1: 檢查$_FILES中是否有對應的檔案
                if (isset($_FILES[$answer_key]) && $_FILES[$answer_key]['error'] === 0) {
                    $has_file = true;
                }

                // 方式2: 檢查表單中是否有標記已上傳檔案
                if (isset($input[$answer_key . '_uploaded']) && $input[$answer_key . '_uploaded'] === 'true') {
                    $has_file = true;
                }

                // 方式3: 檢查之前是否已經上傳過檔案（針對既有回應）
                if (isset($input['response_id']) && !empty($input['response_id'])) {
                    $response_id = intval($input['response_id']);
                    $sql = "SELECT file_path FROM surveys_answers 
                           WHERE response_id = ? AND question_id = ? AND file_path IS NOT NULL 
                           LIMIT 1";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$response_id, $q_id]);
                    if ($stmt->rowCount() > 0) {
                        $has_file = true;
                    }
                }

                // 記錄檢查情況
                error_log("問題 {$q_id} (檔案上傳) 檢查結果: " . ($has_file ? '已有檔案' : '無檔案'));
                error_log("FILES內容: " . print_r($_FILES, true));

                if (!$has_file) {
                    $errors[$q_id] = "問題 {$q_id} 必須上傳檔案";
                }
            }
            // 處理矩陣單選題的必填驗證
            else if ($question_type === 'matrix_single') {
                // 取得矩陣項目
                $sql = "SELECT item_id FROM surveys_matrix_items WHERE question_id = ? ORDER BY order_num";
                $stmt = $db->prepare($sql);
                $stmt->execute([$q_id]);
                $matrix_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // 檢查每個項目是否都已回答
                $all_items_answered = true;
                foreach ($matrix_items as $item_id) {
                    $matrix_answer_key = 'question_' . $q_id . '_item_' . $item_id;
                    if (!isset($input[$matrix_answer_key]) || trim($input[$matrix_answer_key]) === '') {
                        $all_items_answered = false;
                        break;
                    }
                }
                
                if (!$all_items_answered) {
                    $errors[$q_id] = "問題 {$q_id} 必須回答";
                }
            }
            // 對其他類型的問題保持原有的檢查邏輯
            else if (
                !isset($input[$answer_key]) || (is_array($input[$answer_key]) && empty($input[$answer_key])) ||
                (is_string($input[$answer_key]) && trim($input[$answer_key]) === '')
            ) {
                $errors[$q_id] = "問題 {$q_id} 必須回答";
            }
        }
    }

    if (!empty($errors)) {
        error_log("表單驗證錯誤: " . print_r($errors, true));
        echo json_encode(['success' => false, 'message' => '存在必填問題未回答', 'errors' => $errors]);
        exit;
    }

    // 開始事務處理
    $db->beginTransaction();

    // 建立回應記錄
    $sql = "INSERT INTO surveys_responses 
           (survey_id, user_id, respondent_name, respondent_email, respondent_phone, 
            ip_address, user_agent, started_at, completed_at, is_completed, created_at) 
           VALUES 
           (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1, NOW())";

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $respondent_name = isset($input['respondent_name']) ? trim($input['respondent_name']) : null;
    $respondent_email = isset($input['respondent_email']) ? trim($input['respondent_email']) : null;
    $respondent_phone = isset($input['respondent_phone']) ? trim($input['respondent_phone']) : null;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    // 如果問卷不是匿名且未提供填答者資訊
    if (!$survey['is_anonymous'] && (empty($respondent_name) || empty($respondent_email))) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => '非匿名問卷需提供姓名和電子郵件']);
        exit;
    }

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

        // 處理矩陣單選題的答案
        if ($question_type === 'matrix_single') {
            // 取得矩陣項目
            $sql = "SELECT item_id FROM surveys_matrix_items WHERE question_id = ? ORDER BY order_num";
            $stmt = $db->prepare($sql);
            $stmt->execute([$q_id]);
            $matrix_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 儲存每個項目的答案
            foreach ($matrix_items as $item_id) {
                $matrix_answer_key = 'question_' . $q_id . '_item_' . $item_id;
                if (isset($input[$matrix_answer_key]) && !empty($input[$matrix_answer_key])) {
                    $option_id = intval($input[$matrix_answer_key]);
                    
                    $sql = "INSERT INTO surveys_answers 
                           (response_id, question_id, option_id, matrix_item_id, created_at) 
                           VALUES (?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$response_id, $q_id, $option_id, $item_id]);
                    
                    // 記錄處理情況
                    error_log("矩陣題 {$q_id} 項目 {$item_id} 的答案已儲存，選項 ID: {$option_id}");
                }
            }
        }
        // 處理其他類型的答案
        else if (isset($input[$answer_key])) {
            $answer_value = $input[$answer_key];

            // 根據問題類型處理答案
            switch ($question_type) {
                case 'single_choice':
                    $option_id = intval($answer_value);

                    $sql = "INSERT INTO surveys_answers 
                           (response_id, question_id, option_id, created_at) 
                           VALUES (?, ?, ?, NOW())";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$response_id, $q_id, $option_id]);

                    // 處理「其他」選項的文字
                    $other_key = 'other_option_' . $q_id;
                    if (isset($input[$other_key]) && !empty($input[$other_key])) {
                        $sql = "UPDATE surveys_answers 
                               SET answer_text = ? 
                               WHERE response_id = ? AND question_id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$input[$other_key], $response_id, $q_id]);
                    }
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

                        // 處理「其他」選項的文字
                        $other_key = 'other_option_' . $q_id;
                        if (isset($input[$other_key]) && !empty($input[$other_key])) {
                            // 找到「其他」選項ID
                            $sql = "SELECT option_id FROM surveys_options 
                                   WHERE question_id = ? AND is_other = 1 LIMIT 1";
                            $stmt = $db->prepare($sql);
                            $stmt->execute([$q_id]);
                            $other_option = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($other_option && in_array($other_option['option_id'], $answer_value)) {
                                $sql = "UPDATE surveys_answers 
                                       SET answer_text = ? 
                                       WHERE response_id = ? AND question_id = ? AND option_id = ?";
                                $stmt = $db->prepare($sql);
                                $stmt->execute([$input[$other_key], $response_id, $q_id, $other_option['option_id']]);
                            }
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

                case 'upload':
                    // 檔案上傳處理會在upload_file.php中完成
                    // 這裡只建立答案紀錄，檔案路徑暫時為空
                    $sql = "INSERT INTO surveys_answers 
                           (response_id, question_id, created_at) 
                           VALUES (?, ?, NOW())";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$response_id, $q_id]);
                    break;
            }
        }
    }

    // 儲存子問題答案
    foreach ($input as $key => $value) {
        if (strpos($key, 'subquestion_') === 0 && !empty($value)) {
            $subquestion_id = intval(str_replace('subquestion_', '', $key));

            // 從子問題ID獲取父問題ID
            $sql = "SELECT question_id FROM surveys_subquestions WHERE subquestion_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$subquestion_id]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($parent) {
                // 記錄子問題答案
                $sql = "INSERT INTO surveys_answers 
                   (response_id, question_id, subquestion_id, answer_text, created_at) 
                   VALUES (?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);
                $stmt->execute([$response_id, $parent['question_id'], $subquestion_id, $value]);

                // 記錄處理情況
                error_log("子問題 {$subquestion_id} 的答案已儲存，答案文字：" . substr($value, 0, 30) . '...');
            } else {
                error_log("找不到子問題 {$subquestion_id} 的父問題");
            }
        }
    }

    // 提交所有變更
    $db->commit();

    // 記錄成功訊息
    error_log('問卷回答成功提交，回應ID: ' . $response_id);

    // 回傳成功結果
    echo json_encode([
        'success' => true,
        'message' => '問卷回答已成功提交',
        'response_id' => $response_id,
        'survey_id' => $survey_id
    ]);

} catch (PDOException $e) {
    // 發生錯誤時回滾
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '資料庫錯誤: ' . $e->getMessage()]);
} catch (Exception $e) {
    // 發生錯誤時回滾
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '發生錯誤: ' . $e->getMessage()]);
}

// 記錄API執行結束
error_log('--- submit_response.php 執行結束 ---');