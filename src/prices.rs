use anyhow::{Result, Context};
use serde_json::Value;
use time::format_description::well_known::Rfc3339;
use time::OffsetDateTime;

#[derive(Debug, Clone)]
pub struct HourPrice {
    pub start: OffsetDateTime,
    pub end: OffsetDateTime,
    pub price_nok: f64,
}

impl PartialEq for HourPrice {
    fn eq(&self, other: &Self) -> bool {
        self.start.eq(&other.start) && self.end.eq(&other.end) && self.price_nok.eq(&other.price_nok)
    }

    fn ne(&self, other: &Self) -> bool {
        !self.eq(other)
    }
}

pub type DayPrices = Vec<HourPrice>;

pub struct Parser {

}

impl Parser {
    pub fn day_prices(json: &Value) -> Result<DayPrices> {
        let mut prices: DayPrices = Vec::new();

        for price in json.as_array().ok_or("Failed to get as array").unwrap() {
            let start = price["time_start"].as_str().ok_or("Error").unwrap();
            let end = price["time_end"].as_str().ok_or("Error").unwrap();

            prices.push(HourPrice {
                start: OffsetDateTime::parse(start, &Rfc3339).context("Failed to parse start date")?,
                end: OffsetDateTime::parse(end, &Rfc3339).context("Failed to parse end date")?,
                price_nok: price["NOK_per_kWh"].as_f64().ok_or("Error").unwrap(),
            });
        }

        Ok(prices)
    }
}

pub struct OptimalPrices {

}

impl OptimalPrices {
    pub fn resolve_optimal(prices: &DayPrices, time_to_charge: i64) -> DayPrices {
        let mut prices_sorted: DayPrices = prices.to_vec();
        prices_sorted.sort_by(|a, b| a.price_nok.partial_cmp(&b.price_nok).unwrap());

        let min_per = {
            let minimum = prices_sorted.first().unwrap();
            let maximum = prices_sorted.last().unwrap();
            (minimum.price_nok / &maximum.price_nok) * 100 as f64
        };

        let mut threshold = 2 as i8;
        let max_threshold = 50 as i8;
        let mut time_so_far = 0 as i64;
        let mut possible_prices = Vec::new();
        let maximum_price = prices_sorted.last().unwrap().price_nok;

        while prices_sorted.len() > 0 && time_to_charge > time_so_far && threshold < max_threshold {
            let mut matched_prices = Vec::new();

            for price in prices_sorted.iter() {
                let per = (price.price_nok / maximum_price) * 100 as f64;

                if (per - min_per) <= threshold as f64 {
                    possible_prices.push(price.clone());
                    matched_prices.push(price.clone());
                    time_so_far += 60 * 60;
                }
            }

            prices_sorted.retain(|i| !matched_prices.contains(&i));

            threshold += 2;
        }

        possible_prices
    }
}
