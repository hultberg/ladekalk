<?php
declare(strict_types=1);

namespace HbLib\ChargeCalc;

class Runner
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
    ) { }

    private static $help = <<<'EOL'
    Usage: [options]

      --charge <kwh>    The kWh each hour from the charger
      --battery <kwh>   The battery capacity in kWh
      --level <perc>    The current charge level in percentage
      --max <perc>      The max charge level in percentage, default is 80.
      --end <time>      The end time to stop charging like a departure time in H:i format.
                        If the current time is after this time then the end time is for the next day.
                        Example: Clock is 01.01.2023 13:00 with --end 08:00 resolves to 02.01.2023 08:00
                        Example: Clock is 01.01.2023 17:00 with --end 16:00 resolves to 01.01.2023 16:00
      --pricearea <code> The price area. One of NO1 (Oslo), NO2 (Kristiansand), NO3 (Bergen), NO4 (Trondheim), or NO5 (Tromsø)

    The application will attempt to find the options via env by looking for
    variables:
      LADEKALK_CHARGE for --charge
      LADEKALK_BATTERY for --battery
      LADEKALK_LEVEL for --level
      LADEKALK_MAX for --max
      LADEKALK_END for --end
      LADEKALK_PRICE_AREA for --pricearea

    Report bugs at https://github.com/hultberg/ladekalk/issues/
    Thanks to https://www.hvakosterstrommen.no/ for electricity prices API.

    This application is intended for norwegian electricity consumers who chargers their
    electric cars and want to calculate the optimal charge hours based on the price.
    EOL;

    public function run(array $args): int
    {
        $options = array_replace(
            $this->loadEnvOptions(),
            $this->parseAppOptions($args),
        );

        if (!isset($options['charge'], $options['battery'], $options['pricearea'], $options['level'])) {
            fwrite($this->stderr, self::$help . PHP_EOL);
            return 1;
        }

        $options['max'] ??= 80;

        $now = new \DateTimeImmutable();

        if (isset($options['end'])) {
            $endDateTime = \DateTimeImmutable::createFromFormat('H:i', $options['end']);

            if ($now > $endDateTime) {
                $endDateTime = $endDateTime->modify('+1 day');
            }
        } else {
            $endDateTime = $now->modify('+1 day')->setTime(23, 59, 59);
        }

        $dates = [$now];

        if ($now->format('Y-m-d') !== $endDateTime->format('Y-m-d')) {
            $dates[] = $endDateTime;
        }

        $prices = $this->getPrices($dates, $options['pricearea']);
        $prices = array_values(array_filter($prices, static fn (HourElectricPrice $p) => $p->start >= $now && $p->end <= $endDateTime));

        $chargeCalculator = new ChargeCalculator();
        $timeToCharge = $chargeCalculator->calculate(
            $options['battery'],
            $options['charge'],
            $options['level'],
            $options['max'],
        );

        $optimalPriceResolver = new OptimalPriceResolver();
        $optimalHours = $optimalPriceResolver->resolve($prices, $timeToCharge, $options['maxthreshold'] ?? 100);

        if (count($optimalHours) === 0) {
            fwrite($this->stderr, 'No optimal hours was found' . PHP_EOL);
            return 1;
        }

        $firstPrice = $prices[array_key_first($prices)];
        $lastPrice = $prices[array_key_last($prices)];
        fwrite($this->stdout, sprintf(
            'Prices fetched for period %s - %s',
            $firstPrice->start->format('Y-m-d H:i:s'),
            $lastPrice->end->format('Y-m-d H:i:s'),
        ) . PHP_EOL);

        $iterator = iterator_to_array($optimalHours);

        static $priceLineTemplate = <<<'EOL'
        :start - :end @ :price NOK (+:threshold%)
        EOL;

        do {
            $groupSums = [];

            while (key($iterator) !== null) {
                $price = current($iterator);

                $endFormat = 'H:i';
                if ($price->start->format('Y-m-d') !== $price->end->format('Y-m-d')) {
                    $endFormat = 'Y-m-d ' . $endFormat;
                }

                fwrite($this->stdout, strtr($priceLineTemplate, [
                    ':start' => $price->start->format('Y-m-d H:i'),
                    ':end' => $price->end->format($endFormat),
                    ':price' => number_format($price->priceNok, 4),
                    ':threshold' => round($optimalHours->getThreshold($price), 4),
                ]) . PHP_EOL);

                $groupSums[] = $price->priceNok;

                $nextPrice = next($iterator);

                if ($nextPrice && $price->end->modify('+1 minute') < $nextPrice->start) {
                    break;
                }
            }

            $sum = array_sum($groupSums);
            $avg = $sum / count($groupSums);
            $content = 'Session Average: ' . number_format($avg, 4) . ' NOK' . PHP_EOL;
            $content .= 'Session Total: ' . number_format($sum, 2) . ' NOK' . PHP_EOL;
            $content .= '----------------';
            fwrite($this->stdout, $content . PHP_EOL);
        } while (key($iterator) !== null);

        $sum = array_sum(array_map(static fn (HourElectricPrice $a) => $a->priceNok, iterator_to_array($optimalHours)));
        $avg = $sum / count($optimalHours);

        $content = 'Total Average: ' . number_format($avg, 4) . ' NOK' . PHP_EOL;
        $content .= 'Total Total: ' . number_format($sum, 2) . ' NOK';
        fwrite($this->stdout, $content . PHP_EOL);

        return 0;
    }

    /**
     * @param array $args
     * @return array{
     *     battery?: int,
     *     level?: int,
     *     max?: int,
     *     charge?: float,
     *     pricearea?: string,
     *     maxthreshold?: float,
     *     end?: string,
     * }
     */
    private function parseAppOptions(array $args): array
    {
        $parsed = [];

        $expectedIntArgs = [
            '/--(?<arg>battery)=?\s?(?<value>\d+)/',
            '/--(?<arg>level)=?\s?(?<value>\d+)/',
            '/--(?<arg>max)=?\s?(?<value>\d+)/',
        ];

        $expectedFloatArgs = [
            '/--(?<arg>charge)=?\s?(?<value>[0-9.]+)/',
            '/--(?<arg>maxthreshold)=?\s?(?<value>[0-9.]+)/',
        ];

        $expectedStringArgs = [
            '/--(?<arg>pricearea)=?\s?(?<value>\w+)/',
            '/--(?<arg>end)=?\s?(?<value>[0-9:]+)/',
        ];

        assert(array_is_list($args));
        foreach ($args as $key => $arg) {
            if (isset($args[$key + 1]) && str_starts_with($arg, '--')) {
                $arg .= ' ' . $args[$key + 1];
            }

            foreach ($expectedIntArgs as $regex) {
                if (preg_match($regex, $arg, $matches)) {
                    $parsed[$matches['arg']] = (int) $matches['value'];
                    continue 2;
                }
            }

            foreach ($expectedFloatArgs as $regex) {
                if (preg_match($regex, $arg, $matches)) {
                    $parsed[$matches['arg']] = (float) $matches['value'];
                    continue 2;
                }
            }

            foreach ($expectedStringArgs as $regex) {
                if (preg_match($regex, $arg, $matches)) {
                    $parsed[$matches['arg']] = $matches['value'];
                    continue 2;
                }
            }
        }

        return $parsed;
    }

    /**
     * @return array{
     *     battery?: int,
     *     level?: int,
     *     max?: int,
     *     charge?: float,
     *     pricearea?: string,
     *     end?: string,
     *     maxthreshold?: float,
     * }
     */
    private function loadEnvOptions(): array
    {
        $options = array_merge(
            array_map('intval', array_filter([
                'battery' => $_ENV['LADEKALK_BATTERY'] ?? null,
                'level' => $_ENV['LADEKALK_LEVEL'] ?? null,
                'max' => $_ENV['LADEKALK_MAX'] ?? null,
            ])),
            array_map('floatval', array_filter([
                'charge' => $_ENV['LADEKALK_CHARGE'] ?? null,
                'maxthreshold' => $_ENV['LADEKALK_MAX_THRESHOLD'] ?? null,
            ])),
        );

        if (isset($_ENV['LADEKALK_PRICE_AREA'])) {
            $options['pricearea'] = $_ENV['LADEKALK_PRICE_AREA'];
        }

        if (isset($_ENV['LADEKALK_END'])) {
            $options['end'] = $_ENV['LADEKALK_END'];
        }

        return $options;
    }

    /**
     * @param \DateTimeImmutable[] $dates
     * @param string $priceArea
     * @return array|HourElectricPrice[]
     * @throws \JsonException
     * @throws \Throwable
     */
    private function getPrices(array $dates, string $priceArea): array
    {
        /** @var \Fiber[] $fibers */
        $fibers = [];
        $priceFetcher = new PriceFetcher();

        foreach ($dates as $date) {
            $fibers[] = $priceFetcher->getForDate($date, $priceArea);
        }

        if (count($fibers) > 2) {
            throw new \RuntimeException('This application is intended for fetching maximum 2 days of prices.');
        }

        /** @var HourElectricPrice[] $prices */
        $prices = [];

        do {
            foreach ($fibers as $key => $fiber) {
                if (!$fiber->isStarted()) {
                    $fiber->start();
                    continue;
                }

                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                if (!$fiber->isTerminated()) {
                    continue;
                }

                $value = $fiber->getReturn();
                assert(is_array($value) && array_is_list($value));

                $prices = [...$prices, ...$value];

                unset($fibers[$key]);
            }

            usleep(1000);
        } while (count($fibers) > 0);

        return $prices;
    }
}
