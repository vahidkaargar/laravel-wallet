<?php

namespace vahidkaargar\LaravelWallet\Services;

use Exception;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\ValueObjects\Money;
use Illuminate\Support\Facades\Log;

/**
 * Service for processing wallet aging, such as charging interest on debt.
 */
class CreditAgingService
{
    /**
     * @param WalletLedgerService $ledgerService
     * @param CreditManagerService $creditManager
     */
    public function __construct(
        protected WalletLedgerService  $ledgerService,
        protected CreditManagerService $creditManager
    )
    {
    }

    /**
     * Process aging for a single wallet.
     */
    public function processWalletAging(Wallet $wallet): void
    {
        try {
            $debt = $this->creditManager->getDebt($wallet);

            if ($debt->isZero()) {
                return;
            }

            // In a real app, get rate from wallet/user/config
            $interestRate = config('wallet.interest_rate', 0.05); // e.g., 5%
            $interest = $this->creditManager->calculateInterest($wallet, $interestRate);

            if ($interest->isPositive()) {
                $this->ledgerService->chargeInterest(
                    $wallet,
                    $interest,
                    true, // Auto-approve interest charges
                    'Monthly interest charge',
                    [
                        'interest_rate' => $interestRate,
                        'debt_amount' => $debt->toDecimal(),
                        'processed_at' => now()->toISOString(),
                    ]
                );

                Log::info("Interest charged for wallet $wallet->id", [
                    'wallet_id' => $wallet->id,
                    'debt' => $debt->toDecimal(),
                    'interest_rate' => $interestRate,
                    'interest_amount' => $interest->toDecimal(),
                ]);
            }
        } catch (Exception $e) {
            Log::error("Failed to process wallet aging for wallet ID $wallet->id: {$e->getMessage()}", [
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process all active wallets with outstanding debt.
     * This is intended to be called from a scheduled command.
     *
     * @return int
     */
    public function processAllWallets(): int
    {
        $count = 0;
        $processedCount = 0;

        Wallet::query()
            ->where('balance', '<', 0)
            ->where('is_active', true)
            ->chunk(100, function ($wallets) use (&$count, &$processedCount) {
                foreach ($wallets as $wallet) {
                    try {
                        $this->processWalletAging($wallet);
                        $processedCount++;
                    } catch (Exception $e) {
                        Log::error("Failed to process aging for wallet $wallet->id: {$e->getMessage()}");
                    }
                    $count++;
                }
            });

        Log::info("Credit aging process completed", [
            'total_wallets_checked' => $count,
            'wallets_processed' => $processedCount,
            'wallets_failed' => $count - $processedCount,
        ]);

        return $processedCount;
    }

    /**
     * Calculate total interest that would be charged across all wallets.
     *
     * @return Money
     */
    public function calculateTotalInterest(): Money
    {
        $totalInterest = Money::fromCents(0);
        $interestRate = config('wallet.interest_rate', 0.05);

        Wallet::query()
            ->where('balance', '<', 0)
            ->where('is_active', true)
            ->chunk(100, function ($wallets) use (&$totalInterest, $interestRate) {
                foreach ($wallets as $wallet) {
                    $interest = $this->creditManager->calculateInterest($wallet, $interestRate);
                    $totalInterest = $totalInterest->add($interest);
                }
            });

        return $totalInterest;
    }

    /**
     * Get aging summary for all wallets.
     *
     * @return array
     */
    public function getAgingSummary(): array
    {
        $summary = [
            'total_wallets_with_debt' => 0,
            'total_debt_amount' => 0,
            'total_interest_to_charge' => 0,
            'wallets_by_debt_range' => [
                '0-100' => 0,
                '100-1000' => 0,
                '1000-10000' => 0,
                '10000+' => 0,
            ],
        ];

        $interestRate = config('wallet.interest_rate', 0.05);

        Wallet::query()
            ->where('balance', '<', 0)
            ->where('is_active', true)
            ->chunk(100, function ($wallets) use (&$summary, $interestRate) {
                foreach ($wallets as $wallet) {
                    $debt = $this->creditManager->getDebt($wallet);
                    $interest = $this->creditManager->calculateInterest($wallet, $interestRate);

                    $summary['total_wallets_with_debt']++;
                    $summary['total_debt_amount'] += $debt->toDecimal();
                    $summary['total_interest_to_charge'] += $interest->toDecimal();

                    // Categorize by debt range
                    $debtAmount = $debt->toDecimal();
                    if ($debtAmount <= 100) {
                        $summary['wallets_by_debt_range']['0-100']++;
                    } elseif ($debtAmount <= 1000) {
                        $summary['wallets_by_debt_range']['100-1000']++;
                    } elseif ($debtAmount <= 10000) {
                        $summary['wallets_by_debt_range']['1000-10000']++;
                    } else {
                        $summary['wallets_by_debt_range']['10000+']++;
                    }
                }
            });

        return $summary;
    }
}