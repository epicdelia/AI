<?php

declare(strict_types=1);

namespace App\Domains\Entity\Contracts\Input;

interface WithInputSecondInterface
{
    public function getInputSecond(): int;

    public function inputSecond(int $input): static;
}
