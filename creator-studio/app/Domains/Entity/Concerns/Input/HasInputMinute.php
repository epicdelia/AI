<?php

declare(strict_types=1);

namespace App\Domains\Entity\Concerns\Input;

use RuntimeException;

trait HasInputMinute
{
    protected float $inputMinute;

    public function getInputMinute(): float
    {
        if (! isset($this->inputMinute)) {
            throw new RuntimeException('Input is not provided');
        }

        return $this->inputMinute;
    }

    public function inputMinute(float $inputMinute): static
    {
        $this->inputMinute = $inputMinute;

        return $this;
    }
}
