---
status: complete
---

# Link extended address data to the order address

**As a** merchant,  
**I want** the extended address data to be linked to the order addresses when an order is placed,  
**So that** the data remains available for the order after the quote is gone.

## Acceptance Criteria

### when-order-is-placed

**Given** a quote address has a row in `loki_quote_address_extended`  
**When** the order is placed  
**Then** the row gets the `order_address_id` of the matching order address.

### when-quote-address-is-reused-for-another-order

**Given** a row is already linked to an order address  
**When** the same quote address is changed again or used for another order  
**Then** a copy of the row is created for the quote address  
**And** the row of the first order keeps its value.

### when-quote-address-is-deleted

**Given** a row linked to an order address  
**When** the quote address is deleted  
**Then** the row stays linked to the order address.

## Tests
- `Test/Unit/Observer/LinkOrderAddressesToQuoteAddressExtendedTest.php`
- `Test/Unit/Repository/QuoteAddressExtendedRepositoryTest.php`
- `Test/Integration/LinkOrderAddressesTest.php`
- `Test/Integration/Observer/LinkOrderAddressesToQuoteAddressExtendedTest.php`
- `Test/Integration/Observer/LinkCustomerOrderAddressesTest.php`
- `Test/Integration/Model/ResourceModel/QuoteAddressExtendedTest.php`
