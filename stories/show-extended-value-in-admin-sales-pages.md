---
status: complete
---

# Show an extended address value on admin sales pages

**As a** merchant,  
**I want** to see the extended address values on the admin order, invoice, shipment and credit memo pages,  
**So that** I can process the order with all address information.

## Acceptance Criteria

### when-admin-order-page-is-opened

**Given** an order with an extended address value  
**When** the admin opens the order page  
**Then** `OrderAddressExtendedView::getValue()` returns the value.

### when-admin-invoice-shipment-or-credit-memo-page-is-opened

**Given** an order with an extended address value  
**When** the admin opens an invoice, shipment or credit memo of that order  
**Then** the value of the order is returned.

### when-order-has-no-value

**Given** an order without a row or an unknown order, invoice, shipment or credit memo ID  
**When** the page is opened  
**Then** an empty string is returned and nothing is shown.

## Tests
- `Test/Unit/ViewModel/Adminhtml/OrderAddressExtendedViewTest.php`
- `Test/Integration/ViewModel/Adminhtml/OrderAddressExtendedViewTest.php`
- `Test/Playwright/order-test.spec.ts`
