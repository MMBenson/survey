<?php
// survey/api/upload_file.php

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
error_log('--- upload_file.php 執行開始 ---' . date('Y-m-d H:i:s'));
error_log('接收檔案: ' . print_r($_FILES, true));
error_log('接收參數: ' . print_r($_POST, true));

// 設置必要的常數與變數
$UPLOAD_DIR = dirname(dirname(dirname(__FILE__))) . '/uploads/survey_answers/';
$MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
$ALLOWED_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

// 確保上傳目錄存在
if (!file_exists($UPLOAD_DIR)) {
    if (!mkdir($UPLOAD_DIR, 0777, true)) {
        error_log('無法創建上傳目錄: ' . $UPLOAD_DIR);
        echo json_encode(['success' => false, 'message' => '無法創建上傳目錄']);
        exit;
    }
}

// 獲取必要參數
$survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
$question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
$response_id = isset($_POST['response_id']) ? intval($_POST['response_id']) : 0;

// 驗證參數
if ($survey_id <= 0 || $question_id <= 0) {
    error_log('參數無效: survey_id=' . $survey_id . ', question_id=' . $question_id);
    echo json_encode(['success' => false, 'message' => '問卷ID或問題ID無效']);
    exit;
}

// 處理上傳文件
if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
    $file = $_FILES['file'];
    
    // 檢查文件大小
    if ($file['size'] > $MAX_FILE_SIZE) {
        error_log('檔案過大: ' . $file['size'] . ' bytes');
        echo json_encode(['success' => false, 'message' => '檔案超過最大限制 (5MB)']);
        exit;
    }
    
    // 檢查文件類型
    $file_type = $file['type'];
    if (!in_array($file_type, $ALLOWED_TYPES)) {
        error_log('不支援的檔案類型: ' . $file_type);
        echo json_encode(['success' => false, 'message' => '不支援的檔案類型，僅支援 PDF, JPG, PNG, DOC, DOCX, XLS, XLSX']);
        exit;
    }
    
    try {
        // 連接資料庫
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            // 在獨立執行API時建立資料庫連接
            $database = new Database();
            $db = $database->getConnection();
        }
        
        // 檢查問卷和問題是否存在
        $sql = "SELECT q.question_id FROM surveys_questions q 
               INNER JOIN surveys s ON q.survey_id = s.survey_id 
               WHERE q.question_id = ? AND s.survey_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$question_id, $survey_id]);
        
        if ($stmt->rowCount() === 0) {
            error_log('找不到對應的問題: question_id=' . $question_id . ', survey_id=' . $survey_id);
            echo json_encode(['success' => false, 'message' => '找不到對應的問題']);
            exit;
        }
        
        // 生成唯一的文件名
        $original_filename = $file['name'];
        $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        
        $new_filename = '';
        if ($response_id > 0) {
            $new_filename = 'response_' . $response_id . '_question_' . $question_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
        } else {
            $new_filename = 'survey_' . $survey_id . '_question_' . $question_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
        }
        
        $file_path = 'uploads/survey_answers/' . $new_filename;
        $full_path = $UPLOAD_DIR . $new_filename;
        
        // 移動上傳的文件
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            // 如果沒有回應ID，僅返回文件路徑
            if ($response_id <= 0) {
                error_log('檔案上傳成功 (臨時): ' . $file_path);
                echo json_encode([
                    'success' => true,
                    'message' => '檔案上傳成功',
                    'data' => [
                        'original_filename' => $original_filename,
                        'file_path' => $file_path,
                        'file_type' => $file_type,
                        'file_size' => $file['size']
                    ]
                ]);
                exit;
            }
            
            // 獲取或創建回答記錄
            $answer_id = null;
            
            // 檢查回答記錄是否存在
            $sql = "SELECT answer_id FROM surveys_answers 
                   WHERE response_id = ? AND question_id = ? 
                   LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([$response_id, $question_id]);
            $answer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($answer) {
                $answer_id = $answer['answer_id'];
                
                // 更新現有記錄的文件路徑
                $sql = "UPDATE surveys_answers 
                       SET file_path = ? 
                       WHERE answer_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$file_path, $answer_id]);
            } else {
                // 創建新的回答記錄
                $sql = "INSERT INTO surveys_answers 
                       (response_id, question_id, file_path, created_at) 
                       VALUES (?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);
                $stmt->execute([$response_id, $question_id, $file_path]);
                $answer_id = $db->lastInsertId();
            }
            
            // 添加文件記錄到surveys_answer_files表
            $sql = "INSERT INTO surveys_answer_files 
                   (answer_id, response_id, question_id, original_filename, 
                    file_path, file_type, file_size, created_at) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $answer_id,
                $response_id,
                $question_id,
                $original_filename,
                $file_path,
                $file_type,
                $file['size']
            ]);
            
            // 記錄成功訊息
            error_log('檔案上傳成功: ' . $file_path . ', answer_id=' . $answer_id);
            
            // 返回成功結果
            echo json_encode([
                'success' => true,
                'message' => '檔案上傳成功',
                'data' => [
                    'answer_id' => $answer_id,
                    'original_filename' => $original_filename,
                    'file_path' => $file_path,
                    'file_type' => $file_type,
                    'file_size' => $file['size']
                ]
            ]);
        } else {
            error_log('檔案上傳失敗，無法移動臨時檔案: ' . $file['tmp_name'] . ' 到 ' . $full_path);
            echo json_encode(['success' => false, 'message' => '檔案上傳失敗，無法保存檔案']);
        }
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '資料庫錯誤: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log('Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '發生錯誤: ' . $e->getMessage()]);
    }
} else {
    $error_message = isset($_FILES['file']) ? '上傳錯誤 (代碼: ' . $_FILES['file']['error'] . ')' : '沒有收到檔案';
    error_log('檔案上傳失敗: ' . $error_message);
    echo json_encode(['success' => false, 'message' => $error_message]);
}

// 記錄API執行結束
error_log('--- upload_file.php 執行結束 ---');