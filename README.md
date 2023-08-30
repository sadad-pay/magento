# Sadad Payment for Magento2 e-commerce

## Installation
1. Install the Sadad Payment Magento2 module via [sadad/magento-payment](https://packagist.org/packages/sadad/magento-payment) composer.
```bash
composer require sadad/magento-payment
```

2. In the command line, run the below Magento commands to enable Sadad Gateway module.
```bash
php -f bin/magento module:enable --clear-static-content Sadad_Gateway
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush
```

## Admin Configurations

1. Login into Magento admin panel.
2. In the left Menu → Stores → Configuration section
2. In Sales Section → Payment Methods → Sadad Payment
3. Login to the Sadad account and get Sadad Client ID and Secret. Then, use that information in the Magento Sadad Pay settings page.
