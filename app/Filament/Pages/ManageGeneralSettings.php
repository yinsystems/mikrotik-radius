<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class ManageGeneralSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static string $view = 'filament.pages.manage-general-settings';
    
    protected static ?string $navigationLabel = 'General Settings';
    
    protected static ?string $title = 'General Settings';
    
    protected static ?string $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 1;

    public ?array $data = [];
    
    public function mount(): void
    {
        $settings = app(GeneralSettings::class);
        $this->form->fill($settings->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Company Information')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\Section::make('Basic Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('company_name')
                                            ->label('Company Name')
                                            ->required()
                                            ->maxLength(255),
                                        
                                        Forms\Components\TextInput::make('company_email')
                                            ->label('Company Email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                        
                                        Forms\Components\TextInput::make('company_phone')
                                            ->label('Company Phone')
                                            ->tel()
                                            ->maxLength(255),
                                        
                                        Forms\Components\Textarea::make('company_address')
                                            ->label('Company Address')
                                            ->rows(3)
                                            ->maxLength(500),
                                        
                                        Forms\Components\TextInput::make('website_url')
                                            ->label('Website URL')
                                            ->url()
                                            ->maxLength(255),
                                    ])->columns(2),
                                
                                Forms\Components\Section::make('Media & Branding')
                                    ->schema([
                                        Forms\Components\FileUpload::make('company_logo')
                                            ->label('Company Logo')
                                            ->helperText('Optional: Upload your company logo for branding (max 2MB)')
                                            ->image()
                                            ->directory('settings/logos')
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ])
                                            ->maxSize(2048) // 2MB
                                            ->nullable(),
                                        
                                        Forms\Components\FileUpload::make('banner_image')
                                            ->label('Banner Image')
                                            ->helperText('Optional: Upload a banner image for your homepage (max 2MB)')
                                            ->image()
                                            ->directory('settings/banners')
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->maxSize(2048) // 2MB
                                            ->nullable(),
                                        
                                        Forms\Components\FileUpload::make('promotional_video')
                                            ->label('Promotional Video')
                                            ->helperText('Optional: Upload a promotional video (MP4, WebM, or OGG). Max size: 2MB due to server limits.')
                                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                                            ->directory('settings/videos')
                                            ->visibility('public')
                                            // ->maxSize(2048) // 2MB limit to match PHP configuration
                                            ->nullable(),
                                        
                                        Forms\Components\FileUpload::make('favicon')
                                            ->label('Favicon')
                                            ->helperText('Optional: Upload a favicon icon (.ico or .png, max 1MB)')
                                            ->image()
                                            ->directory('settings/icons')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/x-icon', 'image/png'])
                                            ->maxSize(1024) // 1MB
                                            ->nullable(),
                                    ])->columns(2),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('WhatsApp Support')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Forms\Components\Section::make('WhatsApp Configuration')
                                    ->description('Configure WhatsApp support for customer assistance')
                                    ->schema([
                                        Forms\Components\Toggle::make('whatsapp_support_enabled')
                                            ->label('Enable WhatsApp Support')
                                            ->default(true)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('whatsapp_number')
                                            ->label('WhatsApp Number')
                                            ->helperText('Include country code (e.g., +233XXXXXXXXX)')
                                            ->placeholder('+233XXXXXXXXX')
                                            ->required()
                                            ->maxLength(20)
                                            ->visible(fn (Forms\Get $get) => $get('whatsapp_support_enabled')),
                                        
                                        Forms\Components\Textarea::make('whatsapp_message')
                                            ->label('Default WhatsApp Message')
                                            ->helperText('This message will be pre-filled when customers click the WhatsApp button')
                                            ->placeholder('Hello! I need help with my internet service.')
                                            ->rows(3)
                                            ->maxLength(500)
                                            ->visible(fn (Forms\Get $get) => $get('whatsapp_support_enabled')),
                                        
                                        Forms\Components\Placeholder::make('whatsapp_preview')
                                            ->label('WhatsApp Link Preview')
                                            ->content(function (Forms\Get $get) {
                                                $number = $get('whatsapp_number');
                                                $message = $get('whatsapp_message');
                                                if ($number && $message) {
                                                    $cleanNumber = preg_replace('/[^0-9]/', '', $number);
                                                    $encodedMessage = urlencode($message);
                                                    return "https://wa.me/{$cleanNumber}?text={$encodedMessage}";
                                                }
                                                return 'Enter number and message to see preview';
                                            })
                                            ->visible(fn (Forms\Get $get) => $get('whatsapp_support_enabled')),
                                    ]),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Announcements')
                            ->icon('heroicon-o-megaphone')
                            ->schema([
                                Forms\Components\Section::make('Customer Announcements')
                                    ->description('Display important messages to your customers')
                                    ->schema([
                                        Forms\Components\Toggle::make('announcement_enabled')
                                            ->label('Show Announcements')
                                            ->default(true)
                                            ->live(),
                                        
                                        Forms\Components\Select::make('announcement_type')
                                            ->label('Announcement Type')
                                            ->options([
                                                'info' => 'Information (Blue)',
                                                'success' => 'Success (Green)',
                                                'warning' => 'Warning (Yellow)',
                                                'error' => 'Important (Red)',
                                            ])
                                            ->default('info')
                                            ->visible(fn (Forms\Get $get) => $get('announcement_enabled')),
                                        
                                        Forms\Components\Textarea::make('announcement_message')
                                            ->label('Announcement Message')
                                            ->placeholder('Welcome to our service! Check out our latest packages and offers.')
                                            ->rows(4)
                                            ->maxLength(1000)
                                            ->visible(fn (Forms\Get $get) => $get('announcement_enabled')),
                                    ]),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Customer Instructions')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Section::make('User Guides and Instructions')
                                    ->description('Provide helpful information and guides for your customers')
                                    ->schema([
                                        Forms\Components\Textarea::make('user_instructions')
                                            ->label('General Instructions')
                                            ->helperText('Basic instructions for using your service')
                                            ->rows(6)
                                            ->maxLength(2000),
                                        
                                        Forms\Components\Textarea::make('setup_guide')
                                            ->label('Device Setup Guide')
                                            ->helperText('Step-by-step guide for different devices')
                                            ->rows(6)
                                            ->maxLength(2000),
                                        
                                        Forms\Components\Textarea::make('troubleshooting_guide')
                                            ->label('Troubleshooting Guide')
                                            ->helperText('Common issues and solutions')
                                            ->rows(6)
                                            ->maxLength(2000),
                                        
                                        Forms\Components\Textarea::make('faq_content')
                                            ->label('Frequently Asked Questions')
                                            ->helperText('Common questions and answers')
                                            ->rows(8)
                                            ->maxLength(3000),
                                    ])->columns(1),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Advertisement')
                            ->icon('heroicon-o-speaker-wave')
                            ->schema([
                                Forms\Components\Section::make('Advertisement Section')
                                    ->description('Promote your packages and special offers')
                                    ->schema([
                                        Forms\Components\Toggle::make('advertisement_enabled')
                                            ->label('Show Advertisement')
                                            ->default(true)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('advertisement_title')
                                            ->label('Advertisement Title')
                                            ->placeholder('Upgrade to Premium!')
                                            ->maxLength(255)
                                            ->visible(fn (Forms\Get $get) => $get('advertisement_enabled')),
                                        
                                        Forms\Components\Textarea::make('advertisement_description')
                                            ->label('Advertisement Description')
                                            ->placeholder('Get faster speeds and unlimited data with our premium packages.')
                                            ->rows(3)
                                            ->maxLength(500)
                                            ->visible(fn (Forms\Get $get) => $get('advertisement_enabled')),
                                        
                                        Forms\Components\FileUpload::make('advertisement_image')
                                            ->label('Advertisement Image')
                                            ->helperText('Optional: Upload an image for your advertisement (max 2MB)')
                                            ->image()
                                            ->directory('settings/advertisements')
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->maxSize(2048) // 2MB
                                            ->nullable()
                                            ->visible(fn (Forms\Get $get) => $get('advertisement_enabled')),
                                        
                                        Forms\Components\TextInput::make('advertisement_link')
                                            ->label('Advertisement Link')
                                            ->helperText('URL where customers will be redirected when they click')
                                            ->url()
                                            ->placeholder('https://yourisp.com/packages')
                                            ->maxLength(255)
                                            ->visible(fn (Forms\Get $get) => $get('advertisement_enabled')),
                                        
                                        Forms\Components\TextInput::make('advertisement_button_text')
                                            ->label('Button Text')
                                            ->placeholder('View Packages')
                                            ->maxLength(50)
                                            ->visible(fn (Forms\Get $get) => $get('advertisement_enabled')),
                                    ])->columns(2),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('System Configuration')
                            ->icon('heroicon-o-cog-8-tooth')
                            ->schema([
                                Forms\Components\Section::make('Authentication Settings')
                                    ->description('Configure authentication and security settings')
                                    ->schema([
                                        Forms\Components\Toggle::make('enable_otp_verification')
                                            ->label('Enable Registration OTP Verification')
                                            ->helperText('When enabled, users must verify their phone number with an OTP code during registration. When disabled, users can register directly without OTP verification.')
                                            ->default(true)
                                            ->inline(false),
                                        
                                        Forms\Components\Toggle::make('enable_login_otp_verification')
                                            ->label('Enable Login OTP Verification')
                                            ->helperText('When enabled, users must verify their phone number with an OTP code during login. When disabled, users login with their phone number and password.')
                                            ->default(true)
                                            ->inline(false),
                                    ])->columns(1),
                                
                                Forms\Components\Section::make('Regional Settings')
                                    ->schema([
                                        Forms\Components\Select::make('timezone')
                                            ->label('Timezone')
                                            ->options([
                                                'Africa/Accra' => 'Africa/Accra (GMT)',
                                                'Africa/Lagos' => 'Africa/Lagos (WAT)',
                                                'Africa/Cairo' => 'Africa/Cairo (EET)',
                                                'UTC' => 'UTC',
                                                'America/New_York' => 'America/New_York (EST)',
                                                'Europe/London' => 'Europe/London (GMT)',
                                            ])
                                            ->default('Africa/Accra')
                                            ->searchable(),
                                        
                                        Forms\Components\Select::make('currency')
                                            ->label('Currency')
                                            ->options([
                                                'GHS' => 'Ghanaian Cedi (GHS)',
                                                'NGN' => 'Nigerian Naira (NGN)',
                                                'USD' => 'US Dollar (USD)',
                                                'EUR' => 'Euro (EUR)',
                                                'GBP' => 'British Pound (GBP)',
                                            ])
                                            ->default('GHS'),
                                        
                                        Forms\Components\TextInput::make('currency_symbol')
                                            ->label('Currency Symbol')
                                            ->placeholder('₵')
                                            ->maxLength(5),
                                        
                                        Forms\Components\Select::make('date_format')
                                            ->label('Date Format')
                                            ->options([
                                                'd/m/Y' => 'DD/MM/YYYY (31/12/2024)',
                                                'm/d/Y' => 'MM/DD/YYYY (12/31/2024)',
                                                'Y-m-d' => 'YYYY-MM-DD (2024-12-31)',
                                                'd-M-Y' => 'DD-MMM-YYYY (31-Dec-2024)',
                                            ])
                                            ->default('d/m/Y'),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Package Types')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Forms\Components\Section::make('Package Type Management')
                                    ->description('Define package types that can be assigned to internet packages. These help organize packages into categories.')
                                    ->schema([
                                        Forms\Components\TagsInput::make('package_types')
                                            ->label('Package Types')
                                            ->placeholder('Enter package types...')
                                            ->helperText('Add package types such as Residential, Business, Student, Premium, etc. Press Enter after each type.')
                                            ->required()
                                            ->default(['Residential', 'Business', 'Student', 'Premium'])
                                            ->splitKeys(['Enter', ','])
                                            ->nestedRecursiveRules([
                                                'min:2',
                                                'max:50'
                                            ]),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('USSD Maintenance')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                Forms\Components\Section::make('USSD Maintenance Mode')
                                    ->description('Control USSD service availability and display custom maintenance messages to users.')
                                    ->schema([
                                        Forms\Components\Toggle::make('ussd_maintenance_mode')
                                            ->label('Enable Maintenance Mode')
                                            ->helperText('When enabled, all USSD requests will show the maintenance message below.')
                                            ->default(false)
                                            ->live(),
                                            
                                        Forms\Components\Textarea::make('ussd_maintenance_message')
                                            ->label('Maintenance Message')
                                            ->placeholder('Sorry, our service is temporarily unavailable for maintenance. Please try again later.')
                                            ->helperText('This message will be displayed to users when maintenance mode is enabled.')
                                            ->required()
                                            ->rows(4)
                                            ->maxLength(160)
                                            ->visible(fn (callable $get) => $get('ussd_maintenance_mode')),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        $settings = app(GeneralSettings::class);
        
        foreach ($data as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
            }
        }
        
        $settings->save();

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}