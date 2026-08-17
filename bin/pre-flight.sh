#!/bin/bash
# bin/pre-flight.sh

echo "Running Pre-Flight Checks..."

fail() {
    echo "ERROR: $1"
    exit 1
}

# 1. Check WP-CLI
command -v wp >/dev/null || fail "WP-CLI is not installed."
echo "✅ WP-CLI is installed."

# 2. Check PHP 7.4 & PHP 8.2
PHP74_VERSION=$(php7.4 -v 2>/dev/null | head -n 1 | grep -i "^php 7\.4")
if [ -z "$PHP74_VERSION" ]; then
    fail "PHP 7.4 is not active or php7.4 command is missing."
fi
echo "✅ PHP 7.4 is available."

PHP82_VERSION=$(php8.2 -v 2>/dev/null | head -n 1 | grep -i "^php 8\.2")
if [ -z "$PHP82_VERSION" ]; then
    fail "PHP 8.2 is not active or php8.2 command is missing."
fi
echo "✅ PHP 8.2 is available."

# 3. Check Node.js
command -v node >/dev/null || fail "Node.js is not installed."
echo "✅ Node.js is installed."

# 4. Check ImageMagick and Ghostscript
command -v convert >/dev/null || fail "ImageMagick (convert) is not installed."
echo "✅ ImageMagick is installed."

command -v gs >/dev/null || fail "Ghostscript (gs) is not installed."
echo "✅ Ghostscript is installed."

# 5. Check ImageMagick PDF read/write permissions
POLICY_XML=$(find /etc/ImageMagick* -name policy.xml 2>/dev/null | head -n 1)
if [ -n "$POLICY_XML" ]; then
    if grep -q 'pattern="PDF".*rights="none"' "$POLICY_XML"; then
        fail "ImageMagick policy.xml restricts PDF read/write."
    fi
    echo "✅ ImageMagick PDF policy allows read/write."
else
    echo "⚠️ ImageMagick policy.xml not found, assuming PDF allowed."
fi

# 6. Check PHP Imagick extensions for both versions
if ! php7.4 -m 2>/dev/null | grep -q -i imagick; then
    fail "PHP Imagick extension is not enabled for PHP 7.4."
fi
echo "✅ PHP 7.4 Imagick extension is enabled."

if ! php8.2 -m 2>/dev/null | grep -q -i imagick; then
    fail "PHP Imagick extension is not enabled for PHP 8.2."
fi
echo "✅ PHP 8.2 Imagick extension is enabled."

echo "All pre-flight checks passed successfully!"
exit 0
