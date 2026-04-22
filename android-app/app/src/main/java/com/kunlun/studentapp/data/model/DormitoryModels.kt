package com.kunlun.studentapp.data.model

data class DormitoryData(
    val items: List<DormitoryItem>,
    val keyword: String
)

data class DormitoryItem(
    val id: Int,
    val dormitory_no: String,
    val weekly_score: Double,
    val monthly_score: Double
)

