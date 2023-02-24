<?php
declare(strict_types=1);

namespace HbLib\ChargeCalc;

use Traversable;

class OptimalHourCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var HourElectricPrice[]
     */
    protected array $storage;
    /**
     * @var \SplObjectStorage<HourElectricPrice, float>
     */
    protected \SplObjectStorage $thresholds;

    public function __construct()
    {
        $this->storage = [];
        $this->thresholds = new \SplObjectStorage();
    }

    public function getIterator(): Traversable
    {
        yield from $this->storage;
    }

    public function count(): int
    {
        return \count($this->storage);
    }

    public function add(HourElectricPrice $price, float $threshold): void
    {
        $this->storage[] = $price;
        $this->thresholds->attach($price, $threshold);
    }

    public function getThreshold(HourElectricPrice $price): float
    {
        return $this->thresholds->offsetGet($price);
    }
}
