---
status: complete
---

# Sync extended values with the customer address book

**As a** customer,  
**I want** the extended address values to be saved with my address book and reused in the checkout,  
**So that** I only need to enter them once.

## Acceptance Criteria

### when-customer-address-is-selected-in-checkout

**Given** a column exists in both `loki_quote_address_extended` and `loki_customer_address_extended`  
**And** the customer address has a value for it  
**When** the quote address is saved with that customer address  
**Then** the value is copied to the quote address, unless the quote address already has a value.

### when-customer-switches-to-another-saved-address

**Given** the quote address uses customer address A  
**When** the customer selects customer address B  
**Then** the values of the columns that exist in both tables are replaced by the values of B (empty when B has none).

### when-address-is-saved-in-address-book

**Given** a customer quote address with extended values and "save in address book"  
**When** the order is placed  
**Then** the values of the columns that exist in both tables are copied to the customer address.

### when-customer-address-has-no-extended-row-yet

**Given** a customer quote address with extended values linked to a customer address without a row in `loki_customer_address_extended`  
**When** the order is placed  
**Then** a row is created with the values, also without "save in address book".

### when-shipping-address-is-same-as-billing

**Given** the shipping address is the same as the billing address and only the shipping quote address has extended values  
**When** the billing address is saved in the address book  
**Then** the values of the shipping quote address are copied to the customer address.

### when-column-exists-in-one-table-only

**Given** a column exists in only one of the two tables  
**When** values are copied  
**Then** that column is not copied.

## Tests
- `Test/Unit/Observer/CopyCustomerAddressExtendedToQuoteAddressTest.php`
- `Test/Unit/Observer/CopyQuoteAddressExtendedToCustomerAddressTest.php`
- `Test/Unit/Repository/CustomerAddressExtendedRepositoryTest.php`
- `Test/Unit/Model/CustomerAddressExtendedTest.php`
- `Test/Unit/Util/ExtendedDataCopierTest.php`
- `Test/Integration/Observer/CopyCustomerAddressExtendedToQuoteAddressTest.php`
- `Test/Integration/Observer/CopyQuoteAddressExtendedToCustomerAddressTest.php`
- `Test/Integration/Repository/CustomerAddressExtendedRepositoryTest.php`
