<?php

namespace vahidkaargar\LaravelWallet\Services;

use Exception;
use vahidkaargar\LaravelWallet\Contracts\ExchangeRateProvider;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use vahidkaargar\LaravelWallet\ValueObjects\MoneyWithCurrency;

/**
 * Currency converter service that uses an exchange rate provider.
 */
class CurrencyConverterService
{
    /**
     * @param ExchangeRateProvider $exchangeRateProvider
     */
    public function __construct(protected ExchangeRateProvider $exchangeRateProvider)
    {
    }

    /**
     * Convert an amount from one currency to another.
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float
     * @throws Exception
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return round($amount, 2);
        }

        $rate = $this->exchangeRateProvider->getExchangeRate($fromCurrency, $toCurrency);
        return round($amount * $rate, 2);
    }

    /**
     * Convert Money object from one currency to another.
     *
     * @param Money $money
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return Money
     * @throws Exception
     */
    public function convertMoney(Money $money, string $fromCurrency, string $toCurrency): Money
    {
        if ($fromCurrency === $toCurrency) {
            return $money;
        }

        $rate = $this->exchangeRateProvider->getExchangeRate($fromCurrency, $toCurrency);
        return $money->multiply($rate);
    }

    /**
     * Convert MoneyWithCurrency to another currency.
     *
     * @param MoneyWithCurrency $money
     * @param string $toCurrency
     * @return MoneyWithCurrency
     * @throws Exception
     */
    public function convertMoneyWithCurrency(MoneyWithCurrency $money, string $toCurrency): MoneyWithCurrency
    {
        if ($money->getCurrency() === $toCurrency) {
            return $money;
        }

        $rate = $this->exchangeRateProvider->getExchangeRate($money->getCurrency(), $toCurrency);
        return $money->multiply($rate);
    }

    /**
     * Set a new exchange rate provider.
     * @param ExchangeRateProvider $provider
     * @return void
     */
    public function setExchangeRateProvider(ExchangeRateProvider $provider): void
    {
        $this->exchangeRateProvider = $provider;
    }

    /**
     * Get the current exchange rate provider.
     * @return ExchangeRateProvider
     */
    public function getExchangeRateProvider(): ExchangeRateProvider
    {
        return $this->exchangeRateProvider;
    }
}