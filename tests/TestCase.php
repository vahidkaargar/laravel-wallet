<?php

namespace vahidkaargar\LaravelWallet\Tests;

use vahidkaargar\LaravelWallet\Traits\HasWallets;
use vahidkaargar\LaravelWallet\WalletServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * @property User $user
 * @property \vahidkaargar\LaravelWallet\Models\Wallet $wallet
 */
abstract class TestCase extends OrchestraTestCase
{
    protected $user;
    protected $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase($this->app);

        $this->user = User::create(['name' => 'Test User']);
        $this->wallet = $this->user->createWallet([
            'name' => 'Test Wallet',
            'slug' => 'test-wallet',
            'currency' => 'USD',
            'is_active' => true,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            WalletServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load the wallet configuration
        $app['config']->set('wallet', require __DIR__ . '/../config/wallet.php');
    }

    protected function setUpDatabase($app): void
    {
        // Create a users table for testing relationships
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        // Run the package's migrations
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }
}

/**
 * A dummy User model for testing purposes.
 */
class User extends \Illuminate\Foundation\Auth\User
{
    use HasWallets;

    protected $table = 'users';
    protected $guarded = [];
}