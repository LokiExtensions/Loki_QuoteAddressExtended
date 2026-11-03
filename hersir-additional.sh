#!/bin/bash
# Install Loki_QuoteAddressExtendedExample, needed by the Playwright tests (never in production)
set -e

composer show loki-checkout/magento2-quote-address-extended-example >/dev/null 2>&1 \
  || composer require -n -- loki-checkout/magento2-quote-address-extended-example:@dev
bin/magento module:enable Loki_QuoteAddressExtendedExample
