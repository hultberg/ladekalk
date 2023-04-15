# ladekalk

_English: This application calculates in theory the cheapest hours to charge your electric car. There is only support to fetch norwegian electric prices._

Enkel ladekalkulator for å finne rimligste tidsrom å lade bilen på fra nå. Forventet bruk er når du skal planlegge lading for natt og neste dag.

Kalkulatoren trenger å vite hvor stort ditt batteri er i kWt, hvor mange kWt din lader yter, ditt nåværende ladenivå i prosent, maksimum ønsket ladenivå (standard 80%), og ønsket tidspunkt for ferdig lading. Hvis klokka er etter tidspunktet vil det legges til en dag.

Takk til hvakosterstrommen.no som leverer et gratis API for strømpriser.

Kalkulatoren er ment for privat bruk for å sjekke når det gunstigst å lade elbilden. Strømpriser blir mellomlagret i mappen `var/tmp/` etter at de er hentet fra hvakosterstrommen.no.  Strømpriser for neste dag blir tilgjengelig etter kl 13:00 en gang.

[<img src="https://ik.imagekit.io/ajdfkwyt/hva-koster-strommen/strompriser-levert-av-hvakosterstrommen_oTtWvqeiB.png" alt="Strømpriser levert av Hva koster strømmen.no" width="200" height="45" />](https://www.hvakosterstrommen.no/)

## Eksempel

```shell
# Eksempel for natt lading med avgangstid kl 08:00
lap:/app$ bin/console --charge 3.1 --battery 77 --level 50 --max 80 --end 08:00 --pricearea NO2
Prices fetched for period 2023-02-25 21:00:00 - 2023-02-26 07:59:59
2023-02-25 21:00 - 21:59 @ 1.0515 NOK (+0%)
2023-02-25 22:00 - 22:59 @ 1.0539 NOK (+0.2072%)
2023-02-25 23:00 - 23:59 @ 1.0532 NOK (+0.1419%)
2023-02-26 00:00 - 00:59 @ 1.1041 NOK (+4.5205%)
2023-02-26 01:00 - 01:59 @ 1.0910 NOK (+3.3923%)
2023-02-26 02:00 - 02:59 @ 1.0891 NOK (+3.2324%)
Session Average: 1.0738 NOK
Session Total: 6.44 NOK
----------------
2023-02-26 04:00 - 04:59 @ 1.1032 NOK (+4.4457%)
2023-02-26 05:00 - 05:59 @ 1.1185 NOK (+5.7622%)
Session Average: 1.1109 NOK
Session Total: 2.22 NOK
----------------
Total Average: 1.0831 NOK
Total Total: 8.66 NOK
```

## Kjøre applikasjonen

Appliksjonen er skrevet i rust og må kompileres med cargo.

## Lisens

Prosjektet er under MIT lisens. Se filen [LICENSE](./LICENSE).
