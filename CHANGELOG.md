# "Regenerate Url Rewrites" Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Changed
- the command now reports failures instead of silently skipping them: products/categories whose URL
  rewrites couldn't be generated or saved (and failed cleanup steps) are listed in an end-of-run summary —
  counts per entity type plus up to 20 of the failures — and the command **exits with code 1**. Reindex and
  cache refresh still run first. Previously it always exited 0, so cron jobs/scripts that check the exit
  code may now start alerting on failures that used to be hidden.
- all-stores runs no longer regenerate every store view twice: the store 0 (default scope) pass now only
  updates default-scope `url_key`/`url_path`, and each store view is generated once — with identical
  results; on a two-store-view sample catalog full runs were ~15–30% faster, and the saving grows with the
  number of store views

### Fixed
- `--store-id=0` (global scope) runs dropped the product rewrites of every store view after the first
  (identical paths in different store views were treated as duplicates) and didn't replace the store views'
  current rewrites, so old URLs kept serving pages and `--save-old-urls` redirects were lost
- `--save-old-urls` now actually creates 301 redirects: when a URL changes, the old URL redirects to the
  new one. Before, the old URL kept serving the page as an ordinary rewrite (duplicate content) and the
  redirect Magento generated was silently dropped when saving (#174, #142). This includes switching to
  `--add-sku-to-url` with `--save-old-urls`: the old unsuffixed URL now redirects to the SKU URL
- `--add-sku-to-url` (and path clean-up/de-duplication in general) no longer alters custom URL
  rewrites: admin-created rewrites, and old-URL redirects kept by `--save-old-urls`, keep their exact
  request path. Before, `--add-sku-to-url` appended the SKU to them too, so a custom URL like
  `promo/sale` became `promo/sale-<sku>` and the original URL stopped working.
- with `--add-sku-to-url`, custom redirects (and `--save-old-urls` redirects) pointing at a product's URL
  now follow it to the SKU-suffixed URL; before, they kept pointing at the old unsuffixed URL, which no
  longer existed (redirect to a 404)
- a product/category whose generated URL paths were all empty no longer has its existing rewrites
  touched (nothing to write, so the current rewrites are kept)

## [1.9.1] - 2026-08-26
### Added
- new options `--set-product-suffix` / `--set-category-suffix` — set the product/category URL suffix
  (e.g. `.html`) before regenerating, via the same write path Magento's own `config:set` CLI command
  uses, so validation and the automatic suffix swap on existing url_rewrite rows both run. Applied to
  Default Config and every store view, or only the store given via `--store-id`. If either suffix value
  fails Magento's validation, the whole command aborts before any regeneration runs.

### Fixed
- category `url_path` was not recalculated per store view: when a category's `url_path` had only ever
  been saved at the default (store 0) scope, Magento's `CategoryUrlPathGenerator` short-circuited and
  handed back that inherited value instead of building a new one from the store-scoped `url_key`,
  because the category object's still-populated `url_path` made its `dataHasChangedFor()` checks look
  unchanged. Now `url_path` is cleared on the category object before generation, matching the pattern
  already used on the product side. (#184)

## [1.9.0] - 2026-08-11
### Added
- new option `--skip-products` — skip regenerating associated product URLs when regenerating categories (useful on large catalogs when "Use Category Path for Product URLs" is enabled)
- new option `--skip-existing` — skip an entity entirely if it already has any url_rewrite for the current store, instead of always deleting + regenerating
- new option `--include-not-visible` — also regenerate URLs for products with visibility "Not Visible Individually" (e.g. configurable child products), excluded by default since 1.6.2
- new option `--add-sku-to-url` — append the product's SKU as a URL segment in generated product Url Rewrites, e.g. `screws.html` -> `screws-2244000004.html`

### Fixed
- guard against writing a blank url_key when Magento's own transliteration can't handle the product/category title (e.g. Arabic/other non-Latin scripts)

## [1.8.0] - 2026-08-10
### Changed
- **BREAKING**: inverted url_key regeneration default — url_key is no longer regenerated automatically; use the new `--regen-url-key` option to opt in. The old `--no-regen-url-key` option is removed entirely (not deprecated) — update any existing scripts/cron jobs that relied on the previous default.
- fixed data loss risk in `saveUrlRewrites()`: deleting existing rewrites and inserting new ones now happen in a single transaction, so a failed insert can no longer leave an entity with its rewrites deleted and nothing to replace them
- fixed category URL rewrite regeneration to process every category (top level down to the deepest child) instead of only top-level categories, so child categories' own url_key/url_path are regenerated too
- skip writing a redundant per-store url_key override when the regenerated value is identical to the default-scope value

### Added
- new option `--delete-orphaned-rewrites` — deletes url_rewrite rows (for the given `--entity-type`) whose product/category no longer exists

## [1.7.3] - 2026-08-09
### Changed
- fixed deprecated non-canonical (double) type cast for PHP 8.5 compatibility
- use the current PHP executable (PHP_BINARY) instead of a hardcoded "php" command for reindex/cache calls
- fixed category URL rewrite regeneration stopping entirely when a single broken/orphaned category is encountered
- added missing type hint and defensive casting in a couple of helper methods
- declared minimum supported PHP version (>=8.2) in composer.json

## [1.7.2] - 2026-03-31
### Changed
- fixed deprecated functionality: ctype_digit()
- improved SQL safety in URL rewrite regeneration queries
- fixed SQL escaping in URL rewrite cleanup logic
- fixed _clearRequestPath() to correctly handle multiple consecutive slashes
- removed dead code from category URL rewrite model
- display validation errors before command failure
- display a completed progress bar for empty product/category collections
- normalized line endings to LF

## [1.7.1] - 2025-05-30
### Changed
- adapted for compatibility with PHP 8.4 (deprecated implicitly nullable types)

## [1.7.0] - 2025-04-15
### Changed
- adapted for compatibility with Magento 2.4.7-p4
- adapted the composer.json file to be compatible with Composer 2

## [1.6.2] - 2023-10-10
### Changed
- fixed Symfony Command constant issue
- exclude non visible products from url regeneration

## [1.6.1] - 2023-08-24
### Changed
- fixed compatibility with Symfony Console 5 and Magento 2.4.6
- updated contact email to Gmail email (my own domain olegkoval.com was stolen)

## [1.6.0] - 2021-01-27
### Changed
- adapted to Magento 2.3.5
- fixed incorrect generation when URL suffix is slash

## [1.5.6] - 2020-04-13
### Changed
- updated logic of "cleaning" of Url Rewrites and duplications check

## [1.5.5] - 2020-04-02
### Changed
- updated logic of Url Rewrite regeneration via category entity
- fixed compilation issue in helper

## [1.5.4] - 2020-03-21
### Changed
- fixed issue of non-empty/non-false "request_path" of product entity.
- modified logic of Url Rewrite db table updates

## [1.5.3] - 2020-03-20
### Changed
- updated Url Rewrite preparing function
- updated logic of Url Rewrite regeneration via category entity
- updated save logic

## [1.5.2] - 2020-03-18
### Changed
- updated logic of Url Rewrite regeneration via category entity
- CLI options logic optimized (for category entity)

## [1.5.1] - 2020-03-08
### Changed
- fixed issue of url_key and url rewrites regeneration based on product name value

## [1.5.0] - 2020-02-26
### Changed
- revised and restructured code
- modified functional logic of extension
- removed option "--check-use-category-in-product-url"

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
- a fix for category/products rewrites for multistore
- fixed issue of division by zero in progress bar
- update the url_key regeneration behavior to use UrlPathGenerators
- modified logic of displaying console messages (notifications, errors, exceptions...)

## [1.4.1] - 2019-02-20
### Changed
- fixed the issue of removing previously added URL rewrites of product when the same URL key exists;
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
- fixed issue of store filter in a category collection

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
- improve the store ID arguments workflow

## [1.0.4] - 2017-11-13
### Added
- additional checks of storeId argument

## [1.0.3] - 2017-10-25
### Added
- check if area code is set

## [1.0.2] - 2017-10-20
### Fixed
- fix "Area code not set" issue

## [1.0.1] - 2017-10-10
### Fixed
- fix store id issue in collection filter

## [1.0.0] - 2017-09-29
Release of Magento 2 "Regenerate Url Rewrites" extension
