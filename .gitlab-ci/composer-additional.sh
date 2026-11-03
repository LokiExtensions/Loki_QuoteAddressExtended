#!/bin/bash
# The Playwright tests run against both Luma and Hyva, and use the field of Loki_QuoteAddressExtendedExample
if [ "${CI_JOB_NAME}" = "playwright" ]; then
  composer config repositories.quote-address-extended-example vcs https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtendedExample.git
  composer require --no-update -- loki-checkout/magento2-quote-address-extended-example:@dev
fi
