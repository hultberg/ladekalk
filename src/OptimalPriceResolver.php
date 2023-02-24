<?php
declare(strict_types=1);

namespace HbLib\ChargeCalc;

class OptimalPriceResolver
{
    /**
     * @param non-empty-array<HourElectricPrice> $prices
     * @return OptimalHourCollection Hours are sorted by start date
     */
    public function resolve(
        array $prices,
        int $timeToCharge,
        float $maxThreshold = 100
    ): OptimalHourCollection {
        usort($prices, static fn (HourElectricPrice $a, HourElectricPrice $b) => $a->priceNok <=> $b->priceNok);

        /** @var HourElectricPrice|null $minPrice */
        $minPrice = $prices[array_key_first($prices)];
        /** @var HourElectricPrice|null $maxPrice */
        $maxPrice = $prices[array_key_last($prices)];

        $possibleOptimalHours = new OptimalHourCollectionMutable();
        $timesSoFarTimestamp = 0;
        $maxTimestamp = $timeToCharge;
        $threshold = 2;
        $minPercentage = ($minPrice->priceNok / $maxPrice->priceNok) * 100;

        while (count($prices) > 0 && $maxTimestamp > $timesSoFarTimestamp && $threshold < $maxThreshold) {
            for (reset($prices); key($prices) !== null && $maxTimestamp > $timesSoFarTimestamp; next($prices)) {
                $price = current($prices);
                assert($price instanceof HourElectricPrice);

                $percentage = ($price->priceNok / $maxPrice->priceNok) * 100;

                if (($percentage - $minPercentage) <= $threshold) {
                    $possibleOptimalHours->add($price, $percentage - $minPercentage);
                    $timesSoFarTimestamp += 60 * 60;
                    unset($prices[key($prices)]);
                }
            }

            $threshold += 2;
        }

        $possibleOptimalHours->sort();

        return $possibleOptimalHours->toImmutable();
    }
}
