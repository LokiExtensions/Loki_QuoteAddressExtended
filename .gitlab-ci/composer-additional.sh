#!/bin/bash
# The Playwright tests run against both Luma and Hyva, and use the field of Loki_QuoteAddressExtendedExample
if [ "${CI_JOB_NAME}" = "playwright" ]; then
  for HYVA_PACKAGE in magento2-default-theme-csp magento2-theme-module magento2-base-layout-reset magento2-email-module magento2-graphql-tokens magento2-graphql-view-model magento2-order-cancellation-webapi magento2-mollie-theme-bundle magento2-compat-module-fallback; do
    composer config repositories.hyva-${HYVA_PACKAGE} vcs https://github.com/hyva-themes/${HYVA_PACKAGE}.git
  done

  composer require -n --prefer-source -- \
    loki-checkout/magento2-hyva \
    hyva-themes/magento2-default-theme-csp \
    hyva-themes/magento2-theme-module \
    hyva-themes/magento2-base-layout-reset \
    hyva-themes/magento2-email-module \
    hyva-themes/magento2-graphql-tokens \
    hyva-themes/magento2-graphql-view-model \
    hyva-themes/magento2-order-cancellation-webapi \
    hyva-themes/magento2-compat-module-fallback

  composer require --no-update -- loki-checkout/magento2-quote-address-extended-example:@dev
fi
