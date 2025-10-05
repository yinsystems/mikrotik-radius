<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.company_name', 'Your Internet Service Provider');
        $this->migrator->add('general.company_email', 'info@yourisp.com');
        $this->migrator->add('general.company_phone', '+233 XXX XXX XXX');
        $this->migrator->add('general.company_address', 'Your Company Address Here');
        $this->migrator->add('general.website_url', 'https://yourisp.com');
        
        // WhatsApp Support
        $this->migrator->add('general.whatsapp_number', '+233XXXXXXXXX');
        $this->migrator->add('general.whatsapp_message', 'Hello! I need help with my internet service.');
        $this->migrator->add('general.whatsapp_support_enabled', true);
        
        // Customer Announcements
        $this->migrator->add('general.announcement_message', 'Welcome to our service! Check out our latest packages and offers.');
        $this->migrator->add('general.announcement_enabled', true);
        $this->migrator->add('general.announcement_type', 'info');
        
        // Media & Branding
        $this->migrator->add('general.company_logo', null);
        $this->migrator->add('general.banner_image', null);
        $this->migrator->add('general.promotional_video', null);
        $this->migrator->add('general.favicon', null);
        
        // Customer Instructions
        $this->migrator->add('general.user_instructions', 'Follow these simple steps to connect to our service:

1. Connect to our WiFi network
2. Open your browser
3. Enter your username and password
4. Enjoy unlimited internet access');
        
        $this->migrator->add('general.setup_guide', 'Device Setup Guide:

• For Android: Go to Settings > WiFi > Select our network
• For iPhone: Go to Settings > WiFi > Select our network
• For Windows: Click WiFi icon > Select our network
• For Mac: Click WiFi icon > Select our network');
        
        $this->migrator->add('general.troubleshooting_guide', 'Common Issues:

• Cannot connect: Check your username and password
• Slow internet: Try restarting your device
• No internet: Contact our support team
• Frequent disconnections: Move closer to the router');
        
        $this->migrator->add('general.faq_content', 'Frequently Asked Questions:

Q: How do I reset my password?
A: Contact our support team via WhatsApp

Q: Can I share my account?
A: Each account is for single user only

Q: What are your operating hours?
A: We operate 24/7 with support available during business hours');
        
        // Advertisement Section
        $this->migrator->add('general.advertisement_title', 'Upgrade to Premium!');
        $this->migrator->add('general.advertisement_description', 'Get faster speeds and unlimited data with our premium packages. Special offers available now!');
        $this->migrator->add('general.advertisement_image', null);
        $this->migrator->add('general.advertisement_link', 'https://yourisp.com/packages');
        $this->migrator->add('general.advertisement_enabled', true);
        $this->migrator->add('general.advertisement_button_text', 'View Packages');
        
        // System Configuration
        $this->migrator->add('general.timezone', 'Africa/Accra');
        $this->migrator->add('general.currency', 'GHS');
        $this->migrator->add('general.currency_symbol', '₵');
        $this->migrator->add('general.date_format', 'd/m/Y');
    }
    
    public function down(): void
    {
        $this->migrator->delete('general.company_name');
        $this->migrator->delete('general.company_email');
        $this->migrator->delete('general.company_phone');
        $this->migrator->delete('general.company_address');
        $this->migrator->delete('general.website_url');
        $this->migrator->delete('general.whatsapp_number');
        $this->migrator->delete('general.whatsapp_message');
        $this->migrator->delete('general.whatsapp_support_enabled');
        $this->migrator->delete('general.announcement_message');
        $this->migrator->delete('general.announcement_enabled');
        $this->migrator->delete('general.announcement_type');
        $this->migrator->delete('general.company_logo');
        $this->migrator->delete('general.banner_image');
        $this->migrator->delete('general.promotional_video');
        $this->migrator->delete('general.favicon');
        $this->migrator->delete('general.user_instructions');
        $this->migrator->delete('general.setup_guide');
        $this->migrator->delete('general.troubleshooting_guide');
        $this->migrator->delete('general.faq_content');
        $this->migrator->delete('general.advertisement_title');
        $this->migrator->delete('general.advertisement_description');
        $this->migrator->delete('general.advertisement_image');
        $this->migrator->delete('general.advertisement_link');
        $this->migrator->delete('general.advertisement_enabled');
        $this->migrator->delete('general.advertisement_button_text');
        $this->migrator->delete('general.timezone');
        $this->migrator->delete('general.currency');
        $this->migrator->delete('general.currency_symbol');
        $this->migrator->delete('general.date_format');
    }
};