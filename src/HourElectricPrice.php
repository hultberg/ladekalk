<?php
declare(strict_types=1);

namespace HbLib\ChargeCalc;

class HourElectricPrice
{
    public function __construct(
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
        public readonly float $priceNok,
    ) { }
}
