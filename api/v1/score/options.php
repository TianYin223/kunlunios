<?php

require_once __DIR__ . '/../../../includes/api_auth.php';

apiRequireMethod('GET');
apiRequireAuth('inspector');

apiSuccess('获取成功', [
    'score_options' => getScoreOptionValues(),
    'daily_limit' => getDailyLimit(),
    'current_week' => getCurrentWeekdayCn(),
    'current_month' => getCurrentMonthPeriod(),
    'weekly_max_score' => getWeeklyMaxScore(),
]);
