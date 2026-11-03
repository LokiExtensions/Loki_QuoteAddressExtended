# Project Memory

- Playwright tests depend on `Loki_QuoteAddressExtendedExample` (provides field `loki-checkout-customer-example-address-field-field`). CI installs it via `.gitlab-ci/composer-additional.sh`; Hersir installs it via `hersir-additional.sh` (run by `hersir.py module-install`/`go`). Keep both in sync.
- Blank admin pages (HTTP 200, empty body) in Hersir were caused by the image patch `lazy-objects.patch` lazy-proxying `Magento\Backend\Model\View\Page\Builder` (its constructor calls `$layout->setBuilder()`). Fixed in the Hersir images (now Mage-OS 4.0.0).
