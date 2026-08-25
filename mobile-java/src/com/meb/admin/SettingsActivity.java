package com.meb.admin;

import android.app.Activity;
import android.graphics.Color;
import android.graphics.Typeface;
import android.os.Bundle;
import android.text.InputType;
import android.view.Gravity;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

public class SettingsActivity extends Activity {

    private EditText urlInput;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        ScrollView root = createLayout();
        setContentView(root);
    }

    private ScrollView createLayout() {
        int bg = 0xFF0F0F0F;
        int accent = 0xFFC9A86A;

        ScrollView scroll = new ScrollView(this);
        scroll.setBackgroundColor(bg);

        LinearLayout outer = new LinearLayout(this);
        outer.setOrientation(LinearLayout.VERTICAL);
        outer.setPadding(dp(24), dp(24), dp(24), dp(24));

        TextView title = new TextView(this);
        title.setText("\u041D\u0430\u0441\u0442\u0440\u043E\u0439\u043A\u0438");
        title.setTextSize(22);
        title.setTextColor(accent);
        title.setTypeface(Typeface.DEFAULT_BOLD);

        TextView urlLabel = new TextView(this);
        urlLabel.setText("\u0410\u0434\u0440\u0435\u0441 \u0441\u0435\u0440\u0432\u0435\u0440\u0430");
        urlLabel.setTextSize(13);
        urlLabel.setTextColor(0xFF888888);
        urlLabel.setPadding(0, dp(20), 0, dp(8));

        urlInput = new EditText(this);
        urlInput.setInputType(InputType.TYPE_TEXT_VARIATION_URI);
        urlInput.setText(ApiClient.getBaseUrl(this));
        urlInput.setTextColor(Color.WHITE);
        urlInput.setTextSize(15);
        urlInput.setBackgroundColor(0xFF1A1A1A);
        urlInput.setPadding(dp(16), dp(14), dp(16), dp(14));

        Button saveBtn = new Button(this);
        saveBtn.setText("\u0421\u043E\u0445\u0440\u0430\u043D\u0438\u0442\u044C");
        saveBtn.setBackgroundColor(accent);
        saveBtn.setTextColor(0xFF0F0F0F);
        saveBtn.setTypeface(Typeface.DEFAULT_BOLD);
        LinearLayout.LayoutParams saveLp = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, dp(50));
        saveLp.topMargin = dp(24);

        final TextView info = new TextView(this);
        info.setText("\u0422\u0435\u043A\u0443\u0449\u0438\u0439 URL:\n" + ApiClient.getBaseUrl(this));
        info.setTextSize(12);
        info.setTextColor(0xFF666666);
        info.setPadding(0, dp(16), 0, dp(8));

        outer.addView(title);
        outer.addView(urlLabel);
        outer.addView(urlInput);
        outer.addView(saveBtn, saveLp);
        outer.addView(info);

        saveBtn.setOnClickListener(new android.view.View.OnClickListener() {
            @Override
            public void onClick(android.view.View v) {
                String url = urlInput.getText().toString().trim();
                if (!url.isEmpty()) {
                    if (url.endsWith("/")) url = url.substring(0, url.length() - 1);
                    ApiClient.setBaseUrl(SettingsActivity.this, url);
                    info.setText("\u0422\u0435\u043A\u0443\u0449\u0438\u0439 URL:\n" + url);
                }
            }
        });

        scroll.addView(outer);
        return scroll;
    }

    private int dp(int dp) {
        return (int) (dp * getResources().getDisplayMetrics().density);
    }
}
