<?php

namespace vahidkaargar\LaravelWallet\Events;

use Illuminate\Broadcasting\Channel;
use vahidkaargar\LaravelWallet\Models\Wallet;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditRepaid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    /**
     * Create a new event instance.
     *
     * @param Wallet $wallet
     * @param float $amount
     * @param WalletTransaction $transaction
     */
    public function __construct(
        public Wallet            $wallet,
        public float             $amount,
        public WalletTransaction $transaction
    )
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|PrivateChannel|array
     */
    public function broadcastOn(): Channel|PrivateChannel|array
    {
        return new PrivateChannel('wallet.' . $this->wallet->id);
    }
}