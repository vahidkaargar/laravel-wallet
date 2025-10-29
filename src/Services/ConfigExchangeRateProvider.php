<?php

namespace vahidkaargar\LaravelWallet\Services;

use Exception;
use vahidkaargar\LaravelWallet\Contracts\ExchangeRateProvider;
use vahidkaargar\LaravelWallet\ValueObjects\Money;

class ConfigExchangeRateProvider implements ExchangeRateProvider
{
    private array $rates;

    /**
     * @param array $rates
     */
    public function __construct(array $rates = [])
    {
        $this->rates = $rates;
    }

    /**
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float
     * @throws Exception
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        if (!isset($this->rates[$fromCurrency][$toCurrency])) {
            throw new Exception("Exchange rate from $fromCurrency to $toCurrency not found in configuration.");
        }

        return $this->rates[$fromCurrency][$toCurrency];
    }

    /**
     * @param Money $money
     * @param string $toCurrency
     * @return Money
     * @throws Exception
     */
    public function convert(Money $money, string $toCurrency): Money
    {
        // For now, we'll assume the money is in USD - this should be enhanced
        // to track the currency of the Money object
        $rate = $this->getExchangeRate('USD', $toCurrency);
        return $money->multiply($rate);
    }

    /**
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return bool
     */
    public function supports(string $fromCurrency, string $toCurrency): bool
    {
        if ($fromCurrency === $toCurrency) {
            return true;
        }

        return isset($this->rates[$fromCurrency][$toCurrency]);
    }
}
