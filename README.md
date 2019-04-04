“Regenerate Url rewrites” extension
=====================
Magento 2 "Regenerate Url rewrites" extension add a CLI feature which allow to regenerate a Url rewrites of products/categories in all stores or specific store.
Extension homepage: https://github.com/olegkoval/magento2-regenerate_url_rewrites

## CONTACTS
* Email: contact@olegkoval.com
* LinkedIn: https://www.linkedin.com/in/oleg-koval-85bb2314/

## DONATIONS / SUPPORT ME ON
* [Patreon](https://www.patreon.com/olegkoval)

## INSTALLATION

### COMPOSER INSTALLATION
* run composer command:
>`$> composer require olegkoval/magento2-regenerate-url-rewrites`

### MANUAL INSTALLATION
* extract files from an archive

* deploy files into Magento2 folder `app/code/OlegKoval/RegenerateUrlRewrites`

### ENABLE EXTENSION
* enable extension (use Magento 2 command line interface \*):
>`$> php bin/magento module:enable OlegKoval_RegenerateUrlRewrites`

* to make sure that the enabled module is properly registered, run 'setup:upgrade':
>`$> php bin/magento setup:upgrade`

* [if needed] re-deploy static view files:
>`$> php bin/magento setup:static-content:deploy`


## HOW TO USE IT:
* to regenerate URL rewrites of all products in all stores (only products) set entity type to "product":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product`

because `product` entity type is default - you can skip it:
>`$> php bin/magento ok:urlrewrites:regenerate`

* to regenerate URL rewrites of all categories\*\* in all stores set entity type to "category":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category`

\*\* take into account that this is a heavy process, see section "INFO".

* to regenerate URL rewrites in the specific store view (e.g.: store view id is "2") use option `--store-id`:
>`$> php bin/magento ok:urlrewrites:regenerate --store-id=2`

* to regenerate URL rewrites of specific product use option `product-id` (e.g.: product ID is "122"):
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --product-id=122`

or
>`$> php bin/magento ok:urlrewrites:regenerate --product-id=122`

* to regenerate URL rewrites of specific products range use option `products-range` (e.g.: regenerate for all products with ID between "101" and "152"):
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --products-range=101-152`

\* if in the range you have a gap of ID's (in range 101-152 products with ID's 110, 124, 150 not exists) - do not worry, script handle this.

or
>`$> php bin/magento ok:urlrewrites:regenerate --products-range=101-152`

* to regenerate URL rewrites of specific category use option `category-id` (e.g.: category ID is "15"):
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --category-id=15`

* to regenerate URL rewrites of specific categories range use option `categories-range` (e.g.: regenerate for all categories with ID between "4" and "12"):
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --categories-range=4-12`

\* if in the range you have a gap of ID's (in range 4-12 category with ID "6" not exists) - do not worry, script handle this.

* to skip re-generating a products URL rewrites in category if config option "Use Categories Path for Product URLs" is "No":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --check-use-category-in-product-url`

* to save a current URL rewrites (you want to get a new URL rewites and save current) use option `--save-old-urls`:
>`$> php bin/magento ok:urlrewrites:regenerate --save-old-urls`

* do not run full reindex at the end of URL rewrites generation use option `--no-reindex`:
>`$> php bin/magento ok:urlrewrites:regenerate --no-reindex`

* do not run cache:clean at the end of URL rewrites generation use option `--no-cache-clean`:
>`$> php bin/magento ok:urlrewrites:regenerate --no-cache-clean`

* do not run cache:flush at the end of URL rewrites generation use option `--no-cache-flush`:
>`$> php bin/magento ok:urlrewrites:regenerate --no-cache-flush`

* do not display a progress dots in the console (usefull for a stores with a big number of products):
>`$> php bin/magento ok:urlrewrites:regenerate --no-progress`

### YOU CAN COMBINE OPTIONS
>`$> php bin/magento ok:urlrewrites:regenerate --store-id=2 --save-old-urls --no-reindex`

### YOU CAN NOT COMBINE THIS OPTIONS TOGETHER
* `--entity-type=product` and `--category-id`/`--categories-range`
* `--entity-type=category` and `--product-id`/`--products-range`
* `--category-id` and/or `--categories-range` and/or `--product-id` and/or `--products-range`

### EXAMPLES
* Regenerate URL rewrites for product with ID "38" in store with ID "3":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --store-id=3 --product-id=38`

or
>`$> php bin/magento ok:urlrewrites:regenerate --store-id=3 --product-id=38`

* Regenerate URL rewrites for products with ID's 5,6,7,8,9,10,11,12 in store with ID "2" and do not run full reindex at the end of process:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --store-id=2 --products-range=5-12 --no-reindex`

* Regenerate URL rewrites for category with ID "22" in all stores and save current URL rewrites:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --category-id=22 --save-old-urls`

* Regenerate URL rewrites for categories with ID's 21,22,23,24,25 in store with ID "2":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --categories-range=21-25 --store-id=2`

## INFO
When you regenerate URL rewrites of some category then you regenerate URL rewrites of all products from this category AND URL rewrites of all subcategories (all levels - Magento use recursive logic) AND all products from this subcategories. This is the built-in Magento logic. So, take into account that regenerating of URL rewrites of any category (specially from top level) is a "heavy" process.

## HOW TO USE DEBUG INFORMATION:
If you see in the console log a message(-s) like this:
>`URL key for specified store already exists. Product ID: 1680. Request path: modelautos/schaal/revell-honda-nsx-1990-grijs-1-18.html`

or

>`URL key for specified store already exists. Category ID: 359. Request path: modelautos/automerk/filmauto.html`

Then you can find a product (or category) by provided ID and copy product (or category) name. After that you can search in the store for the product (or category) with same name and resolve conflict by updating/changing name of one of the products (or categories).

Enjoy!

Best regards,
Oleg Koval

-------------
\* see: http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands.html
