# Prycing

![License](https://img.shields.io/github/license/prycing/woocommerce-plugin)
![WP Compatible](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![WC Compatible](https://img.shields.io/badge/WooCommerce-3.0%2B-a46497)
![PHP Compatible](https://img.shields.io/badge/PHP-7.2%2B-blueviolet)

A WordPress plugin that automatically updates WooCommerce product prices from the Prycing platform with support for High-Performance Order Storage (HPOS).

## Description

Prycing allows you to easily synchronize your WooCommerce product prices with the Prycing software platform. The plugin fetches data from your Prycing account and updates your product prices automatically, either on a schedule or manually when needed.

### Features

- **Price Synchronization**: Import product prices from Prycing platform
- **Regular & Sale Prices**: Support for both regular prices and sale prices
- **Sale Date Ranges**: Set sale start and end dates automatically
- **Flexible Scheduling**: Update prices from every minute to weekly
- **Product Matching**: Match products using EAN/barcode or SKU
- **WooCommerce HPOS Compatible**: Works with High-Performance Order Storage
- **Performance Optimized**: Uses WP_Query instead of direct database queries
- **Detailed Logging**: Track all updates and errors in WooCommerce logs
- **Simple UI**: Clean and intuitive admin interface

## Installation

### From GitHub:

1. Download the latest release
2. Upload the entire `prycing` directory to `/wp-content/plugins/`
3. Activate through the 'Plugins' menu in WordPress

### Using Composer:

```bash
composer require prycing/woocommerce-plugin
```

## Configuration

1. Go to WooCommerce → Prycing in your WordPress admin
2. Enter the URL of your Prycing feed
3. Choose your preferred update frequency
4. Save settings

## Product Matching Logic

The plugin matches products in the following order:

1. First checks if the EAN is stored as a product SKU
2. Then checks various meta fields commonly used for storing EANs/barcodes:
   - `_ean`
   - `_barcode`
   - `ean`
   - `barcode`

## Update Frequencies

Choose from the following update frequencies:

- **Every 30 Seconds**: For near real-time pricing (use with caution - high server load)
- **Every Minute**: For very frequent price updates
- **Every 5 Minutes**: Good balance for regular updates
- **Every 10 Minutes**: Moderate update frequency
- **Every 30 Minutes**: Regular updates with minimal server impact
- **Hourly**: Updates once per hour
- **Twice Daily**: Updates twice per day
- **Daily**: Updates once per day (default)
- **Weekly**: Updates once per week

**Note**: Using very frequent updates (30 seconds, 1 minute, or 5 minutes) can significantly impact server performance and is recommended only for development environments or small product catalogs. For production sites with many products, consider using longer intervals.

## WooCommerce Compatibility

This plugin is fully compatible with:

- WooCommerce 3.0+
- High-Performance Order Storage (HPOS)
- Custom Order Tables
- All standard WooCommerce product types

## Development

### Requirements

- PHP 7.2+
- WordPress 5.0+
- WooCommerce 3.0+
- Composer (for development)
- Prycing platform account

### Setup Development Environment

```bash
# Clone the repository
git clone https://github.com/prycing/woocommerce-plugin.git
cd woocommerce

# Install dependencies
composer install
```

### Code Standards

The codebase follows WordPress coding standards. You can check compliance using:

```bash
composer lint
```

Fix coding standards issues automatically:

```bash
composer fix
```

## Support

For bugs and feature requests, please use the [issues section](https://github.com/prycing/woocommerce-plugin/issues).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by Prycing. 