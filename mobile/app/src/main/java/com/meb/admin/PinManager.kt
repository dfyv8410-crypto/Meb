package com.meb.admin

import android.content.Context
import java.security.MessageDigest

object PinManager {
    private const val PREFS = "meb_secure"
    private fun prefs(ctx: Context) = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun isPinSet(ctx: Context) = prefs(ctx).getString("pin_hash", null) != null

    fun setPin(ctx: Context, pin: String) =
        prefs(ctx).edit().putString("pin_hash", sha(pin)).apply()

    fun checkPin(ctx: Context, pin: String): Boolean =
        prefs(ctx).getString("pin_hash", null) == sha(pin)

    fun clear(ctx: Context) = prefs(ctx).edit().remove("pin_hash").apply()

    private fun sha(s: String): String =
        MessageDigest.getInstance("SHA-256").digest(s.toByteArray())
            .joinToString("") { "%02x".format(it) }

    fun cacheLeads(ctx: Context, json: String) {
        ctx.openFileOutput("leads_cache.json", Context.MODE_PRIVATE).use { it.write(json.toByteArray()) }
    }

    fun loadCachedLeads(ctx: Context): String? = try {
        ctx.openFileInput("leads_cache.json").bufferedReader().use { it.readText() }
    } catch (e: Exception) { null }
}
