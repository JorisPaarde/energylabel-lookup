# Energy Label Lookup WordPress Plugin

A WordPress plugin to look up energy labels using the EP Online API. The plugin, created by JPWebCreation (Joris Paardekooper), supports postcode, house number, and addition inputs and is compatible with Elementor Pro.

## Features

- ✅ Postcode, house number, and addition input
- ✅ EP Online API integration
- ✅ Elementor Pro shortcode support
- ✅ Responsive and modern design
- ✅ AJAX-powered form handling for a smooth user experience
- ✅ Secure API key management through WordPress admin
- ✅ Internationalized for easy translation
- ✅ Robust error handling and input validation

## Installation

### 1. Plugin Upload

1.  Download the plugin as a ZIP file.
2.  Navigate to your WordPress Admin dashboard → Plugins → Add New.
3.  Click "Upload Plugin", select the downloaded ZIP file, and upload.
4.  Activate the plugin.

### 2. API Key Configuration

1.  Go to your WordPress Admin dashboard → Settings → Energy Label Lookup.
2.  Enter your EP Online API key in the provided field.
3.  Click "Save Settings".

**Alternative Configuration (Advanced Users):**
You can also define your API key in your `wp-config.php` file:

```php
define('EP_ONLINE_API_KEY', 'your_actual_api_key_here');
define('EP_ONLINE_API_URL', 'https://www.ep-online.nl/PublicData');
```

This will override the setting in the WordPress admin area.

## Usage

### Shortcode

Use the following shortcode on any page or post:

`[energylabel_lookup]`

### Shortcode Parameters

You can customize the title and description:

`[energylabel_lookup title="My Custom Title" description="My custom description."]`

### Elementor Pro

1.  Drag a "Shortcode" widget onto your page.
2.  Enter the `[energylabel_lookup]` shortcode into the widget.
3.  The lookup form will be automatically rendered.

## API Integration

The plugin integrates with the EP Online API, which requires:

-   **Postcode**: Format `1234 AB`
-   **House Number**: Numeric (e.g., `123`)
-   **Addition**: Optional (e.g., `A`, `II`, `bis`)

## Security

- ✅ Nonce verification on all AJAX requests to prevent CSRF attacks.
- ✅ Input sanitization and validation on all user-submitted data.
- ✅ Use of `esc_html` to prevent XSS vulnerabilities from API responses.
- ✅ Secure API key storage in WordPress database.
- ✅ WordPress admin-based configuration for easy management.

## File Structure

```
energylabel-lookup/
├── energylabel-lookup.php          # Main plugin file
├── assets/
│   ├── css/
│   │   └── ell-style.css          # Stylesheet
│   └── js/
│       └── ell-script.js          # JavaScript functionality
├── install.php                     # Installation script
├── .gitignore                      # Git ignore rules
└── README.md                       # This documentation
```

## Customization

You can override the plugin's CSS classes in your theme's stylesheet:

-   `.ell-container` - The main container.
-   `.ell-form` - The form itself.
-   `.ell-results` - The results display area.
-   `.ell-label-display` - The energy label visual.

## Troubleshooting

### Plugin Not Working
1.  Verify the API key is correctly entered in Settings → Energy Label Lookup.
2.  Check that the plugin is activated.
3.  Enable WordPress debugging (`WP_DEBUG`) to check for PHP errors.

### API Errors
1.  Ensure your API key is valid and active.
2.  Confirm the postcode format is correct (`1234 AB`).
3.  Make sure the API URL is correct (default: `https://www.ep-online.nl/PublicData`).

## Changelog

### Version 1.3.4
-   UI uitgebreid met status badges, info cards en prestatie-KPI's
-   Datakwaliteit validatie: CO2 en energieverbruik worden alleen getoond als geldig
-   Technische details verplaatst naar accordion voor betere UX
-   Conversie-blok met dynamische CTA's op basis van label status
-   Indicatieve waarden gemarkeerd met "Indicatief (EP-Online)" label
-   Nieuwe velden: Status, Soort opname, Opnamedatum prominent weergegeven

### Version 1.3.3
-   Improved nonce handling for cache-proof operation
-   Added fresh nonce retrieval endpoint to prevent caching issues
-   Enhanced security error messages with refresh button
-   Better nonce verification supporting both POST field formats
-   Added debug logging for nonce failures (without sensitive data)

### Version 1.3.2
-   Added "Energielabel check door MijnEnergielabelBerekenen.nl" footer link

### Version 1.3.1
-   Elementor optimized - removed header text for custom Elementor integration
-   Added beautiful animations and transitions
-   Energy label colors from red to green (A+++ to G)
-   Improved API response handling with detailed information
-   Enhanced admin dashboard with usage statistics
-   Better error handling and validation feedback

### Version 1.2.1
-   Rebranded plugin to "Energy Label Lookup" by JPWebCreation.
-   Refactored codebase with new `ell_` prefixes and updated file names.
-   Improved security with nonce checks and output sanitization.
-   Enhanced robustness with better API response validation.
-   Added WordPress admin settings page for API key management.
-   Removed external configuration files for better security.

### Version 1.0.0
-   Initial release ("Energie Label Calculator").

## Support

For support or inquiries, please contact JPWebCreation.

## License

GPL v2 or later.

## Credits

-   **Author:** JPWebCreation - Joris Paardekooper
-   **API:** EP Online API
-   Built with compatibility for Elementor Pro. 