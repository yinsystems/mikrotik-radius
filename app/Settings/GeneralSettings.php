<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    // Company Information
    public string $company_name;
    public string $company_email;
    public string $company_phone;
    public string $company_address;
    public string $website_url;
    
    // WhatsApp Support
    public string $whatsapp_number;
    public string $whatsapp_message;
    public bool $whatsapp_support_enabled;
    
    // Customer Announcements
    public string $announcement_message;
    public bool $announcement_enabled;
    public string $announcement_type; // info, warning, success, error
    
    // Media & Branding
    public ?string $company_logo;
    public ?string $banner_image;
    public ?string $promotional_video;
    public ?string $favicon;
    
    // Customer Instructions
    public string $user_instructions;
    public string $setup_guide;
    public string $troubleshooting_guide;
    public string $faq_content;
    
    // Advertisement Section
    public string $advertisement_title;
    public string $advertisement_description;
    public ?string $advertisement_image;
    public string $advertisement_link;
    public bool $advertisement_enabled;
    public string $advertisement_button_text;
    
    // System Configuration
    public string $timezone;
    public string $currency;
    public string $currency_symbol;
    public string $date_format;
    
    public static function group(): string
    {
        return 'general';
    }
    
    public static function defaults(): array
    {
        return [
            // Company Information
            'company_name' => 'Your Internet Service Provider',
            'company_email' => 'info@yourisp.com',
            'company_phone' => '+233 XXX XXX XXX',
            'company_address' => 'Your Company Address Here',
            'website_url' => 'https://yourisp.com',
            
            // WhatsApp Support
            'whatsapp_number' => '+233XXXXXXXXX', // Include country code
            'whatsapp_message' => 'Hello! I need help with my internet service.',
            'whatsapp_support_enabled' => true,
            
            // Customer Announcements
            'announcement_message' => 'Welcome to our service! Check out our latest packages and offers.',
            'announcement_enabled' => true,
            'announcement_type' => 'info',
            
            // Media & Branding
            'company_logo' => null,
            'banner_image' => null,
            'promotional_video' => null,
            'favicon' => null,
            
            // Customer Instructions
            'user_instructions' => 'Follow these simple steps to connect to our service:\n\n1. Connect to our WiFi network\n2. Open your browser\n3. Enter your username and password\n4. Enjoy unlimited internet access',
            'setup_guide' => 'Device Setup Guide:\n\n• For Android: Go to Settings > WiFi > Select our network\n• For iPhone: Go to Settings > WiFi > Select our network\n• For Windows: Click WiFi icon > Select our network\n• For Mac: Click WiFi icon > Select our network',
            'troubleshooting_guide' => 'Common Issues:\n\n• Cannot connect: Check your username and password\n• Slow internet: Try restarting your device\n• No internet: Contact our support team\n• Frequent disconnections: Move closer to the router',
            'faq_content' => 'Frequently Asked Questions:\n\nQ: How do I reset my password?\nA: Contact our support team via WhatsApp\n\nQ: Can I share my account?\nA: Each account is for single user only\n\nQ: What are your operating hours?\nA: We operate 24/7 with support available during business hours',
            
            // Advertisement Section
            'advertisement_title' => 'Upgrade to Premium!',
            'advertisement_description' => 'Get faster speeds and unlimited data with our premium packages. Special offers available now!',
            'advertisement_image' => null,
            'advertisement_link' => 'https://yourisp.com/packages',
            'advertisement_enabled' => true,
            'advertisement_button_text' => 'View Packages',
            
            // System Configuration
            'timezone' => 'Africa/Accra',
            'currency' => 'GHS',
            'currency_symbol' => '₵',
            'date_format' => 'd/m/Y',
        ];
    }
}