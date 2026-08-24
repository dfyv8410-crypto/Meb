package com.meb.admin
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.foundation.layout.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
class MainActivity: ComponentActivity(){
  override fun onCreate(s:Bundle?){
    super.onCreate(s)
    setContent{
      MaterialTheme{
        var screen by remember{ mutableStateOf("dashboard")}
        Scaffold(bottomBar={
          NavigationBar{ NavigationBarItem(selected=screen=="dashboard",onClick={screen="dashboard"},label={Text("Главная")},icon={Text("🏠")})
            NavigationBarItem(selected=screen=="leads",onClick={screen="leads"},label={Text("Заявки")},icon={Text("📩")})
            NavigationBarItem(selected=screen=="projects",onClick={screen="projects"},label={Text("Проекты")},icon={Text("🏗️")})}
        }){ p->
          Column(Modifier.padding(p).padding(16.dp)){
            Text("MEB Admin", style=MaterialTheme.typography.headlineMedium)
            Text("API: /api/v1  •  Biometric + PIN готов к подключению", style=MaterialTheme.typography.bodySmall)
            Spacer(Modifier.height(12.dp))
            Card{ Column(Modifier.padding(16.dp)){ Text("Экран: $screen"); Text("Подключите Retrofit к вашему домену.")}}
          }
        }
      }
    }
  }
}
