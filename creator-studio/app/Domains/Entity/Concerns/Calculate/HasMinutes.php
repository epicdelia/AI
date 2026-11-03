<?php

declare(strict_types=1);

namespace App\Domains\Entity\Concerns\Calculate;

trait HasMinutes
{
    public function calculate(): float
    {
        return $this->getInputMinute() * $this->getCreditIndex();
    }
}
