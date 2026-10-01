# Loki_QuoteAddressExtended

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/-/badges/release.svg)
<!-- badges.specs.end -->


## Installation
Install this package via composer:
```bash
composer require loki-checkout/magento2-quote-address-extended
```

Next, enable this module:
```bash
bin/magento module:enable Loki_QuoteAddressExtended
```


## Usage
This module adds a table `loki_quote_address_extended` with one row per quote address (`quote_address_id`). When the order is placed, each row is linked to the matching order address (`order_address_id`).

To store a custom value:

1. Add a column to `loki_quote_address_extended` via your own `etc/db_schema.xml` (plus `db_schema_whitelist.json`).
2. Add a field block to the Loki Checkout whose name ends with the column name (e.g. `loki-checkout.customer.example_address_field`), using the template `Loki_FieldComponents::form/field.phtml`. Optionally add the block arguments:
   - `address_type`: `shipping`, `billing` or `auto` (default; the shipping address, or the billing address for virtual quotes);
   - `field_name`: the column name, when it differs from the last part of the block name.
3. Register the block in your `etc/loki_components.xml` with the group `quoteAddressExtendedFields`.

To display the value:

- Frontend (current quote): `Loki\QuoteAddressExtended\ViewModel\CheckoutQuoteAddressExtendedView::getValue('example_address_field', 'auto')`
- Admin order and invoice pages: `Loki\QuoteAddressExtended\ViewModel\Adminhtml\OrderAddressExtendedView::getValue('example_address_field', 'auto')`
- Order and invoice emails: `{{var loki_shipping_address_extended.example_address_field}}` and `{{var loki_billing_address_extended.example_address_field}}`

Rows that are linked to neither a quote address nor an order address are removed by the cron job `loki_quote_address_extended_delete_orphaned_rows`.

See `Loki_QuoteAddressExtendedExample` for a complete example.


## Current status

<!-- badges.test.start -->
[![Static Tests](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/badges/main/pipeline.svg?job=static-tests)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/-/pipelines)
[![Unit Tests](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/badges/main/pipeline.svg?job=unit-tests)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/-/pipelines)
[![Integration Tests](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/badges/main/pipeline.svg?job=integration-tests)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/-/pipelines)
[![Playwright](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/badges/main/pipeline.svg?job=playwright)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/-/pipelines)
[![DI Compilation](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/badges/main/pipeline.svg?job=compile)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/-/pipelines)
<!-- badges.test.end -->
