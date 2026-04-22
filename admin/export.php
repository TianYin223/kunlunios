<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

requireAdmin();

renderHeader('数据导出');
?>

<div class="container" style="padding-top: 20px;">
    <div style="display: flex;">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">仪表盘</a></li>
                <li><a href="dormitories.php">宿舍管理</a></li>
                <li><a href="users.php">账号管理</a></li>
                <li><a href="settings.php">打分设置</a></li>
                <li><a href="records.php">打分记录</a></li>
                <li><a href="reset.php">分数重置</a></li>
                <li><a href="history.php">历史记录</a></li>
                <li><a href="export.php" class="active">数据导出</a></li>
                <li><a href="system.php">系统设置</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <h2>数据导出</h2>
            </div>
            
            <div style="display: flex; gap: 20px;">
                <div class="card" style="flex: 1;">
                    <h3 style="margin-bottom: 15px;">导出打分记录</h3>
                    <p style="color: #666; margin-bottom: 15px;">
                        导出所有打分记录的Excel文件，包含宿舍号、检查人、分数和图片信息
                    </p>
                    <form action="export_records.php" method="GET">
                        <div class="form-group">
                            <label>周期筛选</label>
                            <select name="period">
                                <option value="">全部周期</option>
                                <?php 
                                $periods = db()->fetchAll(
                                    "SELECT DISTINCT month_year FROM score_records ORDER BY month_year DESC"
                                );
                                foreach ($periods as $m): 
                                ?>
                                    <option value="<?= h($m['month_year']) ?>"><?= h(formatPeriodDisplay($m['month_year'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn">导出Excel</button>
                    </form>
                </div>
                
                <div class="card" style="flex: 1;">
                    <h3 style="margin-bottom: 15px;">导出现有排行榜</h3>
                    <p style="color: #666; margin-bottom: 15px;">
                        导出当前宿舍分数排行榜的Excel文件
                    </p>
                    <form action="export_ranking.php" method="GET">
                        <button type="submit" class="btn">导出Excel</button>
                    </form>
                </div>
                
                <div class="card" style="flex: 1;">
                    <h3 style="margin-bottom: 15px;">导出历史排行榜</h3>
                    <p style="color: #666; margin-bottom: 15px;">
                        导出历史排行榜记录的Excel文件
                    </p>
                    <form action="export_history.php" method="GET">
                        <div class="form-group">
                            <label>周期筛选</label>
                            <select name="period">
                                <option value="">全部周期</option>
                                <?php 
                                $historyPeriods = db()->fetchAll(
                                    "SELECT DISTINCT month_year FROM history_rankings ORDER BY month_year DESC"
                                );
                                foreach ($historyPeriods as $m): 
                                ?>
                                    <option value="<?= h($m['month_year']) ?>"><?= h(formatPeriodDisplay($m['month_year'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn">导出Excel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
