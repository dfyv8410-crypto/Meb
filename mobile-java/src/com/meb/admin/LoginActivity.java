package com.meb.admin;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.DialogInterface;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.Typeface;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.TextView;

public class LoginActivity extends Activity {

    private EditText emailInput;
    private EditText passwordInput;
    private TextView errorText;
    private ProgressBar progress;
    private Button loginBtn;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        ApiClient.init();

        if (ApiClient.isLoggedIn(this)) {
            startActivity(new Intent(this, MainActivity.class));
            finish();
            return;
        }

        ScrollView root = createLayout();
        setContentView(root);
    }

    private ScrollView createLayout() {
        int bg = 0xFF0F0F0F;
        int accent = 0xFFC9A86A;
        int textLight = 0xFFCCCCCC;
        int textDim = 0xFF888888;

        ScrollView scroll = new ScrollView(this);
        scroll.setBackgroundColor(bg);

        LinearLayout outer = new LinearLayout(this);
        outer.setOrientation(LinearLayout.VERTICAL);
        outer.setGravity(Gravity.CENTER_HORIZONTAL);
        outer.setPadding(dp(32), dp(60), dp(32), dp(32));

        TextView logo = new TextView(this);
        logo.setText("MEB");
        logo.setTextSize(40);
        logo.setTextColor(accent);
        logo.setTypeface(Typeface.DEFAULT_BOLD);
        logo.setGravity(Gravity.CENTER);
        logo.setLetterSpacing(0.3f);

        TextView subtitle = new TextView(this);
        subtitle.setText("\u041F\u0440\u0435\u043C\u0438\u0430\u043B\u044C\u043D\u0430\u044F \u043C\u0435\u0431\u0435\u043B\u044C \u00B7 \u043F\u0430\u043D\u0435\u043B\u044C \u0443\u043F\u0440\u0430\u0432\u043B\u0435\u043D\u0438\u044F");
        subtitle.setTextSize(12);
        subtitle.setTextColor(textDim);
        subtitle.setGravity(Gravity.CENTER);
        subtitle.setPadding(0, dp(4), 0, dp(48));

        TextView emailLabel = new TextView(this);
        emailLabel.setText("Email");
        emailLabel.setTextSize(12);
        emailLabel.setTextColor(textDim);

        emailInput = new EditText(this);
        emailInput.setInputType(InputType.TYPE_TEXT_VARIATION_EMAIL_ADDRESS);
        emailInput.setHint("admin@meb.com");
        emailInput.setHintTextColor(0xFF555555);
        emailInput.setTextColor(Color.WHITE);
        emailInput.setTextSize(15);
        emailInput.setBackgroundColor(0xFF1A1A1A);
        emailInput.setPadding(dp(16), dp(14), dp(16), dp(14));
        LinearLayout.LayoutParams emailLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        emailLp.bottomMargin = dp(16);

        TextView passLabel = new TextView(this);
        passLabel.setText("\u041F\u0430\u0440\u043E\u043B\u044C");
        passLabel.setTextSize(12);
        passLabel.setTextColor(textDim);

        passwordInput = new EditText(this);
        passwordInput.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD);
        passwordInput.setHint("\u2022\u2022\u2022\u2022\u2022\u2022");
        passwordInput.setHintTextColor(0xFF555555);
        passwordInput.setTextColor(Color.WHITE);
        passwordInput.setTextSize(15);
        passwordInput.setBackgroundColor(0xFF1A1A1A);
        passwordInput.setPadding(dp(16), dp(14), dp(16), dp(14));
        LinearLayout.LayoutParams passLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        passLp.bottomMargin = dp(8);

        errorText = new TextView(this);
        errorText.setTextColor(0xFFE57373);
        errorText.setTextSize(13);
        errorText.setGravity(Gravity.CENTER);
        errorText.setPadding(0, dp(4), 0, dp(4));
        errorText.setVisibility(View.GONE);

        progress = new ProgressBar(this);
        progress.setVisibility(View.GONE);

        loginBtn = new Button(this);
        loginBtn.setText("\u0412\u043E\u0439\u0442\u0438");
        loginBtn.setBackgroundColor(accent);
        loginBtn.setTextColor(0xFF0F0F0F);
        loginBtn.setTextSize(16);
        loginBtn.setTypeface(Typeface.DEFAULT_BOLD);
        LinearLayout.LayoutParams loginLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, dp(50));
        loginLp.topMargin = dp(20);

        TextView settingsLink = new TextView(this);
        settingsLink.setText("\u041D\u0430\u0441\u0442\u0440\u043E\u0439\u043A\u0430 \u0441\u0435\u0440\u0432\u0435\u0440\u0430");
        settingsLink.setTextSize(12);
        settingsLink.setTextColor(accent);
        settingsLink.setGravity(Gravity.CENTER);
        settingsLink.setPadding(0, dp(20), 0, 0);
        settingsLink.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                showServerDialog();
            }
        });

        TextView version = new TextView(this);
        version.setText("v1.0.0");
        version.setTextSize(11);
        version.setTextColor(0xFF444444);
        version.setGravity(Gravity.CENTER);
        version.setPadding(0, dp(24), 0, 0);

        outer.addView(logo);
        outer.addView(subtitle);
        outer.addView(emailLabel);
        outer.addView(emailInput, emailLp);
        outer.addView(passLabel);
        outer.addView(passwordInput, passLp);
        outer.addView(errorText);
        outer.addView(progress);
        outer.addView(loginBtn, loginLp);
        outer.addView(settingsLink);
        outer.addView(version);

        scroll.addView(outer);
        return scroll;
    }

    private void attemptLogin() {
        String email = emailInput.getText().toString().trim();
        String password = passwordInput.getText().toString().trim();

        if (email.isEmpty() || password.isEmpty()) {
            showError("\u0417\u0430\u043F\u043E\u043B\u043D\u0438\u0442\u0435 \u0432\u0441\u0435 \u043F\u043E\u043B\u044F");
            return;
        }

        progress.setVisibility(View.VISIBLE);
        errorText.setVisibility(View.GONE);
        loginBtn.setEnabled(false);

        final String e = email;
        final String p = password;

        new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    final org.json.JSONObject result = ApiClient.login(LoginActivity.this, e, p);
                    new Handler(Looper.getMainLooper()).post(new Runnable() {
                        @Override
                        public void run() {
                            progress.setVisibility(View.GONE);
                            loginBtn.setEnabled(true);
                            if (result != null && result.has("token")) {
                                showPinSetupDialog();
                            } else {
                                showError("\u041D\u0435\u0432\u0435\u0440\u043D\u044B\u0439 \u043E\u0442\u0432\u0435\u0442 \u0441\u0435\u0440\u0432\u0435\u0440\u0430");
                            }
                        }
                    });
                } catch (final Exception ex) {
                    new Handler(Looper.getMainLooper()).post(new Runnable() {
                        @Override
                        public void run() {
                            progress.setVisibility(View.GONE);
                            loginBtn.setEnabled(true);
                            showError(ex.getMessage() != null ? ex.getMessage() : "\u041E\u0448\u0438\u0431\u043A\u0430 \u0441\u0435\u0442\u0438");
                        }
                    });
                }
            }
        }).start();
    }

    private void showPinSetupDialog() {
        LinearLayout dialog = new LinearLayout(this);
        dialog.setOrientation(LinearLayout.VERTICAL);
        dialog.setPadding(dp(20), dp(16), dp(20), dp(8));
        dialog.setBackgroundColor(0xFF1A1A1A);

        TextView title = new TextView(this);
        title.setText("\u0423\u0441\u0442\u0430\u043D\u043E\u0432\u043A\u0430 PIN-\u043A\u043E\u0434\u0430");
        title.setTextSize(16);
        title.setTextColor(0xFFC9A86A);

        TextView desc = new TextView(this);
        desc.setText("\u0417\u0430\u0449\u0438\u0442\u0438\u0442\u0435 \u043F\u0440\u0438\u043B\u043E\u0436\u0435\u043D\u0438\u0435 4-\u0437\u043D\u0430\u0447\u043D\u044B\u043C PIN-\u043A\u043E\u0434\u043E\u043C.");
        desc.setTextSize(13);
        desc.setTextColor(0xFFAAAAAA);
        desc.setPadding(0, dp(8), 0, dp(12));

        final EditText pinField = new EditText(this);
        pinField.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_VARIATION_PASSWORD);
        pinField.setHint("PIN (4+\u0446\u0438\u0444\u0440)");
        pinField.setHintTextColor(0xFF555555);
        pinField.setTextColor(Color.WHITE);
        pinField.setTextSize(18);
        pinField.setGravity(Gravity.CENTER);
        pinField.setBackgroundColor(0xFF252525);
        pinField.setPadding(dp(12), dp(10), dp(12), dp(10));

        dialog.addView(title);
        dialog.addView(desc);
        dialog.addView(pinField);

        new AlertDialog.Builder(this)
            .setView(dialog)
            .setPositiveButton("\u0421\u043E\u0445\u0440\u0430\u043D\u0438\u0442\u044C", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface d, int w) {
                    String pin = pinField.getText().toString().trim();
                    if (pin.length() >= 4) {
                        PinManager.setPin(LoginActivity.this, pin);
                    }
                    goToMain();
                }
            })
            .setNegativeButton("\u041F\u0440\u043E\u043F\u0443\u0441\u0442\u0438\u0442\u044C", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface d, int w) {
                    goToMain();
                }
            })
            .setCancelable(false)
            .show();
    }

    private void goToMain() {
        startActivity(new Intent(this, MainActivity.class));
        finish();
    }

    private void showServerDialog() {
        LinearLayout dialog = new LinearLayout(this);
        dialog.setOrientation(LinearLayout.VERTICAL);
        dialog.setPadding(dp(20), dp(16), dp(20), dp(8));
        dialog.setBackgroundColor(0xFF1A1A1A);

        TextView title = new TextView(this);
        title.setText("\u0410\u0434\u0440\u0435\u0441 \u0441\u0435\u0440\u0432\u0435\u0440\u0430");
        title.setTextSize(16);
        title.setTextColor(0xFFC9A86A);

        TextView desc = new TextView(this);
        desc.setText("URL \u0431\u0435\u0437 \u043F\u0440\u043E\u0442\u043E\u043A\u043E\u043B\u0430 (http/https)");
        desc.setTextSize(13);
        desc.setTextColor(0xFFAAAAAA);
        desc.setPadding(0, dp(8), 0, dp(12));

        final EditText urlField = new EditText(this);
        urlField.setInputType(InputType.TYPE_TEXT_VARIATION_URI);
        urlField.setText(ApiClient.getBaseUrl(this));
        urlField.setTextColor(Color.WHITE);
        urlField.setTextSize(14);
        urlField.setBackgroundColor(0xFF252525);
        urlField.setPadding(dp(12), dp(10), dp(12), dp(10));

        dialog.addView(title);
        dialog.addView(desc);
        dialog.addView(urlField);

        new AlertDialog.Builder(this)
            .setView(dialog)
            .setPositiveButton("\u0421\u043E\u0445\u0440\u0430\u043D\u0438\u0442\u044C", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface d, int w) {
                    String url = urlField.getText().toString().trim();
                    if (!url.isEmpty()) {
                        if (url.endsWith("/")) url = url.substring(0, url.length() - 1);
                        ApiClient.setBaseUrl(LoginActivity.this, url);
                    }
                }
            })
            .setNegativeButton("\u041E\u0442\u043C\u0435\u043D\u0430", null)
            .show();
    }

    private void showError(String msg) {
        errorText.setText(msg);
        errorText.setVisibility(View.VISIBLE);
    }

    private int dp(int dp) {
        return (int) (dp * getResources().getDisplayMetrics().density);
    }
}
