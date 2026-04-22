package com.kunlun.studentapp.ui.submit

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.content.res.ColorStateList
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import android.os.Bundle
import android.os.Build
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ProgressBar
import android.widget.RadioButton
import android.widget.RadioGroup
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButton
import com.google.android.material.chip.Chip
import com.google.android.material.chip.ChipGroup
import com.google.android.material.textfield.TextInputEditText
import com.kunlun.studentapp.BuildConfig
import com.kunlun.studentapp.R
import com.kunlun.studentapp.network.NetworkErrorParser
import com.kunlun.studentapp.ui.main.MainActivity
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import retrofit2.HttpException
import java.io.ByteArrayOutputStream
import java.io.File
import java.io.FileOutputStream
import java.util.Locale

class SubmitScoreFragment : Fragment() {
    private val maxUploadBytesPerImage = 5 * 1024 * 1024
    private val maxPhotoCount = 10

    private lateinit var inputDormitoryNo: TextInputEditText
    private lateinit var radioScoreType: RadioGroup
    private lateinit var radioSubtract: RadioButton
    private lateinit var radioAdd: RadioButton
    private lateinit var textScoreLabel: TextView
    private lateinit var chipScoreOptions: ChipGroup
    private lateinit var textSelectedImages: TextView
    private lateinit var btnTakePhoto: MaterialButton
    private lateinit var btnPickPhotos: MaterialButton
    private lateinit var btnClearPhotos: MaterialButton
    private lateinit var btnSubmitScore: MaterialButton
    private lateinit var submitProgress: ProgressBar
    private lateinit var textSubmitMessage: TextView

    private val scoreOptions = mutableListOf<Double>()
    private var selectedScoreValue = 0.5
    private val capturedPhotoFiles = mutableListOf<File>()
    private var pendingCaptureFile: File? = null
    private var pendingPermissionAction: PermissionAction? = null

    private val takePictureLauncher =
        registerForActivityResult(ActivityResultContracts.TakePicture()) { success ->
            val file = pendingCaptureFile
            pendingCaptureFile = null
            if (file == null) {
                return@registerForActivityResult
            }

            if (success) {
                if (capturedPhotoFiles.size >= maxPhotoCount) {
                    file.delete()
                    Toast.makeText(
                        requireContext(),
                        getString(R.string.msg_photo_max_reached),
                        Toast.LENGTH_SHORT
                    ).show()
                    return@registerForActivityResult
                }
                capturedPhotoFiles.add(file)
                updateSelectedImagesText()
            } else if (file.exists()) {
                file.delete()
            }
        }

    private val mediaPermissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
            when (pendingPermissionAction) {
                PermissionAction.CAMERA -> {
                    if (granted) {
                        launchCamera()
                    } else {
                        showMessage(getString(R.string.msg_camera_permission_required), success = false)
                    }
                }

                PermissionAction.GALLERY -> {
                    if (granted) {
                        launchGalleryPicker()
                    } else {
                        showMessage(getString(R.string.msg_gallery_permission_required), success = false)
                    }
                }

                null -> Unit
            }
            pendingPermissionAction = null
        }

    private val pickPhotosLauncher =
        registerForActivityResult(ActivityResultContracts.GetMultipleContents()) { uris ->
            if (uris.isEmpty()) {
                return@registerForActivityResult
            }
            handlePickedPhotos(uris)
        }

    private val initialMediaPermissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { _ ->
            markInitialPermissionPrompted()
        }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        return inflater.inflate(R.layout.fragment_submit_score, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        inputDormitoryNo = view.findViewById(R.id.inputDormitoryNo)
        radioScoreType = view.findViewById(R.id.radioScoreType)
        radioSubtract = view.findViewById(R.id.radioSubtract)
        radioAdd = view.findViewById(R.id.radioAdd)
        textScoreLabel = view.findViewById(R.id.textScoreLabel)
        chipScoreOptions = view.findViewById(R.id.chipScoreOptions)
        textSelectedImages = view.findViewById(R.id.textSelectedImages)
        btnTakePhoto = view.findViewById(R.id.btnTakePhoto)
        btnPickPhotos = view.findViewById(R.id.btnPickPhotos)
        btnClearPhotos = view.findViewById(R.id.btnClearPhotos)
        btnSubmitScore = view.findViewById(R.id.btnSubmitScore)
        submitProgress = view.findViewById(R.id.submitProgress)
        textSubmitMessage = view.findViewById(R.id.textSubmitMessage)

        radioScoreType.setOnCheckedChangeListener { _, _ -> updateScoreModeUi() }
        btnTakePhoto.setOnClickListener { openCamera() }
        btnPickPhotos.setOnClickListener { openGallery() }
        btnClearPhotos.setOnClickListener {
            clearCapturedPhotos(deleteFiles = true)
            showMessage(getString(R.string.msg_photos_cleared), success = true)
        }
        btnSubmitScore.setOnClickListener { submitScore() }

        requestInitialMediaPermissionsIfNeeded()
        updateScoreModeUi()
        updateSelectedImagesText()
        loadScoreOptions()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        pendingCaptureFile?.let {
            if (it.exists()) {
                it.delete()
            }
        }
        pendingCaptureFile = null
    }

    private fun openCamera() {
        if (capturedPhotoFiles.size >= maxPhotoCount) {
            showMessage(getString(R.string.msg_photo_max_reached), success = false)
            return
        }

        if (!isPermissionGranted(Manifest.permission.CAMERA)) {
            pendingPermissionAction = PermissionAction.CAMERA
            mediaPermissionLauncher.launch(Manifest.permission.CAMERA)
            return
        }

        launchCamera()
    }

    private fun openGallery() {
        if (capturedPhotoFiles.size >= maxPhotoCount) {
            showMessage(getString(R.string.msg_photo_max_reached), success = false)
            return
        }

        val galleryPermission = currentGalleryPermission()
        if (!isPermissionGranted(galleryPermission)) {
            pendingPermissionAction = PermissionAction.GALLERY
            mediaPermissionLauncher.launch(galleryPermission)
            return
        }

        launchGalleryPicker()
    }

    private fun launchCamera() {
        if (capturedPhotoFiles.size >= maxPhotoCount) {
            showMessage(getString(R.string.msg_photo_max_reached), success = false)
            return
        }

        try {
            val outputFile = createCameraOutputFile()
            val authority = "${requireContext().packageName}.fileprovider"
            val outputUri: Uri = FileProvider.getUriForFile(requireContext(), authority, outputFile)
            pendingCaptureFile = outputFile
            takePictureLauncher.launch(outputUri)
        } catch (error: Throwable) {
            showMessage(NetworkErrorParser.toMessage(error), success = false)
        }
    }

    private fun launchGalleryPicker() {
        if (capturedPhotoFiles.size >= maxPhotoCount) {
            showMessage(getString(R.string.msg_photo_max_reached), success = false)
            return
        }

        try {
            pickPhotosLauncher.launch("image/*")
        } catch (error: Throwable) {
            showMessage(NetworkErrorParser.toMessage(error), success = false)
        }
    }

    private fun handlePickedPhotos(uris: List<Uri>) {
        val remaining = maxPhotoCount - capturedPhotoFiles.size
        if (remaining <= 0) {
            showMessage(getString(R.string.msg_photo_max_reached), success = false)
            return
        }

        val toImport = uris.take(remaining)
        var addedCount = 0
        toImport.forEach { uri ->
            try {
                val cachedFile = copyUriToCacheFile(uri)
                capturedPhotoFiles.add(cachedFile)
                addedCount++
            } catch (_: Throwable) {
                // Skip single failed image and continue importing other selections.
            }
        }

        if (addedCount > 0) {
            updateSelectedImagesText()
        }

        val hasOverflow = uris.size > remaining
        val hasReadFailure = addedCount < toImport.size
        when {
            addedCount == 0 -> showMessage(getString(R.string.msg_gallery_pick_failed), success = false)
            hasOverflow || hasReadFailure -> showMessage(
                getString(R.string.msg_gallery_pick_partial, addedCount),
                success = false
            )

            else -> showMessage(getString(R.string.msg_gallery_pick_success, addedCount), success = true)
        }
    }

    private fun copyUriToCacheFile(uri: Uri): File {
        val inputStream = requireContext().contentResolver.openInputStream(uri)
            ?: throw IllegalStateException(getString(R.string.msg_gallery_pick_failed))
        inputStream.use { input ->
            val dir = File(requireContext().cacheDir, "camera_uploads")
            if (!dir.exists()) {
                dir.mkdirs()
            }
            val outputFile = File.createTempFile("gallery_${System.currentTimeMillis()}_", ".jpg", dir)
            FileOutputStream(outputFile).use { output ->
                input.copyTo(output)
            }
            return outputFile
        }
    }

    private fun currentGalleryPermission(): String {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            Manifest.permission.READ_MEDIA_IMAGES
        } else {
            Manifest.permission.READ_EXTERNAL_STORAGE
        }
    }

    private fun requestInitialMediaPermissionsIfNeeded() {
        if (isInitialPermissionPrompted()) {
            return
        }

        val missingPermissions = listOf(
            Manifest.permission.CAMERA,
            currentGalleryPermission()
        ).distinct().filterNot(::isPermissionGranted)

        if (missingPermissions.isEmpty()) {
            markInitialPermissionPrompted()
            return
        }

        initialMediaPermissionLauncher.launch(missingPermissions.toTypedArray())
    }

    private fun isInitialPermissionPrompted(): Boolean {
        return permissionPrefs().getBoolean(KEY_INITIAL_MEDIA_PERMISSION_PROMPTED, false)
    }

    private fun markInitialPermissionPrompted() {
        permissionPrefs()
            .edit()
            .putBoolean(KEY_INITIAL_MEDIA_PERMISSION_PROMPTED, true)
            .apply()
    }

    private fun permissionPrefs() =
        requireContext().getSharedPreferences(PREFS_PERMISSION_NAME, Context.MODE_PRIVATE)

    private fun isPermissionGranted(permission: String): Boolean {
        return ContextCompat.checkSelfPermission(
            requireContext(),
            permission
        ) == PackageManager.PERMISSION_GRANTED
    }

    private fun createCameraOutputFile(): File {
        val dir = File(requireContext().cacheDir, "camera_uploads")
        if (!dir.exists()) {
            dir.mkdirs()
        }
        return File.createTempFile("camera_${System.currentTimeMillis()}_", ".jpg", dir)
    }

    private fun submitScore() {
        val dormitoryNo = inputDormitoryNo.text?.toString()?.trim().orEmpty()
        if (dormitoryNo.isBlank()) {
            showMessage(getString(R.string.msg_dormitory_required), success = false)
            return
        }

        if (capturedPhotoFiles.size !in 4..10) {
            showMessage(getString(R.string.msg_photo_count_invalid), success = false)
            return
        }

        val scoreType = if (radioAdd.isChecked) "add" else "subtract"
        val scoreValue = if (scoreType == "add") 0.0 else selectedScoreValue

        setLoading(true)
        lifecycleScope.launch {
            val generatedUploadFiles = mutableListOf<File>()
            try {
                val imageParts = buildImageParts(capturedPhotoFiles, generatedUploadFiles)
                val response = mainActivity().appRepository().submitScore(
                    dormitoryNo = dormitoryNo.toTextBody(),
                    scoreType = scoreType.toTextBody(),
                    score = formatScore(scoreValue).toTextBody(),
                    images = imageParts
                )

                if (response.success) {
                    val signedScore = response.data?.signed_score ?: 0.0
                    val scoreText = if (signedScore >= 0) "+$signedScore" else signedScore.toString()
                    showMessage(
                        getString(R.string.msg_submit_success, dormitoryNo, scoreText),
                        success = true
                    )
                    inputDormitoryNo.setText("")
                    clearCapturedPhotos(deleteFiles = true)
                } else {
                    showMessage(
                        buildSubmitApiFailureMessage(
                            apiMessage = response.message,
                            dormitoryNo = dormitoryNo,
                            scoreType = scoreType,
                            scoreValue = scoreValue,
                            photoCount = capturedPhotoFiles.size
                        ),
                        success = false
                    )
                }
            } catch (error: Throwable) {
                if (error is HttpException && error.code() == 401) {
                    mainActivity().forceRelogin()
                    return@launch
                }
                showMessage(
                    buildSubmitErrorMessage(
                        error = error,
                        dormitoryNo = dormitoryNo,
                        scoreType = scoreType,
                        scoreValue = scoreValue,
                        photoCount = capturedPhotoFiles.size
                    ),
                    success = false
                )
            } finally {
                generatedUploadFiles.forEach {
                    if (it.exists()) {
                        it.delete()
                    }
                }
                setLoading(false)
            }
        }
    }

    private fun loadScoreOptions() {
        lifecycleScope.launch {
            try {
                val response = mainActivity().appRepository().scoreOptions()
                if (response.success && response.data != null) {
                    scoreOptions.clear()
                    scoreOptions.addAll(response.data.score_options)
                    if (scoreOptions.isEmpty()) {
                        scoreOptions.addAll(listOf(0.5, 1.0, 1.5, 2.0))
                    }
                    bindScoreChips()
                    updateScoreModeUi()
                } else {
                    showMessage(response.message, success = false)
                }
            } catch (error: Throwable) {
                if (error is HttpException && error.code() == 401) {
                    mainActivity().forceRelogin()
                    return@launch
                }
                showMessage(NetworkErrorParser.toMessage(error), success = false)
            }
        }
    }

    private fun bindScoreChips() {
        chipScoreOptions.removeAllViews()
        if (scoreOptions.isEmpty()) {
            return
        }

        val bgColors = ContextCompat.getColorStateList(requireContext(), R.color.chip_score_bg)
            ?: ColorStateList.valueOf(ContextCompat.getColor(requireContext(), R.color.chip_bg))
        val textColors = ContextCompat.getColorStateList(requireContext(), R.color.chip_score_text)
            ?: ColorStateList.valueOf(ContextCompat.getColor(requireContext(), R.color.black))

        scoreOptions.forEachIndexed { index, value ->
            val chip = Chip(requireContext()).apply {
                id = View.generateViewId()
                text = formatScore(value)
                isCheckable = true
                setCheckedIconVisible(false)
                chipBackgroundColor = bgColors
                setTextColor(textColors)
                chipStrokeWidth = 1f
                chipStrokeColor = ColorStateList.valueOf(
                    ContextCompat.getColor(requireContext(), R.color.line_subtle)
                )
                textSize = 15f
                minHeight = (40 * resources.displayMetrics.density).toInt()
                setOnCheckedChangeListener { _, isChecked ->
                    if (isChecked) {
                        selectedScoreValue = value
                    }
                }
            }

            chipScoreOptions.addView(chip)
            if (index == 0) {
                chip.isChecked = true
                selectedScoreValue = value
            }
        }
    }

    private fun updateScoreModeUi() {
        val isAdd = radioAdd.isChecked
        chipScoreOptions.isEnabled = !isAdd
        chipScoreOptions.alpha = if (isAdd) 0.45f else 1f
        textScoreLabel.text = if (isAdd) getString(R.string.add_fixed_zero) else getString(R.string.deduct_value)
        if (!isAdd && chipScoreOptions.checkedChipId == View.NO_ID && chipScoreOptions.childCount > 0) {
            (chipScoreOptions.getChildAt(0) as? Chip)?.isChecked = true
        }
    }

    private fun updateSelectedImagesText() {
        val count = capturedPhotoFiles.size
        textSelectedImages.text = if (count <= 0) {
            getString(R.string.photos_empty)
        } else {
            getString(R.string.photos_selected, count)
        }
    }

    private fun clearCapturedPhotos(deleteFiles: Boolean) {
        if (deleteFiles) {
            capturedPhotoFiles.forEach {
                if (it.exists()) {
                    it.delete()
                }
            }
        }
        capturedPhotoFiles.clear()
        updateSelectedImagesText()
    }

    private fun setLoading(loading: Boolean) {
        btnSubmitScore.isEnabled = !loading
        btnTakePhoto.isEnabled = !loading
        btnPickPhotos.isEnabled = !loading
        btnClearPhotos.isEnabled = !loading
        submitProgress.visibility = if (loading) View.VISIBLE else View.GONE
    }

    private fun buildImageParts(
        files: List<File>,
        generatedUploadFiles: MutableList<File>
    ): List<MultipartBody.Part> {
        return files.mapIndexed { index, file ->
            val uploadFile = ensureUploadReadyImage(file)
            generatedUploadFiles.add(uploadFile)
            val body = uploadFile.asRequestBody("image/jpeg".toMediaType())
            MultipartBody.Part.createFormData("images[]", "photo_${index}_${uploadFile.name}", body)
        }
    }

    private fun ensureUploadReadyImage(sourceFile: File): File {
        val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        BitmapFactory.decodeFile(sourceFile.absolutePath, options)
        val sampleSize = calculateInSampleSize(
            width = options.outWidth,
            height = options.outHeight,
            reqWidth = 1920,
            reqHeight = 1920
        )

        val decodeOptions = BitmapFactory.Options().apply {
            inSampleSize = sampleSize
            inPreferredConfig = Bitmap.Config.ARGB_8888
        }
        val bitmap = BitmapFactory.decodeFile(sourceFile.absolutePath, decodeOptions)
            ?: throw IllegalStateException(getString(R.string.msg_photo_decode_failed))

        try {
            var quality = 92
            var compressed = compressBitmap(bitmap, quality)
            while (compressed.size > maxUploadBytesPerImage && quality > 45) {
                quality -= 8
                compressed = compressBitmap(bitmap, quality)
            }

            if (compressed.size > maxUploadBytesPerImage) {
                throw IllegalStateException(getString(R.string.msg_photo_too_large))
            }

            val uploadDir = File(requireContext().cacheDir, "camera_uploads_ready")
            if (!uploadDir.exists()) {
                uploadDir.mkdirs()
            }
            val outputFile = File.createTempFile(
                "ready_${System.currentTimeMillis()}_",
                ".jpg",
                uploadDir
            )

            FileOutputStream(outputFile).use { it.write(compressed) }
            return outputFile
        } finally {
            bitmap.recycle()
        }
    }

    private fun compressBitmap(bitmap: Bitmap, quality: Int): ByteArray {
        val stream = ByteArrayOutputStream()
        bitmap.compress(Bitmap.CompressFormat.JPEG, quality, stream)
        return stream.toByteArray()
    }

    private fun calculateInSampleSize(width: Int, height: Int, reqWidth: Int, reqHeight: Int): Int {
        var inSampleSize = 1
        if (height > reqHeight || width > reqWidth) {
            while ((height / (inSampleSize * 2)) >= reqHeight && (width / (inSampleSize * 2)) >= reqWidth) {
                inSampleSize *= 2
            }
        }
        return if (inSampleSize < 1) 1 else inSampleSize
    }

    private fun formatScore(value: Double): String {
        val rounded = String.format(Locale.US, "%.2f", value)
        return rounded.trimEnd('0').trimEnd('.')
    }

    private fun String.toTextBody(): RequestBody {
        return toRequestBody("text/plain".toMediaType())
    }

    private fun buildSubmitApiFailureMessage(
        apiMessage: String,
        dormitoryNo: String,
        scoreType: String,
        scoreValue: Double,
        photoCount: Int
    ): String {
        val normalMessage = apiMessage.ifBlank { "Submit failed, please retry." }
        if (!BuildConfig.DEBUG) {
            return normalMessage
        }

        val debugMessage = buildString {
            appendLine(normalMessage)
            appendLine()
            appendLine("[DEBUG submit api]")
            appendLine("endpoint=${BuildConfig.API_BASE_URL}api/v1/score/submit.php")
            appendLine("dormitory_no=$dormitoryNo")
            appendLine("score_type=$scoreType")
            appendLine("score=${formatScore(scoreValue)}")
            append("photo_count=$photoCount")
        }
        Log.e(TAG, debugMessage)
        return debugMessage
    }

    private fun buildSubmitErrorMessage(
        error: Throwable,
        dormitoryNo: String,
        scoreType: String,
        scoreValue: Double,
        photoCount: Int
    ): String {
        if (error is HttpException) {
            val code = error.code()
            val body = runCatching { error.response()?.errorBody()?.string().orEmpty() }
                .getOrDefault("")
            val parsedMessage = parseApiMessage(body).ifBlank { "Request failed($code)" }
            if (!BuildConfig.DEBUG) {
                return parsedMessage
            }

            val debugMessage = buildString {
                appendLine(parsedMessage)
                appendLine()
                appendLine("[DEBUG submit http]")
                appendLine("endpoint=${BuildConfig.API_BASE_URL}api/v1/score/submit.php")
                appendLine("http_code=$code")
                appendLine("dormitory_no=$dormitoryNo")
                appendLine("score_type=$scoreType")
                appendLine("score=${formatScore(scoreValue)}")
                appendLine("photo_count=$photoCount")
                append("raw_body=${body.ifBlank { "<empty>" }.take(1200)}")
            }
            Log.e(TAG, debugMessage, error)
            return debugMessage
        }

        val normalMessage = NetworkErrorParser.toMessage(error)
        if (!BuildConfig.DEBUG) {
            return normalMessage
        }

        val debugMessage = buildString {
            appendLine(normalMessage)
            appendLine()
            appendLine("[DEBUG submit throwable]")
            appendLine("endpoint=${BuildConfig.API_BASE_URL}api/v1/score/submit.php")
            appendLine("type=${error::class.java.simpleName}")
            appendLine("message=${error.message ?: "<null>"}")
            appendLine("dormitory_no=$dormitoryNo")
            appendLine("score_type=$scoreType")
            appendLine("score=${formatScore(scoreValue)}")
            append("photo_count=$photoCount")
        }
        Log.e(TAG, debugMessage, error)
        return debugMessage
    }

    private fun parseApiMessage(body: String): String {
        if (body.isBlank()) {
            return ""
        }
        return runCatching {
            JSONObject(body).optString("message")
        }.getOrDefault("")
    }

    private fun showMessage(message: String, success: Boolean) {
        textSubmitMessage.text = message
        textSubmitMessage.setTextColor(
            ContextCompat.getColor(
                requireContext(),
                if (success) R.color.success_green else R.color.error_red
            )
        )
    }

    private fun mainActivity(): MainActivity = requireActivity() as MainActivity

    companion object {
        fun newInstance(): SubmitScoreFragment = SubmitScoreFragment()

        private const val TAG = "SubmitScoreFragment"
        private const val PREFS_PERMISSION_NAME = "student_score_permissions"
        private const val KEY_INITIAL_MEDIA_PERMISSION_PROMPTED = "initial_media_permission_prompted"
    }

    private enum class PermissionAction {
        CAMERA,
        GALLERY
    }
}

