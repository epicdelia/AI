<?php

declare(strict_types=1);

namespace App\Domains\Entity\Concerns\Calculate;

use Exception;

trait HasPresentation
{
    /**
     * @throws Exception
     */
    public function calculate(): float
    {
        return $this->getInputPresentation() * $this->getCreditIndex();
    }
}
