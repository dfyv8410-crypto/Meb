package com.meb.admin

import android.os.StrictMode
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

object ApiClient {
    @Volatile var baseUrl: String = "http://10.0.2.2:3000"
    @Volatile var token: String? = null

    private fun req(method: String, path: String, body: JSONObject? = null): Pair<Int, String> {
        val url = URL(baseUrl.trimEnd('/') + path)
        val c = url.openConnection() as HttpURLConnection
        c.requestMethod = method
        c.connectTimeout = 8000
        c.readTimeout = 8000
        token?.let { c.setRequestProperty("Authorization", "Bearer $it") }
        if (body != null) {
            c.doOutput = true
            c.setRequestProperty("Content-Type", "application/json")
            c.outputStream.use { it.write(body.toString().toByteArray()) }
        }
        val text = c.inputStream?.bufferedReader()?.use { it.readText() } ?: ""
        return c.responseCode to text
    }

    fun login(email: String, password: String): Result<JSONObject> = try {
        val (code, text) = req("POST", "/api/v1/auth/login",
            JSONObject().put("email", email).put("password", password))
        if (code == 200) {
            val j = JSONObject(text)
            token = j.getString("token")
            Result.success(j.getJSONObject("user"))
        } else Result.failure(Exception(JSONObject(text).optString("error", "Ошибка $code")))
    } catch (e: Exception) { Result.failure(e) }

    fun me(): Result<JSONObject> = try {
        val (code, text) = req("GET", "/api/v1/me")
        if (code == 200) Result.success(JSONObject(text)) else Result.failure(Exception(code.toString()))
    } catch (e: Exception) { Result.failure(e) }

    fun leads(): Result<JSONArray> = try {
        val (code, text) = req("GET", "/api/v1/leads")
        if (code == 200) Result.success(JSONArray(text)) else Result.failure(Exception(code.toString()))
    } catch (e: Exception) { Result.failure(e) }

    fun updateLead(id: String, status: String): Result<Boolean> = try {
        val (code, _) = req("PUT", "/api/v1/leads/$id", JSONObject().put("status", status))
        Result.success(code == 200)
    } catch (e: Exception) { Result.failure(e) }

    fun summary(): Result<JSONObject> = try {
        val (code, text) = req("GET", "/api/v1/analytics/summary")
        if (code == 200) Result.success(JSONObject(text)) else Result.failure(Exception(code.toString()))
    } catch (e: Exception) { Result.failure(e) }

    fun health(): Result<JSONObject> = try {
        val (code, text) = req("GET", "/api/v1/health")
        if (code == 200) Result.success(JSONObject(text)) else Result.failure(Exception(code.toString()))
    } catch (e: Exception) { Result.failure(e) }

    fun allowNetworkOnMainThreadForDemo() {
        StrictMode.setThreadPolicy(StrictMode.ThreadPolicy.Builder().permitNetwork().build())
    }
}
