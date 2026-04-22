package com.kunlun.studentapp.data.model

data class UserInfo(
    val id: Int,
    val username: String,
    val real_name: String,
    val role: String
)

data class MeSettings(
    val current_week: String,
    val current_month: String,
    val daily_limit: Int,
    val score_options: List<Double>,
    val weekly_max_score: Double
)

data class MeData(
    val user: UserInfo,
    val settings: MeSettings,
    val today_submit_count: Int,
    val recent_records: List<ScoreRecord>
)

