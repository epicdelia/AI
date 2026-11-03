<?php

declare(strict_types=1);

namespace App\Domains\Entity\Contracts\Input;

interface WithInputMinuteInterface
{
    public function getInputMinute(): float;

    public function inputMinute(float $input): static;
}
