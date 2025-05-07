// 問題計數器
let questionCounter = 0;

// DOM加載完成後初始化
document.addEventListener('DOMContentLoaded', function () {
    // 如果有現有問題，載入它們
    if (typeof existingQuestions !== 'undefined' && existingQuestions.length > 0) {
        existingQuestions.forEach(function (question) {
            const type = question.question_type;
            const typeLabel = getQuestionTypeLabel(type);
            addQuestion(type, typeLabel, question);
        });
    }


    // 初始化 TinyMCE
    if (document.getElementById('survey-description')) {
        tinymce.init({
            selector: '#survey-description',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
            // 啟用圖片上傳功能
            images_upload_url: 'survey/api/upload_image.php',
            automatic_uploads: true,
            images_reuse_filename: true,
            // 其他設定...
            height: 300
        });
    }

    // 初始化問題排序
    initSortable();

    // 綁定範本載入按鈕
    document.getElementById('load-template-btn')?.addEventListener('click', loadTemplate);

    // 綁定新增問題按鈕
    bindAddQuestionButtons();

    // 綁定表單驗證
    bindFormValidation();

    // 初始化子問題相關功能
    initSubquestionFeatures();
});

// 綁定新增問題按鈕
function bindAddQuestionButtons() {
    const addButtons = document.querySelectorAll('.dropdown-item');
    addButtons.forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const type = this.getAttribute('data-type');
            const label = this.textContent.trim();
            if (type) {
                addQuestion(type, label);
            }
        });
    });
}

// 綁定表單驗證
function bindFormValidation() {
    document.getElementById('survey-form')?.addEventListener('submit', function (event) {
        // 檢查問卷標題
        const title = document.querySelector('input[name="title"]').value;
        if (!title) {
            event.preventDefault();
            alert('請輸入問卷標題');
            return;
        }

        // 檢查是否有問題
        const questions = document.querySelectorAll('.question-item');
        if (questions.length === 0) {
            event.preventDefault();
            alert('請至少添加一個問題');
            return;
        }

        // 檢查每個問題的標題和選項
        let hasError = false;
        questions.forEach(function (question, index) {
            const questionText = question.querySelector('.question-text').value;
            if (!questionText) {
                hasError = true;
                alert(`第 ${index + 1} 個問題缺少標題`);
                return;
            }

            const type = question.querySelector('.question-type').value;
            if (['single_choice', 'multiple_choice', 'dropdown'].includes(type)) {
                const options = question.querySelectorAll('.option-item');
                if (options.length < 1) {
                    hasError = true;
                    alert(`第 ${index + 1} 個問題 (${questionText}) 需要至少一個選項`);
                    return;
                }

                options.forEach(function (option, optionIndex) {
                    const optionText = option.querySelector('.option-text').value;
                    if (!optionText) {
                        hasError = true;
                        alert(`第 ${index + 1} 個問題 (${questionText}) 的第 ${optionIndex + 1} 個選項缺少文字`);
                        return;
                    }
                });
            }
            
            // 檢查矩陣單選題
            if (type === 'matrix_single') {
                // 檢查矩陣選項
                const options = question.querySelectorAll('.options-container .option-item');
                if (options.length < 1) {
                    hasError = true;
                    alert(`第 ${index + 1} 個問題 (${questionText}) 需要至少一個選項`);
                    return;
                }
                
                // 檢查矩陣項目
                const items = question.querySelectorAll('.matrix-items-container .matrix-item');
                if (items.length < 1) {
                    hasError = true;
                    alert(`第 ${index + 1} 個問題 (${questionText}) 需要至少一個項目`);
                    return;
                }
                
                // 檢查選項文字
                options.forEach(function (option, optionIndex) {
                    const optionText = option.querySelector('.option-text').value;
                    if (!optionText) {
                        hasError = true;
                        alert(`第 ${index + 1} 個問題 (${questionText}) 的第 ${optionIndex + 1} 個選項缺少文字`);
                        return;
                    }
                });
                
                // 檢查項目文字
                items.forEach(function (item, itemIndex) {
                    const itemText = item.querySelector('.matrix-item-text').value;
                    if (!itemText) {
                        hasError = true;
                        alert(`第 ${index + 1} 個問題 (${questionText}) 的第 ${itemIndex + 1} 個項目缺少文字`);
                        return;
                    }
                });
                
                // 檢查隨機排序設定
                const randomItems = question.querySelector('input[name^="questions"][name$="[random_items]"]');
                if (randomItems && items.length < 2 && randomItems.checked) {
                    hasError = true;
                    alert(`第 ${index + 1} 個問題 (${questionText}) 啟用了隨機排序但項目數量不足，請至少添加兩個項目`);
                    return;
                }
            }

            // 檢查子問題
            const hasSubquestions = question.querySelector('.question-has-subquestions')?.checked;
            if (hasSubquestions) {
                const subquestions = question.querySelectorAll('.subquestion-item');
                if (subquestions.length === 0) {
                    hasError = true;
                    alert(`第 ${index + 1} 個問題 (${questionText}) 啟用了子問題但未添加任何子問題`);
                    return;
                }

                subquestions.forEach(function (subquestion, subIndex) {
                    const subquestionText = subquestion.querySelector('.subquestion-text').value;
                    if (!subquestionText) {
                        hasError = true;
                        alert(`第 ${index + 1} 個問題 (${questionText}) 的第 ${subIndex + 1} 個子問題缺少文字`);
                        return;
                    }
                });
            }
            
            // 檢查評分題
            if (type === 'rating') {
                const ratingOptions = question.querySelectorAll('.rating-options input[type="text"]');
                let hasEmptyLabels = false;
                
                // 檢查關鍵評分點的標籤（最低分和最高分）
                if (ratingOptions.length > 0) {
                    const firstLabel = ratingOptions[0].value.trim();
                    const lastLabel = ratingOptions[ratingOptions.length - 1].value.trim();
                    
                    if (!firstLabel || !lastLabel) {
                        hasEmptyLabels = true;
                    }
                }
                
                if (hasEmptyLabels) {
                    hasError = true;
                    alert(`第 ${index + 1} 個問題 (${questionText}) 的評分題應至少填寫最低分和最高分的標籤`);
                    return;
                }
            }
            
            // 檢查數字題
            if (type === 'number') {
                const minInput = question.querySelector('input[name^="questions"][name$="[min]"]');
                const maxInput = question.querySelector('input[name^="questions"][name$="[max]"]');
                
                if (minInput && maxInput && minInput.value && maxInput.value) {
                    const min = parseFloat(minInput.value);
                    const max = parseFloat(maxInput.value);
                    
                    if (min >= max) {
                        hasError = true;
                        alert(`第 ${index + 1} 個問題 (${questionText}) 的最小值必須小於最大值`);
                        return;
                    }
                }
            }
            
            // 檢查日期題
            if (type === 'date') {
                const minDateInput = question.querySelector('input[name^="questions"][name$="[min_date]"]');
                const maxDateInput = question.querySelector('input[name^="questions"][name$="[max_date]"]');
                
                if (minDateInput && maxDateInput && minDateInput.value && maxDateInput.value) {
                    const minDate = new Date(minDateInput.value);
                    const maxDate = new Date(maxDateInput.value);
                    
                    if (minDate >= maxDate) {
                        hasError = true;
                        alert(`第 ${index + 1} 個問題 (${questionText}) 的最早日期必須早於最晚日期`);
                        return;
                    }
                }
            }
        });

        if (hasError) {
            event.preventDefault();
        }
    });
}

// 獲取問題類型標籤
function getQuestionTypeLabel(type) {
    const labels = {
        'single_choice': '單選題',
        'multiple_choice': '多選題',
        'text': '文字題',
        'number': '數字題',
        'date': '日期題',
        'upload': '檔案上傳',
        'rating': '評分題',
        'dropdown': '下拉選單',
        'matrix_single' : '矩陣單選題'
    };
    return labels[type] || type;
}

// 初始化拖曳排序
function initSortable() {
    const container = document.getElementById('questions-container');
    if (container && typeof Sortable !== 'undefined') {
        new Sortable(container, {
            animation: 150,
            handle: '.question-header',
            ghostClass: 'question-ghost',
            onEnd: function () {
                updateQuestionIndexes();
            }
        });
    }

    // 初始化子問題排序（若有）
    initSubquestionSortable();
}

// 初始化子問題排序
function initSubquestionSortable() {
    const containers = document.querySelectorAll('.subquestions-list');
    if (containers.length && typeof Sortable !== 'undefined') {
        containers.forEach(function (container) {
            new Sortable(container, {
                animation: 150,
                handle: '.subquestion-handle',
                ghostClass: 'subquestion-ghost',
                onEnd: function () {
                    const questionCard = container.closest('.question-item');
                    const questionIndex = Array.from(document.querySelectorAll('.question-item')).indexOf(questionCard);
                    updateSubquestionIndexes(container, questionIndex);
                }
            });
        });
    }
}

// 更新問題索引
function updateQuestionIndexes() {
    const questions = document.querySelectorAll('.question-item');
    questions.forEach(function (question, index) {
        const inputs = question.querySelectorAll('input, textarea, select');
        inputs.forEach(function (input) {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/questions\[\d+\]/, `questions[${index}]`));
            }
        });

        const requiredCheckbox = question.querySelector('.question-required');
        if (requiredCheckbox) {
            requiredCheckbox.id = `required-${index}`;
            const label = question.querySelector(`label[for^="required-"]`);
            if (label) {
                label.setAttribute('for', `required-${index}`);
            }
        }

        // 更新子問題相關元素的索引
        const hasSubquestionsCheckbox = question.querySelector('.question-has-subquestions');
        if (hasSubquestionsCheckbox) {
            hasSubquestionsCheckbox.id = `has-subquestions-${index}`;
            const label = question.querySelector(`label[for^="has-subquestions-"]`);
            if (label) {
                label.setAttribute('for', `has-subquestions-${index}`);
            }
        }

        // 更新子問題容器中的索引
        const subquestionsList = question.querySelector('.subquestions-list');
        if (subquestionsList) {
            updateSubquestionIndexes(subquestionsList, index);
        }
    });
}

// 添加問題
function addQuestion(type, typeLabel, data = null) {
    // 獲取問題容器
    const container = document.getElementById('questions-container');
    if (!container) return null;

    // 隱藏「無問題」提示
    const noQuestions = document.querySelector('.no-questions');
    if (noQuestions) {
        noQuestions.style.display = 'none';
    }

    // 克隆問題範本
    const template = document.getElementById(`template-${type}`);
    if (!template) {
        console.error(`找不到問題範本: template-${type}`);
        return null;
    }

    const questionCard = template.cloneNode(true);
    questionCard.id = '';
    questionCard.classList.add('question-item');

    // 更新問題標題
    const titleElement = questionCard.querySelector('.question-title');
    if (titleElement) {
        titleElement.textContent = typeLabel;
    }

    // 設置問題索引
    const inputs = questionCard.querySelectorAll('input, textarea, select');
    inputs.forEach(function (input) {
        const name = input.getAttribute('name');
        if (name) {
            input.setAttribute('name', name.replace(/questions\[\d+\]/, `questions[${questionCounter}]`));
        }

        // 如果有現有數據，設置值
        if (data) {
            if (input.classList.contains('question-text')) {
                input.value = data.question_text;
            } else if (input.classList.contains('question-description')) {
                input.value = data.description;
            } else if (input.classList.contains('question-required')) {
                input.checked = data.is_required == 1;
            } else if (input.classList.contains('question-type')) {
                input.value = data.question_type; // 確保類型設置正確
            } else if (input.classList.contains('question-has-subquestions')) {
                input.checked = data.has_subquestions == 1;
                // 如果已啟用子問題，顯示子問題容器
                if (data.has_subquestions == 1) {
                    const subquestionsContainer = questionCard.querySelector('.subquestions-container');
                    if (subquestionsContainer) {
                        subquestionsContainer.style.display = 'block';
                    }
                }
            }
        }
    });

    // 更新必填選項ID
    const requiredCheckbox = questionCard.querySelector('.question-required');
    if (requiredCheckbox) {
        requiredCheckbox.id = `required-${questionCounter}`;
        const label = questionCard.querySelector(`label[for^="required-"]`);
        if (label) {
            label.setAttribute('for', `required-${questionCounter}`);
        }
    }

    // 更新子問題選項ID
    const hasSubquestionsCheckbox = questionCard.querySelector('.question-has-subquestions');
    if (hasSubquestionsCheckbox) {
        hasSubquestionsCheckbox.id = `has-subquestions-${questionCounter}`;
        const label = questionCard.querySelector(`label[for^="has-subquestions-"]`);
        if (label) {
            label.setAttribute('for', `has-subquestions-${questionCounter}`);
        }
    }

    // 添加選項（如果適用）
    if (['single_choice', 'multiple_choice', 'dropdown'].includes(type)) {
        const optionsContainer = questionCard.querySelector('.options-container');
        if (optionsContainer) {
            // 如果有現有選項數據，添加它們
            if (data && data.options && data.options.length > 0) {
                data.options.forEach(function (option, optionIndex) {
                    if (option.is_other == 1) {
                        addOtherOption(optionsContainer, questionCounter, optionIndex, option.option_text);
                    } else {
                        addOption(optionsContainer, questionCounter, optionIndex, option.option_text);
                    }
                });
            } else {
                // 添加默認選項
                addOption(optionsContainer, questionCounter, 0, '選項 1');
                addOption(optionsContainer, questionCounter, 1, '選項 2');
            }
        }
    }

    // 評分題的選項處理
    if (type === 'rating' && data && data.options && data.options.length > 0) {
        const ratingOptions = questionCard.querySelectorAll('.rating-options input[type="text"]');
        data.options.forEach((option, index) => {
            if (index < ratingOptions.length) {
                ratingOptions[index].value = option.option_text || '';
            }
        });
    }

    // 添加子問題（如果有）
    if (data && data.subquestions && data.subquestions.length > 0) {
        const subquestionsList = questionCard.querySelector('.subquestions-list');
        if (subquestionsList) {
            data.subquestions.forEach(function (subquestion, subIndex) {
                addSubquestion(subquestionsList, questionCounter, subIndex, subquestion.subquestion_text);
            });
        }
    }

    // 如果是矩陣題，添加預設項目
    if (type === 'matrix_single') {
        const matrixItemsContainer = questionCard.querySelector('.matrix-items-container');
        if (matrixItemsContainer) {
            // 如果有現有數據，添加它們
            if (data && data.matrix_items && data.matrix_items.length > 0) {
                data.matrix_items.forEach(function (item, itemIndex) {
                    addMatrixItem(matrixItemsContainer, questionCounter, itemIndex, item.item_text);
                });
            } else {
                // 添加默認項目
                addMatrixItem(matrixItemsContainer, questionCounter, 0, '項目 1');
                addMatrixItem(matrixItemsContainer, questionCounter, 1, '項目 2');
            }
        }

        // 處理選項
        const optionsContainer = questionCard.querySelector('.options-container');
        if (optionsContainer) {
            // 如果有現有選項數據，添加它們
            if (data && data.options && data.options.length > 0) {
                data.options.forEach(function (option, optionIndex) {
                    addOption(optionsContainer, questionCounter, optionIndex, option.option_text);
                });
            } else {
                // 添加默認選項
                addOption(optionsContainer, questionCounter, 0, '選項 1');
                addOption(optionsContainer, questionCounter, 1, '選項 2');
                addOption(optionsContainer, questionCounter, 2, '選項 3');
            }
        }
    }

    // 綁定事件
    bindQuestionEvents(questionCard);

    // 為矩陣題綁定特殊事件
    if (type === 'matrix_single') {
        bindMatrixQuestionEvents(questionCard);
    }

    // 綁定子問題事件
    bindSubquestionEvents(questionCard);



    // 添加到容器
    container.appendChild(questionCard);

    // 增加計數器
    questionCounter++;

    // 更新索引
    updateQuestionIndexes();

    // 初始化新添加問題的子問題排序
    const subquestionsList = questionCard.querySelector('.subquestions-list');
    if (subquestionsList && typeof Sortable !== 'undefined') {
        new Sortable(subquestionsList, {
            animation: 150,
            handle: '.subquestion-handle',
            ghostClass: 'subquestion-ghost',
            onEnd: function () {
                const questionIndex = Array.from(document.querySelectorAll('.question-item')).indexOf(questionCard);
                updateSubquestionIndexes(subquestionsList, questionIndex);
            }
        });
    }

    return questionCard;
}

// 添加選項
function addOption(container, questionIndex, optionIndex, optionText = '') {
    if (!container) return null;

    // 克隆選項範本
    const template = document.getElementById('template-option');
    if (!template) {
        console.error('找不到選項範本: template-option');
        return null;
    }

    const option = template.cloneNode(true);
    option.id = '';
    option.classList.add('option-item');

    // 更新選項索引
    const input = option.querySelector('.option-text');
    if (input) {
        input.setAttribute('name', `questions[${questionIndex}][options][${optionIndex}][text]`);
        input.value = optionText;
    }

    // 添加刪除按鈕事件
    const deleteBtn = option.querySelector('.delete-option');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            container.removeChild(option);
            updateOptionIndexes(container, questionIndex);
        });
    }

    // 添加到容器
    container.appendChild(option);

    return option;
}

// 添加"其他"選項
function addOtherOption(container, questionIndex, optionIndex, optionText = '其他') {
    if (!container) return null;

    // 克隆其他選項範本
    const template = document.getElementById('template-other-option');
    if (!template) {
        console.error('找不到其他選項範本: template-other-option');
        return null;
    }

    const option = template.cloneNode(true);
    option.id = '';
    option.classList.add('option-item');

    // 更新選項索引
    const input = option.querySelector('.option-text');
    if (input) {
        input.setAttribute('name', `questions[${questionIndex}][options][${optionIndex}][text]`);
        input.value = optionText;
    }

    const isOtherInput = option.querySelector('input[type="hidden"]');
    if (isOtherInput) {
        isOtherInput.setAttribute('name', `questions[${questionIndex}][options][${optionIndex}][is_other]`);
    }

    // 添加刪除按鈕事件
    const deleteBtn = option.querySelector('.delete-option');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            container.removeChild(option);
            updateOptionIndexes(container, questionIndex);
        });
    }

    // 添加到容器
    container.appendChild(option);

    return option;
}

// 更新選項索引
function updateOptionIndexes(container, questionIndex) {
    if (!container) return;

    const options = container.querySelectorAll('.option-item');
    options.forEach(function (option, index) {
        const textInput = option.querySelector('.option-text');
        if (textInput) {
            textInput.setAttribute('name', `questions[${questionIndex}][options][${index}][text]`);
        }

        const isOtherInput = option.querySelector('input[type="hidden"]');
        if (isOtherInput) {
            isOtherInput.setAttribute('name', `questions[${questionIndex}][options][${index}][is_other]`);
        }
    });
}

// 綁定問題卡片事件
function bindQuestionEvents(questionCard) {
    if (!questionCard) return;

    // 問題折疊/展開
    const toggleBtn = questionCard.querySelector('.toggle-question');
    const questionBody = questionCard.querySelector('.question-body');

    if (toggleBtn && questionBody) {
        toggleBtn.addEventListener('click', function () {
            const icon = this.querySelector('i');
            if (questionBody.style.display === 'none') {
                questionBody.style.display = 'block';
                if (icon) {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            } else {
                questionBody.style.display = 'none';
                if (icon) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            }
        });
    }

    // 刪除問題
    const deleteBtn = questionCard.querySelector('.delete-question');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            if (confirm('確定要刪除此問題嗎？')) {
                questionCard.remove();
                updateQuestionIndexes();

                // 如果沒有問題了，顯示「無問題」提示
                const questions = document.querySelectorAll('.question-item');
                if (questions.length === 0) {
                    const noQuestions = document.querySelector('.no-questions');
                    if (noQuestions) {
                        noQuestions.style.display = 'block';
                    }
                }
            }
        });
    }

    // 問題標題更新
    const textInput = questionCard.querySelector('.question-text');
    const titleElement = questionCard.querySelector('.question-title');
    const typeElement = questionCard.querySelector('.question-type');

    if (textInput && titleElement && typeElement) {
        const typeLabel = getQuestionTypeLabel(typeElement.value);

        textInput.addEventListener('input', function () {
            if (this.value) {
                titleElement.textContent = `${typeLabel} - ${this.value}`;
            } else {
                titleElement.textContent = typeLabel;
            }
        });
    }

    // 添加選項按鈕
    const addOptionBtn = questionCard.querySelector('.add-option');
    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', function () {
            const optionsContainer = questionCard.querySelector('.options-container');
            if (optionsContainer) {
                const options = optionsContainer.querySelectorAll('.option-item');
                const questionIndex = Array.from(document.querySelectorAll('.question-item')).indexOf(questionCard);

                addOption(optionsContainer, questionIndex, options.length);
            }
        });
    }

    // 添加其他選項按鈕
    const addOtherOptionBtn = questionCard.querySelector('.add-other-option');
    if (addOtherOptionBtn) {
        addOtherOptionBtn.addEventListener('click', function () {
            const optionsContainer = questionCard.querySelector('.options-container');
            if (optionsContainer) {
                // 檢查是否已經有「其他」選項
                const hasOtherOption = Array.from(optionsContainer.querySelectorAll('input[type="hidden"]')).some(input =>
                    input.name && input.name.includes('[is_other]') && input.value === '1'
                );

                if (hasOtherOption) {
                    alert('已經添加了「其他」選項');
                    return;
                }

                const options = optionsContainer.querySelectorAll('.option-item');
                const questionIndex = Array.from(document.querySelectorAll('.question-item')).indexOf(questionCard);

                addOtherOption(optionsContainer, questionIndex, options.length);
            }
        });
    }
}

// 載入問卷範本
function loadTemplate() {
    const templateSelect = document.getElementById('template-select');
    if (!templateSelect) return;

    const templateId = templateSelect.value;

    if (!templateId) {
        alert('請選擇一個範本');
        return;
    }

    // 提示確認
    if (!confirm('載入範本將會清除目前編輯的內容，確定要繼續嗎？')) {
        return;
    }

    // 重定向到帶有範本ID的URL
    window.location.href = `?page=survey/create&template_id=${templateId}`;
}

// 子問題功能初始化
function initSubquestionFeatures() {
    console.log('初始化子問題功能');

    // 綁定子問題顯示/隱藏
    const checkboxes = document.querySelectorAll('.question-has-subquestions');
    console.log('找到子問題勾選框數量:', checkboxes.length);

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            console.log('子問題勾選框狀態改變:', this.checked);
            const questionCard = this.closest('.question-item');
            const subquestionsContainer = questionCard.querySelector('.subquestions-container');

            if (subquestionsContainer) {
                console.log('找到子問題容器, 顯示狀態修改前:', subquestionsContainer.style.display);
                subquestionsContainer.style.display = this.checked ? 'block' : 'none';
                console.log('子問題容器顯示狀態修改後:', subquestionsContainer.style.display);
            } else {
                console.error('找不到子問題容器');
            }
        });
    });

    // 綁定添加子問題按鈕
    const buttons = document.querySelectorAll('.add-subquestion');
    console.log('找到添加子問題按鈕數量:', buttons.length);

    buttons.forEach(button => {
        button.addEventListener('click', function () {
            console.log('點擊添加子問題按鈕');
            const questionCard = this.closest('.question-item');
            const subquestionsList = questionCard.querySelector('.subquestions-list');
            const questionIndex = Array.from(document.querySelectorAll('.question-item')).indexOf(questionCard);

            console.log('子問題列表元素:', subquestionsList);
            console.log('問題索引:', questionIndex);

            if (subquestionsList) {
                const subquestions = subquestionsList.querySelectorAll('.subquestion-item');
                console.log('現有子問題數量:', subquestions.length);

                // 檢查子問題範本是否存在並可見
                const template = document.getElementById('template-subquestion');
                console.log('子問題範本存在:', !!template);

                if (template) {
                    // 確保子問題範本可用
                    if (template.parentElement && template.parentElement.style.display === 'none') {
                        console.log('子問題範本的父元素被隱藏，臨時修改其顯示狀態');
                        const originalDisplayStyle = template.parentElement.style.display;
                        template.parentElement.style.display = 'block';

                        // 新增子問題
                        const newSubquestion = addSubquestion(subquestionsList, questionIndex, subquestions.length);
                        console.log('成功新增子問題:', !!newSubquestion);

                        // 恢復父元素原本的顯示狀態
                        template.parentElement.style.display = originalDisplayStyle;
                    } else {
                        // 正常新增子問題
                        const newSubquestion = addSubquestion(subquestionsList, questionIndex, subquestions.length);
                        console.log('成功新增子問題:', !!newSubquestion);
                    }
                } else {
                    console.error('找不到子問題範本');
                }
            } else {
                console.error('找不到子問題列表元素');
            }
        });
    });
}

// 添加子問題 - 修改版
function addSubquestion(container, questionIndex, subquestionIndex, subquestionText = '') {
    console.log('添加子問題:', questionIndex, subquestionIndex, subquestionText);

    // 克隆子問題範本
    const template = document.getElementById('template-subquestion');
    if (!template) {
        console.error('找不到子問題範本: template-subquestion');
        return null;
    }

    // 臨時移除範本的 display:none 屬性
    let templateParentDisplayWasNone = false;
    if (template.parentElement && template.parentElement.style.display === 'none') {
        templateParentDisplayWasNone = true;
        template.parentElement.style.display = '';
    }

    const subquestion = template.cloneNode(true);

    // 恢復範本的 display:none 屬性
    if (templateParentDisplayWasNone) {
        template.parentElement.style.display = 'none';
    }

    subquestion.id = '';
    subquestion.classList.add('subquestion-item');

    // 更新子問題索引
    const input = subquestion.querySelector('.subquestion-text');
    if (input) {
        input.setAttribute('name', `questions[${questionIndex}][subquestions][${subquestionIndex}][text]`);
        input.value = subquestionText;
        console.log('設置子問題文字:', input.value);
    }

    // 添加刪除按鈕事件
    const deleteBtn = subquestion.querySelector('.delete-subquestion');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            console.log('刪除子問題');
            container.removeChild(subquestion);
            updateSubquestionIndexes(container, questionIndex);
        });
    }

    // 確保子問題容器可見
    subquestion.style.display = 'block';

    // 添加到容器
    container.appendChild(subquestion);
    console.log('子問題已添加到容器');

    return subquestion;
}

// 綁定子問題事件
function bindSubquestionEvents(questionCard) {
    // 子問題顯示/隱藏事件
    const hasSubquestionsCheckbox = questionCard.querySelector('.question-has-subquestions');
    if (hasSubquestionsCheckbox) {
        hasSubquestionsCheckbox.addEventListener('change', function () {
            const subquestionsContainer = questionCard.querySelector('.subquestions-container');
            if (subquestionsContainer) {
                subquestionsContainer.style.display = this.checked ? 'block' : 'none';
            }
        });
    }

    // 添加子問題事件
    const addSubquestionBtn = questionCard.querySelector('.add-subquestion');
    if (addSubquestionBtn) {
        addSubquestionBtn.addEventListener('click', function () {
            const subquestionsList = questionCard.querySelector('.subquestions-list');
            const questionIndex = Array.from(document.querySelectorAll('.question-item')).indexOf(questionCard);

            if (subquestionsList) {
                const subquestions = subquestionsList.querySelectorAll('.subquestion-item');
                addSubquestion(subquestionsList, questionIndex, subquestions.length);
            }
        });
    }
}

// 更新子問題索引
function updateSubquestionIndexes(container, questionIndex) {
    const subquestions = container.querySelectorAll('.subquestion-item');
    subquestions.forEach(function (subquestion, index) {
        const input = subquestion.querySelector('.subquestion-text');
        if (input) {
            input.setAttribute('name', `questions[${questionIndex}][subquestions][${index}][text]`);
        }
    });
}

// 添加矩陣項目
function addMatrixItem(container, questionIndex, itemIndex, itemText = '') {
    if (!container) return null;

    // 克隆矩陣項目範本
    const template = document.getElementById('template-matrix-item');
    if (!template) {
        console.error('找不到矩陣項目範本: template-matrix-item');
        return null;
    }

    const item = template.cloneNode(true);
    item.id = '';
    item.classList.add('matrix-item');

    // 更新項目索引
    const input = item.querySelector('.matrix-item-text');
    if (input) {
        input.setAttribute('name', `questions[${questionIndex}][matrix_items][${itemIndex}][text]`);
        input.value = itemText;
    }

    // 添加刪除按鈕事件
    const deleteBtn = item.querySelector('.delete-matrix-item');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            container.removeChild(item);
            updateMatrixItemIndexes(container, questionIndex);
        });
    }

    // 添加到容器
    container.appendChild(item);

    return item;
}

// 更新矩陣項目索引
function updateMatrixItemIndexes(container, questionIndex) {
    if (!container) return;

    const items = container.querySelectorAll('.matrix-item');
    items.forEach(function (item, index) {
        const textInput = item.querySelector('.matrix-item-text');
        if (textInput) {
            textInput.setAttribute('name', `questions[${questionIndex}][matrix_items][${index}][text]`);
        }
    });
}

// 綁定矩陣題事件
function bindMatrixQuestionEvents(questionCard) {
    if (!questionCard) return;

    // 添加矩陣項目按鈕
    const addMatrixItemBtn = questionCard.querySelector('.add-matrix-item');
    if (addMatrixItemBtn) {
        addMatrixItemBtn.addEventListener('click', function () {
            const matrixItemsContainer = questionCard.querySelector('.matrix-items-container');
            if (matrixItemsContainer) {
                const items = matrixItemsContainer.querySelectorAll('.matrix-item');
                const questionIndex = Array.from(document.querySelectorAll('.question-item')).indexOf(questionCard);

                addMatrixItem(matrixItemsContainer, questionIndex, items.length, `項目 ${items.length + 1}`);
            }
        });
    }
}