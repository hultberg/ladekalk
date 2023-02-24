<?php
declare(strict_types=1);

namespace HbLib\ChargeCalc;

class OptimalHourCollectionMutable extends OptimalHourCollection
{
    public function add(HourElectricPrice $price, float $threshold): void
    {
        $this->storage[] = $price;
        $this->thresholds->attach($price, $threshold);
    }

    public function toImmutable(): OptimalHourCollection
    {
        $immutable = new OptimalHourCollection();
        $immutable->storage = $this->storage;
        $immutable->thresholds = $this->thresholds;

        return $immutable;
    }

    public function sort(): void
    {
        usort($this->storage, static fn (HourElectricPrice $a, HourElectricPrice $b) => $a->start <=> $b->start);
    }
}
