package com.meb.admin;

import android.content.Context;
import android.content.SharedPreferences;
import java.security.MessageDigest;

public class PinManager {

    private static final String PREFS = "meb_secure";

    public static boolean isPinSet(Context ctx) {
        return prefs(ctx).getString("pin_hash", null) != null;
    }

    public static void setPin(Context ctx, String pin) {
        prefs(ctx).edit().putString("pin_hash", sha(pin)).apply();
    }

    public static boolean checkPin(Context ctx, String pin) {
        String stored = prefs(ctx).getString("pin_hash", null);
        return stored != null && stored.equals(sha(pin));
    }

    public static void clearPin(Context ctx) {
        prefs(ctx).edit().remove("pin_hash").apply();
    }

    private static SharedPreferences prefs(Context ctx) {
        return ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    private static String sha(String s) {
        try {
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            byte[] digest = md.digest(s.getBytes("UTF-8"));
            StringBuilder sb = new StringBuilder();
            for (byte b : digest) {
                sb.append(String.format("%02x", b));
            }
            return sb.toString();
        } catch (Exception e) {
            return s;
        }
    }
}
