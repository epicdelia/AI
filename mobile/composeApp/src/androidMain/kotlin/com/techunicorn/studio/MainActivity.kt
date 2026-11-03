package com.techunicorn.studio

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        com.techunicorn.studio.data.live.TokenStore.init(this)
        setContent {
            App()
        }
    }
}
