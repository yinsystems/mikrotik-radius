<?php

namespace App\Helpers;

use App\Settings\GeneralSettings;

class SettingsHelper
{
    /**
     * Get the general settings instance
     */
    public static function general(): GeneralSettings
    {
        return app(GeneralSettings::class);
    }

    /**
     * Get WhatsApp URL for the configured number and message
     */
    public static function getWhatsAppUrl(?string $customMessage = null): ?string
    {
        $settings = self::general();
        
        if (!$settings->whatsapp_support_enabled || empty($settings->whatsapp_number)) {
            return null;
        }

        $cleanNumber = preg_replace('/[^0-9]/', '', $settings->whatsapp_number);
        $message = $customMessage ?? $settings->whatsapp_message;
        $encodedMessage = urlencode($message);

        return "https://wa.me/{$cleanNumber}?text={$encodedMessage}";
    }

    /**
     * Check if announcements should be displayed
     */
    public static function shouldShowAnnouncement(): bool
    {
        $settings = self::general();
        return $settings->announcement_enabled && !empty($settings->announcement_message);
    }

    /**
     * Check if advertisements should be displayed
     */
    public static function shouldShowAdvertisement(): bool
    {
        $settings = self::general();
        return $settings->advertisement_enabled && !empty($settings->advertisement_title);
    }

    /**
     * Get formatted currency amount
     */
    public static function formatCurrency(float $amount): string
    {
        $settings = self::general();
        return $settings->currency_symbol . number_format($amount, 2);
    }

    /**
     * Get formatted date
     */
    public static function formatDate(\Carbon\Carbon $date): string
    {
        $settings = self::general();
        return $date->format($settings->date_format);
    }

    /**
     * Get company logo URL
     */
    public static function getCompanyLogoUrl(): ?string
    {
        $settings = self::general();
        return $settings->company_logo ? asset('storage/' . $settings->company_logo) : null;
    }

    /**
     * Get banner image URL
     */
    public static function getBannerImageUrl(): ?string
    {
        $settings = self::general();
        return $settings->banner_image ? asset('storage/' . $settings->banner_image) : null;
    }

    /**
     * Get promotional video URL
     */
    public static function getPromotionalVideoUrl(): ?string
    {
        $settings = self::general();
        return $settings->promotional_video ? asset('storage/' . $settings->promotional_video) : null;
    }

    /**
     * Get favicon URL
     */
    public static function getFaviconUrl(): ?string
    {
        $settings = self::general();
        return $settings->favicon ? asset('storage/' . $settings->favicon) : null;
    }

    /**
     * Get advertisement image URL
     */
    public static function getAdvertisementImageUrl(): ?string
    {
        $settings = self::general();
        return $settings->advertisement_image ? asset('storage/' . $settings->advertisement_image) : null;
    }

    /**
     * Get all contact information as array
     */
    public static function getContactInfo(): array
    {
        $settings = self::general();
        
        return [
            'company_name' => $settings->company_name,
            'email' => $settings->company_email,
            'phone' => $settings->company_phone,
            'address' => $settings->company_address,
            'website' => $settings->website_url,
            'whatsapp' => $settings->whatsapp_support_enabled ? $settings->whatsapp_number : null,
            'whatsapp_url' => self::getWhatsAppUrl(),
        ];
    }
}