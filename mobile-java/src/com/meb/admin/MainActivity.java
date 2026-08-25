package com.meb.admin;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.DialogInterface;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.Typeface;
import android.net.http.SslError;
import android.os.Bundle;
import android.view.Gravity;
import android.view.KeyEvent;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.SslErrorHandler;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;

public class MainActivity extends Activity {

    private WebView webView;
    private ProgressBar progressBar;
    private LinearLayout errorOverlay;
    private TextView statusText;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        if (!ApiClient.isLoggedIn(this)) {
            startActivity(new Intent(this, LoginActivity.class));
            finish();
            return;
        }

        LinearLayout root = createLayout();
        setContentView(root);
        loadAdminPanel();
    }

    @Override
    public void onBackPressed() {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
        } else {
            showExitDialog();
        }
    }

    private LinearLayout createLayout() {
        int bg = 0xFF0F0F0F;
        int accent = 0xFFC9A86A;

        LinearLayout outer = new LinearLayout(this);
        outer.setOrientation(LinearLayout.VERTICAL);
        outer.setBackgroundColor(bg);

        LinearLayout toolbar = new LinearLayout(this);
        toolbar.setOrientation(LinearLayout.HORIZONTAL);
        toolbar.setGravity(Gravity.CENTER_VERTICAL);
        toolbar.setBackgroundColor(0xFF1A1A1A);
        toolbar.setPadding(dp(12), dp(8), dp(12), dp(8));
        LinearLayout.LayoutParams toolLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, dp(48));

        TextView title = new TextView(this);
        title.setText("MEB");
        title.setTextSize(18);
        title.setTextColor(accent);
        title.setTypeface(Typeface.DEFAULT_BOLD);
        title.setLetterSpacing(0.2f);

        TextView userLabel = new TextView(this);
        userLabel.setText(ApiClient.getUserName(this));
        userLabel.setTextSize(12);
        userLabel.setTextColor(0xFF888888);
        LinearLayout.LayoutParams userLp = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1);
        userLp.leftMargin = dp(12);

        Button settingsBtn = new Button(this);
        settingsBtn.setText("\u2699");
        settingsBtn.setTextSize(18);
        settingsBtn.setBackgroundColor(Color.TRANSPARENT);
        settingsBtn.setTextColor(0xFFAAAAAA);
        settingsBtn.setPadding(dp(8), 0, dp(4), 0);
        settingsBtn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                showSettingsMenu();
            }
        });

        toolbar.addView(title);
        toolbar.addView(userLabel, userLp);
        toolbar.addView(settingsBtn);

        progressBar = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progressBar.setIndeterminate(false);
        progressBar.setMax(100);
        progressBar.setVisibility(View.GONE);
        LinearLayout.LayoutParams progLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, dp(3));

        webView = new WebView(this);
        webView.setBackgroundColor(0xFF0F0F0F);
        LinearLayout.LayoutParams webLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, 0, 1);

        errorOverlay = new LinearLayout(this);
        errorOverlay.setOrientation(LinearLayout.VERTICAL);
        errorOverlay.setGravity(Gravity.CENTER);
        errorOverlay.setBackgroundColor(bg);
        errorOverlay.setVisibility(View.GONE);

        TextView errIcon = new TextView(this);
        errIcon.setText("\u26A0");
        errIcon.setTextSize(40);
        errIcon.setTextColor(0xFFE57373);
        errIcon.setGravity(Gravity.CENTER);

        TextView errText = new TextView(this);
        errText.setText("\u041D\u0435\u0442 \u0441\u043E\u0435\u0434\u0438\u043D\u0435\u043D\u0438\u044F \u0441 \u0441\u0435\u0440\u0432\u0435\u0440\u043E\u043C");
        errText.setTextSize(16);
        errText.setTextColor(0xFFAAAAAA);
        errText.setGravity(Gravity.CENTER);

        Button retryBtn = new Button(this);
        retryBtn.setText("\u041F\u043E\u0432\u0442\u043E\u0440\u0438\u0442\u044C");
        retryBtn.setBackgroundColor(accent);
        retryBtn.setTextColor(0xFF0F0F0F);
        retryBtn.setTypeface(Typeface.DEFAULT_BOLD);
        LinearLayout.LayoutParams retryLp = new LinearLayout.LayoutParams(dp(180), dp(44));
        retryLp.topMargin = dp(16);

        errorOverlay.addView(errIcon);
        errorOverlay.addView(errText);
        errorOverlay.addView(retryBtn, retryLp);

        retryBtn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                loadAdminPanel();
            }
        });

        statusText = new TextView(this);
        statusText.setTextSize(11);
        statusText.setTextColor(0xFF555555);
        statusText.setGravity(Gravity.CENTER);
        statusText.setBackgroundColor(0xFF1A1A1A);
        statusText.setPadding(dp(8), dp(4), dp(8), dp(4));
        statusText.setVisibility(View.GONE);

        outer.addView(toolbar, toolLp);
        outer.addView(progressBar, progLp);
        outer.addView(webView, webLp);
        outer.addView(errorOverlay, new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, 0, 1));
        outer.addView(statusText);

        setupWebView();
        return outer;
    }

    private void setupWebView() {
        WebSettings ws = webView.getSettings();
        ws.setJavaScriptEnabled(true);
        ws.setDomStorageEnabled(true);
        ws.setDatabaseEnabled(true);
        ws.setAllowFileAccess(true);
        ws.setAllowContentAccess(true);
        ws.setCacheMode(WebSettings.LOAD_DEFAULT);
        ws.setUseWideViewPort(true);
        ws.setLoadWithOverviewMode(true);
        ws.setBuiltInZoomControls(true);
        ws.setDisplayZoomControls(false);
        ws.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        ws.setUserAgentString(ws.getUserAgentString() + " MEBAdminAndroid/1.0");

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                if (newProgress < 100) {
                    progressBar.setVisibility(View.VISIBLE);
                    progressBar.setProgress(newProgress);
                } else {
                    progressBar.setVisibility(View.GONE);
                }
            }
        });

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                errorOverlay.setVisibility(View.GONE);
                statusText.setText(url);
                statusText.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                injectJsBridge(view);
            }

            @Override
            public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
                showError();
            }

            @Override
            public void onReceivedSslError(WebView view, final SslErrorHandler handler, SslError error) {
                new AlertDialog.Builder(MainActivity.this)
                    .setTitle("SSL \u043E\u0448\u0438\u0431\u043A\u0430")
                    .setMessage("\u041F\u0440\u043E\u0438\u0437\u0432\u043E\u043B\u0438\u0442\u044C \u043F\u043E\u0434\u043A\u043B\u044E\u0447\u0435\u043D\u0438\u0435 \u043A \u044D\u0442\u043E\u043C\u0443 \u0441\u0435\u0440\u0432\u0435\u0440\u0443?")
                    .setPositiveButton("\u0414\u0430", new DialogInterface.OnClickListener() {
                        @Override
                        public void onClick(DialogInterface d, int w) {
                            handler.proceed();
                        }
                    })
                    .setNegativeButton("\u041D\u0435\u0442", new DialogInterface.OnClickListener() {
                        @Override
                        public void onClick(DialogInterface d, int w) {
                            handler.cancel();
                        }
                    })
                    .show();
            }
        });
    }

    private void loadAdminPanel() {
        errorOverlay.setVisibility(View.GONE);
        webView.setVisibility(View.VISIBLE);
        String adminUrl = ApiClient.getBaseUrl(this) + "/admin";
        webView.loadUrl(adminUrl);
    }

    private void injectJsBridge(final WebView view) {
        final String token = ApiClient.getToken(this);
        final String baseUrl = ApiClient.getBaseUrl(this);
        if (token == null) return;

        String js = "javascript:(function(){" +
            "window.__MEB_ANDROID__ = true;" +
            "window.__MEB_TOKEN__ = '" + escapeJs(token) + "';" +
            "window.__MEB_BASE_URL__ = '" + escapeJs(baseUrl) + "';" +
            "window.__MEB_GET_TOKEN__ = function() { return '" + escapeJs(token) + "'; };" +
            "window.__MEB_GET_URL__ = function() { return '" + escapeJs(baseUrl) + "'; };" +
            "if(window.onMebAndroidBridge) window.onMebAndroidBridge();" +
            "})()";

        view.evaluateJavascript(js, null);
    }

    private String escapeJs(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\").replace("'", "\\'").replace("\n", "\\n").replace("\r", "");
    }

    private void showError() {
        webView.setVisibility(View.GONE);
        errorOverlay.setVisibility(View.VISIBLE);
    }

    private void showSettingsMenu() {
        final String[] items = {
            "\u0421\u043C\u0435\u043D\u0438\u0442\u044C URL \u0441\u0435\u0440\u0432\u0435\u0440\u0430",
            "\u0421\u043C\u0435\u043D\u0438\u0442\u044C PIN-\u043A\u043E\u0434",
            "\u0412\u044B\u0439\u0442\u0438"
        };

        new AlertDialog.Builder(this)
            .setTitle("\u041D\u0430\u0441\u0442\u0440\u043E\u0439\u043A\u0438")
            .setItems(items, new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface d, int which) {
                    switch (which) {
                        case 0: showChangeUrlDialog(); break;
                        case 1: showChangePinDialog(); break;
                        case 2: confirmLogout(); break;
                    }
                }
            })
            .show();
    }

    private void showChangeUrlDialog() {
        LinearLayout dialog = new LinearLayout(this);
        dialog.setOrientation(LinearLayout.VERTICAL);
        dialog.setPadding(dp(20), dp(16), dp(20), dp(8));
        dialog.setBackgroundColor(0xFF1A1A1A);

        TextView title = new TextView(this);
        title.setText("\u0410\u0434\u0440\u0435\u0441 \u0441\u0435\u0440\u0432\u0435\u0440\u0430");
        title.setTextSize(16);
        title.setTextColor(0xFFC9A86A);

        final EditText urlField = new EditText(this);
        urlField.setText(ApiClient.getBaseUrl(this));
        urlField.setTextColor(Color.WHITE);
        urlField.setTextSize(14);
        urlField.setBackgroundColor(0xFF252525);
        urlField.setPadding(dp(12), dp(10), dp(12), dp(10));

        dialog.addView(title);
        dialog.addView(urlField);

        new AlertDialog.Builder(this)
            .setView(dialog)
            .setPositiveButton("\u0421\u043E\u0445\u0440\u0430\u043D\u0438\u0442\u044C", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface d, int w) {
                    String url = urlField.getText().toString().trim();
                    if (!url.isEmpty()) {
                        if (url.endsWith("/")) url = url.substring(0, url.length() - 1);
                        ApiClient.setBaseUrl(MainActivity.this, url);
                        loadAdminPanel();
                    }
                }
            })
            .setNegativeButton("\u041E\u0442\u043C\u0435\u043D\u0430", null)
            .show();
    }

    private void showChangePinDialog() {
        LinearLayout dialog = new LinearLayout(this);
        dialog.setOrientation(LinearLayout.VERTICAL);
        dialog.setPadding(dp(20), dp(16), dp(20), dp(8));
        dialog.setBackgroundColor(0xFF1A1A1A);

        TextView title = new TextView(this);
        title.setText("\u041D\u043E\u0432\u044B\u0439 PIN-\u043A\u043E\u0434");
        title.setTextSize(16);
        title.setTextColor(0xFFC9A86A);

        final EditText pinField = new EditText(this);
        pinField.setInputType(android.text.InputType.TYPE_CLASS_NUMBER | android.text.InputType.TYPE_NUMBER_VARIATION_PASSWORD);
        pinField.setHint("4+\u0446\u0438\u0444\u0440");
        pinField.setHintTextColor(0xFF555555);
        pinField.setTextColor(Color.WHITE);
        pinField.setTextSize(18);
        pinField.setGravity(Gravity.CENTER);
        pinField.setBackgroundColor(0xFF252525);
        pinField.setPadding(dp(12), dp(10), dp(12), dp(10));

        dialog.addView(title);
        dialog.addView(pinField);

        new AlertDialog.Builder(this)
            .setView(dialog)
            .setPositiveButton("\u0421\u043E\u0445\u0440\u0430\u043D\u0438\u0442\u044C", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface d, int w) {
                    String pin = pinField.getText().toString().trim();
                    if (pin.length() >= 4) {
                        PinManager.setPin(MainActivity.this, pin);
                    }
                }
            })
            .setNegativeButton("\u041E\u0442\u043C\u0435\u043D\u0430", null)
            .show();
    }

    private void confirmLogout() {
        new AlertDialog.Builder(this)
            .setTitle("\u0412\u044B\u0439\u0442\u0438?")
            .setMessage("\u0412\u044B \u0431\u0443\u0434\u0435\u0442\u0435 \u043F\u0435\u0440\u0435\u043D\u0430\u043F\u0440\u0430\u0432\u043B\u0435\u043D\u044B \u043D\u0430 \u044D\u043A\u0440\u0430\u043D \u0432\u0445\u043E\u0434\u0430.")
            .setPositiveButton("\u0412\u044B\u0439\u0442\u0438", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface d, int w) {
                    ApiClient.clearAuth(MainActivity.this);
                    webView.clearHistory();
                    webView.clearCache(true);
                    webView.loadUrl("about:blank");
                    startActivity(new Intent(MainActivity.this, PinLockActivity.class));
                    finish();
                }
            })
            .setNegativeButton("\u041E\u0442\u043C\u0435\u043D\u0430", null)
            .show();
    }

    private void showExitDialog() {
        new AlertDialog.Builder(this)
            .setTitle("MEB Admin")
            .setMessage("\u0417\u0430\u043A\u0440\u044B\u0442\u044C \u043F\u0440\u0438\u043B\u043E\u0436\u0435\u043D\u0438\u0435?")
            .setPositiveButton("\u0412\u044B\u0439\u0442\u0438", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface d, int w) {
                    finish();
                }
            })
            .setNegativeButton("\u041D\u0435\u0442", null)
            .show();
    }

    private int dp(int dp) {
        return (int) (dp * getResources().getDisplayMetrics().density);
    }
}
