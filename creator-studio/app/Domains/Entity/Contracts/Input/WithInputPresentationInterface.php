<?php

declare(strict_types=1);

namespace App\Domains\Entity\Contracts\Input;

interface WithInputPresentationInterface
{
    public function getInputPresentation(): float;

    public function inputPresentation(float $inputPresentation): static;
}
