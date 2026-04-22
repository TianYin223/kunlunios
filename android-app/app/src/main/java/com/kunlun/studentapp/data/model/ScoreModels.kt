package com.kunlun.studentapp.data.model

data class ScoreOptionsData(
    val score_options: List<Double>,
    val daily_limit: Int,
    val current_week: String,
    val current_month: String,
    val weekly_max_score: Double
)

data class SubmitScoreData(
    val record_id: Int,
    val dormitory_no: String,
    val score_type: String,
    val score: Double,
    val signed_score: Double,
    val current_weekly_score: Double,
    val current_monthly_score: Double,
    val period: String,
    val images: List<String>
)

data class ScoreRecordsData(
    val items: List<ScoreRecord>,
    val page: Int,
    val page_size: Int,
    val total: Int,
    val total_pages: Int
)

data class ScoreRecord(
    val id: Int,
    val dormitory_no: String,
    val score_type: String,
    val score: Double,
    val signed_score: Double,
    val reason: String?,
    val period: String,
    val created_at: String,
    val images: List<String>?,
    val image_count: Int
)

