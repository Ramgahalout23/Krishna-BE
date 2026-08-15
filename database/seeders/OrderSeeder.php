<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipping;
use App\Models\OrderTimeline;
use App\Models\Address;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📋 Seeding orders...');

        // Read settings from DB (fall back to seeder defaults)
        $settings = Setting::whereIn('key', ['taxRate', 'freeShippingThreshold', 'shippingFlatRate', 'currency'])
            ->pluck('value', 'key')
            ->toArray();

        $taxRate = (float) ($settings['taxRate'] ?? '18.0') / 100;
        $freeShippingThreshold = (float) ($settings['freeShippingThreshold'] ?? '499');
        $shippingFlatRate = (float) ($settings['shippingFlatRate'] ?? '50');
        $currency = $settings['currency'] ?? 'INR';

        $users = User::where('role', 'CUSTOMER')->get();
        $products = Product::with('images')->get();

        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->warn('   ⚠ Users or products missing — skipping orders');
            return;
        }

        $orderStatuses = ['DELIVERED', 'DELIVERED', 'DELIVERED', 'PENDING', 'PROCESSING', 'SHIPPED', 'CANCELLED'];
        $statusTimestamps = [
            'DELIVERED' => now()->subDays(rand(1, 10)),
            'PENDING' => now()->subHours(rand(1, 12)),
            'PROCESSING' => now()->subDays(rand(1, 3)),
            'SHIPPED' => now()->subDays(rand(2, 5)),
            'CANCELLED' => now()->subDays(rand(5, 15)),
        ];

        $createdOrders = [];
        $productIds = $products->pluck('id')->toArray();

        foreach ($orderStatuses as $idx => $status) {
            $customer = $users[$idx % $users->count()];

            // Pick 1-3 random products
            $numItems = rand(1, 3);
            $selectedProductIds = (array)array_rand(array_flip($productIds), min($numItems, count($productIds)));
            if (!is_array($selectedProductIds)) $selectedProductIds = [$selectedProductIds];

            $subtotal = 0;
            $itemsData = [];
            foreach ($selectedProductIds as $pid) {
                $product = $products->firstWhere('id', $pid);
                if (!$product) continue;
                $qty = rand(1, 3);
                $price = $product->price;
                $itemsData[] = ['product' => $product, 'qty' => $qty, 'price' => $price];
                $subtotal += $price * $qty;
            }

            if (empty($itemsData)) continue;

            $tax = round($subtotal * $taxRate, 2);
            $shippingCost = $subtotal >= $freeShippingThreshold ? 0 : $shippingFlatRate;
            $discount = $subtotal > 1000 ? round($subtotal * 0.1, 2) : 0;
            $total = $subtotal + $tax + $shippingCost - $discount;
            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);

            $address = Address::where('user_id', $customer->id)->first();
            $addressId = $address ? $address->id : Address::create([
                'user_id' => $customer->id, 'type' => 'HOME',
                'first_name' => $customer->first_name, 'last_name' => $customer->last_name,
                'phone_number' => $customer->phone_number ?? '+91 98765 43210',
                'address_line1' => 'Sample Address', 'city' => 'Bangalore',
                'state' => 'Karnataka', 'zip_code' => '560001', 'country' => 'India', 'is_default' => true,
            ])->id;

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $customer->id,
                'shipping_address_id' => $addressId,
                'billing_address_id' => $addressId,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'total' => $total,
                'status' => $status,
                'notes' => 'Sample order for testing',
                'confirmed_at' => $status === 'PENDING' ? null : $statusTimestamps[$status],
                'processing_at' => in_array($status, ['PROCESSING', 'SHIPPED', 'DELIVERED']) ? $statusTimestamps[$status] : null,
                'shipped_at' => in_array($status, ['SHIPPED', 'DELIVERED']) ? $statusTimestamps[$status] : null,
                'delivered_at' => $status === 'DELIVERED' ? $statusTimestamps[$status] : null,
                'cancelled_at' => $status === 'CANCELLED' ? $statusTimestamps[$status] : null,
                'created_at' => $statusTimestamps[$status],
            ]);

            foreach ($itemsData as $item) {
                $itemTotal = round($item['price'] * $item['qty'], 2);
                OrderItem::create([
                    'order_id' => $order->id,
                    'user_id' => $customer->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'total' => $itemTotal,
                    'created_at' => $statusTimestamps[$status],
                ]);
            }

            $paymentMethods = ['RAZORPAY', 'COD', 'STRIPE'];
            $paymentStatuses = ['COMPLETED', 'COMPLETED', 'COMPLETED', 'PENDING', 'COMPLETED', 'COMPLETED', 'REFUNDED'];
            Payment::create([
                'order_id' => $order->id,
                'transaction_id' => 'TXN-' . Str::random(16),
                'method' => $paymentMethods[array_rand($paymentMethods)],
                'amount' => $total,
                'currency' => $currency,
                'status' => $paymentStatuses[$idx] ?? 'COMPLETED',
                'created_at' => $statusTimestamps[$status],
            ]);

            if (!in_array($status, ['PENDING', 'CANCELLED'])) {
                $carriers = ['Delhivery', 'Blue Dart', 'India Post', 'DTDC'];
                Shipping::create([
                    'order_id' => $order->id,
                    'carrier' => $carriers[array_rand($carriers)],
                    'tracking_number' => strtoupper(Str::random(12)),
                    'cost' => $shippingCost,
                    'status' => $status === 'DELIVERED' ? 'DELIVERED' : ($status === 'SHIPPED' ? 'IN_TRANSIT' : 'PROCESSING'),
                    'estimated_delivery' => now()->addDays(3),
                    'actual_delivery' => $status === 'DELIVERED' ? $statusTimestamps[$status] : null,
                    'created_at' => $statusTimestamps[$status],
                ]);
            }

            $timelineStatuses = ['PENDING', 'CONFIRMED'];
            if (in_array($status, ['PROCESSING', 'SHIPPED', 'DELIVERED'])) $timelineStatuses[] = 'PROCESSING';
            if (in_array($status, ['SHIPPED', 'DELIVERED'])) $timelineStatuses[] = 'SHIPPED';
            if ($status === 'DELIVERED') $timelineStatuses[] = 'DELIVERED';
            if ($status === 'CANCELLED') $timelineStatuses = ['PENDING', 'CANCELLED'];

            $descriptions = [
                'PENDING' => 'Order placed successfully',
                'CONFIRMED' => 'Order confirmed by store',
                'PROCESSING' => 'Order is being prepared',
                'SHIPPED' => 'Package has been shipped',
                'DELIVERED' => 'Package delivered successfully',
                'CANCELLED' => 'Order was cancelled',
            ];

            foreach ($timelineStatuses as $ts) {
                OrderTimeline::create([
                    'order_id' => $order->id,
                    'status' => $ts,
                    'description' => $descriptions[$ts] ?? $ts,
                    'created_at' => $statusTimestamps[$status],
                ]);
            }

            $createdOrders[] = $order;
        }

        $this->command->info('   ✓ ' . count($createdOrders) . ' orders with items, payments & timelines created');

        // ── Recalculate sold_count for products in DELIVERED/SHIPPED/COMPLETED orders ──
        $soldProductIds = OrderItem::whereHas('order', function ($q) {
            $q->whereIn('status', ['DELIVERED', 'SHIPPED', 'COMPLETED']);
        })->pluck('product_id')->unique()->toArray();

        if (!empty($soldProductIds)) {
            app(\App\Repositories\ProductRepository::class)->batchUpdateProductSoldCount($soldProductIds);
            $this->command->info('   ✓ sold_count updated for ' . count($soldProductIds) . ' products with delivered/shipped orders');

            // Clear homepage cache so frontend shows fresh sold_count values
            // (products_cache_version is already incremented inside batchUpdateProductSoldCount)
            \Illuminate\Support\Facades\Cache::forget('homepage_all');
        } else {
            $this->command->warn('   ⚠ No products found with delivered/shipped orders — sold_count not updated');
        }
    }
}
