<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('⚙️  Seeding settings...');

        $settings = [
            // Store Info
            ['module' => 'SITE', 'key' => 'storeName', 'value' => 'THREVOLT'],
            ['module' => 'SITE', 'key' => 'brandTagline', 'value' => 'Premium Fashion & Lifestyle'],
            ['module' => 'SITE', 'key' => 'contactEmail', 'value' => 'support@threvolt.com'],
            ['module' => 'SITE', 'key' => 'storeEmail', 'value' => 'support@threvolt.com'],
            ['module' => 'SITE', 'key' => 'storeAddress', 'value' => 'Bangalore, Karnataka, India'],
            ['module' => 'SITE', 'key' => 'contactPhone', 'value' => '+91 98765 43210'],
            ['module' => 'SITE', 'key' => 'currency', 'value' => 'INR'],
            ['module' => 'SITE', 'key' => 'timezone', 'value' => 'IST'],

            // Shipping
            ['module' => 'SHIPPING', 'key' => 'freeShippingThreshold', 'value' => '499'],
            ['module' => 'SHIPPING', 'key' => 'shippingFlatRate', 'value' => '50'],
            ['module' => 'SHIPPING', 'key' => 'shippingPickupAddress', 'value' => 'THREVOLT Fulfillment Center, Bangalore, Karnataka, India'],
            ['module' => 'SHIPPING', 'key' => 'shippingReturnAddress', 'value' => 'THREVOLT Returns, Bangalore, Karnataka, India'],

            // Tax
            ['module' => 'TAX', 'key' => 'taxRate', 'value' => '18.0'],
            ['module' => 'TAX', 'key' => 'taxCalculation', 'value' => 'inclusive'],

            // Maintenance
            ['module' => 'SITE', 'key' => 'maintenanceMode', 'value' => 'false'],
            ['module' => 'SITE', 'key' => 'maintenanceMessage', 'value' => 'We are currently under maintenance. Please check back soon.'],

            // SMTP
            ['module' => 'SMTP', 'key' => 'fromEmailAddress', 'value' => 'support@threvolt.com'],
            ['module' => 'SMTP', 'key' => 'emailTemplate', 'value' => 'default'],

            // WebSocket
            ['module' => 'WEBSOCKET', 'key' => 'socketEnabled', 'value' => 'true'],
            ['module' => 'WEBSOCKET', 'key' => 'socketPingInterval', 'value' => '25000'],
            ['module' => 'WEBSOCKET', 'key' => 'socketPingTimeout', 'value' => '10000'],
            ['module' => 'WEBSOCKET', 'key' => 'socketAllowedOrigins', 'value' => 'http://localhost:3000,http://localhost:5173'],

            // Ads Settings
            ['module' => 'ADS', 'key' => 'metaAccessToken', 'value' => ''],
            ['module' => 'ADS', 'key' => 'metaAdAccountId', 'value' => ''],
            ['module' => 'ADS', 'key' => 'whatsappAccessToken', 'value' => ''],
            ['module' => 'ADS', 'key' => 'googleAdsClientId', 'value' => ''],
            ['module' => 'ADS', 'key' => 'googleAdsDeveloperToken', 'value' => ''],

            // Section Toggles
            ['module' => 'SITE', 'key' => 'salesEnabled', 'value' => 'true'],
            ['module' => 'SITE', 'key' => 'reelsEnabled', 'value' => 'true'],
            ['module' => 'SITE', 'key' => 'curatedLooksEnabled', 'value' => 'true'],
            ['module' => 'SITE', 'key' => 'reviewsEnabled', 'value' => 'true'],
            ['module' => 'SITE', 'key' => 'bestSellersEnabled', 'value' => 'true'],

            // WhatsApp Button
            ['module' => 'SITE', 'key' => 'whatsappButtonEnabled', 'value' => 'false'],
            ['module' => 'SITE', 'key' => 'whatsappButtonNumber', 'value' => ''],
            ['module' => 'SITE', 'key' => 'whatsappButtonMessage', 'value' => 'Hi, I need help with my order'],
            // Phone Lead Banner
            ['module' => 'SITE', 'key' => 'phoneLeadBannerEnabled', 'value' => 'false'],
            ['module' => 'SITE', 'key' => 'phoneLeadBannerHeading', 'value' => 'Get 100 Off Your First Order!'],
            ['module' => 'SITE', 'key' => 'phoneLeadBannerOfferText', 'value' => 'Enter your phone number to receive exclusive offers, updates, and instant 100 discount on your first purchase!'],


            // Cookie Consent
            ['module' => 'SITE', 'key' => 'cookieConsentEnabled', 'value' => 'true'],

            // Chat / Support Settings
            ['module' => 'SITE', 'key' => 'chatbotEnabled', 'value' => 'true'],
            ['module' => 'SITE', 'key' => 'chatWelcomeMessage', 'value' => '👋 Hi there! How can we help you today?'],
            ['module' => 'SITE', 'key' => 'chatOfflineMessage', 'value' => 'We are currently offline. Please leave a message and we will get back to you during business hours.'],
            ['module' => 'SITE', 'key' => 'chatWorkingHoursEnabled', 'value' => 'false'],
            ['module' => 'SITE', 'key' => 'chatWorkingHoursStart', 'value' => '09:00'],
            ['module' => 'SITE', 'key' => 'chatWorkingHoursEnd', 'value' => '18:00'],
            ['module' => 'SITE', 'key' => 'chatWorkingDays', 'value' => 'Monday,Tuesday,Wednesday,Thursday,Friday'],
            ['module' => 'SITE', 'key' => 'chatSupportName', 'value' => 'Support Team'],
            ['module' => 'SITE', 'key' => 'chatResponseTime', 'value' => 'We typically reply in minutes'],
            ['module' => 'SITE', 'key' => 'chatAutoReplyEnabled', 'value' => 'true'],
            ['module' => 'SITE', 'key' => 'chatAutoReplyMessage', 'value' => 'Thank you for your message! One of our team members will get back to you shortly.'],

            // Social Login
            ['module' => 'SITE', 'key' => 'googleClientId', 'value' => ''],
            ['module' => 'SITE', 'key' => 'facebookAppId', 'value' => ''],

            // OpenAI
            ['module' => 'SITE', 'key' => 'openaiApiKey', 'value' => ''],

            // Razorpay
            ['module' => 'PAYMENT', 'key' => 'razorpayEnabled', 'value' => 'true'],
            ['module' => 'PAYMENT', 'key' => 'razorpayKeyId', 'value' => 'rzp_test_xxxxxxxx'],
            ['module' => 'PAYMENT', 'key' => 'razorpayKeySecret', 'value' => ''],

            // COD
            ['module' => 'PAYMENT', 'key' => 'codEnabled', 'value' => 'true'],
            ['module' => 'PAYMENT', 'key' => 'codInstructions', 'value' => 'Pay with cash upon delivery'],

            // Storage Driver (local | s3)
            ['module' => 'SYSTEM', 'key' => 'storage_driver', 'value' => 'local'],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(
                ['module' => $s['module'], 'key' => $s['key']],
                ['value' => $s['value']]
            );
        }

        $this->command->info('   ✓ ' . count($settings) . ' settings created');
    }
}
