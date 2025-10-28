<?php

namespace vahidkaargar\LaravelWallet\Events;

use Illuminate\Broadcasting\Channel;
use vahidkaargar\LaravelWallet\Models\WalletTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionReversed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    /**
     * Create a new event instance.
     *
     * @param WalletTransaction $originalTransaction
     * @param WalletTransaction $reversalTransaction
     */
    public function __construct(
        public WalletTransaction $originalTransaction,
        public WalletTransaction $reversalTransaction
    )
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|PrivateChannel
     */
    public function broadcastOn(): Channel|PrivateChannel
    {
        return new PrivateChannel('wallet.' . $this->originalTransaction->wallet_id);
    }
}