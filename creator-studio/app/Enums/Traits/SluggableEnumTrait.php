<?php

declare(strict_types=1);

namespace App\Enums\Traits;

use App\Helpers\Classes\EntityRemover;
use Illuminate\Support\Facades\Log;
use RuntimeException;

trait SluggableEnumTrait
{
    public function slug(): string
    {
        return str_replace('.', '__', $this->value);
    }

    public static function fromSlug(string $value): self
    {
        $self = self::tryFrom(str_replace('__', '.', $value));

        if ($self === null) {
            EntityRemover::removeEntity($value);
        }

        if ($self === null) {
            $error = [
                'slug'                       => $value,
                'fromSlug_called_from'       => debug_backtrace()[1]['function'],
                'fromSlug_called_from_class' => debug_backtrace()[1]['class'],
                'fromSlug_called_in'         => debug_backtrace()[1]['file'],
                'fromSlug_called_line'       => debug_backtrace()[1]['line'],
            ];

            Log::error('-------------------------------------------------\n');
            Log::error('fromSlug called with invalid slug: ' . json_encode($error, JSON_THROW_ON_ERROR));
            Log::error('-------------------------------------------------\n');

            throw new RuntimeException('Invalid enum slug: ' . $value . '. Please refresh the page');
        }

        return $self;
    }
}
