---
status: complete
---

# Copy extended values on reorder and order edit

**As a** merchant,  
**I want** the extended address values of an order to be copied when I reorder or edit it in the admin,  
**So that** the new order has the same address information.

## Acceptance Criteria

### when-admin-reorders-or-edits-an-order

**Given** an order with extended address values  
**When** the admin starts a reorder or an order edit  
**Then** the values are copied to the addresses of the new admin quote  
**And** they replace values copied from the customer address book.

### when-admin-order-is-placed

**Given** an admin quote with extended address values  
**When** the admin places the order  
**Then** the rows are linked to the new order addresses.

## Tests
- `Test/Unit/Plugin/CopyQuoteAddressExtendedOnInitFromOrderTest.php`
- `Test/Integration/Plugin/CopyQuoteAddressExtendedOnInitFromOrderTest.php`
