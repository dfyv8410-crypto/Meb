package com.meb.admin;

import android.app.Activity;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.Typeface;
import android.os.Bundle;
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

public class PinLockActivity extends Activity {

    private EditText pinInput;
    private TextView errorText;
    private ProgressBar progress;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        ApiClient.init();

        if (!PinManager.isPinSet(this)) {
            startActivity(new Intent(this, LoginActivity.class));
            finish();
            return;
        }

        ScrollView root = createLayout();
        setContentView(root);
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (!PinManager.isPinSet(this) && !ApiClient.isLoggedIn(this)) {
            startActivity(new Intent(this, LoginActivity.class));
            finish();
        }
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
        outer.setPadding(dp(32), dp(48), dp(32), dp(32));

        TextView logo = new TextView(this);
        logo.setText("MEB");
        logo.setTextSize(36);
        logo.setTextColor(accent);
        logo.setTypeface(Typeface.DEFAULT_BOLD);
        logo.setGravity(Gravity.CENTER);
        logo.setLetterSpacing(0.3f);

        TextView subtitle = new TextView(this);
        subtitle.setText("Admin Panel");
        subtitle.setTextSize(14);
        subtitle.setTextColor(textDim);
        subtitle.setGravity(Gravity.CENTER);
        subtitle.setPadding(0, dp(4), 0, dp(40));

        TextView lockIcon = new TextView(this);
        lockIcon.setText("\uD83D\uDD12");
        lockIcon.setTextSize(40);
        lockIcon.setGravity(Gravity.CENTER);

        TextView prompt = new TextView(this);
        prompt.setText("\u0412\u0432\u0435\u0434\u0438\u0442\u0435 PIN-\u043A\u043E\u0434");
        prompt.setTextSize(16);
        prompt.setTextColor(textLight);
        prompt.setGravity(Gravity.CENTER);
        prompt.setPadding(0, dp(16), 0, dp(24));

        pinInput = new EditText(this);
        pinInput.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_VARIATION_PASSWORD);
        pinInput.setHint("\u0426\u0438\u0444\u0440\u044B");
        pinInput.setHintTextColor(textDim);
        pinInput.setTextColor(Color.WHITE);
        pinInput.setTextSize(24);
        pinInput.setGravity(Gravity.CENTER);
        pinInput.setLetterSpacing(0.5f);
        pinInput.setBackgroundColor(0xFF1A1A1A);
        pinInput.setPadding(dp(16), dp(14), dp(16), dp(14));
        LinearLayout.LayoutParams pinLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        pinLp.bottomMargin = dp(16);
        pinInput.setLayoutParams(pinLp);

        errorText = new TextView(this);
        errorText.setTextColor(0xFFE57373);
        errorText.setTextSize(13);
        errorText.setGravity(Gravity.CENTER);
        errorText.setVisibility(View.GONE);

        progress = new ProgressBar(this);
        progress.setVisibility(View.GONE);

        Button unlockBtn = new Button(this);
        unlockBtn.setText("\u0420\u0430\u0437\u0431\u043B\u043E\u043A\u0438\u0440\u043E\u0432\u0430\u0442\u044C");
        stylePrimaryButton(unlockBtn, accent);
        unlockBtn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                attemptUnlock();
            }
        });

        LinearLayout.LayoutParams btnLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, dp(50));
        btnLp.topMargin = dp(8);

        outer.addView(logo);
        outer.addView(subtitle);
        outer.addView(lockIcon);
        outer.addView(prompt);
        outer.addView(pinInput, new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        outer.addView(errorText);
        outer.addView(progress);
        outer.addView(unlockBtn, btnLp);

        scroll.addView(outer);
        return scroll;
    }

    private void attemptUnlock() {
        String pin = pinInput.getText().toString().trim();
        if (pin.length() < 4) {
            showError("\u041C\u0438\u043D\u0438\u043C\u0443\u043C 4 \u0446\u0438\u0444\u0440\u044B");
            return;
        }

        progress.setVisibility(View.VISIBLE);
        errorText.setVisibility(View.GONE);

        if (PinManager.checkPin(this, pin)) {
            progress.setVisibility(View.GONE);
            startActivity(new Intent(this, MainActivity.class));
            finish();
        } else {
            progress.setVisibility(View.GONE);
            showError("\u041D\u0435\u0432\u0435\u0440\u043D\u044B\u0439 PIN");
            pinInput.setText("");
        }
    }

    private void showError(String msg) {
        errorText.setText(msg);
        errorText.setVisibility(View.VISIBLE);
    }

    private void stylePrimaryButton(Button btn, int accent) {
        btn.setBackgroundColor(accent);
        btn.setTextColor(0xFF0F0F0F);
        btn.setTextSize(16);
        btn.setTypeface(Typeface.DEFAULT_BOLD);
    }

    private int dp(int dp) {
        return (int) (dp * getResources().getDisplayMetrics().density);
    }
}
