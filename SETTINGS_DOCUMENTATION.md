# Settings System Documentation

## Overview

The application now includes a comprehensive settings system built with Laravel Settings and integrated with Filament for easy management.

## Settings Classes

### GeneralSettings
Located at `app/Settings/GeneralSettings.php`, this contains all customer-facing and basic application settings:

#### Company Information
- `company_name` - Your company/ISP name
- `company_email` - Main contact email
- `company_phone` - Contact phone number
- `company_address` - Physical address
- `website_url` - Company website

#### WhatsApp Support
- `whatsapp_support_enabled` - Enable/disable WhatsApp support
- `whatsapp_number` - WhatsApp number with country code
- `whatsapp_message` - Default message for WhatsApp links

#### Customer Announcements
- `announcement_enabled` - Show/hide announcements
- `announcement_message` - Announcement text
- `announcement_type` - Type: info, success, warning, error

#### Media & Branding
- `company_logo` - Company logo file
- `banner_image` - Banner/hero image
- `promotional_video` - Promotional video file
- `favicon` - Website favicon

#### Customer Instructions
- `user_instructions` - General usage instructions
- `setup_guide` - Device setup guide
- `troubleshooting_guide` - Common issues and solutions
- `faq_content` - Frequently asked questions

#### Advertisement Section
- `advertisement_enabled` - Show/hide advertisements
- `advertisement_title` - Ad title
- `advertisement_description` - Ad description
- `advertisement_image` - Ad image file
- `advertisement_link` - Link URL when ad is clicked
- `advertisement_button_text` - Button text

#### System Configuration
- `timezone` - Application timezone
- `currency` - Currency code (GHS, USD, etc.)
- `currency_symbol` - Currency symbol (₵, $, etc.)
- `date_format` - Date display format

## Accessing Settings

### Direct Access
```php
use App\Settings\GeneralSettings;

$settings = app(GeneralSettings::class);
$companyName = $settings->company_name;
```

### Using the Helper Class
```php
use App\Helpers\SettingsHelper;

// Get settings instance
$settings = SettingsHelper::general();

// Get WhatsApp URL
$whatsappUrl = SettingsHelper::getWhatsAppUrl();
$customWhatsappUrl = SettingsHelper::getWhatsAppUrl('Custom message here');

// Check if features should be shown
if (SettingsHelper::shouldShowAnnouncement()) {
    // Display announcement
}

if (SettingsHelper::shouldShowAdvertisement()) {
    // Display advertisement
}

// Format currency and dates
$formattedPrice = SettingsHelper::formatCurrency(25.00); // ₵25.00
$formattedDate = SettingsHelper::formatDate(now()); // Based on date_format setting

// Get media URLs
$logoUrl = SettingsHelper::getCompanyLogoUrl();
$bannerUrl = SettingsHelper::getBannerImageUrl();
$videoUrl = SettingsHelper::getPromotionalVideoUrl();
$adImageUrl = SettingsHelper::getAdvertisementImageUrl();

// Get all contact info
$contactInfo = SettingsHelper::getContactInfo();
```

## Managing Settings

### Filament Admin Panel
1. Navigate to **Settings** → **General Settings** in the Filament admin panel
2. Use the tabbed interface to manage different setting categories:
   - **Company Information** - Basic company details and media uploads
   - **WhatsApp Support** - Configure WhatsApp integration with live preview
   - **Announcements** - Manage customer announcements
   - **Customer Instructions** - Add guides and FAQ content
   - **Advertisement** - Configure promotional content
   - **System Configuration** - Regional and system settings

### Features
- **Live Preview**: WhatsApp configuration shows live URL preview
- **File Uploads**: Support for images and videos with built-in editor
- **Conditional Fields**: Fields show/hide based on toggle states
- **Validation**: Built-in validation for URLs, emails, file types
- **Organized Tabs**: Logical grouping of related settings

## WhatsApp Integration

The system automatically generates WhatsApp URLs that work on all devices:

```php
// Generate WhatsApp link
$url = SettingsHelper::getWhatsAppUrl();
// Results in: https://wa.me/233123456789?text=Hello!%20I%20need%20help%20with%20my%20internet%20service.

// Use in HTML
<a href="{{ SettingsHelper::getWhatsAppUrl() }}" target="_blank">
    Contact Support on WhatsApp
</a>
```

## File Storage

All uploaded files are stored in the `storage/app/public/settings/` directory:
- Logos: `settings/logos/`
- Banners: `settings/banners/`
- Videos: `settings/videos/`
- Icons: `settings/icons/`
- Advertisements: `settings/advertisements/`

Make sure to run `php artisan storage:link` to create the symbolic link for public access.

## Database Storage

Settings are stored in the `settings` table with the following structure:
- `group` - Settings group (e.g., 'general')
- `name` - Setting name (e.g., 'company_name')
- `locked` - Whether the setting is locked from changes
- `payload` - JSON-encoded setting value

## Adding New Settings

1. Add the property to the settings class:
```php
public string $new_setting;
```

2. Add the default value:
```php
public static function defaults(): array
{
    return [
        // ... existing defaults
        'new_setting' => 'default value',
    ];
}
```

3. Add the form field in the Filament page:
```php
Forms\Components\TextInput::make('new_setting')
    ->label('New Setting')
    ->required(),
```

4. Create a settings migration if needed:
```php
$this->migrator->add('general.new_setting', 'default value');
```

## Best Practices

1. **Always use defaults** - Define sensible default values for all settings
2. **Validate input** - Use appropriate form validation in Filament
3. **Use helper methods** - Create helper methods for complex logic
4. **Cache settings** - Settings are automatically cached by the package
5. **Organize logically** - Group related settings in the same class
6. **Document changes** - Update this documentation when adding new settings

## Environment Variables

Some settings can be overridden with environment variables for different environments:

```env
# Example environment overrides (if implemented)
APP_COMPANY_NAME="Development ISP"
APP_CURRENCY="USD"
APP_CURRENCY_SYMBOL="$"
```

## Troubleshooting

### Settings not updating
1. Clear the settings cache: `php artisan settings:clear-cache`
2. Rediscover settings: `php artisan settings:discover`

### File uploads not working
1. Ensure storage is linked: `php artisan storage:link`
2. Check directory permissions
3. Verify disk configuration in `config/filesystems.php`

### Settings page not appearing
1. Check if the page class is in the correct namespace
2. Verify the view file exists
3. Clear application cache: `php artisan cache:clear`