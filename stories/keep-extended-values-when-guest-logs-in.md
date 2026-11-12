---
status: complete
---

# Keep extended values when a guest logs in

**As a** customer,  
**I want** the extended address values I entered as a guest to be kept when I log in,  
**So that** I do not need to enter them again.

## Acceptance Criteria

### when-guest-quote-is-merged-into-customer-quote

**Given** a guest quote with extended address values  
**When** the customer logs in and the guest quote is merged into the customer quote  
**Then** the values are copied to the address of the same type (shipping or billing) of the customer quote.

### when-customer-quote-has-another-address

**Given** the address of the customer quote is already filled in, or uses another customer address than the guest address  
**When** the quotes are merged  
**Then** no values are copied.

### when-customer-quote-already-has-a-value

**Given** the customer quote address already has a value for a column  
**When** the quotes are merged  
**Then** that value is kept.

## Tests
- `Test/Unit/Observer/CopyQuoteAddressExtendedOnQuoteMergeTest.php`
- `Test/Unit/Util/ExtendedDataCopierTest.php`
- `Test/Integration/Observer/CopyQuoteAddressExtendedOnQuoteMergeTest.php`
