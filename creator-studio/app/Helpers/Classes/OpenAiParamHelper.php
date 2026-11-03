<?php

namespace App\Helpers\Classes;

use App\Domains\Entity\Enums\EntityEnum;

class OpenAiParamHelper
{
    /**
     * Sanitize OpenAI Chat Completions parameters based on model capabilities.
     *
     * - Converts max_tokens to max_completion_tokens for reasoning/GPT-5 models
     * - Removes temperature, presence_penalty, frequency_penalty for reasoning/GPT-5 models
     *
     * @param  array<string, mixed>  $params  The full request payload (must contain 'model')
     *
     * @return array<string, mixed>
     */
    public static function sanitizeChatParams(array $params): array
    {
        $model = $params['model'] ?? null;

        if (! $model) {
            return $params;
        }

        if ($model instanceof EntityEnum) {
            $entity = $model;
            $params['model'] = $entity->value;
        } else {
            $entity = EntityEnum::tryFrom((string) $model);
        }

        if (! $entity) {
            return $params;
        }

        if ($entity->isReasoningModel()) {
            if (isset($params['messages']) && is_array($params['messages'])) {
                $hints = [];

                if (isset($params['max_tokens'])) {
                    $hints[] = 'Your response must not exceed approximately ' . $params['max_tokens'] . ' tokens.';
                }

                if (isset($params['temperature'])) {
                    $hints[] = match (true) {
                        $params['temperature'] <= 0.3 => 'Be precise and deterministic.',
                        $params['temperature'] <= 0.6 => 'Be balanced between precision and creativity.',
                        $params['temperature'] <= 0.8 => 'Be moderately creative.',
                        default                       => 'Be highly creative and varied.',
                    };
                }

                if (! empty($hints)) {
                    $hintText = implode(' ', $hints);

                    $appended = false;
                    foreach ($params['messages'] as &$message) {
                        if (($message['role'] ?? '') === 'system') {
                            $message['content'] .= "\n" . $hintText;
                            $appended = true;

                            break;
                        }
                    }
                    unset($message);

                    if (! $appended) {
                        array_unshift($params['messages'], [
                            'role'    => 'system',
                            'content' => $hintText,
                        ]);
                    }
                }
            }

            // Reasoning models don't support these parameters
            unset($params['temperature'], $params['presence_penalty'], $params['frequency_penalty'], $params['max_tokens'], $params['max_completion_tokens']);
        }

        return $params;
    }
}
