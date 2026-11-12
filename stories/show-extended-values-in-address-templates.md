---
status: complete
---

# Show extended values in address templates

**As a** merchant,  
**I want** to use extended address values in the address templates,  
**So that** they appear in the customer account, print views and PDFs.

## Acceptance Criteria

### when-order-address-is-rendered

**Given** an address template (for instance `customer/address_templates/html` or `pdf`) contains `{{var <column>}}`  
**When** an order address with an extended value is rendered  
**Then** the value is shown, HTML-escaped for the html format  
**And** `{{depend <column>}}` and `{{if <column>}}` blocks are shown when the value is not empty.

### when-order-address-already-has-the-data

**Given** the order address already has data for that key, or the column is reserved  
**When** the address is rendered  
**Then** the order address data is not overwritten.

## Tests
- `Test/Unit/Observer/AddQuoteAddressExtendedToAddressFormatTest.php`
- `Test/Unit/Plugin/RenderQuoteAddressExtendedInAddressTemplateTest.php`
- `Test/Integration/Observer/AddQuoteAddressExtendedToAddressFormatTest.php`
