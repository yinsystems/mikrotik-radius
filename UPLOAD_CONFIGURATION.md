# File Upload Configuration Guide

## Current Upload Limits
The system currently has the following upload limits based on your PHP configuration:

- **Upload Max Filesize**: 2MB
- **Post Max Size**: 8MB
- **Max Execution Time**: Unlimited
- **Memory Limit**: 1028MB

## Current Application Limits
To match your PHP configuration, the application has been configured with these limits:

- **Company Logo**: 2MB max
- **Banner Image**: 2MB max
- **Promotional Video**: 2MB max
- **Advertisement Image**: 2MB max
- **Favicon**: 1MB max

## Increasing Upload Limits for Larger Videos

If you need to upload larger promotional videos, you'll need to modify your PHP configuration:

### 1. Locate PHP Configuration File
Your PHP configuration file is located at: `C:\php\php.ini`

### 2. Edit PHP Configuration
Open `C:\php\php.ini` in a text editor and modify these values:

```ini
; Maximum allowed size for uploaded files
upload_max_filesize = 50M

; Maximum size of POST data that PHP will accept
post_max_size = 64M

; Maximum execution time for scripts (in seconds)
max_execution_time = 300

; Maximum input time for scripts (in seconds)
max_input_time = 300

; Memory limit
memory_limit = 512M
```

### 3. Update Application Limits
After increasing PHP limits, you can also increase the application limits in:
`app/Filament/Pages/ManageGeneralSettings.php`

For example, to allow 50MB videos:
```php
Forms\Components\FileUpload::make('promotional_video')
    ->label('Promotional Video')
    ->helperText('Optional: Upload a promotional video (MP4, WebM, or OGG). Max size: 50MB.')
    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
    ->directory('settings/videos')
    ->visibility('public')
    ->maxSize(51200) // 50MB (50 * 1024)
    ->nullable(),
```

### 4. Restart Web Server
After making changes to `php.ini`, restart your web server for the changes to take effect.

### 5. Verify Changes
Run this command to verify your new limits:
```bash
php -r "echo 'Upload Max Filesize: ' . ini_get('upload_max_filesize') . PHP_EOL; echo 'Post Max Size: ' . ini_get('post_max_size') . PHP_EOL;"
```

## Troubleshooting Upload Failures

### Common Issues:
1. **File too large**: Check that file size is within limits
2. **Wrong file type**: Ensure file matches accepted types (MP4, WebM, OGG for videos)
3. **Disk space**: Ensure server has sufficient disk space
4. **Directory permissions**: Ensure `storage/app/public/settings/videos` is writable

### Error Messages:
- `failed to upload`: Usually indicates file size or type restrictions
- `UUID-based error`: Often related to temporary file handling during large uploads

### Best Practices:
1. **Compress videos** before uploading to reduce file size
2. **Use web-optimized formats** (MP4 with H.264 encoding)
3. **Keep videos short** for better user experience and smaller file sizes
4. **Test uploads** with small files first to verify configuration

## Recommended Video Specifications
For promotional videos:
- **Format**: MP4 (H.264 codec)
- **Resolution**: 1080p or 720p
- **Duration**: 30-60 seconds
- **File Size**: Under 10MB for best performance
- **Frame Rate**: 30fps