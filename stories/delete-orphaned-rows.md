---
status: complete
---

# Delete orphaned rows

**As a** merchant,  
**I want** rows that are no longer linked to anything to be removed,  
**So that** the tables do not grow forever.

## Acceptance Criteria

### when-quote-row-is-orphaned

**Given** a row in `loki_quote_address_extended` without a quote address and without an order address  
**When** the cron job `loki_quote_address_extended_delete_orphaned_rows` runs  
**Then** the row is deleted  
**And** rows linked to a quote address or an order address are kept.

### when-customer-row-is-orphaned

**Given** a row in `loki_customer_address_extended` whose customer address no longer exists  
**When** the cron job runs  
**Then** the row is deleted.

### when-customer-address-is-deleted

**Given** a customer address with a row in `loki_customer_address_extended`  
**When** the customer address is deleted  
**Then** the row is deleted as well.

## Tests
- `Test/Unit/Cron/DeleteOrphanedRowsTest.php`
- `Test/Unit/Model/ResourceModel/QuoteAddressExtendedTest.php`
- `Test/Unit/Model/ResourceModel/CustomerAddressExtendedTest.php`
- `Test/Integration/Cron/DeleteOrphanedRowsTest.php`
- `Test/Integration/Repository/CustomerAddressExtendedRepositoryTest.php`
