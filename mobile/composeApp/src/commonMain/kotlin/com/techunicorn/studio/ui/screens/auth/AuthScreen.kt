package com.techunicorn.studio.ui.screens.auth

import androidx.compose.animation.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.domain.models.UserRole
import com.techunicorn.studio.ui.components.StudioLogo
import com.techunicorn.studio.ui.theme.*

/**
 * Discrete Authentication Modes for Creator Studio Mobile.
 * Grounded in creator-studio/public/js/pages/auth.js.
 */
enum class AuthMode {
    SIGN_IN,
    CLIENT_REGISTER,
    TWO_FACTOR,
    FORGOT_PASSWORD
}

/**
 * Password Strength Evaluation Level.
 */
enum class PasswordStrengthLevel(val label: String, val color: Color, val fraction: Float) {
    EMPTY("", Color.Transparent, 0f),
    WEAK("Weak — at least 10 characters, letters and numbers", StudioBadLight, 0.25f),
    FAIR("Fair — consider adding numbers or symbols", StudioWarnLight, 0.50f),
    GOOD("Good — meets standard requirements", StudioGoodLight, 0.75f),
    STRONG("Strong — exceptional entropy", StudioGoodLight, 1.0f)
}

/**
 * Pure Flat Design Mobile Authentication Screen.
 *
 * Strict Design Directives:
 * - Pure flat design: solid colors, crisp 1dp borders, zero neon or sci-fi gradients
 * - End-to-end mobile flow: Sign In, Client Portal Registration, 2FA/TOTP verification, and Password Reset
 * - High accessibility & contrast with clean focus handling
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AuthScreen(
    onDismiss: () -> Unit,
    onLoginSuccess: (UserRole, String) -> Unit,
    initialMode: AuthMode = AuthMode.SIGN_IN
) {
    var mode by remember { mutableStateOf(initialMode) }
    val focusManager = LocalFocusManager.current

    // Form Fields
    var email by remember { mutableStateOf("delia@techunicorn.ai") }
    var password by remember { mutableStateOf("") }
    var confirmPassword by remember { mutableStateOf("") }
    var fullName by remember { mutableStateOf("") }
    var companyName by remember { mutableStateOf("") }
    var rememberMe by remember { mutableStateOf(true) }
    var showPassword by remember { mutableStateOf(false) }

    // 2FA Fields
    var totpCode by remember { mutableStateOf("") }
    var useRecoveryCode by remember { mutableStateOf(false) }
    var recoveryCode by remember { mutableStateOf("") }

    // Forgot Password Fields
    var resetEmail by remember { mutableStateOf("") }
    var resetCodeSent by remember { mutableStateOf(false) }
    var resetToken by remember { mutableStateOf("") }
    var newPassword by remember { mutableStateOf("") }

    // Validation & Error Feedback
    var errorMessage by remember { mutableStateOf<String?>(null) }
    var successNotice by remember { mutableStateOf<String?>(null) }
    var isLoading by remember { mutableStateOf(false) }

    // Password strength evaluation
    val passwordStrength = remember(password) {
        if (password.isEmpty()) PasswordStrengthLevel.EMPTY
        else {
            var score = 0
            if (password.length >= 8) score++
            if (password.length >= 12) score++
            if (password.any { it.isUpperCase() } && password.any { it.isLowerCase() }) score++
            if (password.any { it.isDigit() }) score++
            if (password.any { !it.isLetterOrDigit() }) score++

            when {
                score >= 4 -> PasswordStrengthLevel.STRONG
                score == 3 -> PasswordStrengthLevel.GOOD
                score == 2 -> PasswordStrengthLevel.FAIR
                else -> PasswordStrengthLevel.WEAK
            }
        }
    }

    Surface(
        modifier = Modifier.fillMaxSize(),
        color = MaterialTheme.colorScheme.background
    ) {
        Scaffold(
            topBar = {
                TopAppBar(
                    title = {},
                    navigationIcon = {
                        if (mode != AuthMode.SIGN_IN) {
                            IconButton(onClick = {
                                mode = AuthMode.SIGN_IN
                                errorMessage = null
                                successNotice = null
                            }) {
                                Icon(
                                    Icons.AutoMirrored.Filled.ArrowBack,
                                    contentDescription = "Back to Sign In",
                                    tint = MaterialTheme.colorScheme.onSurface
                                )
                            }
                        }
                    },
                    actions = {
                        IconButton(onClick = onDismiss) {
                            Icon(
                                Icons.Default.Close,
                                contentDescription = "Close",
                                tint = MaterialTheme.colorScheme.onSurface
                            )
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.background
                    )
                )
            }
        ) { paddingValues ->
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = 24.dp, vertical = 8.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                // Flat Studio Brand Header
                Box(
                    modifier = Modifier
                        .size(48.dp)
                        .clip(RoundedCornerShape(10.dp))
                        .background(StudioCoral),
                    contentAlignment = Alignment.Center
                ) {
                    StudioLogo(size = 28.dp, tint = Color.White)
                }

                Spacer(Modifier.height(16.dp))

                Text(
                    text = when (mode) {
                        AuthMode.SIGN_IN -> "Sign in to Creator Studio"
                        AuthMode.CLIENT_REGISTER -> "Create your client account"
                        AuthMode.TWO_FACTOR -> "Two-factor verification"
                        AuthMode.FORGOT_PASSWORD -> if (resetCodeSent) "Set new password" else "Reset your password"
                    },
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center,
                    color = MaterialTheme.colorScheme.onBackground
                )

                Spacer(Modifier.height(6.dp))

                Text(
                    text = when (mode) {
                        AuthMode.SIGN_IN -> "Access your content studio, deals, and client projects."
                        AuthMode.CLIENT_REGISTER -> "Track your deliverables, brand vault, contracts, and invoices."
                        AuthMode.TWO_FACTOR -> if (useRecoveryCode) "Enter one of your 8-character recovery backup codes." else "Enter the 6-digit code from your authenticator app."
                        AuthMode.FORGOT_PASSWORD -> if (resetCodeSent) "Enter the 6-digit reset code sent to your email and your new password." else "Enter your registered email address and we'll send a 6-digit code."
                    },
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center
                )

                Spacer(Modifier.height(20.dp))

                // Error and Notice Banners
                if (errorMessage != null) {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = MaterialTheme.colorScheme.errorContainer,
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.error.copy(alpha = 0.5f)),
                        modifier = Modifier.fillMaxWidth().padding(bottom = 14.dp)
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                Icons.Default.ErrorOutline,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.error,
                                modifier = Modifier.size(18.dp)
                            )
                            Spacer(Modifier.width(8.dp))
                            Text(
                                text = errorMessage!!,
                                color = MaterialTheme.colorScheme.onErrorContainer,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }
                }

                if (successNotice != null) {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = StudioGoodSoftLight,
                        border = BorderStroke(1.dp, StudioGoodLight.copy(alpha = 0.5f)),
                        modifier = Modifier.fillMaxWidth().padding(bottom = 14.dp)
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                Icons.Default.CheckCircle,
                                contentDescription = null,
                                tint = StudioGoodLight,
                                modifier = Modifier.size(18.dp)
                            )
                            Spacer(Modifier.width(8.dp))
                            Text(
                                text = successNotice!!,
                                color = StudioInkLight,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }
                }

                // Flat Segmented Control: Sign In vs Register (Only when in those modes)
                if (mode == AuthMode.SIGN_IN || mode == AuthMode.CLIENT_REGISTER) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(8.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant,
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.5f))
                    ) {
                        Row(
                            modifier = Modifier.padding(3.dp),
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            val isSignIn = mode == AuthMode.SIGN_IN
                            Surface(
                                shape = RoundedCornerShape(6.dp),
                                color = if (isSignIn) MaterialTheme.colorScheme.surface else Color.Transparent,
                                shadowElevation = if (isSignIn) 1.dp else 0.dp,
                                modifier = Modifier
                                    .weight(1f)
                                    .clip(RoundedCornerShape(6.dp))
                                    .clickable {
                                        mode = AuthMode.SIGN_IN
                                        errorMessage = null
                                    }
                            ) {
                                Text(
                                    text = "Sign In",
                                    fontSize = 13.sp,
                                    fontWeight = if (isSignIn) FontWeight.Bold else FontWeight.Medium,
                                    textAlign = TextAlign.Center,
                                    color = if (isSignIn) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                                    modifier = Modifier.padding(vertical = 8.dp)
                                )
                            }

                            val isRegister = mode == AuthMode.CLIENT_REGISTER
                            Surface(
                                shape = RoundedCornerShape(6.dp),
                                color = if (isRegister) MaterialTheme.colorScheme.surface else Color.Transparent,
                                shadowElevation = if (isRegister) 1.dp else 0.dp,
                                modifier = Modifier
                                    .weight(1f)
                                    .clip(RoundedCornerShape(6.dp))
                                    .clickable {
                                        mode = AuthMode.CLIENT_REGISTER
                                        errorMessage = null
                                    }
                            ) {
                                Text(
                                    text = "Client Sign-up",
                                    fontSize = 13.sp,
                                    fontWeight = if (isRegister) FontWeight.Bold else FontWeight.Medium,
                                    textAlign = TextAlign.Center,
                                    color = if (isRegister) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                                    modifier = Modifier.padding(vertical = 8.dp)
                                )
                            }
                        }
                    }
                    Spacer(Modifier.height(20.dp))
                }

                // ==================== MODE: SIGN IN ====================
                if (mode == AuthMode.SIGN_IN) {
                    // Google Sign-In Flat Button
                    Surface(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .border(BorderStroke(1.dp, MaterialTheme.colorScheme.outline))
                            .clickable {
                                isLoading = true
                                onLoginSuccess(UserRole.STUDIO, "Delia Lazarescu")
                            },
                        color = MaterialTheme.colorScheme.surface
                    ) {
                        Row(
                            modifier = Modifier.padding(vertical = 12.dp, horizontal = 16.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.Center
                        ) {
                            GoogleBrandIcon()
                            Spacer(Modifier.width(12.dp))
                            Text(
                                text = "Continue with Google",
                                fontWeight = FontWeight.SemiBold,
                                fontSize = 14.sp,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                        }
                    }

                    Spacer(Modifier.height(18.dp))

                    // Clean Flat Divider with Text
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        HorizontalDivider(
                            modifier = Modifier.weight(1f),
                            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.5f)
                        )
                        Text(
                            text = "or with email",
                            fontSize = 12.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(horizontal = 12.dp)
                        )
                        HorizontalDivider(
                            modifier = Modifier.weight(1f),
                            color = MaterialTheme.colorScheme.outline.copy(alpha = 0.5f)
                        )
                    }

                    Spacer(Modifier.height(18.dp))

                    // Email Field
                    OutlinedTextField(
                        value = email,
                        onValueChange = { email = it; errorMessage = null },
                        label = { Text("Email address") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Email,
                            imeAction = ImeAction.Next
                        ),
                        keyboardActions = KeyboardActions(
                            onNext = { focusManager.moveFocus(FocusDirection.Down) }
                        ),
                        shape = RoundedCornerShape(8.dp)
                    )

                    Spacer(Modifier.height(12.dp))

                    // Password Field
                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it; errorMessage = null },
                        label = { Text("Password") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Password,
                            imeAction = ImeAction.Done
                        ),
                        keyboardActions = KeyboardActions(
                            onDone = { focusManager.clearFocus() }
                        ),
                        trailingIcon = {
                            IconButton(onClick = { showPassword = !showPassword }) {
                                Icon(
                                    imageVector = if (showPassword) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                    contentDescription = if (showPassword) "Hide password" else "Show password",
                                    modifier = Modifier.size(20.dp),
                                    tint = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        },
                        shape = RoundedCornerShape(8.dp)
                    )

                    Spacer(Modifier.height(8.dp))

                    // Remember Me & Forgot Password
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.clickable { rememberMe = !rememberMe }
                        ) {
                            Checkbox(
                                checked = rememberMe,
                                onCheckedChange = { rememberMe = it },
                                modifier = Modifier.size(32.dp)
                            )
                            Spacer(Modifier.width(6.dp))
                            Text(
                                "Remember me",
                                fontSize = 12.sp,
                                color = MaterialTheme.colorScheme.onSurface
                            )
                        }

                        Text(
                            text = "Forgot password?",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = StudioCoral,
                            modifier = Modifier
                                .clickable {
                                    mode = AuthMode.FORGOT_PASSWORD
                                    errorMessage = null
                                }
                                .padding(vertical = 4.dp)
                        )
                    }

                    Spacer(Modifier.height(18.dp))

                    // Sign In Submit Button
                    Button(
                        onClick = {
                            if (email.isBlank() || password.isBlank()) {
                                errorMessage = "Please enter both your email and password."
                                return@Button
                            }
                            if (email.contains("delia")) {
                                mode = AuthMode.TWO_FACTOR
                                errorMessage = null
                            } else {
                                onLoginSuccess(UserRole.CLIENT, "ScaleWorks Inc.")
                            }
                        },
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                    ) {
                        Text("Sign In", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    }

                    Spacer(Modifier.height(24.dp))

                    // Flat Quick-Demo Login Bar
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(8.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline.copy(alpha = 0.4f))
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text(
                                text = "QUICK TESTING PRESETS",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                            Spacer(Modifier.height(8.dp))
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                OutlinedButton(
                                    onClick = {
                                        email = "delia@techunicorn.ai"
                                        password = "password1234"
                                        mode = AuthMode.TWO_FACTOR
                                    },
                                    modifier = Modifier.weight(1f),
                                    shape = RoundedCornerShape(6.dp),
                                    contentPadding = PaddingValues(vertical = 8.dp)
                                ) {
                                    Text("Delia (Studio)", fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                }

                                OutlinedButton(
                                    onClick = {
                                        email = "client@scaleworks.io"
                                        password = "password1234"
                                        onLoginSuccess(UserRole.CLIENT, "ScaleWorks Inc.")
                                    },
                                    modifier = Modifier.weight(1f),
                                    shape = RoundedCornerShape(6.dp),
                                    contentPadding = PaddingValues(vertical = 8.dp)
                                ) {
                                    Text("Client Portal", fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                                }
                            }
                        }
                    }
                }

                // ==================== MODE: CLIENT REGISTER ====================
                if (mode == AuthMode.CLIENT_REGISTER) {
                    OutlinedTextField(
                        value = fullName,
                        onValueChange = { fullName = it; errorMessage = null },
                        label = { Text("Your full name") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        shape = RoundedCornerShape(8.dp)
                    )

                    Spacer(Modifier.height(10.dp))

                    OutlinedTextField(
                        value = companyName,
                        onValueChange = { companyName = it; errorMessage = null },
                        label = { Text("Company or brand name") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        shape = RoundedCornerShape(8.dp)
                    )

                    Spacer(Modifier.height(10.dp))

                    OutlinedTextField(
                        value = email,
                        onValueChange = { email = it; errorMessage = null },
                        label = { Text("Work email address") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                        shape = RoundedCornerShape(8.dp)
                    )

                    Spacer(Modifier.height(10.dp))

                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it; errorMessage = null },
                        label = { Text("Password (at least 10 chars)") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                        trailingIcon = {
                            IconButton(onClick = { showPassword = !showPassword }) {
                                Icon(
                                    imageVector = if (showPassword) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                    contentDescription = null,
                                    modifier = Modifier.size(20.dp)
                                )
                            }
                        },
                        shape = RoundedCornerShape(8.dp)
                    )

                    // Password Strength Flat Meter
                    if (passwordStrength != PasswordStrengthLevel.EMPTY) {
                        Spacer(Modifier.height(6.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            val bars = 4
                            val activeBars = (passwordStrength.fraction * bars).toInt()
                            for (i in 1..bars) {
                                Box(
                                    modifier = Modifier
                                        .weight(1f)
                                        .height(4.dp)
                                        .clip(RoundedCornerShape(2.dp))
                                        .background(
                                            if (i <= activeBars) passwordStrength.color
                                            else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
                                        )
                                )
                            }
                        }
                        Spacer(Modifier.height(4.dp))
                        Text(
                            text = passwordStrength.label,
                            fontSize = 11.sp,
                            color = passwordStrength.color,
                            fontWeight = FontWeight.Medium
                        )
                    }

                    Spacer(Modifier.height(10.dp))

                    OutlinedTextField(
                        value = confirmPassword,
                        onValueChange = { confirmPassword = it; errorMessage = null },
                        label = { Text("Confirm password") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        visualTransformation = PasswordVisualTransformation(),
                        shape = RoundedCornerShape(8.dp)
                    )

                    Spacer(Modifier.height(18.dp))

                    Button(
                        onClick = {
                            if (fullName.isBlank() || companyName.isBlank() || email.isBlank() || password.isBlank()) {
                                errorMessage = "Please fill in all registration fields."
                                return@Button
                            }
                            if (password != confirmPassword) {
                                errorMessage = "Passwords do not match."
                                return@Button
                            }
                            if (password.length < 10) {
                                errorMessage = "Password must be at least 10 characters."
                                return@Button
                            }
                            onLoginSuccess(UserRole.CLIENT, companyName)
                        },
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                    ) {
                        Text("Create Client Account", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    }
                }

                // ==================== MODE: TWO FACTOR (2FA) ====================
                if (mode == AuthMode.TWO_FACTOR) {
                    if (!useRecoveryCode) {
                        // 6-Digit TOTP Entry
                        OutlinedTextField(
                            value = totpCode,
                            onValueChange = {
                                if (it.length <= 6 && it.all { char -> char.isDigit() }) {
                                    totpCode = it
                                    errorMessage = null
                                    if (it.length == 6) {
                                        onLoginSuccess(UserRole.STUDIO, "Delia Lazarescu")
                                    }
                                }
                            },
                            label = { Text("6-Digit Code") },
                            placeholder = { Text("000000") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                            textStyle = LocalTextStyle.current.copy(
                                fontSize = 22.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 8.sp,
                                textAlign = TextAlign.Center,
                                fontFamily = FontFamily.Monospace
                            ),
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            shape = RoundedCornerShape(8.dp)
                        )

                        Spacer(Modifier.height(16.dp))

                        Button(
                            onClick = {
                                if (totpCode.length == 6) {
                                    onLoginSuccess(UserRole.STUDIO, "Delia Lazarescu")
                                } else {
                                    errorMessage = "Please enter the complete 6-digit verification code."
                                }
                            },
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                        ) {
                            Text("Verify Code", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }

                        Spacer(Modifier.height(12.dp))

                        TextButton(
                            onClick = {
                                useRecoveryCode = true
                                errorMessage = null
                            }
                        ) {
                            Text("Use a recovery code instead", fontSize = 12.sp, color = StudioViolet)
                        }
                    } else {
                        // 8-character recovery backup code
                        OutlinedTextField(
                            value = recoveryCode,
                            onValueChange = {
                                if (it.length <= 9) {
                                    recoveryCode = it.uppercase()
                                    errorMessage = null
                                }
                            },
                            label = { Text("Recovery code (e.g. A1B2-C3D4)") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                            textStyle = LocalTextStyle.current.copy(
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 2.sp,
                                textAlign = TextAlign.Center,
                                fontFamily = FontFamily.Monospace
                            ),
                            shape = RoundedCornerShape(8.dp)
                        )

                        Spacer(Modifier.height(16.dp))

                        Button(
                            onClick = {
                                if (recoveryCode.isNotBlank()) {
                                    onLoginSuccess(UserRole.STUDIO, "Delia Lazarescu")
                                } else {
                                    errorMessage = "Please enter your recovery backup code."
                                }
                            },
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                        ) {
                            Text("Sign in with Recovery Code", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }

                        Spacer(Modifier.height(12.dp))

                        TextButton(
                            onClick = {
                                useRecoveryCode = false
                                errorMessage = null
                            }
                        ) {
                            Text("Return to authenticator app code", fontSize = 12.sp, color = StudioViolet)
                        }
                    }
                }

                // ==================== MODE: FORGOT PASSWORD ====================
                if (mode == AuthMode.FORGOT_PASSWORD) {
                    if (!resetCodeSent) {
                        OutlinedTextField(
                            value = resetEmail,
                            onValueChange = { resetEmail = it; errorMessage = null },
                            label = { Text("Account email address") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                            shape = RoundedCornerShape(8.dp)
                        )

                        Spacer(Modifier.height(16.dp))

                        Button(
                            onClick = {
                                if (resetEmail.isBlank()) {
                                    errorMessage = "Please enter your email address."
                                    return@Button
                                }
                                resetCodeSent = true
                                successNotice = "We've sent a 6-digit reset code to $resetEmail."
                            },
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                        ) {
                            Text("Send Reset Code", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }
                    } else {
                        OutlinedTextField(
                            value = resetToken,
                            onValueChange = { resetToken = it; errorMessage = null },
                            label = { Text("6-Digit Reset Code") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            shape = RoundedCornerShape(8.dp)
                        )

                        Spacer(Modifier.height(12.dp))

                        OutlinedTextField(
                            value = newPassword,
                            onValueChange = { newPassword = it; errorMessage = null },
                            label = { Text("New password (min 10 chars)") },
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                            visualTransformation = PasswordVisualTransformation(),
                            shape = RoundedCornerShape(8.dp)
                        )

                        Spacer(Modifier.height(16.dp))

                        Button(
                            onClick = {
                                if (resetToken.length < 6 || newPassword.length < 10) {
                                    errorMessage = "Please enter the 6-digit code and a password of at least 10 characters."
                                    return@Button
                                }
                                successNotice = "Password reset successfully. Sign in with your new password."
                                mode = AuthMode.SIGN_IN
                                resetCodeSent = false
                            },
                            modifier = Modifier.fillMaxWidth().height(48.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = StudioCoral)
                        ) {
                            Text("Save New Password & Sign In", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }
                    }

                    Spacer(Modifier.height(12.dp))

                    TextButton(
                        onClick = {
                            mode = AuthMode.SIGN_IN
                            errorMessage = null
                            successNotice = null
                        }
                    ) {
                        Text("Cancel and return to Sign In", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }

                Spacer(Modifier.height(28.dp))
            }
        }
    }
}

/**
 * Standard 4-color Flat Google Icon.
 * Conforms to Google's sign-in branding guidelines without gradients.
 */
@Composable
fun GoogleBrandIcon(modifier: Modifier = Modifier.size(18.dp)) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val sx = w / 24f
        val sy = h / 24f

        // Blue Path
        val blue = Path().apply {
            moveTo(23.75f * sx, 12.25f * sy)
            lineTo(23.75f * sx, 10.05f * sy)
            lineTo(12.25f * sx, 10.05f * sy)
            lineTo(12.25f * sx, 14.5f * sy)
            lineTo(18.8f * sx, 14.5f * sy)
            // curve arc towards bottom
            lineTo(18.2f * sx, 17.5f * sy)
            lineTo(21.4f * sx, 20.0f * sy)
            close()
        }
        drawPath(blue, SolidColor(Color(0xFF4285F4)))

        // Red Path
        val red = Path().apply {
            moveTo(12.25f * sx, 4.25f * sy)
            lineTo(15.5f * sx, 4.25f * sy)
            lineTo(18.0f * sx, 1.8f * sy)
            lineTo(12.25f * sx, 0f * sy)
            lineTo(4.5f * sx, 3.2f * sy)
            lineTo(7.8f * sx, 5.8f * sy)
            close()
        }
        drawPath(red, SolidColor(Color(0xFFEA4335)))

        // Yellow Path
        val yellow = Path().apply {
            moveTo(4.5f * sx, 3.2f * sy)
            lineTo(1.5f * sx, 5.5f * sy)
            lineTo(1.5f * sx, 18.5f * sy)
            lineTo(7.8f * sx, 13.8f * sy)
            lineTo(7.8f * sx, 5.8f * sy)
            close()
        }
        drawPath(yellow, SolidColor(Color(0xFFFBBC05)))

        // Green Path
        val green = Path().apply {
            moveTo(1.5f * sx, 18.5f * sy)
            lineTo(12.25f * sx, 24f * sy)
            lineTo(21.4f * sx, 20.0f * sy)
            lineTo(18.2f * sx, 17.5f * sy)
            lineTo(12.25f * sx, 19.5f * sy)
            lineTo(7.8f * sx, 13.8f * sy)
            close()
        }
        drawPath(green, SolidColor(Color(0xFF34A853)))
    }
}
