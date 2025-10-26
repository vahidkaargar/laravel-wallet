<?php

namespace vahidkaargar\LaravelWallet\Contracts;

use vahidkaargar\LaravelWallet\ValueObjects\Money;

interface ExchangeRateProvider
{
    /**
     * Get the exchange rate from one currency to another.
     *
     * @param string $fromCurrency The source currency code (e.g., 'USD')
     * @param string $toCurrency The target currency code (e.g., 'EUR')
     * @return float The exchange rate (e.g., 0.95 for USD to EUR)
     * @throws \Exception If the exchange rate cannot be determined
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float;

    /**
     * Convert money from one currency to another.
     *
     * @param Money $money The money to convert
     * @param string $toCurrency The target currency code
     * @return Money The converted money amount
     * @throws \Exception If the conversion cannot be performed
     */
    public function convert(Money $money, string $toCurrency): Money;

    /**
     * Check if the provider supports the given currency pair.
     *
     * @param string $fromCurrency The source currency code
     * @param string $toCurrency The target currency code
     * @return bool True if the currency pair is supported
     */
    public function supports(string $fromCurrency, string $toCurrency): bool;
}
