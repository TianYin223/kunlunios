package com.kunlun.studentapp.ui.profile

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.RecyclerView
import com.kunlun.studentapp.R
import com.kunlun.studentapp.data.model.ScoreRecord
import java.util.Locale

class RecordAdapter : RecyclerView.Adapter<RecordAdapter.RecordViewHolder>() {
    private val items = mutableListOf<ScoreRecord>()

    fun submitList(list: List<ScoreRecord>) {
        items.clear()
        items.addAll(list)
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): RecordViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_record, parent, false)
        return RecordViewHolder(view)
    }

    override fun onBindViewHolder(holder: RecordViewHolder, position: Int) {
        holder.bind(items[position])
    }

    override fun getItemCount(): Int = items.size

    class RecordViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val textTitle: TextView = itemView.findViewById(R.id.textRecordTitle)
        private val textMeta: TextView = itemView.findViewById(R.id.textRecordMeta)

        fun bind(item: ScoreRecord) {
            val scoreText = if (item.signed_score >= 0) {
                "+${formatScore(item.signed_score)}"
            } else {
                formatScore(item.signed_score)
            }

            textTitle.text = "${item.dormitory_no}  $scoreText"
            textTitle.setTextColor(
                ContextCompat.getColor(
                    itemView.context,
                    if (item.signed_score >= 0) R.color.success_green else R.color.error_red
                )
            )
            textMeta.text = itemView.context.getString(
                R.string.label_record_meta,
                item.period,
                item.created_at,
                item.image_count
            )
        }

        private fun formatScore(value: Double): String {
            val rounded = String.format(Locale.US, "%.2f", value)
            return rounded.trimEnd('0').trimEnd('.')
        }
    }
}
