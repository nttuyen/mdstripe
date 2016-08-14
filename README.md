# Stripe
![Stripe](/views/img/stripebtnlogo.png)

Accept Payments with Stripe in PrestaShop with this free and open source module.

## Features
### Mission
The aim of this module is to obsolete all existing available modules for Stripe through its simplicity, security and speed.  
Contributions are more than welcome!

### Current features
- Process Credit Card, Alipay and Bitcoin (USD only) payments with Stripe
- Process refunds received by webhooks:
    - Partial refund
    - Full refund
    - Generate credit slip
- Refund from Back Office Order page
    - Partial refund
    - Full refund
- View transactions on Back Office Order Page
- View all transactions on the module configuration page
- Uses Stripe's Checkout form to stay up to date with the latest version
- Supports the Advanced checkout page of the Advanced EU Compliance module
- Supports the new `paymentOptions` hook of PrestaShop 1.7
- Supports the One Page Checkout module of PresTeamShop

### Roadmap
The issue page will give you a good overview of the current roadmap and priorities:
https://github.com/firstred/mdstripe/issues

## Installation
### Module installation
- Upload the module through FTP or your Back Office
- Install the module
- Check if there are any errors and correct them if necessary
- Profit!

## Documentation
The wiki can be found here: https://github.com/firstred/mdstripe/wiki

## Compatibility
This module has been tested with these versions:  
- `1.6.1.0`, `1.6.1.1`, `1.6.1.2`, `1.6.1.3`, `1.6.1.4`, `1.6.1.5`, `1.6.1.6`

The module is **NOT** compatible with Cloud

## Minimum requirements
- PrestaShop `1.6.1.0`
- PHP `5.4`
- `TLSv1.2` enabled cURL extension for PHP. More info: https://github.com/firstred/mdstripe/wiki/TLSv1.2

## License
Academic Free License 3.0
