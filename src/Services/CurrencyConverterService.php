<?php

namespace vahidkaargar\LaravelWallet\Services;

use vahidkaargar\LaravelWallet\Contracts\ExchangeRateProvider;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use vahidkaargar\LaravelWallet\ValueObjects\MoneyWithCurrency;

/**
 * Currency converter service that uses an exchange rate provider.
 */
class CurrencyConverterService
{
    public function __construct(protected ExchangeRateProvider $exchangeRateProvider)
    {
    }

    /**
     * Convert an amount from one currency to another.
     *
     * @throws \Exception
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
     * @throws \Exception
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
     * @throws \Exception
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
     */
    public function setExchangeRateProvider(ExchangeRateProvider $provider): void
    {
        $this->exchangeRateProvider = $provider;
    }

    /**
     * Get the current exchange rate provider.
     */
    public function getExchangeRateProvider(): ExchangeRateProvider
    {
        return $this->exchangeRateProvider;
    }
}