package com.meb.admin;

import android.content.Context;
import android.content.SharedPreferences;
import android.os.StrictMode;
import org.json.JSONObject;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class ApiClient {

    private static final String PREFS_NAME = "meb_settings";
    private static final String KEY_BASE_URL = "base_url";
    private static final String KEY_TOKEN = "auth_token";
    private static final String KEY_USER_NAME = "user_name";
    private static final String KEY_USER_EMAIL = "user_email";
    private static final String KEY_USER_ROLE = "user_role";
    private static final String DEFAULT_URL = "http://10.0.2.2:3000";

    public static void init() {
        StrictMode.setThreadPolicy(
            new StrictMode.ThreadPolicy.Builder().permitAll().build()
        );
    }

    public static String getBaseUrl(Context ctx) {
        return prefs(ctx).getString(KEY_BASE_URL, DEFAULT_URL);
    }

    public static void setBaseUrl(Context ctx, String url) {
        prefs(ctx).edit().putString(KEY_BASE_URL, url).apply();
    }

    public static String getToken(Context ctx) {
        return prefs(ctx).getString(KEY_TOKEN, null);
    }

    public static void saveAuth(Context ctx, String token, String name, String email, String role) {
        prefs(ctx).edit()
            .putString(KEY_TOKEN, token)
            .putString(KEY_USER_NAME, name)
            .putString(KEY_USER_EMAIL, email)
            .putString(KEY_USER_ROLE, role)
            .apply();
    }

    public static void clearAuth(Context ctx) {
        prefs(ctx).edit()
            .remove(KEY_TOKEN)
            .remove(KEY_USER_NAME)
            .remove(KEY_USER_EMAIL)
            .remove(KEY_USER_ROLE)
            .apply();
    }

    public static String getUserName(Context ctx) {
        return prefs(ctx).getString(KEY_USER_NAME, "");
    }

    public static String getUserEmail(Context ctx) {
        return prefs(ctx).getString(KEY_USER_EMAIL, "");
    }

    public static String getUserRole(Context ctx) {
        return prefs(ctx).getString(KEY_USER_ROLE, "");
    }

    public static boolean isLoggedIn(Context ctx) {
        return getToken(ctx) != null;
    }

    public static JSONObject login(Context ctx, String email, String password) throws Exception {
        JSONObject body = new JSONObject();
        body.put("email", email);
        body.put("password", password);

        String baseUrl = getBaseUrl(ctx);
        JSONObject result = post(ctx, baseUrl + "/api/v1/auth/login", body, null);
        if (result != null && result.has("token")) {
            saveAuth(ctx,
                result.getString("token"),
                result.optJSONObject("user") != null ? result.optJSONObject("user").optString("name", "") : "",
                result.optJSONObject("user") != null ? result.optJSONObject("user").optString("email", "") : "",
                result.optJSONObject("user") != null ? result.optJSONObject("user").optString("role", "") : ""
            );
        }
        return result;
    }

    public static JSONObject getMe(Context ctx) throws Exception {
        String token = getToken(ctx);
        String baseUrl = getBaseUrl(ctx);
        return get(ctx, baseUrl + "/api/v1/me", token);
    }

    public static JSONObject getAdminPanelUrl(Context ctx) {
        JSONObject result = new JSONObject();
        try {
            result.put("url", getBaseUrl(ctx) + "/admin");
            result.put("token", getToken(ctx));
        } catch (Exception e) {
        }
        return result;
    }

    private static JSONObject get(Context ctx, String urlStr, String token) throws Exception {
        HttpURLConnection conn = null;
        try {
            URL url = new URL(urlStr);
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setConnectTimeout(8000);
            conn.setReadTimeout(8000);
            if (token != null) {
                conn.setRequestProperty("Authorization", "Bearer " + token);
            }

            int code = conn.getResponseCode();
            BufferedReader reader;
            if (code >= 200 && code < 300) {
                reader = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
            } else {
                reader = new BufferedReader(new InputStreamReader(conn.getErrorStream(), StandardCharsets.UTF_8));
            }

            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
            reader.close();

            if (code >= 200 && code < 300) {
                return new JSONObject(sb.toString());
            } else {
                throw new Exception("HTTP " + code + ": " + sb.toString());
            }
        } finally {
            if (conn != null) conn.disconnect();
        }
    }

    private static JSONObject post(Context ctx, String urlStr, JSONObject body, String token) throws Exception {
        HttpURLConnection conn = null;
        try {
            URL url = new URL(urlStr);
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setConnectTimeout(8000);
            conn.setReadTimeout(8000);
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json");
            if (token != null) {
                conn.setRequestProperty("Authorization", "Bearer " + token);
            }

            byte[] data = body.toString().getBytes(StandardCharsets.UTF_8);
            OutputStream os = conn.getOutputStream();
            os.write(data);
            os.close();

            int code = conn.getResponseCode();
            BufferedReader reader;
            if (code >= 200 && code < 300) {
                reader = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
            } else {
                reader = new BufferedReader(new InputStreamReader(conn.getErrorStream(), StandardCharsets.UTF_8));
            }

            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
            reader.close();

            if (code >= 200 && code < 300) {
                return new JSONObject(sb.toString());
            } else {
                String msg = sb.toString();
                try {
                    JSONObject err = new JSONObject(msg);
                    if (err.has("error")) msg = err.getString("error");
                } catch (Exception ignored) {
                }
                throw new Exception("HTTP " + code + ": " + msg);
            }
        } finally {
            if (conn != null) conn.disconnect();
        }
    }

    private static SharedPreferences prefs(Context ctx) {
        return ctx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
    }
}
