<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use FilesystemIterator;
use Illuminate\Http\Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

/**
 * Ensures every API controller that accepts write payloads (POST/PUT/PATCH)
 * uses the MapsCamelCaseFields trait so that frontend camelCase fields
 * are properly converted to backend snake_case.
 *
 * Controllers that already handle both conventions natively
 * (e.g., CartController, CheckoutController) are exempt.
 */
class MapsCamelCaseFieldsTraitTest extends TestCase
{
    /**
     * Controllers that are exempt from the trait because they handle
     * both camelCase and snake_case natively in their validation rules.
     *
     * - CartController and CheckoutController validate both cases in their rules.
     */
    private const EXEMPT_CONTROLLERS = [
        CartController::class,
        CheckoutController::class,
    ];

    /**
     * Controllers that intentionally use only pure snake_case payloads
     * and therefore do not need camelCase mapping.
     *
     * If you add a new controller that validates pure snake_case,
     * add its short class name here with a brief comment explaining why.
     * If the controller accepts camelCase payloads, use the MapsCamelCaseFields trait instead.
     */
    private const NATIVE_SNAKE_CASE_CONTROLLERS = [
        'AddressController',       // validates snake_case: first_name, last_name, phone_number, zip_code
        'AdminController',          // validates snake_case: role, is_active, is_blocked
        'AIController',             // pure snake_case payloads
        'BrandController',          // validates snake_case: name, description, logo_url
        'ChatController',           // pure snake_case payloads
        'CuratedLookController',    // validates snake_case via product fields
        'CurrencyController',       // pure snake_case payloads
        'EmailTemplateController',  // validates snake_case: subject, body, content_html
        'InventoryController',      // validates snake_case: variant_id, quantity_adjustment
        'InvoiceController',        // pure snake_case payloads
        'LoyaltyController',        // validates snake_case: points, action
        'MarketingController',      // pure snake_case payloads
        'NotificationController',   // validates snake_case: type, title, message
        'OrderController',          // validates snake_case: product_id, status, shipping_address
        'PageController',           // validates snake_case: slug, title, content
        'PaymentController',        // validates snake_case: order_id, payment_method
        'ProductVariantController', // validates snake_case: product_id, sku, price, stock
        'ReelController',           // validates snake_case: image_url, is_active, display_order
        'RefundController',         // validates snake_case: order_id, amount, reason
        'ReturnController',         // validates snake_case: order_id, items, reason
        'ReviewController',         // validates snake_case: product_id, rating, comment
        'SeoController',            // validates snake_case: page, meta_title, meta_description
        'SettingsController',       // validates snake_case: key, value
        'SMSController',            // validates snake_case: phone, message
        'TrackingController',       // validates snake_case: order_id, carrier
        'TranslationController',    // validates snake_case: locale, key, value
        'WalletController',         // validates snake_case: amount, transaction_type
        'WebhookController',        // validates snake_case: event, payload
        'WishlistController',       // validates snake_case: product_id
    ];

    /**
     * Short names of controllers that do not accept write payloads
     * (read-only, docs, or triggers only) and therefore don't need the trait.
     */
    private const READ_ONLY_CONTROLLERS = [
        'DocsController',
        'BroadcastingController',
        'SchedulerController',
    ];

    public function test_all_api_controllers_with_write_methods_use_maps_camel_case_trait(): void
    {
        $controllers = $this->getApiControllers();
        $failures = [];

        foreach ($controllers as $shortName => $className) {
            $result = $this->checkController($shortName, $className);
            if ($result !== null) {
                $failures[] = $result;
            }
        }

        if (!empty($failures)) {
            $this->fail(implode("\n\n", $failures));
        }

        // Ensure we actually tested something
        $this->assertNotEmpty($controllers, 'No API controllers found to test.');
    }

    /**
     * Check a single controller and return an error message if it fails.
     */
    private function checkController(string $shortName, string $className): ?string
    {
        // Skip exempt controllers
        if (in_array($className, self::EXEMPT_CONTROLLERS, true)) {
            return null;
        }

        // Skip controllers that intentionally use pure snake_case
        if (in_array($shortName, self::NATIVE_SNAKE_CASE_CONTROLLERS, true)) {
            return null;
        }

        // Skip read-only controllers
        if (in_array($shortName, self::READ_ONLY_CONTROLLERS, true)) {
            return null;
        }

        // Skip the base Controller
        if ($shortName === 'Controller') {
            return null;
        }

        $reflection = new \ReflectionClass($className);
        $writeMethods = $this->getWriteMethods($reflection);

        // Skip controllers with no write methods (read-only)
        if (empty($writeMethods)) {
            return null;
        }

        // Check if the controller uses the trait
        $usesTrait = in_array(MapsCamelCaseFields::class, $reflection->getTraitNames(), true);

        if (!$usesTrait) {
            return sprintf(
                "[%s] Has write methods (%s) but does not use the MapsCamelCaseFields trait.\n"
                . "  Add 'use MapsCamelCaseFields;' to the class and define \$this->fieldMappings.\n"
                . "  If this controller intentionally handles both cases natively, add it to\n"
                . "  the EXEMPT_CONTROLLERS list in %s.",
                $shortName,
                implode(', ', $writeMethods),
                __FILE__
            );
        }

        return null;
    }

    /**
     * Discover all controller classes in the Api namespace.
     *
     * @return array<string, string> ShortName => FullClassName
     */
    private function getApiControllers(): array
    {
        $controllers = [];
        $directory = __DIR__ . '/../../../app/Http/Controllers/Api';

        if (!is_dir($directory)) {
            return $controllers;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $path = $file->getPathname();

            // Must be a Controller.php file
            if (!preg_match('/^.+Controller\.php$/i', $file->getBasename())) {
                continue;
            }

            // Skip files in Traits directory
            if (str_contains($path, '/Traits/') || str_contains($path, '\\Traits\\')) {
                continue;
            }

            // Convert path to class name
            $relativePath = str_replace(
                [__DIR__ . '/../../../app/', '/', '\\', '.php'],
                ['', '\\', '\\', ''],
                $path
            );
            $className = 'App\\' . $relativePath;

            if (class_exists($className)) {
                $controllers[class_basename($className)] = $className;
            }
        }

        ksort($controllers);

        return $controllers;
    }

    /**
     * Get the names of write methods on this controller.
     * Write methods are public methods that accept a Request parameter,
     * indicating they handle POST/PUT/PATCH payload data.
     *
     * @return string[]
     */
    private function getWriteMethods(\ReflectionClass $reflection): array
    {
        $writeMethods = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip inherited methods (from Controller, etc.)
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            // Skip constructor
            if ($method->isConstructor()) {
                continue;
            }

            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                if ($type && !$type->isBuiltin() && $type->getName() === Request::class) {
                    $writeMethods[] = $method->getName();
                    break;
                }
            }
        }

        sort($writeMethods);

        return $writeMethods;
    }
}
