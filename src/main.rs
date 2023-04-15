use std::collections::VecDeque;
use std::ops::Add;
use clap::Parser;
use anyhow::{Result, Context};
use time::{Duration, format_description, OffsetDateTime};
use ladekalk::args::Args;
use ladekalk::charge::Calculator;
use ladekalk::fetcher::Prices;
use ladekalk::prices::{DayPrices, OptimalPrices};

async fn fetch_prices(args: &Args) -> Result<DayPrices> {
    let price_fetcher = Prices::new().context("Failed to create client")?;

    let today = OffsetDateTime::now_utc();
    let today_prices_opt = price_fetcher.get(&today.date(), &args.price_area).await.context("Failed to get todays prices")?;
    let tomorrow_prices_opt = price_fetcher.get(&today.add(Duration::days(1)).date(), &args.price_area).await.context("Failed to get tomorrow prices")?;
    let mut prices: DayPrices = Vec::new();

    if let Some(today_prices) = today_prices_opt {
        prices.extend_from_slice(&today_prices);
    }
    if let Some(tomorrow_prices) = tomorrow_prices_opt {
        prices.extend_from_slice(&tomorrow_prices);
    }

    Ok(prices)
}

async fn run(args: Args) -> Result<()> {
    let time_to_charge = Calculator::calc_charge_time(args.battery, args.charge, args.level, args.max);
    let prices = {
        let mut prices = fetch_prices(&args).await?;
        prices = OptimalPrices::resolve_optimal(&prices, time_to_charge);
        prices.sort_by(|a, b| a.start.cmp(&b.start));
        prices
    };

    let date_time_format = format_description::parse("[year]-[month]-[day] [hour]:[minute]")?;
    let time_format = format_description::parse("[hour]:[minute]")?;
    let mut total_sum = 0 as f64;
    let mut total_num = 0;
    let mut prices_queue = VecDeque::from(prices);
    loop {
        let mut group_sum = 0 as f64;
        let mut group_num = 0;

        while let Some(price) = prices_queue.pop_front() {
            let mut effective_format = &time_format;
            if !price.start.date().eq(&price.end.date()) {
                effective_format = &date_time_format;
            }

            println!(
                "{} - {} @ {} NOK",
                price.start.format(&date_time_format).unwrap(),
                price.end.format(effective_format).unwrap(),
                price.price_nok,
            );

            group_sum += price.price_nok;
            group_num += 1;

            if let Some(next) = prices_queue.get(0) {
                if price.end < next.start {
                    break;
                }
            }
        }

        println!("Session average: {} NOK", group_sum / group_num as f64);
        println!("Session total: {} NOK", group_sum);
        println!("------------------");

        total_sum += group_sum;
        total_num += group_num;

        if prices_queue.is_empty() {
            break;
        }
    }

    println!("Total average: {} NOK", total_sum / total_num as f64);
    println!("Total total: {} NOK", total_sum);

    Ok(())
}

#[tokio::main]
async fn main() {
    let args = Args::parse();

    run(args).await.context("").unwrap();
}
