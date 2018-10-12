“Regenerate Url rewrites” extension
=====================
Magento 2 "Regenerate Url rewrites" extension add a CLI feature which allow to regenerate a Url rewrites of products/categories in all stores or specific store.
Extension homepage: https://github.com/olegkoval/magento2-regenerate_url_rewrites

## DONATIONS / SUPPORTING ME
You can support me here:
* https://www.patreon.com/olegkoval
* https://www.liqpay.ua/en/checkout/card/380983346262

## INSTALLATION

### COMPOSER INSTALLATION
* run composer command:
>`$> composer require olegkoval/magento2-regenerate-url-rewrites`

### MANUAL INSTALLATION
* extract files from an archive

* deploy files into Magento2 folder `app/code/OlegKoval/RegenerateUrlRewrites`

### ENABLE EXTENSION
* enable extension (use Magento 2 command line interface *):
>`$> bin/magento module:enable OlegKoval_RegenerateUrlRewrites`

* to make sure that the enabled module is properly registered, run 'setup:upgrade':
>`$> bin/magento setup:upgrade`

* [if needed] re-deploy static view files:
>`$> bin/magento setup:static-content:deploy`


## HOW TO USE IT:
* to re-generate all Url rewrites of the categories/products in all stores (it support a multistores) run:
>`$> bin/magento ok:urlrewrites:regenerate`

* to regenerate all Url rewrites of the categories/products in the specific store view (e.g.: store view id is "2"):
>`$> bin/magento ok:urlrewrites:regenerate 2`
or
>`$> bin/magento ok:urlrewrites:regenerate --storeId=2`

* to save a current URL rewrites (e.g.: you've updated a name of product(s)/category(-ies) and want to get a new URL rewites and save current):
>`$> bin/magento ok:urlrewrites:regenerate --save-old-urls`

* to do not run full reindex at the end of Url rewrites generation:
>`$> bin/magento ok:urlrewrites:regenerate --no-reindex`

* to do not run cache:clean at the end of Url rewrites generation:
>`$> bin/magento ok:urlrewrites:regenerate --no-cache-clean`

* to do not run cache:flush at the end of Url rewrites generation:
>`$> bin/magento ok:urlrewrites:regenerate --no-cache-flush`

* also you can combine a options:
>`$> bin/magento ok:urlrewrites:regenerate 2 --save-old-urls`
or
>`$> bin/magento ok:urlrewrites:regenerate --storeId=2 --save-old-urls`

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
