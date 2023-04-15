use clap::Parser;

#[derive(Parser, Debug)]
pub struct Args {
    #[arg(short, long)]
    pub battery: i64,
    #[arg(short, long)]
    pub end: String,
    #[arg(short, long)]
    pub charge: f64,
    #[arg(short, long)]
    pub level: f64,
    #[arg(short, long)]
    pub max: f64,
    #[arg(short, long)]
    pub price_area: String,
}

