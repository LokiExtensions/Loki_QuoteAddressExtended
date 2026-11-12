---
status: complete
---

# Edit extended values in the admin

**As a** merchant,  
**I want** to edit the extended address values on the admin order address page,  
**So that** I can correct them after the order was placed.

## Acceptance Criteria

### when-admin-opens-order-address-edit-page

**Given** columns are declared in the `fields` argument of `Loki\QuoteAddressExtended\Config\AdminFields` (column => label)  
**When** the admin opens the order address edit page  
**Then** a text field per column is shown with the current value.

### when-admin-saves-order-address

**Given** the admin changed a declared field  
**When** the order address is saved  
**Then** the value is stored in the row linked to the order address  
**And** the row is created when it does not exist yet.

### when-saving-the-extended-values-fails

**Given** the order address was saved  
**When** the extended values cannot be saved  
**Then** a generic error message is shown and the error is logged.

### when-column-is-not-declared-or-reserved

**Given** a posted column is not declared or is reserved  
**When** the order address is saved  
**Then** that column is not saved.

## Tests
- `Test/Unit/Config/AdminFieldsTest.php`
- `Test/Unit/Plugin/AddAdminFieldsToOrderAddressFormTest.php`
- `Test/Unit/Observer/SaveAdminOrderAddressExtendedTest.php`
- `Test/Integration/Plugin/AddAdminFieldsToOrderAddressFormTest.php`
- `Test/Integration/Controller/Adminhtml/OrderAddressSaveTest.php`
