pub struct Calculator {}

impl Calculator {
    pub fn calc_charge_time(
        battery: i64,
        charge_per_hour: f64,
        level: f64,
        max_level: f64,
    ) -> i64 {
        let max_charge_kwh = battery as f64 * (max_level / 100.0);
        let current_charge_kwh = battery as f64 * (level / 100.0);
        let time = ((max_charge_kwh - current_charge_kwh) / charge_per_hour).round() as i64;
        time * 60 * 60
    }
}
