# "Regenerate Url rewrites" Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.4.3] - 2019-05-12
### Added
- new option "no-regen-url-key"

### Changed
- fixed a "typo" issue

## [1.4.2] - 2019-04-04
### Added
- new option "--check-use-category-in-product-url"
- info into log about conflicted URL Rewrites

### Changed
- fixed logical issues in url_key regeneration
- fix for category/products rewrites for multistore
- fixed issue of division by zero in progress bar
- updated the url_key regeneration behavior to use UrlPathGenerators
- modified logic of displaying a console messages (notifications, errors, exceptions...)

## [1.4.1] - 2019-02-20
### Changed
- fixed issue of removing previously added URL rewrites of product when same URL key exists;
- modified progress bar

## [1.4.0] - 2019-02-11
### Added
- new option "--entity-type"
- new option "--products-range"
- new option "--product-id"
- new option "--category-range"
- new option "--category-id"

### Changed
- revised and restructured code
- modified logic of url rewrites regeneration
- removed "--clean-url-key"

## [1.3.1] - 2018-11-14
### Changed
- fixed issue of empty product URL keys
- fixed double slashes issue
- update category attributes via resource saveAttribute()
- use proxy for CategoryUrlPathGenerator

## [1.3.0] - 2018-10-29
### Added
- new option "--no-cache-clean"
- new option "--no-cache-flush"
- new option "--no-progress"
- new option "--no-clean-url-key"

### Changed
- optimized code
- modified logic of url rewrites regeneration
- fixed issue of store filter in categories collection

## [1.2.3] - 2018-10-03
### Added
- display additional debug information for "URL key for specified store already exists" error

### Changed
- modified logic of url rewrites regeneration

## [1.2.2] - 2018-10-02
### Changed
- fixed setStoreId() on null error

## [1.2.1] - 2018-09-25
### Changed
- fixed compilation issues

## [1.2.0] - 2018-09-25
### Changed
- added proxies to CLI commands
- modified logic of url rewrites regeneration
- updated a composer file
- fixed issue of a compatibility with new Magento Commerce versions

## [1.1.1] - 2018-09-10
### Changed
- fix composer file format issue

## [1.1.0] - 2018-09-09
### Added
- added feature to add a Pro features through a "Layer" class

### Changed
- fix issue when optional arguments require value
- updated a code structure

## [1.0.6] - 2018-07-26
### Added
- new option to run URL rewrite generation without running full reindex

### Changed
- update help notice to show INPUT_KEY_SAVE_REWRITES_HISTORY and INPUT_KEY_NO_REINDEX

## [1.0.5] - 2018-05-13
### Added
- new option to save current URL rewrites

### Changed
- improve a store ID arguments workflow

## [1.0.4] - 2017-11-13
### Added
- additional checks of storeId argument

## [1.0.3] - 2017-10-25
### Added
- checks if area code is set

## [1.0.2] - 2017-10-20
### Fixed
- fix "Area code not set" issue

## [1.0.1] - 2017-10-10
### Fixed
- fix store id issue in collection filter

## [1.0.0] - 2017-09-29
Release of Magento 2 "Regenerate Url rewrites" extension
