<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="responsedetail.css.css">

<?php
// survey/responsedetail.php
// if (!defined('IN_SYSTEM')) {
//     die('Access Denied');
// }

// 引入資料庫類別
require_once dirname(dirname(dirname(__FILE__))) . '/includes/database.php';

// 取得 URL 參數
$response_id = isset($_GET['response_id']) ? intval($_GET['response_id']) : 0;
$survey_id = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : 0;

// 參數檢查
if ($response_id <= 0) {
    echo '<div class="alert alert-danger">無效的回應ID</div>';
    exit;
}

// 建立資料庫連線
$database = new Database();
$db = $database->getConnection();

try {
    // 取得回應基本資訊和問卷資訊
    $sql = "SELECT r.*, s.title as survey_title, s.description as survey_description, 
                  s.is_anonymous, s.end_text,
                  u.full_name as user_name
           FROM surveys_responses r
           JOIN surveys s ON r.survey_id = s.survey_id
           LEFT JOIN users u ON r.user_id = u.id
           WHERE r.response_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$response_id]);
    $response = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$response) {
        echo '<div class="alert alert-danger">找不到此回應資料</div>';
        exit;
    }
    
    // 如果未指定問卷ID，使用回應中的問卷ID
    if ($survey_id <= 0) {
        $survey_id = $response['survey_id'];
    }
    
    // 取得問卷的所有問題
    $sql = "SELECT * FROM surveys_questions 
           WHERE survey_id = ? 
           ORDER BY order_num";
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
    
    // 取得回應的答案
    $sql = "SELECT a.*, f.file_path, f.original_filename, f.file_type, f.file_size
           FROM surveys_answers a
           LEFT JOIN surveys_answer_files f ON a.answer_id = f.answer_id
           WHERE a.response_id = ?
           ORDER BY a.question_id, a.answer_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([$response_id]);
    
    $answers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $q_id = $row['question_id'];
        
        if (!isset($answers[$q_id])) {
            $answers[$q_id] = [];
        }
        
        $answers[$q_id][] = $row;
    }
    
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">資料庫錯誤：' . $e->getMessage() . '</div>';
    exit;
}

// 根據問題類型獲取答案顯示文字
function getDisplayAnswer($question, $answer_data, $options) {
    global $db; // 新增此行以在函數內使用資料庫連接
    
    $question_type = $question['question_type'];
    $q_id = $question['question_id'];
    
    if (empty($answer_data)) {
        return '<span class="text-muted">未回答</span>';
    }
    
    switch ($question_type) {
        case 'matrix_single':
            // 取得矩陣項目
            $sql = "SELECT * FROM surveys_matrix_items WHERE question_id = ? ORDER BY order_num";
            $stmt = $db->prepare($sql);
            $stmt->execute([$q_id]);
            $matrix_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 獲取所有選項
            $all_options = [];
            if (!empty($options[$q_id])) {
                foreach ($options[$q_id] as $option) {
                    $all_options[$option['option_id']] = $option['option_text'];
                }
            }
            
            // 建立項目對應答案的映射
            $item_answers = [];
            foreach ($answer_data as $answer) {
                if (isset($answer['matrix_item_id'])) {
                    $item_answers[$answer['matrix_item_id']] = $answer['option_id'];
                }
            }
            
            // 生成表格顯示
            $html = '<div class="matrix-answer-display">';
            $html .= '<table class="table table-bordered matrix-result-table">';
            // 表頭 - 顯示選項名稱
            $html .= '<thead><tr><th></th>';
            foreach ($options[$q_id] as $option) {
                $html .= '<th class="text-center">' . htmlspecialchars($option['option_text']) . '</th>';
            }
            $html .= '</tr></thead>';
            
            // 表身 - 顯示每個項目的選擇
            $html .= '<tbody>';
            foreach ($matrix_items as $item) {
                $html .= '<tr>';
                $html .= '<td class="matrix-item-text">' . htmlspecialchars($item['item_text']) . '</td>';
                
                // 顯示每個選項的選擇狀態
                foreach ($options[$q_id] as $option) {
                    $is_selected = isset($item_answers[$item['item_id']]) && $item_answers[$item['item_id']] == $option['option_id'];
                    $html .= '<td class="text-center">';
                    if ($is_selected) {
                        $html .= '<span class="matrix-selected-mark">●</span>';
                    } else {
                        $html .= '<span class="matrix-unselected-mark">○</span>';
                    }
                    $html .= '</td>';
                }
                
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
            
            return $html;
            
        case 'single_choice':
            $option_id = $answer_data[0]['option_id'] ?? 0;
            $option_text = '未知選項';
            
            // 查找選項文字
            if (!empty($options[$q_id])) {
                foreach ($options[$q_id] as $option) {
                    if ($option['option_id'] == $option_id) {
                        $option_text = htmlspecialchars($option['option_text']);
                        
                        // 檢查是否為「其他」選項且有自定義文字
                        if ($option['is_other'] && !empty($answer_data[0]['answer_text'])) {
                            $option_text .= ': ' . htmlspecialchars($answer_data[0]['answer_text']);
                        }
                        break;
                    }
                }
            }
            
            return $option_text;
            
        case 'multiple_choice':
            $selected_options = [];
            
            // 收集所有選中的選項
            foreach ($answer_data as $answer) {
                $option_id = $answer['option_id'] ?? 0;
                $option_text = '未知選項';
                
                // 查找選項文字
                if (!empty($options[$q_id])) {
                    foreach ($options[$q_id] as $option) {
                        if ($option['option_id'] == $option_id) {
                            $option_text = htmlspecialchars($option['option_text']);
                            
                            // 檢查是否為「其他」選項且有自定義文字
                            if ($option['is_other'] && !empty($answer['answer_text'])) {
                                $option_text .= ': ' . htmlspecialchars($answer['answer_text']);
                            }
                            break;
                        }
                    }
                }
                
                $selected_options[] = $option_text;
            }
            
            return implode('<br>', $selected_options);
            
        case 'text':
            return nl2br(htmlspecialchars($answer_data[0]['answer_text'] ?? ''));
            
        case 'number':
            return htmlspecialchars($answer_data[0]['answer_number'] ?? '');
            
        case 'date':
            $date = $answer_data[0]['answer_date'] ?? '';
            return $date ? date('Y/m/d', strtotime($date)) : '';
            
        case 'rating':
            $rating = $answer_data[0]['answer_number'] ?? 0;
            $rating_html = '';
            
            // 生成星級評分顯示
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $rating) {
                    $rating_html .= '<i class="fa fa-star text-warning"></i>';
                } else {
                    $rating_html .= '<i class="fa fa-star-o text-muted"></i>';
                }
            }
            
            $rating_html .= ' <span class="ms-2">(' . $rating . ')</span>';
            
            return $rating_html;
            
        case 'dropdown':
            $option_id = $answer_data[0]['option_id'] ?? 0;
            $option_text = '未知選項';
            
            // 查找選項文字
            if (!empty($options[$q_id])) {
                foreach ($options[$q_id] as $option) {
                    if ($option['option_id'] == $option_id) {
                        $option_text = htmlspecialchars($option['option_text']);
                        break;
                    }
                }
            }
            
            return $option_text;
            
        case 'upload':
            $file_html = '';
            
            foreach ($answer_data as $answer) {
                if (!empty($answer['file_path']) || !empty($answer['original_filename'])) {
                    $file_path = $answer['file_path'] ?? '';
                    $file_name = $answer['original_filename'] ?? basename($file_path);
                    $file_type = $answer['file_type'] ?? '';
                    $file_size = $answer['file_size'] ?? 0;
                    
                    // 顯示檔案大小
                    $size_text = '';
                    if ($file_size > 0) {
                        if ($file_size < 1024) {
                            $size_text = $file_size . ' B';
                        } elseif ($file_size < 1024 * 1024) {
                            $size_text = round($file_size / 1024, 2) . ' KB';
                        } else {
                            $size_text = round($file_size / (1024 * 1024), 2) . ' MB';
                        }
                    }
                    
                    // 檢查是否為圖片
                    $is_image = strpos($file_type, 'image/') === 0;
                    
                    if ($is_image) {
                        $base_url = getBaseURL();  // 使用函數取得基礎路徑
                        $file_html .= '<div class="mb-2">';
                        $file_html .= '<a href="' . $base_url . $file_path . '" target="_blank">';
                        $file_html .= '<img src="' . $base_url . $file_path . '" alt="' . htmlspecialchars($file_name) . '" class="img-thumbnail" style="max-height: 150px;">';
                        $file_html .= '</a>';
                        $file_html .= '<div class="mt-1">';
                        $file_html .= '<a href="' . $base_url . $file_path . '" target="_blank">' . htmlspecialchars($file_name) . '</a>';
                        if ($size_text) {
                            $file_html .= ' <span class="text-muted">(' . $size_text . ')</span>';
                        }
                        $file_html .= '</div>';
                        $file_html .= '</div>';
                    } else {
                        // 為不同類型的檔案顯示不同的圖示
                        $icon_class = 'fa-file';
                        if (strpos($file_type, 'pdf') !== false) {
                            $icon_class = 'fa-file-pdf-o';
                        } elseif (strpos($file_type, 'word') !== false || strpos($file_type, 'document') !== false) {
                            $icon_class = 'fa-file-word-o';
                        } elseif (strpos($file_type, 'excel') !== false || strpos($file_type, 'spreadsheet') !== false) {
                            $icon_class = 'fa-file-excel-o';
                        }
                        
                        $base_url = getBaseURL();  // 使用函數取得基礎路徑
                        $file_html .= '<div class="mb-2">';
                        $file_html .= '<a href="' . $base_url . $file_path . '" target="_blank" class="d-flex align-items-center text-decoration-none">';
                        $file_html .= '<i class="fa ' . $icon_class . ' fa-2x me-2"></i>';
                        $file_html .= '<div>';
                        $file_html .= htmlspecialchars($file_name);
                        if ($size_text) {
                            $file_html .= ' <span class="text-muted">(' . $size_text . ')</span>';
                        }
                        $file_html .= '</div>';
                        $file_html .= '</a>';
                        $file_html .= '</div>';
                    }
                }
            }
            
            return $file_html ? $file_html : '<span class="text-muted">未上傳檔案</span>';
            
        default:
            return '<span class="text-muted">未支援的問題類型</span>';
    }
}

// 格式化日期時間
function formatDateTime($datetime) {
    if (!$datetime) return '';
    return date('Y/m/d H:i:s', strtotime($datetime));
}

// 取得網站根路徑
function getBaseURL() {
    // 取得目前的基礎路徑
    $script_name = $_SERVER['SCRIPT_NAME'];
    $base_dir = dirname($script_name);
    
    // 處理路徑格式，確保以斜線結尾
    $base_url = rtrim($base_dir, '/') . '/';
    
    // 如果是根目錄，則返回空字符串
    if ($base_url == '//') {
        return '/';
    }
    
    // 這是預設值，您可以根據實際情況修改
    return '/EIP/Meeting/Webpage/';
}

// 計算回答完成率
function calculateCompletionRate($questions, $answers) {
    $required_count = 0;
    $answered_count = 0;
    
    foreach ($questions as $question) {
        if ($question['is_required']) {
            $required_count++;
            
            $q_id = $question['question_id'];
            if (isset($answers[$q_id]) && !empty($answers[$q_id])) {
                $answered_count++;
            }
        }
    }
    
    if ($required_count === 0) return 100;
    return round(($answered_count / $required_count) * 100);
}

// 計算完成率
$completion_rate = calculateCompletionRate($questions, $answers);
?>

<div class="layoutBox">
    <!-- 標題和操作區 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3>回應詳情</h3>
            <div class="text-muted">問卷：<?= htmlspecialchars($response['survey_title']) ?></div>
        </div>
        <div>
            <a href="?page=survey/responses&survey_id=<?= $survey_id ?>" class="mainbtn bg_gray">
                <i class="fa fa-arrow-left"></i> 返回回應列表
            </a>
            <button class="mainbtn bg_blue" onclick="printResponseDetail()">
                <i class="fa fa-print"></i> 列印
            </button>
        </div>
    </div>

    <!-- 回應資訊摘要 -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>回應資訊</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 150px;">回應編號：</th>
                            <td><?= $response_id ?></td>
                        </tr>
                        <?php if (!$response['is_anonymous']): ?>
                        <tr>
                            <th>填答者：</th>
                            <td><?= htmlspecialchars($response['respondent_name'] ?: ($response['user_name'] ?: '未提供')) ?></td>
                        </tr>
                        <tr>
                            <th>電子郵件：</th>
                            <td><?= htmlspecialchars($response['respondent_email'] ?: '未提供') ?></td>
                        </tr>
                        <tr>
                            <th>電話：</th>
                            <td><?= htmlspecialchars($response['respondent_phone'] ?: '未提供') ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 150px;">填寫時間：</th>
                            <td><?= formatDateTime($response['completed_at']) ?></td>
                        </tr>
                        <tr>
                            <th>填答裝置：</th>
                            <td>
                                <?php 
                                $user_agent = $response['user_agent'] ?? '';
                                if (strpos($user_agent, 'Mobile') !== false) {
                                    echo '<i class="fa fa-mobile"></i> 行動裝置';
                                } elseif (strpos($user_agent, 'Tablet') !== false) {
                                    echo '<i class="fa fa-tablet"></i> 平板裝置';
                                } else {
                                    echo '<i class="fa fa-desktop"></i> 桌上型電腦';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>IP 位址：</th>
                            <td><?= htmlspecialchars($response['ip_address'] ?: '未記錄') ?></td>
                        </tr>
                        <tr>
                            <th>完成率：</th>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?= $completion_rate == 100 ? 'bg-success' : 'bg-warning' ?>" 
                                         role="progressbar" 
                                         style="width: <?= $completion_rate ?>%;" 
                                         aria-valuenow="<?= $completion_rate ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?= $completion_rate ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 回應詳細內容 -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>回答內容</h5>
        </div>
        <div class="card-body p-0">
            <table class="main_Table response-detail-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">序號</th>
                        <th>問題</th>
                        <th>回答</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $index => $question): ?>
                    <?php 
                    $q_id = $question['question_id'];
                    $answer_data = $answers[$q_id] ?? [];
                    ?>
                    <tr>
                        <td class="text-center"><?= $index + 1 ?></td>
                        <td>
                            <div class="question-text">
                                <?= htmlspecialchars($question['question_text']) ?>
                                <?php if ($question['is_required']): ?>
                                <span class="text-danger">*</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($question['description'])): ?>
                            <div class="question-description text-muted small mt-1">
                                <?= nl2br(htmlspecialchars($question['description'])) ?>
                            </div>
                            <?php endif; ?>
                            <div class="question-type small text-muted mt-1">
                                <?php
                                $type_labels = [
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
                                echo $type_labels[$question['question_type']] ?? $question['question_type'];
                                ?>
                            </div>
                        </td>
                        <td>
                            <div class="answer-content">
                                <?= getDisplayAnswer($question, $answer_data, $options) ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 結束文字（如果有） -->
    <?php if (!empty($response['end_text'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>問卷結語</h5>
        </div>
        <div class="card-body">
            <?= nl2br(htmlspecialchars($response['end_text'])) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// 列印功能
function printResponseDetail() {
    window.print();
}
</script>

<style>
/* 回應詳情頁面樣式 */
.response-detail-table td,
.response-detail-table th {
    padding: 12px 15px;
    border: 1px solid #dee2e6;
}

.response-detail-table .question-text {
    font-weight: 500;
}

.response-detail-table .answer-content {
    min-height: 24px;
}

/* 矩陣題回應顯示樣式 */
.matrix-answer-display {
    overflow-x: auto;
    margin-bottom: 15px;
}
.matrix-result-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #dee2e6;
}
.matrix-result-table th,
.matrix-result-table td {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    text-align: center;
}
.matrix-result-table thead th {
    background-color: #f8f9fa;
    font-weight: 500;
}
.matrix-result-table tbody tr:nth-child(odd) {
    background-color: #f9f9f9;
}
.matrix-item-text {
    text-align: left;
    font-weight: 500;
}
.matrix-selected-mark {
    display: inline-block;
    width: 20px;
    height: 20px;
    line-height: 20px;
    color: #28a745;
    font-size: 20px;
}
.matrix-unselected-mark {
    display: inline-block;
    width: 20px;
    height: 20px;
    line-height: 20px;
    color: #dee2e6;
    font-size: 20px;
}

/* 列印樣式 */
@media print {
    .layoutBox {
        margin: 0;
        padding: 0;
        border: none;
    }
    
    .mainbtn {
        display: none;
    }
    
    .card {
        border: 1px solid #ddd;
        margin-bottom: 20px;
        break-inside: avoid;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #ddd;
        padding: 10px 15px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .response-detail-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    
    .response-detail-table th,
    .response-detail-table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
    }
    
    .response-detail-table th {
        background-color: #f8f9fa !important;
    }
    
    /* 確保圖片大小適中 */
    img.img-thumbnail {
        max-height: 100px !important;
    }
    
    .matrix-answer-display {
        page-break-inside: avoid;
    }
    
    .matrix-result-table th,
    .matrix-result-table td {
        border: 1px solid #aaa;
    }
    
    .matrix-selected-mark {
        color: #000;
    }
}
</style>