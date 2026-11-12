---
status: complete
---

# Use extended address values in sales emails

**As a** merchant,  
**I want** the extended address values to be available in the order, invoice, shipment and credit memo emails,  
**So that** customers receive all address information.

## Acceptance Criteria

### when-sales-email-is-sent

**Given** an order with rows for its shipping and billing address  
**When** the order, invoice, shipment or credit memo email is sent  
**Then** the variables `loki_shipping_address_extended` and `loki_billing_address_extended` are available  
**And** `{{var loki_shipping_address_extended.<column>}}` renders the value.

### when-row-is-not-linked-yet

**Given** the row is not yet linked to the order address  
**When** the email is sent  
**Then** the row is found through the quote address of the order address.

### when-there-are-no-rows

**Given** an order without rows  
**When** the email is sent  
**Then** the variables are not added.

## Tests
- `Test/Unit/Observer/AddQuoteAddressExtendedToEmailVarsTest.php`
- `Test/Integration/Observer/AddQuoteAddressExtendedToEmailVarsTest.php`
- `Test/Integration/Email/TemplateVariableTest.php`
