<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\SmsService;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // The service automatically uses the configured driver
        $smsService = app(SmsService::class);

// Check current driver
        $status = $smsService->getStatus();
        error_log(json_encode($status));
        $result = $smsService->testConnection();
        error_log(json_encode($result));
        $result = $smsService->send('0554138989', 'Hello World');
        error_log(json_encode($result));
    }
}
