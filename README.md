# Liquidlab EuPlatesc Hyva Checkout Compatibility Module

This module provides compatibility between the EuroPayment_EuPlatesc payment module and Hyva Checkout.

## Installation

### Composer Installation

```bash
composer require liquidlab/module-euplatesc-hyva
```

### Manual Installation

1. Create the following directory structure in your Magento installation:
   ```
   app/code/Liquidlab/HyvaEuPlatesc
   ```

2. Download the module files and place them in the directory you created.

3. Enable the module:
   ```bash
   bin/magento module:enable Liquidlab_HyvaEuPlatesc
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy
   ```

## Features

- Integrates EuPlatesc payment method with Hyva Checkout
- Provides a seamless checkout experience for customers using EuPlatesc payment gateway

## Requirements

- Magento 2.4.x
- Hyva Checkout 1.1.0 or higher
- EuroPayment_EuPlatesc module

## Configuration

No additional configuration is required. The module automatically integrates with Hyva Checkout when both Hyva Checkout and EuroPayment_EuPlatesc are installed and enabled.

## Support

For support, please contact Liquidlab Agency at info@liquidlab.ro.

## License

This module is licensed under the MIT License.