package com.meb.admin

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch

enum class Screen { LOGIN, DASHBOARD, LEADS }

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        ApiClient.allowNetworkOnMainThreadForDemo()
        setContent { MebTheme { App() } }
    }
}

@Composable
fun MebTheme(content: @Composable () -> Unit) =
    MaterialTheme(colorScheme = darkColorScheme(primary = Color(0xFFC9A86A))) { content() }

@Composable
fun App() {
    var screen by remember { mutableStateOf(Screen.LOGIN) }
    var userName by remember { mutableStateOf("") }
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()

    Scaffold(snackbarHost = { SnackbarHost(snackbar) }) { p ->
        Box(Modifier.padding(p)) {
            when (screen) {
                Screen.LOGIN -> LoginScreen { name -> userName = name; screen = Screen.DASHBOARD }
                Screen.DASHBOARD -> DashboardScreen(
                    name = userName,
                    onLeads = { screen = Screen.LEADS },
                    onLogout = { ApiClient.token = null; screen = Screen.LOGIN })
                Screen.LEADS -> LeadsScreen(onBack = { screen = Screen.DASHBOARD })
            }
        }
    }
}

@Composable
fun LoginScreen(onOk: (String) -> Unit) {
    var email by remember { mutableStateOf("admin@meb.local") }
    var pwd by remember { mutableStateOf("Admin123!") }
    var err by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.Center) {
        Text("MEB Admin", style = MaterialTheme.typography.headlineLarge)
        Spacer(Modifier.height(20.dp))
        OutlinedTextField(email, { email = it }, label = { Text("Email") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(pwd, { pwd = it }, label = { Text("Пароль") },
            visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth())
        if (err.isNotEmpty()) Text(err, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(top = 8.dp))
        Button(onClick = {
            ApiClient.login(email, pwd).onSuccess { onOk(it.optString("name", "Админ")) }
                .onFailure { err = it.message ?: "Ошибка" }
        }, modifier = Modifier.fillMaxWidth().padding(top = 16.dp)) { Text("Войти") }
    }
}

@Composable
fun DashboardScreen(name: String, onLeads: () -> Unit, onLogout: () -> Unit) {
    var summary by remember { mutableStateOf<JSONObject?>(null) }
    var health by remember { mutableStateOf<JSONObject?>(null) }
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

@Composable
fun LeadsScreen(onBack: () -> Unit) {
    var leads by remember { mutableStateOf<List<JSONObject>>(emptyList()) }
    LaunchedEffect(Unit) { leads = ApiClient.leads().getOrNull()?.let { a -> (0 until a.length()).map { a.getJSONObject(it) } } ?: emptyList() }
    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth()) {
            Text("Заявки (${leads.size})", style = MaterialTheme.typography.headlineSmall)
            TextButton(onClick = onBack) { Text("Назад") }
        }
        LazyColumn(Modifier.padding(top = 8.dp)) {
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
