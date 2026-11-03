<?php

declare(strict_types=1);

namespace App\Domains\Entity\Concerns\Input;

use RuntimeException;

trait HasInputSecond
{
    protected int $inputSecond;

    public function getInputSecond(): int
    {
        if (! isset($this->inputSecond)) {
            throw new RuntimeException('Input is not provided');
        }

        return $this->inputSecond;
    }

    public function inputSecond(int $inputSecond): static
    {
        $this->inputSecond = $inputSecond;

        return $this;
    }
}
