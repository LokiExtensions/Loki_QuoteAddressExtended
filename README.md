# Loki_QuoteAddressExtended

<!-- badges.specs.start -->
<div class="gitlab-specs-badges">

![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/-/badges/release.svg)

</div>
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

1. Add a column to `loki_quote_address_extended` (and optionally to `loki_customer_address_extended`) via your own `etc/db_schema.xml` (plus `db_schema_whitelist.json`).
2. Add a field block to the Loki Checkout whose name ends with the column name (e.g. `loki-checkout.customer.example_address_field`), using the template `Loki_FieldComponents::form/field.phtml`. Optionally add the block arguments:
   - `address_type`: `shipping`, `billing` or `auto` (default; the shipping address, or the billing address for virtual quotes);
   - `field_name`: the column name, when it differs from the last part of the block name.
3. Register the block in your `etc/loki_components.xml` with the group `quoteAddressExtendedFields`.

To display the value:

- Frontend (current quote): `Loki\QuoteAddressExtended\ViewModel\CheckoutQuoteAddressExtendedView::getValue('example_address_field', 'auto')`
- Admin order, invoice, shipment and credit memo pages: `Loki\QuoteAddressExtended\ViewModel\Adminhtml\OrderAddressExtendedView::getValue('example_address_field', 'auto')`
- Order, invoice, shipment and credit memo emails: `{{var loki_shipping_address_extended.example_address_field}}` and `{{var loki_billing_address_extended.example_address_field}}`
- Address templates (customer account, print views and PDFs, configured under **Stores > Configuration > Customers > Customer Configuration > Address Templates**): `{{var example_address_field}}`. The values are added to the order address data through the event `customer_address_format`, without overwriting existing order address data, and rendered by a plugin on `Magento\Customer\Block\Address\Renderer\DefaultRenderer::renderArray()` (HTML-escaped for the `html` format). `{{depend example_address_field}}` and `{{if example_address_field}}...{{else}}...{{/if}}` are supported as well, also nested.

### Editing in the admin
To make a column editable on the admin order address edit page, declare it with a label in your `etc/di.xml`:

```xml
<type name="Loki\QuoteAddressExtended\Config\AdminFields">
    <arguments>
        <argument name="fields" xsi:type="array">
            <item name="example_address_field" xsi:type="string">Example address field</item>
        </argument>
    </arguments>
</type>
```

Only declared columns are saved. Reserved columns (`entity_id`, `quote_address_id`, `order_address_id`, `address_type`, `created_at`, `updated_at`) are ignored.

### Customer address book
The table `loki_customer_address_extended` holds one row per customer address (`customer_address_id`). Add the same column to this table to sync it with the address book:

- when a quote address gets a customer address, the values of the customer address are copied to the quote address (only when the quote address has no value yet); when the customer switches to another customer address, the values are replaced;
- when an address is saved in the address book while placing an order (or the customer address has no row yet), the values of the quote address are copied to the customer address. With "same as billing", the values of the shipping address are used for the billing address.

Only columns that exist in both tables are copied, and only when the customer address belongs to the customer of the quote (never for guests).

### Other behaviour
- When a guest logs in, the values of the guest quote are copied to the customer quote (per address type), when that address is still empty or uses the same customer address, and only for columns without a value.
- When an order is reordered or edited in the admin, the values of the order are copied to the new admin quote, and linked to the new order when it is placed.
- Rows of `loki_quote_address_extended` that are linked to neither a quote address nor an order address, and rows of `loki_customer_address_extended` without an existing customer address, are removed by the cron job `loki_quote_address_extended_delete_orphaned_rows`.

### Limitations
Multishipping checkout is not supported: the rows are linked to the quote addresses of a regular checkout only.

See `Loki_QuoteAddressExtendedExample` for a complete example.


## Current status

<!-- badges.test.start -->
<div class="gitlab-pipeline-badges">

[![GitLab CI Pipelines](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/badges/main/pipeline.svg?style=flat-square
)](https://gitlab.yireo.com/loki-checkout/Loki_QuoteAddressExtended/-/pipelines)

</div>
<!-- badges.test.end -->
