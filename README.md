# Stripe
![Stripe](/views/img/stripebtnlogo.png)

Accept Payments with Stripe in PrestaShop with this free and open source module.

## Features
### Mission
The aim of this module is to obsolete all existing available modules for Stripe through its simplicity, security and speed.  
Contributions are more than welcome!

### Current features
- Process Credit Card, Apple Pay, Alipay and Bitcoin (USD only) payments with Stripe
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

### Roadmap
The issues page will give you a good overview of the current roadmap and priorities:
https://github.com/firstred/mdstripe/issues

## Installation
### Module installation
- Upload the module via your Prestashop Back Office
- Install the module
- Check if there are any errors and correct them if necessary
- Profit!

## Documentation
The wiki can be found here: https://github.com/firstred/mdstripe/wiki

## Minimum requirements
- PrestaShop `>= 1.5.0.17`
- PHP `>= 5.4`
- `TLSv1.2` enabled cURL extension for PHP. More info: https://github.com/firstred/mdstripe/wiki/TLSv1.2

### Compatibility
- PrestaShop `1.5.0.17` - `1.5.6.3` (Credit card form needs a lot of markup adjustments, depending on the theme)
- PrestaShop `1.6.0.5` - `1.6.1.10` (Credit card form doesn't need a lot of adjustments if the theme supports Bootstrap)
- PrestaShop `1.7.0.0` - `1.7.0.0` (Apple Pay and credit card form are not available, just the original Stripe checkout)

## License
Academic Free License 3.0
