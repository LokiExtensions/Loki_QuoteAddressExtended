# Project Memory

- Playwright tests depend on `Loki_QuoteAddressExtendedExample` (provides field `loki-checkout-customer-example-address-field-field`). CI installs it via `.gitlab-ci/composer-additional.sh`; Hersir installs it via `hersir-additional.sh` (run by `hersir.py module-install`/`go`). Keep both in sync.
- CI playwright job: generic `.gitlab-ci/magento-install.sh` needs `socat` (HTTPS proxy on :8443) in image `yireo/magento2playwright`. Because of `magento-install.sh || true`, install failures surface later as `ECONNREFUSED 127.0.0.1:8443` in Playwright. `socat` was added to the image on 2026-11-03.
- Blank admin pages (HTTP 200, empty body) in Hersir were caused by the image patch `lazy-objects.patch` lazy-proxying `Magento\Backend\Model\View\Page\Builder` (its constructor calls `$layout->setBuilder()`). Fixed in the Hersir images (now Mage-OS 4.0.0).
