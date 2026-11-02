package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.theme.StudioCoral
import com.techunicorn.studio.ui.theme.StudioEmerald
import com.techunicorn.studio.ui.theme.StudioViolet

@Composable
fun AuthModal(
    onDismiss: () -> Unit,
    onLoginSuccess: (UserRole, String) -> Unit
) {
    var selectedTab by remember { mutableStateOf(0) } // 0: Sign In, 1: Register (Client)
    var email by remember { mutableStateOf("delia@example.com") }
    var password by remember { mutableStateOf("••••••••••••") }
    var companyName by remember { mutableStateOf("ScaleWorks Inc.") }
    var fullName by remember { mutableStateOf("Sarah Chen") }
    var showPassword by remember { mutableStateOf(false) }
    var is2FaRequired by remember { mutableStateOf(false) }
    var totpCode by remember { mutableStateOf("") }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(32.dp)
                            .clip(CircleShape)
                            .background(StudioCoral),
                        contentAlignment = Alignment.Center
                    ) {
                        StudioLogo(size = 18.dp, tint = Color.White)
                    }
                    Spacer(Modifier.width(10.dp))
                    Text(if (is2FaRequired) "Two-Factor Authentication" else "Account Sign In", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                }
                IconButton(onClick = onDismiss) {
                    Icon(Icons.Default.Close, contentDescription = "Close", modifier = Modifier.size(20.dp))
                }
            }
        },
        text = {
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                if (is2FaRequired) {
                    Text("Enter the 6-digit code from your authenticator app (Google Authenticator / 1Password):", fontSize = 13.sp)
                    OutlinedTextField(
                        value = totpCode,
                        onValueChange = { if (it.length <= 6) totpCode = it },
                        label = { Text("6-Digit TOTP Code") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        placeholder = { Text("123456") }
                    )
                    Button(
                        onClick = {
                            if (totpCode.length == 6) {
                                onLoginSuccess(UserRole.STUDIO, "Delia Lazarescu")
                            } else {
                                errorMessage = "Please enter a valid 6-digit code."
                            }
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("Verify & Continue")
                    }
                } else {
                    TabRow(
                        selectedTabIndex = selectedTab,
                        containerColor = MaterialTheme.colorScheme.surfaceVariant,
                        contentColor = StudioCoral,
                        modifier = Modifier.clip(RoundedCornerShape(8.dp))
                    ) {
                        Tab(selected = selectedTab == 0, onClick = { selectedTab = 0 }, text = { Text("Sign In", fontSize = 12.sp) })
                        Tab(selected = selectedTab == 1, onClick = { selectedTab = 1 }, text = { Text("Client Register", fontSize = 12.sp) })
                    }

                    if (selectedTab == 1) {
                        OutlinedTextField(
                            value = fullName,
                            onValueChange = { fullName = it },
                            label = { Text("Full Name") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                        OutlinedTextField(
                            value = companyName,
                            onValueChange = { companyName = it },
                            label = { Text("Company Name") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true
                        )
                    }

                    OutlinedTextField(
                        value = email,
                        onValueChange = { email = it },
                        label = { Text("Email Address") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it },
                        label = { Text("Password") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                        trailingIcon = {
                            IconButton(onClick = { showPassword = !showPassword }) {
                                Icon(
                                    imageVector = if (showPassword) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                            }
                        }
                    )

                    if (errorMessage != null) {
                        Text(errorMessage!!, color = MaterialTheme.colorScheme.error, fontSize = 11.sp)
                    }

                    Button(
                        onClick = {
                            if (selectedTab == 0) {
                                if (email.contains("delia")) {
                                    is2FaRequired = true
                                } else {
                                    onLoginSuccess(UserRole.CLIENT, fullName)
                                }
                            } else {
                                onLoginSuccess(UserRole.CLIENT, fullName)
                            }
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(if (selectedTab == 0) "Sign In with Password" else "Create Client Account")
                    }

                    Divider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))

                    // Quick Demo Shortcuts
                    Text("QUICK DEMO ACCESS (1-TAP):", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Button(
                            onClick = { onLoginSuccess(UserRole.STUDIO, "Delia Lazarescu") },
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral),
                            modifier = Modifier.weight(1f),
                            contentPadding = PaddingValues(4.dp)
                        ) {
                            Text("Studio Admin", fontSize = 11.sp)
                        }
                        Button(
                            onClick = { onLoginSuccess(UserRole.CLIENT, "ScaleWorks Inc.") },
                            colors = ButtonDefaults.buttonColors(containerColor = StudioViolet),
                            modifier = Modifier.weight(1f),
                            contentPadding = PaddingValues(4.dp)
                        ) {
                            Text("Client Portal", fontSize = 11.sp)
                        }
                    }
                }
            }
        },
        confirmButton = {}
    )
}

