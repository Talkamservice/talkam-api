<?php

namespace App\Console\Commands\Finance\Currency;

use App\Helpers\MethodsHelper;
use App\Models\Currency;
use App\Services\Finance\Currency\CurrencyService;
use App\Services\General\Guzzle\GuzzleService;
use Illuminate\Console\Command;

class UpdateCurrencyRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:currency_rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update currencies with the latest conversion rates';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    private $retries = 0;
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->process();
        return 0;
    }

    public function process()
    {
        $url = "https://v6.exchangerate-api.com/v6/739b3281d5433720a17905ad/latest/USD";
        $response = (new GuzzleService())->get($url);

        if (empty($response)) {
            $this->retries++;
            if ($this->retries > 3) {
                return;
            }
            return $this->process();
        }

        $currencies = $response["data"]["conversion_rates"];

        foreach ($currencies as $key => $amount) {
            $currency_ = MethodsHelper::validateCurrencyCode($key);

            if ($currency_) {
                (new CurrencyService)->updateRate($currency_, $amount);
            }
        }
    }
}
