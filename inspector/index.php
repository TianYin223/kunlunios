<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireLogin();

$currentUser = getCurrentUser();
$message = pullFlashMessage('message');
$error = pullFlashMessage('error');

function getUploadedImageFiles($fieldName = 'images') {
    $files = [];
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]['name'])) {
        return $files;
    }

    $count = count($_FILES[$fieldName]['name']);
    for ($i = 0; $i < $count; $i++) {
        $error = $_FILES[$fieldName]['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => $_FILES[$fieldName]['name'][$i] ?? '',
            'type' => $_FILES[$fieldName]['type'][$i] ?? '',
            'tmp_name' => $_FILES[$fieldName]['tmp_name'][$i] ?? '',
            'error' => $error,
            'size' => $_FILES[$fieldName]['size'][$i] ?? 0,
        ];
    }

    return $files;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $dormitoryNo = trim($_POST['dormitory_no'] ?? '');
    $scoreType = $_POST['score_type'] ?? '';
    $score = floatval($_POST['score'] ?? 0);
    $scoreOptions = getScoreOptionValues();
    $imageFiles = getUploadedImageFiles('images');
    $imageCount = count($imageFiles);
    
    // 使用验证器
    $validator = new Validator($_POST);
    $validator->required('dormitory_no', '请输入宿舍号')
              ->required('score_type', '请选择加分或减分')
              ->in('score_type', ['add', 'subtract'], '打分类型无效');
    
    if ($validator->fails()) {
        $error = $validator->firstError();
    } elseif ($scoreType === 'add') {
        $score = 0;
    } elseif (!isScoreInOptions($score, $scoreOptions)) {
        $error = '请选择系统设置的扣分分值';
    } elseif ($imageCount < 4 || $imageCount > 10) {
        $error = '请上传4到10张图片';
    } else {
        $dormitory = db()->fetch(
            "SELECT * FROM dormitories WHERE dormitory_no = ? AND status = 1",
            [$dormitoryNo]
        );
        
        if (!$dormitory) {
            $error = '宿舍号不存在或已禁用';
            Logger::warning("打分失败: 宿舍不存在 - {$dormitoryNo}");
        } else {
            $dailyLimit = getDailyLimit();
            $todayCount = getTodayScoreCount($dormitory['id']);
            
            if ($todayCount >= $dailyLimit) {
                $error = "该宿舍今日打分次数已达上限({$dailyLimit}次)";
                Logger::warning("打分失败: 超过每日限制 - 宿舍{$dormitoryNo}");
            } else {
                $weeklyMaxScore = getWeeklyMaxScore();

                $currentWeeklyScore = getWeeklyScore($dormitory);
                $currentMonthlyScore = getMonthlyScore($dormitory);

                if ($scoreType === 'add') {
                    $score = 0;
                } else {
                    $maxSubtractable = min($currentWeeklyScore, $currentMonthlyScore);
                    if ($score > $maxSubtractable) {
                        $error = "减分后将低于0分，当前最多可减 {$maxSubtractable} 分";
                    }
                }

                if (!$error) {
                    $actualScore = $scoreType === 'subtract' ? -$score : $score;
                    $newWeeklyScore = $currentWeeklyScore + $actualScore;
                    $newMonthlyScore = $currentMonthlyScore + $actualScore;

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    $maxFileSize = 5 * 1024 * 1024;
                    $mimeToExt = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif',
                    ];
                    $savedImages = [];
                    $uploadDay = date('Ymd');
                    $uploadDir = __DIR__ . '/../uploads/score_images/' . $uploadDay;

                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                        $error = '创建图片目录失败，请联系管理员';
                    }

                    if (!$error) {
                        foreach ($imageFiles as $file) {
                            $check = validateUpload($file, $allowedTypes, $maxFileSize);
                            if (!$check['success']) {
                                $error = $check['message'];
                                break;
                            }

                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $file['tmp_name']);
                            finfo_close($finfo);

                            if (!isset($mimeToExt[$mimeType])) {
                                $error = '仅支持 JPG/PNG/WEBP/GIF 图片';
                                break;
                            }

                            $filename = date('His') . '_' . bin2hex(random_bytes(8)) . '.' . $mimeToExt[$mimeType];
                            $targetPath = $uploadDir . '/' . $filename;
                            $relativePath = 'uploads/score_images/' . $uploadDay . '/' . $filename;

                            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $error = '图片保存失败，请重试';
                                break;
                            }

                            $savedImages[] = $relativePath;
                        }
                    }

                    if ($error && !empty($savedImages)) {
                        foreach ($savedImages as $savedPath) {
                            $fullPath = __DIR__ . '/../' . $savedPath;
                            if (is_file($fullPath)) {
                                @unlink($fullPath);
                            }
                        }
                    }
                    
                    if (!$error) {
                        try {
                        db()->beginTransaction();
                        
                        db()->insert('score_records', [
                            'dormitory_id' => $dormitory['id'],
                            'dormitory_no' => $dormitory['dormitory_no'],
                            'inspector_id' => $currentUser['id'],
                            'inspector_name' => $currentUser['real_name'],
                            'score_type' => $scoreType,
                            'score' => $score,
                            'reason' => '',
                            'images_json' => json_encode($savedImages, JSON_UNESCAPED_UNICODE),
                            'month_year' => getCurrentPeriod()
                        ]);
                        
                        setDormitoryScores($dormitory['id'], $newWeeklyScore, $newMonthlyScore);
                        
                        db()->commit();
                        
                        $message = '打分成功！';
                        Logger::info("打分成功: 宿舍{$dormitoryNo} {$scoreType} {$score}分");
                        auditLog('score', 'dormitory', $dormitory['id'], [
                            'dormitory_no' => $dormitoryNo,
                            'score_type' => $scoreType,
                            'score' => $score,
                            'image_count' => count($savedImages)
                        ]);
                        
                        // 清空表单
                        $_POST = [];
                        } catch (Exception $e) {
                            db()->rollBack();
                            foreach ($savedImages as $savedPath) {
                                $fullPath = __DIR__ . '/../' . $savedPath;
                                if (is_file($fullPath)) {
                                    @unlink($fullPath);
                                }
                            }
                            $error = '打分失败，请重试';
                            Logger::error("打分异常: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    if ($message && !$error) {
        setFlashMessage('message', $message);
        redirect($_SERVER['REQUEST_URI']);
    }
}

$weeklyMaxScore = getWeeklyMaxScore();
$scoreOptions = getScoreOptionValues();
$currentPeriod = getCurrentPeriod();
$currentMonth = getCurrentMonthPeriod();
$currentWeekday = getCurrentWeekdayCn();
$currentDisplayTime = getCurrentDisplayDatetime();
$selectedScoreType = $_POST['score_type'] ?? 'subtract';
$selectedScore = round(floatval($_POST['score'] ?? ($scoreOptions[0] ?? 0.5)), 2);

$pageStyles = <<<CSS
.score-form .btn-submit {
    min-width: 140px;
}
.score-type-group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 10px;
}
.score-type-option {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #dfe3f0;
    border-radius: 8px;
    background: #f9fbff;
    cursor: pointer;
    transition: all 0.2s ease;
}
.score-type-option input[type="radio"] {
    width: 18px;
    height: 18px;
    accent-color: #667eea;
    margin: 0;
}
.score-type-option .score-type-text {
    font-weight: 600;
    font-size: 15px;
    line-height: 1;
}
.score-type-option.add .score-type-text {
    color: #1c9a49;
}
.score-type-option.subtract .score-type-text {
    color: #d94848;
}
.score-type-option.active {
    border-color: #667eea;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.12);
    background: #eef2ff;
}
.score-option-group {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-top: 8px;
}
.score-option {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 42px;
    border: 1px solid #d7deef;
    border-radius: 10px;
    background: #f8fbff;
    cursor: pointer;
    transition: all 0.2s ease;
}
.score-option input[type="radio"] {
    width: 16px;
    height: 16px;
    margin: 0;
}
.score-option span {
    font-weight: 600;
    color: #1f2937;
}
.score-option.active {
    border-color: #2563eb;
    background: #eef4ff;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.14);
}
.add-zero-hint {
    min-height: 42px;
    display: flex;
    align-items: center;
    padding: 0 12px;
    border: 1px solid #d7deef;
    border-radius: 10px;
    background: #f8fbff;
    color: #334155;
    font-weight: 600;
}
.desktop-picker-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 6px;
}
.desktop-picker-row #selectedFilesText {
    color: #666;
}
.mobile-camera-panel {
    display: none;
}
.camera-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.camera-card {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 12px;
    border: 1px dashed #c7d4ea;
    background: #f8fbff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.2s ease;
}
.camera-card.add:hover {
    border-color: #8da8dd;
    background: #eef4ff;
}
.camera-plus {
    display: block;
    font-size: 28px;
    line-height: 1;
    color: #2f5eb8;
    margin-bottom: 4px;
}
.camera-tip {
    display: block;
    font-size: 13px;
    color: #3b4d69;
}
.camera-card-inner {
    text-align: center;
    padding: 10px;
}
.camera-card.filled {
    border: 1px solid #d3dff2;
    background: #fff;
}
.camera-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.camera-index {
    position: absolute;
    left: 8px;
    bottom: 8px;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 999px;
    color: #1e3a8a;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid #bfdbfe;
}
.camera-remove {
    position: absolute;
    right: 8px;
    top: 8px;
    border: none;
    border-radius: 999px;
    padding: 3px 8px;
    background: rgba(15, 23, 42, 0.75);
    color: #fff;
    font-size: 12px;
    cursor: pointer;
}
.camera-remove:hover {
    background: rgba(15, 23, 42, 0.88);
}
.mobile-camera-tools {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.mobile-camera-tools .btn {
    min-height: 34px;
}
@media (max-width: 768px) {
    .score-type-group {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .score-type-option {
        min-height: 46px;
        padding: 9px 10px;
    }
    .score-type-option .score-type-text {
        font-size: 16px;
    }
    .score-option-group {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .score-form .btn-submit {
        width: 100%;
        min-height: 44px;
        font-size: 16px;
    }
    #selectedFilesText {
        font-size: 14px;
        word-break: break-all;
    }
    .desktop-picker-row {
        display: none;
    }
    .mobile-camera-panel {
        display: block;
        margin-top: 6px;
    }
    .mobile-camera-tools {
        justify-content: flex-start;
    }
    .camera-tip {
        font-size: 12px;
    }
}
@media (min-width: 769px) {
    .mobile-camera-panel {
        display: none !important;
    }
}
CSS;

renderHeader('打分上报', $pageStyles);
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php" class="active">打分上报</a></li>
                <li><a href="records.php">打分记录</a></li>
                <li><a href="ranking.php">排行榜</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h2>打分上报</h2>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= h($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" id="scoreForm" enctype="multipart/form-data" class="score-form">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="dormitory_no">宿舍号 *</label>
                        <input type="text" id="dormitory_no" name="dormitory_no" required
                               placeholder="请输入宿舍号" value="<?= h($_POST['dormitory_no'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>打分类型 *</label>
                        <div class="score-type-group">
                            <label class="score-type-option add <?= ($_POST['score_type'] ?? '') === 'add' ? 'active' : '' ?>">
                                <input type="radio" name="score_type" value="add"
                                       <?= $selectedScoreType === 'add' ? 'checked' : '' ?>>
                                <span class="score-type-text">加分</span>
                            </label>
                            <label class="score-type-option subtract <?= ($_POST['score_type'] ?? '') === 'subtract' ? 'active' : '' ?>">
                                <input type="radio" name="score_type" value="subtract"
                                       <?= $selectedScoreType === 'subtract' ? 'checked' : '' ?>>
                                <span class="score-type-text">减分</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="scoreOptionsGroup" style="<?= $selectedScoreType === 'add' ? 'display:none;' : '' ?>">
                        <label for="score">分数 *</label>
                        <div class="score-option-group">
                            <?php foreach ($scoreOptions as $index => $option): ?>
                                <?php
                                $optionText = rtrim(rtrim(number_format($option, 2, '.', ''), '0'), '.');
                                $isChecked = abs($selectedScore - round(floatval($option), 2)) < 0.0001;
                                ?>
                                <label class="score-option <?= $isChecked ? 'active' : '' ?>">
                                    <input type="radio"
                                           name="score"
                                           value="<?= h($optionText) ?>"
                                           <?= $isChecked ? 'checked' : '' ?>
                                           <?= $selectedScoreType === 'subtract' && $index === 0 ? 'required' : '' ?>>
                                    <span><?= h($optionText) ?> 分</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: #666;">减分时请选择一个分值，不需要手动输入。</small>
                    </div>

                    <div class="form-group" id="addScoreHint" style="<?= $selectedScoreType === 'add' ? '' : 'display:none;' ?>">
                        <label>分数</label>
                        <div class="add-zero-hint">加分固定为 0 分，无需选择扣分项。</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="images">现场图片 *</label>
                        <input type="file" id="images" name="images[]" accept="image/*" multiple style="display: none;">
                        <input type="file" id="imagePicker" accept="image/*" multiple style="display: none;">
                        <input type="file" id="cameraPicker" accept="image/*" capture="environment" style="display: none;">

                        <div class="desktop-picker-row">
                            <button type="button" class="btn btn-secondary" id="desktopPickBtn">选择图片</button>
                            <span id="selectedFilesText">未选择图片</span>
                        </div>

                        <div class="mobile-camera-panel">
                            <div id="cameraGrid" class="camera-grid"></div>
                            <div class="mobile-camera-tools">
                                <button type="button" class="btn btn-secondary btn-sm" id="mobileAlbumBtn">从相册选择</button>
                                <button type="button" class="btn btn-secondary btn-sm" id="clearImagesBtn">清空图片</button>
                            </div>
                        </div>

                        <small id="imageCountHint" style="color: #666; display: block; margin-top: 5px;">
                            请上传4到10张图片（支持 JPG/PNG/WEBP/GIF，单张不超过5MB）
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-submit">提交打分</button>
                </form>
            </div>
            
            <div class="card">
                <h3 style="margin-bottom: 15px;">注意事项</h3>
                <ul style="color: #666; line-height: 1.8; padding-left: 20px;">
                    <li>当前星期：<strong data-live-clock="weekday"><?= h($currentWeekday) ?></strong></li>
                    <li>时间表：<strong data-live-clock="datetime"><?= h($currentDisplayTime) ?></strong></li>
                    <li>当前计分月份：<strong><?= $currentMonth ?></strong></li>
                    <li>每周总分上限：<strong><?= $weeklyMaxScore ?></strong> 分</li>
                    <li>每个宿舍每天最多打分 <strong><?= getDailyLimit() ?></strong> 次</li>
                    <li>减分分值选项：<strong><?= h(serializeScoreOptionValues($scoreOptions)) ?></strong></li>
                    <li>每次上报必须上传 <strong>4-10张</strong> 现场图片</li>
                    <li>加分固定为 <strong>0</strong> 分</li>
                    <li>提交后无法修改，请仔细核对</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function updateScoreTypeSelection() {
    document.querySelectorAll('.score-type-option').forEach(function (option) {
        var radio = option.querySelector('input[type="radio"]');
        option.classList.toggle('active', !!(radio && radio.checked));
    });
}

function updateScoreOptionSelection() {
    document.querySelectorAll('.score-option').forEach(function (option) {
        var radio = option.querySelector('input[type="radio"]');
        option.classList.toggle('active', !!(radio && radio.checked));
    });
}

function syncScoreModeUI() {
    var checkedType = document.querySelector('input[name="score_type"]:checked');
    var isAdd = !!(checkedType && checkedType.value === 'add');
    var scoreOptionsGroup = document.getElementById('scoreOptionsGroup');
    var addScoreHint = document.getElementById('addScoreHint');
    var scoreRadios = document.querySelectorAll('input[name="score"]');

    if (scoreOptionsGroup) {
        scoreOptionsGroup.style.display = isAdd ? 'none' : '';
    }
    if (addScoreHint) {
        addScoreHint.style.display = isAdd ? '' : 'none';
    }
    scoreRadios.forEach(function (radio, index) {
        radio.required = !isAdd && index === 0;
    });
}

(function () {
    var MIN_IMAGES = 4;
    var MAX_IMAGES = 10;
    var MAX_FILE_SIZE = 5 * 1024 * 1024;
    var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    function supportsDataTransfer() {
        try {
            return typeof DataTransfer !== 'undefined' && !!new DataTransfer();
        } catch (e) {
            return false;
        }
    }

    function isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    function fileKey(file) {
        return [file.name, file.size, file.lastModified].join('::');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('scoreForm');
        var imagesInput = document.getElementById('images');
        var imagePicker = document.getElementById('imagePicker');
        var cameraPicker = document.getElementById('cameraPicker');
        var desktopPickBtn = document.getElementById('desktopPickBtn');
        var mobileAlbumBtn = document.getElementById('mobileAlbumBtn');
        var clearImagesBtn = document.getElementById('clearImagesBtn');
        var cameraGrid = document.getElementById('cameraGrid');
        var selectedFilesText = document.getElementById('selectedFilesText');
        var imageCountHint = document.getElementById('imageCountHint');
        var canUseDataTransfer = supportsDataTransfer();
        var selectedFiles = [];
        var mobile = isMobileDevice();
        var replaceIndex = -1;

        if (!form || !imagesInput) {
            return;
        }

        function syncHiddenInput() {
            if (!canUseDataTransfer) {
                return;
            }
            var dt = new DataTransfer();
            selectedFiles.forEach(function (file) {
                dt.items.add(file);
            });
            imagesInput.files = dt.files;
        }

        function updateImageCountHint() {
            var count = selectedFiles.length;
            if (selectedFilesText) {
                selectedFilesText.textContent = count > 0 ? ('已选择 ' + count + ' 张图片') : '未选择图片';
            }
            if (imageCountHint) {
                if (count > 0) {
                    var remain = Math.max(0, MIN_IMAGES - count);
                    imageCountHint.textContent = '已选择 ' + count + ' 张，至少还需 ' + remain + ' 张（最多10张，支持 JPG/PNG/WEBP/GIF，单张不超过5MB）';
                } else {
                    imageCountHint.textContent = '请上传4到10张图片（支持 JPG/PNG/WEBP/GIF，单张不超过5MB）';
                }
            }
        }

        function openSelectPicker(preferCamera, indexToReplace) {
            if (selectedFiles.length >= MAX_IMAGES && indexToReplace < 0) {
                alert('最多上传10张图片');
                return;
            }

            replaceIndex = typeof indexToReplace === 'number' ? indexToReplace : -1;

            if (preferCamera && cameraPicker) {
                cameraPicker.value = '';
                cameraPicker.click();
                return;
            }
            if (imagePicker) {
                imagePicker.value = '';
                imagePicker.click();
            }
        }

        function renderCameraGrid() {
            if (!cameraGrid) {
                return;
            }

            cameraGrid.innerHTML = '';
            var totalSlots = Math.min(MAX_IMAGES, Math.max(MIN_IMAGES, selectedFiles.length + 1));

            for (var i = 0; i < totalSlots; i++) {
                if (i < selectedFiles.length) {
                    (function (index) {
                        var file = selectedFiles[index];
                        var card = document.createElement('div');
                        card.className = 'camera-card filled';

                        var img = document.createElement('img');
                        img.className = 'camera-preview';
                        img.alt = '现场图片';
                        img.src = URL.createObjectURL(file);
                        img.onload = function () {
                            URL.revokeObjectURL(img.src);
                        };

                        var badge = document.createElement('span');
                        badge.className = 'camera-index';
                        badge.textContent = '第' + (index + 1) + '张';

                        var removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'camera-remove';
                        removeBtn.textContent = '删除';
                        removeBtn.addEventListener('click', function (event) {
                            event.stopPropagation();
                            selectedFiles.splice(index, 1);
                            syncHiddenInput();
                            updateImageCountHint();
                            renderCameraGrid();
                        });

                        card.appendChild(img);
                        card.appendChild(badge);
                        card.appendChild(removeBtn);
                        card.addEventListener('click', function () {
                            openSelectPicker(mobile, index);
                        });
                        cameraGrid.appendChild(card);
                    })(i);
                } else {
                    var addCard = document.createElement('button');
                    addCard.type = 'button';
                    addCard.className = 'camera-card add';

                    var inner = document.createElement('div');
                    inner.className = 'camera-card-inner';

                    var plus = document.createElement('span');
                    plus.className = 'camera-plus';
                    plus.textContent = '+';

                    var tip = document.createElement('span');
                    tip.className = 'camera-tip';
                    tip.textContent = i < MIN_IMAGES ? '点击拍照' : '继续添加';

                    inner.appendChild(plus);
                    inner.appendChild(tip);
                    addCard.appendChild(inner);

                    addCard.addEventListener('click', function () {
                        openSelectPicker(mobile, -1);
                    });

                    cameraGrid.appendChild(addCard);
                }
            }
        }

        function pushFiles(fileList) {
            if (!fileList || fileList.length === 0) {
                return;
            }

            if (!canUseDataTransfer) {
                var basicFiles = Array.from(fileList).slice(0, MAX_IMAGES);
                if (replaceIndex >= 0 && selectedFiles[replaceIndex] && basicFiles.length > 0) {
                    selectedFiles[replaceIndex] = basicFiles[0];
                } else {
                    selectedFiles = basicFiles;
                }
                replaceIndex = -1;
                try {
                    imagesInput.files = fileList;
                } catch (e) {
                    // 忽略不支持赋值的浏览器
                }
                updateImageCountHint();
                renderCameraGrid();
                return;
            }

            var seen = {};
            selectedFiles.forEach(function (file) {
                seen[fileKey(file)] = true;
            });

            var nextReplaceIndex = replaceIndex;
            if (nextReplaceIndex >= 0 && selectedFiles[nextReplaceIndex]) {
                delete seen[fileKey(selectedFiles[nextReplaceIndex])];
            }
            replaceIndex = -1;

            for (var i = 0; i < fileList.length; i++) {
                var file = fileList[i];

                if (nextReplaceIndex < 0 && selectedFiles.length >= MAX_IMAGES) {
                    alert('最多上传10张图片');
                    break;
                }

                if (ALLOWED_TYPES.indexOf(file.type) === -1) {
                    alert('仅支持 JPG/PNG/WEBP/GIF 图片');
                    continue;
                }

                if (file.size > MAX_FILE_SIZE) {
                    alert('图片 ' + file.name + ' 超过5MB，已跳过');
                    continue;
                }

                var key = fileKey(file);
                if (seen[key]) {
                    continue;
                }

                if (nextReplaceIndex >= 0) {
                    selectedFiles[nextReplaceIndex] = file;
                    nextReplaceIndex = -1;
                } else {
                    selectedFiles.push(file);
                }
                seen[key] = true;
            }

            syncHiddenInput();
            updateImageCountHint();
            renderCameraGrid();
        }

        if (desktopPickBtn && imagePicker) {
            desktopPickBtn.addEventListener('click', function () {
                openSelectPicker(false, -1);
            });
        }

        if (mobileAlbumBtn && imagePicker) {
            mobileAlbumBtn.addEventListener('click', function () {
                openSelectPicker(false, -1);
            });
        }

        if (clearImagesBtn) {
            clearImagesBtn.addEventListener('click', function () {
                if (selectedFiles.length === 0) {
                    return;
                }
                if (!confirm('确定清空已选图片吗？')) {
                    return;
                }
                selectedFiles = [];
                syncHiddenInput();
                updateImageCountHint();
                renderCameraGrid();
            });
        }

        if (imagePicker) {
            imagePicker.addEventListener('change', function (event) {
                pushFiles(event.target.files);
            });
        }

        if (cameraPicker) {
            cameraPicker.addEventListener('change', function (event) {
                pushFiles(event.target.files);
            });
        }

        document.querySelectorAll('input[name="score_type"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                updateScoreTypeSelection();
                syncScoreModeUI();
            });
        });
        document.querySelectorAll('input[name="score"]').forEach(function (radio) {
            radio.addEventListener('change', updateScoreOptionSelection);
        });
        updateScoreTypeSelection();
        updateScoreOptionSelection();
        syncScoreModeUI();
        updateImageCountHint();
        renderCameraGrid();

        if (!canUseDataTransfer && mobile && imageCountHint) {
            imageCountHint.textContent = '当前浏览器对连续拍照支持有限，建议使用“从相册选择”一次选择4-10张图片。';
        }

        form.addEventListener('submit', function (e) {
            var count = selectedFiles.length;
            if (count < MIN_IMAGES || count > MAX_IMAGES) {
                e.preventDefault();
                alert('请上传4到10张图片后再提交');
                return;
            }
            syncHiddenInput();
        });
    });
})();
</script>

<?php renderFooter(); ?>

