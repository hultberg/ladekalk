<?php
declare(strict_types=1);

namespace HbLib\ChargeCalc;

class ChargeCalculator
{
    /**
     * @param int $batteryCapacity Battery capacity in kWh
     * @param float $chargePerHour Charge per hour in kWh
     * @param int $chargeLevel Current charge level in percentage
     * @param int $chargeMaxLevel Maximum charge level in percentage
     * @return int Seconds needed to charge the battery to max
     */
    public function calculate(
        int $batteryCapacity,
        float $chargePerHour,
        int $chargeLevel,
        int $chargeMaxLevel = 80
    ): int {
        // Specific for my own car...
        $maxChargeKwh = round($batteryCapacity * ($chargeMaxLevel / 100), 2);
        $currentChargeKwh = round($batteryCapacity * ($chargeLevel / 100), 2);

        $timeForCharge = ($maxChargeKwh - $currentChargeKwh) / $chargePerHour;

        return (int) round($timeForCharge * 60 * 60);
    }
}
