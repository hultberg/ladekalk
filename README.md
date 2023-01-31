# ladekalk

Enkel ladekalkulator for å finne rimligste tidsrom å lade bilen på fra nå. Forventet bruk er når du skal planlegge lading for natt og neste dag.

Innstillingene tar utgangspunkt i min egen bil, du kan endre hvor stort batteri du har i filen `src/Application.php` og hvor mye du vil lade batteriet som maksmum (80% er standard og anbefalt av min produsent).

Kalkulatoren er laget for mitt eget personlig bruk.

```
lap ~/projects/ladekalk (master ✘)✚✭ ᐅ podman run --rm -it -v $(pwd):/app -w /app docker.io/library/php:8.2-cli bin/console 50
Average: 0.8347 NOK
2023-01-31 23:00:00 - 2023-01-31 23:59:59 @ 0.8252 NOK
2023-02-01 00:00:00 - 2023-02-01 00:59:59 @ 0.8904 NOK
2023-02-01 01:00:00 - 2023-02-01 01:59:59 @ 0.8360 NOK
2023-02-01 02:00:00 - 2023-02-01 02:59:59 @ 0.7990 NOK
2023-02-01 03:00:00 - 2023-02-01 03:59:59 @ 0.8030 NOK
2023-02-01 04:00:00 - 2023-02-01 04:59:59 @ 0.7976 NOK
2023-02-01 05:00:00 - 2023-02-01 05:59:59 @ 0.8918 NOK
```

Takk til hvakosterstrommen.no som leverer et gratis API for strømpriser.

[<img src="https://ik.imagekit.io/ajdfkwyt/hva-koster-strommen/strompriser-levert-av-hvakosterstrommen_oTtWvqeiB.png" alt="Strømpriser levert av Hva koster strømmen.no" width="200" height="45" />](https://www.hvakosterstrommen.no/)

Strømpriser blir mellomlagret i mappen `var/tmp/` etter at de er hentet fra hvakosterstrommen.no.

Strømpriser for neste dag blir tilgjengelig etter kl 13:00.

## Gjøremål

- [ ] Mulig å sette et ønsket tidsrom å lade på, feks fra midnatt til kl 08.
- [ ] Bedre mulighet for å stille på batterikapasitet og maks lading.
- [ ] Opprydding av mellomlagringen
- [ ] ???
