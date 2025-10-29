<?php

namespace vahidkaargar\LaravelWallet\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Centralized logging service for wallet operations.
 * 
 * Provides configurable logging with support for different channels,
 * log levels, and optional features like stack traces and data masking.
 */
class LoggingService
{
    /**
     * Log an error with wallet operation context.
     *
     * @param string $message
     * @param array $context
     * @param Throwable|null $exception
     * @return void
     */
    public function logError(string $message, array $context = [], ?Throwable $exception = null): void
    {
        if (!$this->isErrorLoggingEnabled()) {
            return;
        }

        $logContext = $this->prepareLogContext($context, $exception);
        
        $this->log('error', $message, $logContext);
    }

    /**
     * Log an audit event for successful operations.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function logAudit(string $message, array $context = []): void
    {
        if (!$this->isAuditLoggingEnabled()) {
            return;
        }

        $logContext = $this->prepareLogContext($context);
        
        $this->log('info', $message, $logContext);
    }

    /**
     * Log a warning with wallet operation context.
     *
     * @param string $message
     * @param array $context
     * @param Throwable|null $exception
     * @return void
     */
    public function logWarning(string $message, array $context = [], ?Throwable $exception = null): void
    {
        if (!$this->isErrorLoggingEnabled()) {
            return;
        }

        $logContext = $this->prepareLogContext($context, $exception);
        
        $this->log('warning', $message, $logContext);
    }

    /**
     * Log an info message with wallet operation context.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function logInfo(string $message, array $context = []): void
    {
        if (!$this->isErrorLoggingEnabled()) {
            return;
        }

        $logContext = $this->prepareLogContext($context);
        
        $this->log('info', $message, $logContext);
    }

    /**
     * Check if error logging is enabled.
     *
     * @return bool
     */
    public function isErrorLoggingEnabled(): bool
    {
        return Config::get('wallet.logging.enabled', true);
    }

    /**
     * Check if audit logging is enabled.
     *
     * @return bool
     */
    public function isAuditLoggingEnabled(): bool
    {
        return Config::get('wallet.logging.audit_enabled', true);
    }

    /**
     * Get the configured log channel.
     *
     * @return string|null
     */
    public function getLogChannel(): ?string
    {
        return Config::get('wallet.logging.channel');
    }

    /**
     * Get the configured log level.
     *
     * @return string
     */
    public function getLogLevel(): string
    {
        return Config::get('wallet.logging.level', 'error');
    }

    /**
     * Check if stack traces should be included.
     *
     * @return bool
     */
    public function shouldIncludeStackTrace(): bool
    {
        return Config::get('wallet.logging.include_stack_trace', true);
    }

    /**
     * Check if sensitive data should be masked.
     *
     * @return bool
     */
    public function shouldMaskSensitiveData(): bool
    {
        return Config::get('wallet.logging.mask_sensitive_data', false);
    }

    /**
     * Prepare log context with optional exception and data masking.
     *
     * @param array $context
     * @param Throwable|null $exception
     * @return array
     */
    protected function prepareLogContext(array $context = [], ?Throwable $exception = null): array
    {
        $logContext = $context;

        // Add exception information if provided
        if ($exception) {
            $logContext['error'] = $exception->getMessage();
            
            if ($this->shouldIncludeStackTrace()) {
                $logContext['trace'] = $exception->getTraceAsString();
            }
        }

        // Add timestamp
        $logContext['timestamp'] = now()->toISOString();

        // Add package version for debugging
        $logContext['package'] = 'laravel-wallet';
        $logContext['version'] = '1.0.0';

        // Mask sensitive data if enabled
        if ($this->shouldMaskSensitiveData()) {
            $logContext = $this->maskSensitiveData($logContext);
        }

        return $logContext;
    }

    /**
     * Mask sensitive data in log context.
     *
     * @param array $context
     * @return array
     */
    protected function maskSensitiveData(array $context): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'card_number', 'ssn'];
        
        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys)) {
                $context[$key] = $this->maskValue($value);
            } elseif (is_array($value)) {
                $context[$key] = $this->maskSensitiveData($value);
            }
        }

        return $context;
    }

    /**
     * Mask a sensitive value.
     *
     * @param mixed $value
     * @return string
     */
    protected function maskValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '[MASKED]';
        }

        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
    }

    /**
     * Log a message using the configured channel and level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $channel = $this->getLogChannel();
        
        if ($channel) {
            Log::channel($channel)->{$level}($message, $context);
        } else {
            Log::{$level}($message, $context);
        }
    }
}
