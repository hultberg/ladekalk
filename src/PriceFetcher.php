<?php
declare(strict_types=1);

namespace HbLib\ChargeCalc;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class PriceFetcher
{
    private readonly ClientInterface $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * Get prices for a date. A Fiber is returned.
     * @return \Fiber
     * @throws \JsonException
     */
    public function getForDate(\DateTimeImmutable $dt, string $priceArea): \Fiber
    {
        return new \Fiber(function () use ($dt, $priceArea): ?array {
            $cacheFilePath = __DIR__ . '/../var/tmp/prices_' . $priceArea . '_' . $dt->format('Ymd') . '.json';

            if (file_exists($cacheFilePath) && is_readable($cacheFilePath)) {
                $data = $this->getCache($cacheFilePath);
            } else {
                try {
                    $data = $this->fetchPriceForDate($dt, $priceArea);
                } catch (ClientException $e) {
                    if ($e->getResponse()->getStatusCode() === 404) {
                        return [];
                    }

                    throw $e;
                }

                $this->storeCache($cacheFilePath, $data);
            }

            return $this->transformToPriceInstances($data);
        });
    }

    /**
     * @return HourElectricPrice[]
     */
    private function transformToPriceInstances(array $data): array
    {
        $prices = [];

        foreach ($data as $datum) {
            $prices[] = new HourElectricPrice(
                new \DateTimeImmutable($datum['time_start']),
                (new \DateTimeImmutable($datum['time_end']))->modify('-1 sec'),
                $datum['NOK_per_kWh'],
            );
        }

        return $prices;
    }

    /**
     * @throws GuzzleException
     * @throws \JsonException
     */
    private function fetchPriceForDate(\DateTimeImmutable $dt, string $priceArea): array
    {
        $url = sprintf(
            'https://www.hvakosterstrommen.no/api/v1/prices/%s/%s-%s_%s.json',
            $dt->format('Y'),
            $dt->format('m'),
            $dt->format('d'),
            $priceArea,
        );

        $promise = $this->client->requestAsync('GET', $url);

        do {
            \Fiber::suspend();
            $promise->wait();
        } while ($promise->getState() === 'pending');

        $response = $promise->wait(true);
        assert($response instanceof ResponseInterface);

        return json_decode(
            json: $response->getBody()->getContents(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private function getCache(string $cacheFilePath): array
    {
        return json_decode(
            json: file_get_contents($cacheFilePath),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private function storeCache(string $cacheFilePath, array $data): void
    {
        file_put_contents($cacheFilePath, json_encode($data, JSON_THROW_ON_ERROR));
    }
}
