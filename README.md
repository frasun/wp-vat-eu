# WP VAT EU — WooCommerce EU VAT Plugin

EU VAT compliance for WooCommerce stores.

## Features

- Compatible with classic and block themes
- Adds VAT/tax ID field to checkout and My Account settings
- Displays VAT/tax ID in order summary
- Saves VAT / tax ID in customer billing address
- Validates EU VAT number using VIES API
- Uses local VAT number validators as a fallback
- Sets VAT exemption for validated customers
- Adds VAT exemption cookie for dynamic cache compatibility

## Testing

```bash
npm test                # PHPUnit tests via wp-env
npm run coverage:text   # test coverage in terminal
npm run coverage:html   # test coverage as HTML report
```
