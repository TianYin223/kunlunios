package com.kunlun.studentapp.ui.profile

import android.content.Context
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.google.android.material.button.MaterialButton
import com.kunlun.studentapp.R
import com.kunlun.studentapp.data.model.ScoreRecord
import com.kunlun.studentapp.network.NetworkErrorParser
import com.kunlun.studentapp.ui.main.MainActivity
import kotlinx.coroutines.launch
import retrofit2.HttpException

class ProfileFragment : Fragment() {
    interface Callbacks {
        fun onLogoutRequested()
    }

    private var callbacks: Callbacks? = null

    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var textName: TextView
    private lateinit var textUsername: TextView
    private lateinit var textWeek: TextView
    private lateinit var textMonth: TextView
    private lateinit var textDaily: TextView
    private lateinit var textOptions: TextView
    private lateinit var btnRefresh: MaterialButton
    private lateinit var btnLogout: MaterialButton
    private lateinit var recyclerRecords: RecyclerView
    private lateinit var recordAdapter: RecordAdapter

    override fun onAttach(context: Context) {
        super.onAttach(context)
        callbacks = context as? Callbacks
    }

    override fun onDetach() {
        callbacks = null
        super.onDetach()
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        return inflater.inflate(R.layout.fragment_profile, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        swipeRefresh = view.findViewById(R.id.swipeRefresh)
        textName = view.findViewById(R.id.textName)
        textUsername = view.findViewById(R.id.textUsername)
        textWeek = view.findViewById(R.id.textWeek)
        textMonth = view.findViewById(R.id.textMonth)
        textDaily = view.findViewById(R.id.textDaily)
        textOptions = view.findViewById(R.id.textOptions)
        btnRefresh = view.findViewById(R.id.btnRefresh)
        btnLogout = view.findViewById(R.id.btnLogout)
        recyclerRecords = view.findViewById(R.id.recyclerRecords)

        recordAdapter = RecordAdapter()
        recyclerRecords.layoutManager = LinearLayoutManager(requireContext())
        recyclerRecords.adapter = recordAdapter

        swipeRefresh.setOnRefreshListener { loadData() }
        btnRefresh.setOnClickListener { loadData() }
        btnLogout.setOnClickListener { callbacks?.onLogoutRequested() }

        loadData()
    }

    private fun loadData() {
        swipeRefresh.isRefreshing = true
        lifecycleScope.launch {
            try {
                val meResp = mainActivity().appRepository().me()
                if (!meResp.success || meResp.data == null) {
                    Toast.makeText(requireContext(), meResp.message, Toast.LENGTH_SHORT).show()
                    return@launch
                }

                val me = meResp.data
                textName.text = getString(R.string.label_name_with_value, me.user.real_name)
                textUsername.text = getString(R.string.label_account_with_value, me.user.username)
                textWeek.text = getString(R.string.label_week_with_value, me.settings.current_week)
                textMonth.text = getString(R.string.label_month_with_value, me.settings.current_month)
                textDaily.text = getString(
                    R.string.label_today_with_value,
                    me.today_submit_count,
                    me.settings.daily_limit
                )
                textOptions.text = getString(
                    R.string.label_options_with_value,
                    me.settings.score_options.joinToString(",")
                )

                val recordResp = mainActivity().appRepository().scoreRecords(page = 1, pageSize = 20)
                val records = if (recordResp.success && recordResp.data != null) {
                    recordResp.data.items
                } else {
                    me.recent_records
                }
                bindRecords(records)
            } catch (error: Throwable) {
                if (error is HttpException && error.code() == 401) {
                    mainActivity().forceRelogin()
                    return@launch
                }
                Toast.makeText(
                    requireContext(),
                    NetworkErrorParser.toMessage(error),
                    Toast.LENGTH_SHORT
                ).show()
            } finally {
                swipeRefresh.isRefreshing = false
            }
        }
    }

    private fun bindRecords(records: List<ScoreRecord>) {
        recordAdapter.submitList(records)
    }

    private fun mainActivity(): MainActivity = requireActivity() as MainActivity

    companion object {
        fun newInstance(): ProfileFragment = ProfileFragment()
    }
}
