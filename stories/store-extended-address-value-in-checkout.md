---
status: complete
---

# Store an extended address value in the checkout

**As a** developer,  
**I want** to add a custom column to `loki_quote_address_extended` and a Loki Checkout field for it,  
**So that** customers can enter additional address data that is stored per quote address.

## Acceptance Criteria

### when-field-is-filled-in

**Given** a column has been added to `loki_quote_address_extended`  
**And** a field block with that column name is registered in the component group `quoteAddressExtendedFields`  
**When** the customer fills in the field in the checkout  
**Then** the value is stored in the row linked to the quote address  
**And** the value is shown again after switching steps or reloading the checkout.

### when-address-type-is-auto

**Given** the block argument `address_type` is `auto` or missing  
**When** the value is saved  
**Then** it is stored with the shipping address, or with the billing address when the quote is virtual.

### when-address-type-is-shipping-or-billing

**Given** the block argument `address_type` is `shipping` or `billing`  
**When** the value is saved  
**Then** it is stored with that address.

### when-field-name-is-a-reserved-column

**Given** the field name is one of `entity_id`, `quote_address_id`, `order_address_id`, `address_type`, `created_at` or `updated_at`  
**When** a value is saved  
**Then** nothing is stored.

### when-a-new-checkout-is-started

**Given** an order was placed with a value  
**When** the customer starts a new checkout  
**Then** the field is empty.

## Tests
- `Test/Unit/Component/QuoteAddressExtendedField/QuoteAddressExtendedFieldRepositoryTest.php`
- `Test/Unit/Component/QuoteAddressExtendedField/QuoteAddressExtendedFieldViewModelTest.php`
- `Test/Unit/Util/AddressResolverTest.php`
- `Test/Integration/Component/QuoteAddressExtendedFieldRepositoryTest.php`
- `Test/Integration/Repository/QuoteAddressExtendedRepositoryTest.php`
- `Test/Playwright/checkout-test.spec.ts`
