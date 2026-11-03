<?php

declare(strict_types=1);

namespace App\Domains\Entity\Concerns\Input;

use RuntimeException;

trait HasInputPresentation
{
    protected float $inputPresentation;

    public function getInputPresentation(): float
    {
        if (! isset($this->inputPresentation)) {
            throw new RuntimeException('Input is not provided');
        }

        return $this->inputPresentation;
    }

    public function inputPresentation(float $inputPresentation): static
    {
        $this->inputPresentation = $inputPresentation;

        return $this;
    }
}
