---
status: complete
---

# Show an extended address value in the checkout

**As a** developer,  
**I want** a view model that returns an extended address value of the current quote,  
**So that** I can show it in the checkout, for instance in the sidebar.

## Acceptance Criteria

### when-value-exists

**Given** the current quote address has a value for a column  
**When** `CheckoutQuoteAddressExtendedView::getValue()` is called with that column and an address type  
**Then** the value is returned.

### when-no-quote-or-row-exists

**Given** there is no quote, no quote address or no row  
**When** `getValue()` is called  
**Then** an empty string is returned.

## Tests
- `Test/Unit/ViewModel/CheckoutQuoteAddressExtendedViewTest.php`
- `Test/Integration/ViewModel/CheckoutQuoteAddressExtendedViewTest.php`
