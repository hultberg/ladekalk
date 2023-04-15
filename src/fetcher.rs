use anyhow::{anyhow, bail, Context, Result};
use serde_json::Value;
use time::Date;
use std::fs::File;
use std::io::{BufReader, BufWriter, Write};
use std::path::{Path, PathBuf};
use reqwest::Response;
use crate::prices::{Parser, DayPrices};

fn pad_num(num: u8) -> String {
    let d = num.to_string();

    if d.len() == 2 {
        return d;
    }

    let mut dd = String::from("0");
    dd.push_str(&d);
    dd
}

pub struct Prices {
    cache: Cache,
    http_client: HttpFetchClient,
}

impl Prices {
    pub fn new() -> Result<Prices> {
        Ok(Prices {
            cache: Cache::new(),
            http_client: HttpFetchClient::new()?,
        })
    }
    pub async fn get(&self, date: &Date, price_area: &str) -> Result<Option<DayPrices>> {
        let cache_key = format!(
            "{}-{}-{}_{}",
            date.year(),
            pad_num(date.month() as u8),
            pad_num(date.day()),
            &price_area,
        );

        if let Some(cache) = self.cache.get(&cache_key) {
            return Ok(Some(Parser::day_prices(&cache)?));
        }

        let response = self.http_client.fetch(&date, &price_area).await.context("Failed to fetch prices")?;
        let status = response.status();

        if status.as_u16() == 404 {
            return Ok(None);
        }

        if !status.is_success() {
            bail!("Unexpected status code {:?}", status);
        }

        let json: Value = {
            let resp_content = response.bytes().await.context("Failed to read response")?.to_vec();
            serde_json::from_slice(&resp_content).context("Failed to parse as JSON")?
        };

        let prices = Parser::day_prices(&json).context("Failed to parse")?;
        self.cache.write(&cache_key, &json).context("Failed to write cache")?;

        Ok(Some(prices))
    }
}

struct Cache {
    cache_dir: PathBuf,
}

impl Cache {
    pub fn new() -> Cache {
        Cache {
            cache_dir: Path::new("/var/tmp/").to_path_buf(),
        }
    }

    pub fn get(&self, key: &str) -> Option<Value> {
        let path = self.get_path_for_key(key);

        if !path.exists() {
            return None;
        }

        let file = File::open(path).unwrap();
        let reader = BufReader::new(file);
        let json: Value = serde_json::from_reader(reader).context("Unable to read as JSON").unwrap();
        Some(json)
    }

    pub fn write(&self, key: &str, value: &Value) -> Result<()> {
        let path = self.get_path_for_key(key);

        if path.exists() {
            return Err(anyhow!("Path already exists: {:?}", path));
        }

        let file = File::create(path).unwrap();
        let mut writer = BufWriter::new(file);
        writer.write_all(value.to_string().as_bytes()).context("Unable to write cache")?;

        Ok(())
    }

    fn get_path_for_key(&self, key: &str) -> PathBuf {
        let mut path = PathBuf::from(&self.cache_dir);
        path.push(format!("prices_{}", &key));
        path.set_extension("json");
        path
    }
}

struct HttpFetchClient {
    client: reqwest::Client,
}

impl HttpFetchClient {
    pub fn new() -> Result<HttpFetchClient> {
        let client = reqwest::ClientBuilder::new().build().context("Unable to create http client")?;
        Ok(HttpFetchClient { client })
    }

    pub async fn fetch(&self, date: &Date, price_area: &str) -> Result<Response> {
        let url = format!(
            "https://www.hvakosterstrommen.no/api/v1/prices/{}/{}-{}_{}.json",
            date.year(),
            pad_num(date.month() as u8),
            pad_num(date.day()),
            price_area,
        );
        let resp = self.client.get(url).send().await.context("Failed to send http request")?;

        Ok(resp)
    }
}
