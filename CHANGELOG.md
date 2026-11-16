# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 16 November 2026
### Fixed
- Log the exception object when copying extended values fails
- Harden address template rendering of extended values
- Check that an existing address book row is kept without save in address book
- Show a generic message when saving admin address fields fails
- Let order values win over address book values on admin reorder
- Resolve address template blocks of empty extended columns
- Support nested depend and if directives in address templates
- Only copy extended values of customer addresses owned by the quote customer
- Add user stories and describe new features
- Cover new tables, observers, plugins and cron in ModuleTest
- Note socat requirement for CI playwright job

### Added
- Render extended values in address templates
- Edit extended values on the admin order address page
- Copy extended values on admin reorder and order edit
- Keep extended values when a guest quote is merged
- Sync extended values with the customer address book
- Support shipments and credit memos

## [0.0.2] - 29 October 2026
### Fixed
- Declare `Magento_Store` and phpunit dependencies

### Added
- Link extended quote address data to order addresses
- Add cron job to delete orphaned rows

## [0.0.1] - September 2026
- Initial release
