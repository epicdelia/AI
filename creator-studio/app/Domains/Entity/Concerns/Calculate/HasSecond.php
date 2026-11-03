<?php

declare(strict_types=1);

namespace App\Domains\Entity\Concerns\Calculate;

trait HasSecond
{
    public function calculate(): float
    {
        return $this->getInputSecond() * $this->getCreditIndex();
    }
}
