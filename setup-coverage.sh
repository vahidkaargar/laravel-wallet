#!/bin/bash

# Laravel Wallet - Code Coverage Setup Script
# This script helps you install code coverage drivers for PHPUnit

echo "🔍 Checking for code coverage drivers..."

# Check if Xdebug is installed
if php -m | grep -q "xdebug"; then
    echo "✅ Xdebug is already installed and enabled"
    echo "📊 You can now run: composer test-coverage"
    exit 0
fi

# Check if PCOV is installed
if php -m | grep -q "pcov"; then
    echo "✅ PCOV is already installed and enabled"
    echo "📊 You can now run: composer test-coverage"
    exit 0
fi

echo "❌ No code coverage driver found"
echo ""
echo "To enable code coverage, you need to install either Xdebug or PCOV:"
echo ""
echo "🐘 For Xdebug (recommended for development):"
echo "   - macOS: brew install php-xdebug"
echo "   - Ubuntu: sudo apt-get install php-xdebug"
echo "   - CentOS: sudo yum install php-xdebug"
echo ""
echo "⚡ For PCOV (faster, recommended for CI):"
echo "   - macOS: brew install php-pcov"
echo "   - Ubuntu: sudo apt-get install php-pcov"
echo "   - CentOS: sudo yum install php-pcov"
echo ""
echo "After installation, restart your web server and run: composer test-coverage"
echo ""
echo "💡 Note: You can still run tests without coverage using: composer test"
