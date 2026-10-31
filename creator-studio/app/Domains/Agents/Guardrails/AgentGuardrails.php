<?php

namespace App\Domains\Agents\Guardrails;

use RuntimeException;

/**
 * Agent Safety Guardrails & Circuit Breakers.
 * Implements enterprise Policy-as-Code, loop detection, PII/Secret redaction,
 * step budgeting, and token expenditure circuit breakers.
 */
class AgentGuardrails
{
    public const MAX_STEPS = 10;
    public const MAX_CONSECUTIVE_IDENTICAL_TOOL_CALLS = 2;
    public const MAX_BUDGET_USD_PER_RUN = 0.50; // $0.50 safety cap per autonomous run

    private int $stepCount = 0;
    private array $toolHistory = [];
    private int $promptTokens = 0;
    private int $completionTokens = 0;
    private array $violations = [];
    private array $redactions = [];

    /**
     * Check if a tool execution is allowed by the circuit breaker.
     *
     * @throws RuntimeException if step budget is exceeded or loop is detected.
     */
    public function recordAndValidateToolCall(string $tool, array $args = []): void
    {
        $this->stepCount++;

        if ($this->stepCount > self::MAX_STEPS) {
            $this->violations[] = "Circuit breaker triggered: Step count exceeded maximum limit (".self::MAX_STEPS.").";
            throw new RuntimeException("Agent execution halted: Step budget (".self::MAX_STEPS.") exhausted to prevent infinite loop.");
        }

        $signature = $tool.':'.md5(json_encode($args));
        $this->toolHistory[] = $signature;

        // Check last N calls for infinite loop trap
        $recent = array_slice($this->toolHistory, -self::MAX_CONSECUTIVE_IDENTICAL_TOOL_CALLS - 1);
        if (count($recent) > self::MAX_CONSECUTIVE_IDENTICAL_TOOL_CALLS) {
            $allIdentical = count(array_unique($recent)) === 1;
            if ($allIdentical) {
                $this->violations[] = "Circuit breaker triggered: Infinite loop detected on tool '{$tool}'.";
                throw new RuntimeException("Agent loop detected: Tool '{$tool}' called repeatedly with identical parameters.");
            }
        }
    }

    /**
     * Sanitize and redact private secrets, API keys, or sensitive PII from agent text.
     */
    public function sanitizeContent(string $content): string
    {
        $sanitized = $content;

        // 1. Secret API keys (OpenAI, GitHub, generic bearer tokens)
        $patterns = [
            'API Key / Token' => '/\b(?:sk-[a-zA-Z0-9_\-]{20,}|ghp_[a-zA-Z0-9]{20,}|AIza[0-9A-Za-z-_]{35}|bearer\s+[a-zA-Z0-9_\-\.]{25,})\b/i',
            'Private Email' => '/\b[A-Za-z0-9._%+-]+@(?!example\.com|google\.com|anthropic\.com)[A-Za-z0-9.-]+\.[A-Z|a-z]{2,7}\b/',
            'Internal AWS/GCP Credential' => '/\bAKIA[0-9A-Z]{16}\b/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $sanitized, $matches)) {
                foreach ($matches[0] as $match) {
                    $this->redactions[] = "Redacted {$type}";
                    $this->violations[] = "Detected sensitive data pattern: {$type}";
                    $sanitized = str_replace($match, '[REDACTED_'.strtoupper(str_replace(' ', '_', $type)).']', $sanitized);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Record token usage and verify economic budget.
     */
    public function recordTokens(int $prompt, int $completion): void
    {
        $this->promptTokens += $prompt;
        $this->completionTokens += $completion;

        $cost = $this->estimateCostUsd();
        if ($cost > self::MAX_BUDGET_USD_PER_RUN) {
            $this->violations[] = "Budget circuit breaker: estimated cost (\${$cost}) exceeded cap (\${self::MAX_BUDGET_USD_PER_RUN}).";
            throw new RuntimeException("Agent budget exceeded: Run halted at \${$cost} to protect API budget.");
        }
    }

    /**
     * Estimate token cost in USD based on standard blended tier rates ($0.25/1M input, $1.00/1M output).
     */
    public function estimateCostUsd(): float
    {
        $inputCost = ($this->promptTokens / 1_000_000) * 0.25;
        $outputCost = ($this->completionTokens / 1_000_000) * 1.00;

        return round($inputCost + $outputCost, 4);
    }

    /**
     * Get the complete guardrail audit summary.
     *
     * @return array{
     *     steps: int,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     estimated_cost_usd: float,
     *     violations: array<int, string>,
     *     redactions: array<int, string>,
     *     healthy: bool
     * }
     */
    public function summary(): array
    {
        return [
            'steps' => $this->stepCount,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'estimated_cost_usd' => $this->estimateCostUsd(),
            'violations' => array_values(array_unique($this->violations)),
            'redactions' => array_values(array_unique($this->redactions)),
            'healthy' => empty($this->violations),
        ];
    }
}

