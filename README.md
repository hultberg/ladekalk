# ladekalk

Enkel ladekalkulator for å finne rimligste tidsrom å lade bilen på fra nå. Forventet bruk er når du skal planlegge lading for natt og neste dag.

Kalkulatoren trenger å vite hvor stort ditt batteri er i kWt, hvor mange kWt din lader yter, ditt nåværende ladenivå i prosent, maksimum ønsket ladenivå (standard 80%), og ønsket tidspunkt for ferdig lading. Hvis klokka er etter tidspunktet vil det legges til en dag.

Kalkulatoren er ment for privat bruk for å sjekke når det gunstigst å lade elbilden.

```shell
# Eksempel for natt lading med avgangstid kl 08:00
lap:/app$ bin/console --charge 3.1 --battery 77 --level 50 --max 80 --end 08:00 --pricearea NO2
Prices fetched for period 2023-02-25 00:00:00 - 2023-02-25 23:59:59
2023-02-25 03:00 - 03:59 @ 0.9243 NOK (+3.1007%)
2023-02-25 04:00 - 04:59 @ 0.9273 NOK (+3.3736%)
2023-02-25 05:00 - 05:59 @ 0.9366 NOK (+4.2319%)
2023-02-25 06:00 - 06:59 @ 0.9430 NOK (+4.8174%)
Session Average: 0.9328 NOK
----------------
2023-02-25 11:00 - 11:59 @ 0.9226 NOK (+2.9393%)
2023-02-25 12:00 - 12:59 @ 0.8986 NOK (+0.7275%)
2023-02-25 13:00 - 13:59 @ 0.8907 NOK (+0%)
2023-02-25 14:00 - 14:59 @ 0.8986 NOK (+0.7275%)
Session Average: 0.9026 NOK
----------------
Total Average: 0.9177 NOK
```

Takk til hvakosterstrommen.no som leverer et gratis API for strømpriser.

[<img src="https://ik.imagekit.io/ajdfkwyt/hva-koster-strommen/strompriser-levert-av-hvakosterstrommen_oTtWvqeiB.png" alt="Strømpriser levert av Hva koster strømmen.no" width="200" height="45" />](https://www.hvakosterstrommen.no/)

Strømpriser blir mellomlagret i mappen `var/tmp/` etter at de er hentet fra hvakosterstrommen.no.

Strømpriser for neste dag blir tilgjengelig etter kl 13:00.

## Usage

```shell
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
```

## Gjøremål

- [X] Mulig å sette et ønsket tidsrom å lade på, feks fra midnatt til kl 08.
- [X] Bedre mulighet for å stille på batterikapasitet og maks lading.
- [ ] Opprydding av mellomlagringen
- [ ] ???
