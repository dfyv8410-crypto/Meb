package com.meb.admin

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import org.json.JSONArray
import java.security.MessageDigest

enum class Screen { LOCK, LOGIN, SETUP_PIN, DASHBOARD, LEADS }

class MainActivity : FragmentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        ApiClient.allowNetworkOnMainThreadForDemo()
        val start = if (PinManager.isPinSet(this)) Screen.LOCK else Screen.LOGIN
        setContent { MebTheme { App(start) } }
    }
}

@Composable
fun MebTheme(content: @Composable () -> Unit) =
    MaterialTheme(colorScheme = darkColorScheme(primary = Color(0xFFC9A86A))) { content() }

@Composable
fun App(start: Screen) {
    val ctx = LocalContext.current
    var screen by remember { mutableStateOf(start) }
    var userName by remember { mutableStateOf("") }
    Scaffold { p -> Box(Modifier.padding(p)) {
        when (screen) {
            Screen.LOCK -> LockScreen(onUnlocked = { screen = Screen.DASHBOARD })
            Screen.LOGIN -> LoginScreen(
                onOk = { name -> userName = name; screen = Screen.SETUP_PIN })
            Screen.SETUP_PIN -> SetupPinScreen(onDone = { screen = Screen.DASHBOARD })
            Screen.DASHBOARD -> DashboardScreen(
                name = userName,
                onLeads = { screen = Screen.LEADS },
                onLogout = { ApiClient.token = null
                    screen = if (PinManager.isPinSet(ctx)) Screen.LOCK else Screen.LOGIN })
            Screen.LEADS -> LeadsScreen(onBack = { screen = Screen.DASHBOARD })
        }
    }}
}

/* ============ LOCK (PIN + биометрия) ============ */
@Composable
fun LockScreen(onUnlocked: () -> Unit) {
    val ctx = LocalContext.current
    var pin by remember { mutableStateOf("") }
    var err by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { tryBiometric(ctx, onUnlocked) }
    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally) {
        Text("🔒 MEB Admin", style = MaterialTheme.typography.headlineMedium)
        Spacer(Modifier.height(16.dp))
        OutlinedTextField(pin,
            { if (it.length <= 6) { pin = it; err = false } },
            label = { Text("PIN-код") },
            isError = err,
            visualTransformation = PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(keyboardType = androidx.compose.ui.text.input.KeyboardType.NumberPassword),
            modifier = Modifier.fillMaxWidth())
        if (err) Text("Неверный PIN", color = MaterialTheme.colorScheme.error)
        Button(onClick = {
            if (PinManager.checkPin(ctx, pin)) onUnlocked() else err = true
        }, modifier = Modifier.fillMaxWidth().padding(top = 12.dp)) { Text("Разблокировать") }
        OutlinedButton(onClick = { tryBiometric(ctx, onUnlocked) },
            modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) { Text("Войти по биометрии") }
    }
}

fun tryBiometric(ctx: android.content.Context, onSuccess: () -> Unit) {
    val fm = (ctx as? FragmentActivity)?.fragmentManager ?: return
    val bm = BiometricManager.from(ctx)
    if (bm.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK)
        != BiometricManager.BIOMETRIC_SUCCESS) return
    val prompt = BiometricPrompt(fm, ContextCompat.getMainExecutor(ctx),
        object : BiometricPrompt.AuthenticationCallback() {
            override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                onSuccess()
            }
        })
    prompt.authenticate(BiometricPrompt.PromptInfo.Builder()
        .setTitle("Вход в MEB Admin")
        .setNegativeButtonText("Использовать PIN")
        .build())
}

/* ============ LOGIN + SETUP PIN ============ */
@Composable
fun LoginScreen(onOk: (String) -> Unit) {
    var email by remember { mutableStateOf("") }
    var pwd by remember { mutableStateOf("") }
    var err by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.Center) {
        Text("MEB Admin", style = MaterialTheme.typography.headlineLarge)
        Text("Премиальная мебель · панель управления",
            style = MaterialTheme.typography.bodySmall, color = Color.Gray)
        Spacer(Modifier.height(20.dp))
        OutlinedTextField(email, { email = it }, label = { Text("Email") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(pwd, { pwd = it }, label = { Text("Пароль") },
            visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth())
        if (err.isNotEmpty()) Text(err, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(top = 8.dp))
        Button(onClick = {
            ApiClient.login(email, pwd).onSuccess { onOk(it.optString("name", "Админ")) }
                .onFailure { err = it.message ?: "Ошибка сети" }
        }, modifier = Modifier.fillMaxWidth().padding(top = 16.dp)) { Text("Войти") }
    }
}

@Composable
fun SetupPinScreen(onDone: () -> Unit) {
    val ctx = LocalContext.current
    var pin by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.Center) {
        Text("Защитите приложение", style = MaterialTheme.typography.headlineSmall)
        Text("Установите PIN для быстрого входа. Биометрия подключится автоматически.",
            style = MaterialTheme.typography.bodySmall, color = Color.Gray)
        Spacer(Modifier.height(16.dp))
        OutlinedTextField(pin, { if (it.length <= 6) pin = it },
            label = { Text("PIN (4–6 цифр)") },
            visualTransformation = PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(keyboardType = androidx.compose.ui.text.input.KeyboardType.NumberPassword),
            modifier = Modifier.fillMaxWidth())
        Row(Modifier.fillMaxWidth().padding(top = 12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedButton(onClick = onDone, modifier = Modifier.weight(1f)) { Text("Пропустить") }
            Button(onClick = {
                if (pin.length >= 4) { PinManager.setPin(ctx, pin); onDone() }
            }, modifier = Modifier.weight(1f), enabled = pin.length >= 4) { Text("Сохранить") }
        }
    }
}

/* ============ DASHBOARD ============ */
@Composable
fun DashboardScreen(name: String, onLeads: () -> Unit, onLogout: () -> Unit) {
    var summary by remember { mutableStateOf<org.json.JSONObject?>(null) }
    var health by remember { mutableStateOf<org.json.JSONObject?>(null) }
    LaunchedEffect(Unit) {
        summary = ApiClient.summary().getOrNull()
        health = ApiClient.health().getOrNull()
    }
    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("Привет, $name", style = MaterialTheme.typography.headlineSmall)
            TextButton(onClick = onLogout) { Text("Выйти") }
        }
        Card(Modifier.fillMaxWidth().padding(top = 8.dp)) {
            Column(Modifier.padding(16.dp)) {
                Text("Система", style = MaterialTheme.typography.titleMedium)
                Text("API: ${health?.optString("status") ?: "—"} · v${health?.optString("version") ?: "—"}")
            }
        }
        Card(Modifier.fillMaxWidth().padding(top = 12.dp)) {
            Column(Modifier.padding(16.dp)) {
                Text("Аналитика", style = MaterialTheme.typography.titleMedium)
                val s = summary
                if (s == null) Text("Загрузка…") else Column {
                    Text("Заявки: ${s.optInt("leads")}")
                    Text("Проекты: ${s.optInt("projects")}")
                    Text("Каталог: ${s.optInt("catalog")}")
                    Text("Отзывы: ${s.optInt("reviews")}")
                }
            }
        }
        Button(onClick = onLeads, modifier = Modifier.fillMaxWidth().padding(top = 12.dp)) { Text("Заявки") }
    }
}

/* ============ LEADS с offline-кэшем ============ */
@Composable
fun LeadsScreen(onBack: () -> Unit) {
    val ctx = LocalContext.current
    var leads by remember { mutableStateOf<List<org.json.JSONObject>>(emptyList()) }
    var offline by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        val result = ApiClient.leads()
        result.onSuccess { arr ->
            offline = false
            PinManager.cacheLeads(ctx, arr.toString())
            leads = (0 until arr.length()).map { arr.getJSONObject(it) }
        }.onFailure {
            val cached = PinManager.loadCachedLeads(ctx)
            if (cached != null) {
                val arr = JSONArray(cached)
                leads = (0 until arr.length()).map { arr.getJSONObject(it) }
            }
            offline = true
        }
    }
    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth()) {
            Text(if (offline) "Заявки (офлайн)" else "Заявки (${leads.size})",
                style = MaterialTheme.typography.headlineSmall)
            TextButton(onClick = onBack) { Text("Назад") }
        }
        if (offline) Card(Modifier.fillMaxWidth().padding(vertical = 4.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFF4A3B1A))) {
            Text("Нет сети — показаны сохранённые данные", Modifier.padding(10.dp),
                style = MaterialTheme.typography.bodySmall, color = Color(0xFFC9A86A))
        }
        LazyColumn(Modifier.padding(top = 4.dp)) {
            items(leads) { l ->
                Card(Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                    Column(Modifier.padding(12.dp)) {
                        Text(l.optString("name"), style = MaterialTheme.typography.titleMedium)
                        Text(l.optString("phone"))
                        Text("Статус: ${l.optString("status")}", style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
    }
}
