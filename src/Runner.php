<?php
declare(strict_types=1);

namespace HbLib\ChargeCalc;

use GuzzleHttp\Client;

class Runner
{
    public function run(array $args): int
    {
        if (count($args) === 0) {
            echo 'Missing arguments for charge level' . PHP_EOL;
            return 1;
        }

        $today = new \DateTimeImmutable();

        $prices = $this->getPrices($today);
        $tomorrowPrices = $this->getPrices($today->modify('+1 day'));

        if (is_array($tomorrowPrices)) {
            $prices = [...$prices, ...$tomorrowPrices];
        }
        unset($tomorrowPrices);

        $prices = array_values(array_filter($prices, static fn (HourElectricPrice $p) => $p->start >= $today));

        if (!isset($args[0]) || !is_numeric($args[0]) || $args[0] < 0) {
            echo 'Invalid current charge state' . PHP_EOL;
            return 1;
        }

        $timeForCharge = $this->getTimeToCharge((int) $args[0]);

        if ($timeForCharge <= 0) {
            echo 'Time to charge is <= 0' . PHP_EOL;
            return 1;
        }

        /** @var HourElectricPrice|null $minPrice */
        $minPrice = null;
        /** @var HourElectricPrice|null $maxPrice */
        $maxPrice = null;

        foreach ($prices as $priceItem) {
            if ($minPrice === null
                || $minPrice->priceNok > $priceItem->priceNok) {
                $minPrice = $priceItem;
            }

            if ($maxPrice === null
                || $priceItem->priceNok > $maxPrice->priceNok) {
                $maxPrice = $priceItem;
            }
        }

        /** @var HourElectricPrice[] $possibleOptimalHours */
        $possibleOptimalHours = [];
        $timesSoFarTimestamp = 0;
        $maxTimestamp = $timeForCharge;
        $threshold = 2;
        $minP = ($minPrice->priceNok / $maxPrice->priceNok) * 100;

        $pricesQueue = $prices;
        usort($pricesQueue, static fn (HourElectricPrice $a, HourElectricPrice $b) => $a->priceNok <=> $b->priceNok);

        while (count($pricesQueue) > 0 && $maxTimestamp > $timesSoFarTimestamp && $threshold < 100) {
            for (reset($pricesQueue); key($pricesQueue) !== null && $maxTimestamp > $timesSoFarTimestamp; next($pricesQueue)) {
                $price = current($pricesQueue);
                assert($price instanceof HourElectricPrice);

                $p = ($price->priceNok / $maxPrice->priceNok) * 100;

                if (($p - $minP) <= $threshold) {
                    $possibleOptimalHours[] = $price;
                    $timesSoFarTimestamp += 60 * 60;
                    unset($pricesQueue[key($pricesQueue)]);
                }
            }

            $threshold += 2;
        }

        if (count($possibleOptimalHours) === 0) {
            echo 'Found no optimal hours' . PHP_EOL;
            return 1;
        }

        usort($possibleOptimalHours, static fn (HourElectricPrice $a, HourElectricPrice $b) => $a->start <=> $b->start);

        $avg = array_sum(array_map(static fn (HourElectricPrice $a) => $a->priceNok, $possibleOptimalHours)) / count($possibleOptimalHours);
        echo 'Average: ' . number_format($avg, 4) . ' NOK' . PHP_EOL;

        foreach ($possibleOptimalHours as $price) {
            echo $price->start->format('Y-m-d H:i:s');
            echo ' - ' . $price->end->format('Y-m-d H:i:s');
            echo ' @ ' . number_format($price->priceNok, 4) . ' NOK';
            echo PHP_EOL;
        }

        return 0;
    }

    /**
     * @param int $chargeLevelPercent Current charge percentage.
     * @return int Seconds for the time needed to charge to max charge percent
     */
    private function getTimeToCharge(int $chargeLevelPercent): int
    {
        // Specific for my own car...
        $batteryPackKwh = Application::CAR_BATTERY_CAPACITY;
        $chargePerHour = Application::CHARGER_PER_HOUR; // specific for my home charger
        $maxChargeKwh = round($batteryPackKwh * (Application::CAR_MAX_CHARGE_PERCENT / 100), 2);
        $currentChargeKwh = round($batteryPackKwh * ($chargeLevelPercent / 100), 2);

        $timeForCharge = ($maxChargeKwh - $currentChargeKwh) / $chargePerHour;

        return (int) round($timeForCharge * 60 * 60);
    }

    /**
     * @param \DateTimeImmutable $dt
     * @return list<HourElectricPrice>|null
     */
    private function getPrices(\DateTimeImmutable $dt): ?array
    {
        $cacheFile = __DIR__ . '/../var/tmp/prices_' . $dt->format('Ymd') . '.json';

        if (!file_exists($cacheFile) || !is_readable($cacheFile)) {
            $data = $this->fetchPrices($dt);

            file_put_contents($cacheFile, json_encode($data, JSON_THROW_ON_ERROR));
            unset($response, $client, $body);
        } else {
            $data = json_decode(file_get_contents($cacheFile), true, 512, JSON_THROW_ON_ERROR);
        }

        if (!is_array($data)) {
            return null;
        }

        $data = array_map(
            static fn (array $i) => new HourElectricPrice(
                new \DateTimeImmutable($i['time_start']),
                (new \DateTimeImmutable($i['time_end']))->modify('-1 sec'),
                $i['NOK_per_kWh'],
            ),
            $data,
        );

        return $data;
    }

    private function fetchPrices(\DateTimeImmutable $dt): array
    {
        $url = sprintf(
            'https://www.hvakosterstrommen.no/api/v1/prices/%s/%s-%s_%s.json',
            $dt->format('Y'),
            $dt->format('m'),
            $dt->format('d'),
            'NO2',
        );

        $client = new Client();
        $response = $client->request('GET', $url);

        $body = null;

        if ($response->getStatusCode() === 200) {
            $body = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } else if ($response->getStatusCode() !== 404) {
            throw new \RuntimeException('Got response ' . $response->getStatusCode());
        }

        return $body;
    }
}