#!/bin/bash
if [ "${GITHUB_JOB}" = "playwright" ]; then
  composer config repositories.quote-address-extended-example vcs https://github.com/LokiExtensions/Loki_QuoteAddressExtendedExample.git
  composer require --no-update -- loki-checkout/magento2-quote-address-extended-example:@dev
fi
