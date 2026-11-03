package com.techunicorn.studio.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.screens.auth.AuthMode
import com.techunicorn.studio.ui.screens.auth.AuthScreen

/**
 * Mobile Authentication Modal Wrapper.
 * Bridges into the full flat-design AuthScreen with edge-to-edge modal presentation.
 */
@Composable
fun AuthModal(
    onDismiss: () -> Unit,
    onLoginSuccess: (UserRole, String) -> Unit,
    initialMode: AuthMode = AuthMode.SIGN_IN
) {
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(
            usePlatformDefaultWidth = false,
            dismissOnBackPress = true,
            dismissOnClickOutside = false
        )
    ) {
        AuthScreen(
            onDismiss = onDismiss,
            onLoginSuccess = onLoginSuccess,
            initialMode = initialMode
        )
    }
}
