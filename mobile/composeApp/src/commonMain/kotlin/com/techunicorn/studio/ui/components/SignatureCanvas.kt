package com.techunicorn.studio.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Clear
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.techunicorn.studio.ui.theme.StudioColors
import com.techunicorn.studio.ui.viewmodels.StrokePath

@Composable
fun SignatureCanvas(
    strokes: List<StrokePath>,
    onStrokeAdded: (StrokePath) -> Unit,
    onClear: () -> Unit,
    modifier: Modifier = Modifier
) {
    var currentPoints by remember { mutableStateOf<List<Offset>>(emptyList()) }

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFF0F172A))
            .border(1.dp, StudioColors.BorderSubtle, RoundedCornerShape(12.dp))
            .padding(12.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Cryptographic E-Signature Pad",
                fontSize = 12.sp,
                color = Color(0xFF94A3B8)
            )
            TextButton(
                onClick = {
                    currentPoints = emptyList()
                    onClear()
                },
                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Clear,
                    contentDescription = "Clear",
                    modifier = Modifier.size(14.dp),
                    tint = Color(0xFFEF4444)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text("Clear", fontSize = 12.sp, color = Color(0xFFEF4444))
            }
        }

        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(140.dp)
                .clip(RoundedCornerShape(8.dp))
                .background(Color(0xFF0A0F1D))
                .pointerInput(Unit) {
                    detectDragGestures(
                        onDragStart = { offset ->
                            currentPoints = listOf(offset)
                        },
                        onDrag = { change, _ ->
                            change.consume()
                            currentPoints = currentPoints + change.position
                        },
                        onDragEnd = {
                            if (currentPoints.isNotEmpty()) {
                                onStrokeAdded(StrokePath(currentPoints))
                                currentPoints = emptyList()
                            }
                        },
                        onDragCancel = {
                            currentPoints = emptyList()
                        }
                    )
                }
        ) {
            Canvas(modifier = Modifier.fillMaxSize()) {
                val baseLineY = size.height * 0.78f
                // Guidelines
                drawLine(
                    color = Color.White.copy(alpha = 0.08f),
                    start = Offset(20f, baseLineY),
                    end = Offset(size.width - 20f, baseLineY),
                    strokeWidth = 1f
                )

                // Render completed strokes
                for (stroke in strokes) {
                    if (stroke.points.size > 1) {
                        val path = Path()
                        path.moveTo(stroke.points[0].x, stroke.points[0].y)
                        for (i in 1 until stroke.points.size) {
                            val p0 = stroke.points[i - 1]
                            val p1 = stroke.points[i]
                            // Bezier mid-point smoothing
                            val midX = (p0.x + p1.x) / 2f
                            val midY = (p0.y + p1.y) / 2f
                            path.quadraticTo(p0.x, p0.y, midX, midY)
                        }
                        path.lineTo(stroke.points.last().x, stroke.points.last().y)

                        drawPath(
                            path = path,
                            color = StudioColors.UnicornBlue,
                            style = Stroke(
                                width = 3.5f,
                                cap = StrokeCap.Round,
                                join = StrokeJoin.Round
                            )
                        )
                    }
                }

                // Render in-progress stroke
                if (currentPoints.size > 1) {
                    val path = Path()
                    path.moveTo(currentPoints[0].x, currentPoints[0].y)
                    for (i in 1 until currentPoints.size) {
                        val p0 = currentPoints[i - 1]
                        val p1 = currentPoints[i]
                        val midX = (p0.x + p1.x) / 2f
                        val midY = (p0.y + p1.y) / 2f
                        path.quadraticTo(p0.x, p0.y, midX, midY)
                    }
                    path.lineTo(currentPoints.last().x, currentPoints.last().y)

                    drawPath(
                        path = path,
                        color = StudioColors.NeonPurple,
                        style = Stroke(
                            width = 3.5f,
                            cap = StrokeCap.Round,
                            join = StrokeJoin.Round
                        )
                    )
                }
            }

            if (strokes.isEmpty() && currentPoints.isEmpty()) {
                Text(
                    text = "Sign here with finger or stylus",
                    color = Color.White.copy(alpha = 0.25f),
                    fontSize = 13.sp,
                    modifier = Modifier.align(Alignment.Center)
                )
            }
        }
    }
}

