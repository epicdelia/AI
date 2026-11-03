package com.techunicorn.studio.ui.components

import androidx.compose.animation.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.data.api.StudioPaymentService
import com.techunicorn.studio.domain.models.*
import com.techunicorn.studio.platform.haptics.rememberPlatformHapticEngine
import com.techunicorn.studio.ui.theme.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MobilePaymentSheet(
    packageId: String,
    packageName: String,
    amount: Double,
    currency: String = "USD",
    clientEmailDefault: String = "",
    clientNameDefault: String = "",
    companyNameDefault: String = "",
    onDismiss: () -> Unit,
    onPaymentSuccess: (StudioPaymentTransaction) -> Unit = {}
) {
    val haptics = rememberPlatformHapticEngine()
    val paymentService = remember { StudioPaymentService() }
    val scope = rememberCoroutineScope()

    var selectedMethod by remember { mutableStateOf(PaymentMethodType.GOOGLE_PAY) }
    var customerName by remember { mutableStateOf(clientNameDefault.ifBlank { "Alex Rivera" }) }
    var customerEmail by remember { mutableStateOf(clientEmailDefault.ifBlank { "alex@company.com" }) }
    var companyName by remember { mutableStateOf(companyNameDefault.ifBlank { "ScaleWorks Inc." }) }
    var vatNumber by remember { mutableStateOf("DE382910482") }

    // Card Fields
    var cardNumber by remember { mutableStateOf("4242 •••• •••• 4242") }
    var cardExpiry by remember { mutableStateOf("12/28") }
    var cardCvc by remember { mutableStateOf("923") }

    var isProcessing by remember { mutableStateOf(false) }
    var completedTransaction by remember { mutableStateOf<StudioPaymentTransaction?>(null) }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    val scrollState = rememberScrollState()

    ModalBottomSheet(
        onDismissRequest = {
            if (!isProcessing) onDismiss()
        },
        containerColor = MaterialTheme.colorScheme.surface,
        dragHandle = { BottomSheetDefaults.DragHandle() }
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 8.dp)
                .verticalScroll(scrollState)
        ) {
            if (completedTransaction != null) {
                // Success Confirmation Screen
                val tx = completedTransaction!!
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(64.dp)
                            .clip(CircleShape)
                            .background(StudioEmerald.copy(alpha = 0.15f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            Icons.Default.CheckCircle,
                            contentDescription = "Success",
                            tint = StudioEmerald,
                            modifier = Modifier.size(36.dp)
                        )
                    }

                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            text = "Payment Succeeded!",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Invoice #${tx.invoiceNumber} has been issued and sent to ${tx.clientEmail}",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }

                    // Receipt Card
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f),
                        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline)
                    ) {
                        Column(
                            modifier = Modifier.padding(16.dp),
                            verticalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text("Amount Paid", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text(
                                    "\$${"%,.2f".format(tx.amount)} ${tx.currency}",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 16.sp
                                )
                            }
                            HorizontalDivider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.5f))
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text("Service Package", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text(tx.packageName, fontWeight = FontWeight.SemiBold)
                            }
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text("Transaction ID", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text(tx.transactionId, fontFamily = FontFamily.Monospace, fontSize = 11.sp)
                            }
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text("Payment Method", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text(tx.paymentMethod.name.replace("_", " "), fontWeight = FontWeight.Medium)
                            }
                        }
                    }

                    Button(
                        onClick = {
                            onPaymentSuccess(tx)
                            onDismiss()
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = StudioEmerald),
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Text("Done & Return to Studio", fontWeight = FontWeight.Bold)
                    }
                }
            } else {
                // Checkout Entry Sheet
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            "Complete Mobile Checkout",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            "Bank-grade encrypted Stripe & mobile processing",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.Close, contentDescription = "Close")
                    }
                }

                Spacer(Modifier.height(16.dp))

                // Package Order Summary Card
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    color = StudioViolet.copy(alpha = 0.08f),
                    border = BorderStroke(1.dp, StudioViolet.copy(alpha = 0.3f))
                ) {
                    Row(
                        modifier = Modifier.padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text(packageName, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                            Text(
                                "Instant digital code & kickoff rights",
                                fontSize = 12.sp,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                        Text(
                            "\$${"%,.2f".format(amount)}",
                            fontSize = 20.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = StudioViolet
                        )
                    }
                }

                Spacer(Modifier.height(18.dp))

                // Payment Method Selector Tabs
                Text("Select Payment Method", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                Spacer(Modifier.height(8.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Google Pay / Apple Pay Tab
                    Surface(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(10.dp))
                            .clickable {
                                haptics.click()
                                selectedMethod = PaymentMethodType.GOOGLE_PAY
                            },
                        shape = RoundedCornerShape(10.dp),
                        color = if (selectedMethod == PaymentMethodType.GOOGLE_PAY) StudioCoral.copy(alpha = 0.15f)
                        else MaterialTheme.colorScheme.surfaceVariant,
                        border = BorderStroke(
                            1.dp,
                            if (selectedMethod == PaymentMethodType.GOOGLE_PAY) StudioCoral else MaterialTheme.colorScheme.outline
                        )
                    ) {
                        Row(
                            modifier = Modifier.padding(vertical = 10.dp, horizontal = 12.dp),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(Icons.Default.Payment, contentDescription = null, tint = StudioCoral, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Google Pay", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                        }
                    }

                    // Credit Card Tab
                    Surface(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(10.dp))
                            .clickable {
                                haptics.click()
                                selectedMethod = PaymentMethodType.CREDIT_CARD
                            },
                        shape = RoundedCornerShape(10.dp),
                        color = if (selectedMethod == PaymentMethodType.CREDIT_CARD) StudioViolet.copy(alpha = 0.15f)
                        else MaterialTheme.colorScheme.surfaceVariant,
                        border = BorderStroke(
                            1.dp,
                            if (selectedMethod == PaymentMethodType.CREDIT_CARD) StudioViolet else MaterialTheme.colorScheme.outline
                        )
                    ) {
                        Row(
                            modifier = Modifier.padding(vertical = 10.dp, horizontal = 12.dp),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(Icons.Default.CreditCard, contentDescription = null, tint = StudioViolet, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Credit Card", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))

                // Dynamic Method Form
                when (selectedMethod) {
                    PaymentMethodType.GOOGLE_PAY, PaymentMethodType.APPLE_PAY -> {
                        Surface(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                            color = Color.Black,
                            border = BorderStroke(1.dp, Color.White.copy(alpha = 0.2f))
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(14.dp),
                                horizontalArrangement = Arrangement.Center,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.Lock, contentDescription = null, tint = StudioEmerald, modifier = Modifier.size(14.dp))
                                Spacer(Modifier.width(8.dp))
                                Text(
                                    "Biometric 1-Tap Pay ready with Google Pay",
                                    color = Color.White,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Medium
                                )
                            }
                        }
                    }
                    PaymentMethodType.CREDIT_CARD -> {
                        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                            OutlinedTextField(
                                value = cardNumber,
                                onValueChange = { cardNumber = it },
                                label = { Text("Card Number") },
                                leadingIcon = { Icon(Icons.Default.CreditCard, contentDescription = null) },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number)
                            )
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                OutlinedTextField(
                                    value = cardExpiry,
                                    onValueChange = { cardExpiry = it },
                                    label = { Text("Expires (MM/YY)") },
                                    modifier = Modifier.weight(1f),
                                    singleLine = true
                                )
                                OutlinedTextField(
                                    value = cardCvc,
                                    onValueChange = { cardCvc = it },
                                    label = { Text("CVC") },
                                    modifier = Modifier.weight(1f),
                                    singleLine = true,
                                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number)
                                )
                            }
                        }
                    }
                    else -> {}
                }

                Spacer(Modifier.height(14.dp))

                // Customer Details
                OutlinedTextField(
                    value = customerName,
                    onValueChange = { customerName = it },
                    label = { Text("Signatory Full Name") },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true
                )
                Spacer(Modifier.height(8.dp))

                OutlinedTextField(
                    value = customerEmail,
                    onValueChange = { customerEmail = it },
                    label = { Text("Billing Email (for Invoice)") },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email)
                )

                Spacer(Modifier.height(20.dp))

                // Error Message Banner if any
                if (errorMessage != null) {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = Color.Red.copy(alpha = 0.1f),
                        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp)
                    ) {
                        Text(
                            text = errorMessage!!,
                            color = Color.Red,
                            fontSize = 12.sp,
                            modifier = Modifier.padding(10.dp)
                        )
                    }
                }

                // Primary Instant Pay Button
                Button(
                    onClick = {
                        if (customerEmail.isBlank() || customerName.isBlank()) {
                            errorMessage = "Please enter your name and billing email."
                            haptics.error()
                            return@Button
                        }
                        errorMessage = null
                        isProcessing = true
                        haptics.heavy()

                        scope.launch {
                            try {
                                val tx = paymentService.processMobilePayment(
                                    packageId = packageId,
                                    packageName = packageName,
                                    amount = amount,
                                    paymentMethod = selectedMethod,
                                    customerEmail = customerEmail,
                                    companyName = companyName
                                )
                                completedTransaction = tx
                                isProcessing = false
                                haptics.success()
                            } catch (e: Exception) {
                                errorMessage = e.message ?: "Payment processing failed. Try again."
                                isProcessing = false
                                haptics.error()
                            }
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = if (selectedMethod == PaymentMethodType.GOOGLE_PAY) StudioCoral else StudioViolet
                    ),
                    enabled = !isProcessing
                ) {
                    if (isProcessing) {
                        CircularProgressIndicator(modifier = Modifier.size(20.dp), color = Color.White, strokeWidth = 2.dp)
                        Spacer(Modifier.width(10.dp))
                        Text("Authorizing Secure Payment...")
                    } else {
                        Icon(Icons.Default.Lock, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(8.dp))
                        Text(
                            text = if (selectedMethod == PaymentMethodType.GOOGLE_PAY) "Pay with Google Pay · \$${"%,.2f".format(amount)}"
                            else "Pay \$${"%,.2f".format(amount)} Now",
                            fontWeight = FontWeight.Bold,
                            fontSize = 15.sp
                        )
                    }
                }

                Spacer(Modifier.height(14.dp))
            }
        }
    }
}
