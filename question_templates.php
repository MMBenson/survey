<?php
/**
 * 問卷系統 - 問題範本檔案
 * 
 * 此檔案包含所有問題類型的 HTML 範本，
 * 用於在問卷建立頁面中動態生成問題卡片。
 */

// 確保只能通過主程式訪問
if (!defined('IN_SYSTEM')) {
    die('Access Denied');
}
?>

<!-- 問題範本 (隱藏) -->
<div id="question-templates" style="display: none;">

    <!-- 單選題範本 -->
    <div id="template-single_choice" class="question-card">
        <div class="card mb-3">
            <div class="card-header question-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="question-title">單選題</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body question-body">
                <div class="mb-3">
                    <label class="form-label">問題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control question-text" name="questions[0][text]" required>
                    <input type="hidden" class="question-type" name="questions[0][type]" value="single_choice">
                </div>
                <div class="mb-3">
                    <label class="form-label">問題說明 (選填)</label>
                    <textarea class="form-control question-description" name="questions[0][description]"
                        rows="2"></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-required" id="required-0"
                        name="questions[0][required]" checked>
                    <label class="form-check-label" for="required-0">必填題目</label>
                </div>

                <!-- 子問題選項 -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-has-subquestions" id="has-subquestions-0"
                        name="questions[0][has_subquestions]">
                    <label class="form-check-label" for="has-subquestions-0">包含子問題</label>
                </div>

                <!-- 子問題容器 -->
                <div class="subquestions-container" style="display: none;">
                    <label class="form-label">子問題列表</label>
                    <div class="subquestions-list">
                        <!-- 子問題將透過JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-subquestion">
                            <i class="fa fa-plus"></i> 新增子問題
                        </button>
                    </div>
                </div>

                <!-- 選項列表 -->
                <div class="mb-3">
                    <label class="form-label">選項</label>
                    <div class="options-container">
                        <!-- 選項將由JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-option">
                            <i class="fa fa-plus"></i> 新增選項
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-other-option">
                            <i class="fa fa-plus"></i> 新增「其他」選項
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 多選題範本 -->
    <div id="template-multiple_choice" class="question-card">
        <div class="card mb-3">
            <div class="card-header question-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="question-title">多選題</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body question-body">
                <div class="mb-3">
                    <label class="form-label">問題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control question-text" name="questions[0][text]" required>
                    <input type="hidden" class="question-type" name="questions[0][type]" value="multiple_choice">
                </div>
                <div class="mb-3">
                    <label class="form-label">問題說明 (選填)</label>
                    <textarea class="form-control question-description" name="questions[0][description]"
                        rows="2"></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-required" id="required-0"
                        name="questions[0][required]" checked>
                    <label class="form-check-label" for="required-0">必填題目</label>
                </div>

                <!-- 子問題選項 -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-has-subquestions" id="has-subquestions-0"
                        name="questions[0][has_subquestions]">
                    <label class="form-check-label" for="has-subquestions-0">包含子問題</label>
                </div>

                <!-- 子問題容器 -->
                <div class="subquestions-container" style="display: none;">
                    <label class="form-label">子問題列表</label>
                    <div class="subquestions-list">
                        <!-- 子問題將透過JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-subquestion">
                            <i class="fa fa-plus"></i> 新增子問題
                        </button>
                    </div>
                </div>

                <!-- 選項列表 -->
                <div class="mb-3">
                    <label class="form-label">選項</label>
                    <div class="options-container">
                        <!-- 選項將由JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-option">
                            <i class="fa fa-plus"></i> 新增選項
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-other-option">
                            <i class="fa fa-plus"></i> 新增「其他」選項
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 文字題範本 -->
    <div id="template-text" class="question-card">
        <div class="card mb-3">
            <div class="card-header question-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="question-title">文字題</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body question-body">
                <div class="mb-3">
                    <label class="form-label">問題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control question-text" name="questions[0][text]" required>
                    <input type="hidden" class="question-type" name="questions[0][type]" value="text">
                </div>
                <div class="mb-3">
                    <label class="form-label">問題說明 (選填)</label>
                    <textarea class="form-control question-description" name="questions[0][description]"
                        rows="2"></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-required" id="required-0"
                        name="questions[0][required]" checked>
                    <label class="form-check-label" for="required-0">必填題目</label>
                </div>

                <!-- 子問題選項 -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-has-subquestions" id="has-subquestions-0"
                        name="questions[0][has_subquestions]">
                    <label class="form-check-label" for="has-subquestions-0">包含子問題</label>
                </div>

                <!-- 子問題容器 -->
                <div class="subquestions-container" style="display: none;">
                    <label class="form-label">子問題列表</label>
                    <div class="subquestions-list">
                        <!-- 子問題將透過JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-subquestion">
                            <i class="fa fa-plus"></i> 新增子問題
                        </button>
                    </div>
                </div>

                <!-- 文字題預覽 -->
                <div class="mb-3">
                    <label class="form-label">預覽</label>
                    <textarea class="form-control" rows="3" disabled placeholder="填答者將在此處輸入文字回答"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- 數字題範本 -->
    <div id="template-number" class="question-card">
        <div class="card mb-3">
            <div class="card-header question-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="question-title">數字題</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body question-body">
                <div class="mb-3">
                    <label class="form-label">問題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control question-text" name="questions[0][text]" required>
                    <input type="hidden" class="question-type" name="questions[0][type]" value="number">
                </div>
                <div class="mb-3">
                    <label class="form-label">問題說明 (選填)</label>
                    <textarea class="form-control question-description" name="questions[0][description]"
                        rows="2"></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-required" id="required-0"
                        name="questions[0][required]" checked>
                    <label class="form-check-label" for="required-0">必填題目</label>
                </div>

                <!-- 子問題選項 -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-has-subquestions" id="has-subquestions-0"
                        name="questions[0][has_subquestions]">
                    <label class="form-check-label" for="has-subquestions-0">包含子問題</label>
                </div>

                <!-- 子問題容器 -->
                <div class="subquestions-container" style="display: none;">
                    <label class="form-label">子問題列表</label>
                    <div class="subquestions-list">
                        <!-- 子問題將透過JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-subquestion">
                            <i class="fa fa-plus"></i> 新增子問題
                        </button>
                    </div>
                </div>

                <!-- 數字範圍設定 -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">最小值 (選填)</label>
                        <input type="number" class="form-control" name="questions[0][min]" placeholder="不限制">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">最大值 (選填)</label>
                        <input type="number" class="form-control" name="questions[0][max]" placeholder="不限制">
                    </div>
                </div>

                <!-- 數字題預覽 -->
                <div class="mb-3">
                    <label class="form-label">預覽</label>
                    <input type="number" class="form-control" disabled placeholder="填答者將在此處輸入數字">
                </div>
            </div>
        </div>
    </div>

    <!-- 日期題範本 -->
    <div id="template-date" class="question-card">
        <div class="card mb-3">
            <div class="card-header question-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="question-title">日期題</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body question-body">
                <div class="mb-3">
                    <label class="form-label">問題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control question-text" name="questions[0][text]" required>
                    <input type="hidden" class="question-type" name="questions[0][type]" value="date">
                </div>
                <div class="mb-3">
                    <label class="form-label">問題說明 (選填)</label>
                    <textarea class="form-control question-description" name="questions[0][description]"
                        rows="2"></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-required" id="required-0"
                        name="questions[0][required]" checked>
                    <label class="form-check-label" for="required-0">必填題目</label>
                </div>

                <!-- 子問題選項 -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-has-subquestions" id="has-subquestions-0"
                        name="questions[0][has_subquestions]">
                    <label class="form-check-label" for="has-subquestions-0">包含子問題</label>
                </div>

                <!-- 子問題容器 -->
                <div class="subquestions-container" style="display: none;">
                    <label class="form-label">子問題列表</label>
                    <div class="subquestions-list">
                        <!-- 子問題將透過JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-subquestion">
                            <i class="fa fa-plus"></i> 新增子問題
                        </button>
                    </div>
                </div>

                <!-- 日期範圍設定 -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">最早日期 (選填)</label>
                        <input type="date" class="form-control" name="questions[0][min_date]">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">最晚日期 (選填)</label>
                        <input type="date" class="form-control" name="questions[0][max_date]">
                    </div>
                </div>

                <!-- 日期題預覽 -->
                <div class="mb-3">
                    <label class="form-label">預覽</label>
                    <input type="date" class="form-control" disabled>
                </div>
            </div>
        </div>
    </div>

    <!-- 檔案上傳題範本 -->
    <div id="template-upload" class="question-card">
        <div class="card mb-3">
            <div class="card-header question-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="question-title">檔案上傳</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body question-body">
                <div class="mb-3">
                    <label class="form-label">問題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control question-text" name="questions[0][text]" required>
                    <input type="hidden" class="question-type" name="questions[0][type]" value="upload">
                </div>
                <div class="mb-3">
                    <label class="form-label">問題說明 (選填)</label>
                    <textarea class="form-control question-description" name="questions[0][description]"
                        rows="2"></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-required" id="required-0"
                        name="questions[0][required]">
                    <label class="form-check-label" for="required-0">必填題目</label>
                </div>

                <!-- 子問題選項 -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-has-subquestions" id="has-subquestions-0"
                        name="questions[0][has_subquestions]">
                    <label class="form-check-label" for="has-subquestions-0">包含子問題</label>
                </div>

                <!-- 子問題容器 -->
                <div class="subquestions-container" style="display: none;">
                    <label class="form-label">子問題列表</label>
                    <div class="subquestions-list">
                        <!-- 子問題將透過JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-subquestion">
                            <i class="fa fa-plus"></i> 新增子問題
                        </button>
                    </div>
                </div>

                <!-- 檔案類型設定 -->
                <div class="mb-3">
                    <label class="form-label">允許的檔案類型</label>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="questions[0][allowed_types][]"
                                    value="image" id="allow_image_0" checked>
                                <label class="form-check-label" for="allow_image_0">圖片 (JPG, PNG, GIF)</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="questions[0][allowed_types][]"
                                    value="document" id="allow_doc_0" checked>
                                <label class="form-check-label" for="allow_doc_0">文件 (DOC, PDF)</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="questions[0][allowed_types][]"
                                    value="spreadsheet" id="allow_excel_0">
                                <label class="form-check-label" for="allow_excel_0">試算表 (XLS, CSV)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 檔案大小限制 -->
                <div class="mb-3">
                    <label class="form-label">檔案大小限制 (MB)</label>
                    <select class="form-control" name="questions[0][max_size]">
                        <option value="1">1 MB</option>
                        <option value="2">2 MB</option>
                        <option value="5" selected>5 MB</option>
                        <option value="10">10 MB</option>
                    </select>
                </div>

                <!-- 檔案上傳預覽 -->
                <div class="mb-3">
                    <label class="form-label">預覽</label>
                    <input type="file" class="form-control" disabled>
                    <small class="text-muted mt-1">支援的檔案類型: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX (最大 5MB)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 評分題範本 -->
    <div id="template-rating" class="question-card">
        <div class="card mb-3">
            <div class="card-header question-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="question-title">評分題</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body question-body">
                <div class="mb-3">
                    <label class="form-label">問題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control question-text" name="questions[0][text]" required>
                    <input type="hidden" class="question-type" name="questions[0][type]" value="rating">
                </div>
                <div class="mb-3">
                    <label class="form-label">問題說明 (選填)</label>
                    <textarea class="form-control question-description" name="questions[0][description]"
                        rows="2"></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-required" id="required-0"
                        name="questions[0][required]" checked>
                    <label class="form-check-label" for="required-0">必填題目</label>
                </div>

                <!-- 子問題選項 -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-has-subquestions" id="has-subquestions-0"
                        name="questions[0][has_subquestions]">
                    <label class="form-check-label" for="has-subquestions-0">包含子問題</label>
                </div>

                <!-- 子問題容器 -->
                <div class="subquestions-container" style="display: none;">
                    <label class="form-label">子問題列表</label>
                    <div class="subquestions-list">
                        <!-- 子問題將透過JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-subquestion">
                            <i class="fa fa-plus"></i> 新增子問題
                        </button>
                    </div>
                </div>

                <!-- 評分選項設定 -->
                <div class="mb-3">
                    <label class="form-label">評分選項</label>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label">評分級數</label>
                            <select class="form-control rating-scale" name="questions[0][rating_scale]">
                                <option value="5" selected>5 分</option>
                                <option value="10">10 分</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">顯示方式</label>
                            <select class="form-control rating-type" name="questions[0][rating_type]">
                                <option value="star" selected>星星</option>
                                <option value="number">數字</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 評分選項 -->
                <div class="mb-3">
                    <label class="form-label">評分選項標籤</label>
                    <div class="options-container rating-options">
                        <div class="row mb-3">
                            <div class="col-2 text-center">
                                <input type="hidden" name="questions[0][options][0][value]" value="1">
                                <div class="fs-4">1</div>
                                <input type="text" class="form-control" name="questions[0][options][0][text]"
                                    placeholder="低">
                            </div>
                            <div class="col-2 text-center">
                                <input type="hidden" name="questions[0][options][1][value]" value="2">
                                <div class="fs-4">2</div>
                                <input type="text" class="form-control" name="questions[0][options][1][text]"
                                    placeholder="">
                            </div>
                            <div class="col-2 text-center">
                                <input type="hidden" name="questions[0][options][2][value]" value="3">
                                <div class="fs-4">3</div>
                                <input type="text" class="form-control" name="questions[0][options][2][text]"
                                    placeholder="中">
                            </div>
                            <div class="col-2 text-center">
                                <input type="hidden" name="questions[0][options][3][value]" value="4">
                                <div class="fs-4">4</div>
                                <input type="text" class="form-control" name="questions[0][options][3][text]"
                                    placeholder="">
                            </div>
                            <div class="col-2 text-center">
                                <input type="hidden" name="questions[0][options][4][value]" value="5">
                                <div class="fs-4">5</div>
                                <input type="text" class="form-control" name="questions[0][options][4][text]"
                                    placeholder="高">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 評分題預覽 -->
                <div class="mb-3">
                    <label class="form-label">預覽</label>
                    <div class="rating-preview d-flex justify-content-between">
                        <div class="text-center">
                            <div><i class="fa fa-star-o fa-2x"></i></div>
                            <div>1</div>
                        </div>
                        <div class="text-center">
                            <div><i class="fa fa-star-o fa-2x"></i></div>
                            <div>2</div>
                        </div>
                        <div class="text-center">
                            <div><i class="fa fa-star-o fa-2x"></i></div>
                            <div>3</div>
                        </div>
                        <div class="text-center">
                            <div><i class="fa fa-star-o fa-2x"></i></div>
                            <div>4</div>
                        </div>
                        <div class="text-center">
                            <div><i class="fa fa-star-o fa-2x"></i></div>
                            <div>5</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 下拉選單範本 -->
    <div id="template-dropdown" class="question-card">
        <div class="card mb-3">
            <div class="card-header question-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="question-title">下拉選單</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body question-body">
                <div class="mb-3">
                    <label class="form-label">問題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control question-text" name="questions[0][text]" required>
                    <input type="hidden" class="question-type" name="questions[0][type]" value="dropdown">
                </div>
                <div class="mb-3">
                    <label class="form-label">問題說明 (選填)</label>
                    <textarea class="form-control question-description" name="questions[0][description]"
                        rows="2"></textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-required" id="required-0"
                        name="questions[0][required]" checked>
                    <label class="form-check-label" for="required-0">必填題目</label>
                </div>

                <!-- 子問題選項 -->
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input question-has-subquestions" id="has-subquestions-0"
                        name="questions[0][has_subquestions]">
                    <label class="form-check-label" for="has-subquestions-0">包含子問題</label>
                </div>

                <!-- 子問題容器 -->
                <div class="subquestions-container" style="display: none;">
                    <label class="form-label">子問題列表</label>
                    <div class="subquestions-list">
                        <!-- 子問題將透過JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-subquestion">
                            <i class="fa fa-plus"></i> 新增子問題
                        </button>
                    </div>
                </div>

                <!-- 選項列表 -->
                <div class="mb-3">
                    <label class="form-label">選項</label>
                    <div class="options-container">
                        <!-- 選項將由JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-option">
                            <i class="fa fa-plus"></i> 新增選項
                        </button>
                    </div>
                </div>

                <!-- 下拉選單預覽 -->
                <div class="mb-3">
                    <label class="form-label">預覽</label>
                    <select class="form-select" disabled>
                        <option value="">-- 請選擇 --</option>
                        <option value="1">選項1</option>
                        <option value="2">選項2</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- 選項範本 (單選/多選) -->
    <div id="template-option" class="option-item mb-2">
        <div class="input-group">
            <input type="text" class="form-control option-text" name="questions[0][options][0][text]" placeholder="選項文字"
                required>
            <button type="button" class="btn btn-outline-danger delete-option">
                <i class="fa fa-times"></i>
            </button>
        </div>
    </div>

    <!-- 其他選項範本 -->
    <div id="template-other-option" class="option-item mb-2">
        <div class="input-group">
            <input type="text" class="form-control option-text" name="questions[0][options][0][text]" value="其他"
                required>
            <input type="hidden" name="questions[0][options][0][is_other]" value="1">
            <button type="button" class="btn btn-outline-danger delete-option">
                <i class="fa fa-times"></i>
            </button>
        </div>
    </div>

    <!-- 子問題範本 -->
    <div id="template-subquestion" class="subquestion-item mb-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center">
                <i class="fa fa-bars subquestion-handle me-2"></i>
                <input type="text" class="form-control subquestion-text" name="questions[0][subquestions][0][text]"
                    placeholder="子問題文字" required>
            </div>
            <div class="subquestion-actions">
                <button type="button" class="btn btn-sm btn-outline-danger delete-subquestion">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
    </div>


    <!-- 矩陣單選題範本 -->
<div id="template-matrix_single" class="question-card">
    <div class="card mb-3">
        <div class="card-header question-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="question-title">矩陣單選題</div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary toggle-question">
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-question">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body question-body">
            <div class="mb-3">
                <label class="form-label">問題 <span class="text-danger">*</span></label>
                <input type="text" class="form-control question-text" name="questions[0][text]" required>
                <input type="hidden" class="question-type" name="questions[0][type]" value="matrix_single">
            </div>
            <div class="mb-3">
                <label class="form-label">問題說明 (選填)</label>
                <textarea class="form-control question-description" name="questions[0][description]" rows="2"></textarea>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input question-required" id="required-0" name="questions[0][required]" checked>
                <label class="form-check-label" for="required-0">必填題目</label>
            </div>
            
            <!-- 矩陣選項設定 -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">選項 (欄)</label>
                    <div class="options-container">
                        <!-- 選項將由JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-option">
                            <i class="fa fa-plus"></i> 新增選項
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">項目 (列)</label>
                    <div class="matrix-items-container">
                        <!-- 矩陣項目將由JavaScript動態添加 -->
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary add-matrix-item">
                            <i class="fa fa-plus"></i> 新增項目
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- 隨機排序設定 -->
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" name="questions[0][random_items]" id="random_items_0">
                <label class="form-check-label" for="random_items_0">隨機排序項目</label>
            </div>
            
            <!-- 矩陣題預覽 -->
            <div class="mb-3">
                <label class="form-label">預覽</label>
                <div class="matrix-preview p-2 border rounded bg-light">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th class="text-center">選項1</th>
                                    <th class="text-center">選項2</th>
                                    <th class="text-center">選項3</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>項目1</td>
                                    <td class="text-center"><input type="radio" disabled></td>
                                    <td class="text-center"><input type="radio" disabled></td>
                                    <td class="text-center"><input type="radio" disabled></td>
                                </tr>
                                <tr>
                                    <td>項目2</td>
                                    <td class="text-center"><input type="radio" disabled></td>
                                    <td class="text-center"><input type="radio" disabled></td>
                                    <td class="text-center"><input type="radio" disabled></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 矩陣項目範本 -->
<div id="template-matrix-item" class="matrix-item mb-2">
    <div class="input-group">
        <input type="text" class="form-control matrix-item-text" name="questions[0][matrix_items][0][text]" placeholder="項目文字" required>
        <button type="button" class="btn btn-outline-danger delete-matrix-item">
            <i class="fa fa-times"></i>
        </button>
    </div>
</div>
</div>