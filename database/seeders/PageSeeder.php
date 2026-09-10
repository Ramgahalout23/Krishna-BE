<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('📄 Seeding CMS pages with sample templates...');
        }

        $storeName = 'dotoydo';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {
            // Settings table may not exist yet
        }

        // ─── 1. About Us ───────────────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About Us',
                'content' => '
<h1 style="font-size:56px;font-weight:800;text-align:center;padding:100px 20px 20px;background:linear-gradient(135deg,#1a1a1a,#333);color:#fff;margin:0">Welcome to ' . $storeName . '</h1>
<p style="text-align:center;font-size:20px;color:rgba(255,255,255,0.9);padding:0 20px 60px;background:linear-gradient(135deg,#1a1a1a,#333);margin:0">
    Your everyday mart — toys, electronics, home & more, all under one roof.
</p>
<div style="text-align:center;padding-bottom:60px;background:linear-gradient(135deg,#1a1a1a,#333)">
    <a href="/products" style="display:inline-block;background:#fff;color:#1a1a1a;padding:14px 40px;border-radius:8px;font-weight:700;text-decoration:none">Shop Now</a>
</div>
<section style="padding:60px 40px;max-width:1200px;margin:0 auto;background:#fafafa">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:center">
        <div>
            <h2 style="font-size:42px;font-weight:700;margin:0 0 30px;color:#1a1a2e">Our Story</h2>
            <p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">' . $storeName . ' started with a simple idea — bring together everything a family needs every day: toys that spark joy, gadgets that make life easier, and home essentials you can trust. From our first shelf to our growing catalog, every product is chosen with care, quality-checked, and priced honestly.</p>
            <p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0">We believe shopping should be simple, safe, and a little bit fun. That is why we curate trusted brands, keep prices fair, and make sure returns are painless — because happy customers (and happy kids) are what keep us going.</p>
        </div>
        <div style="background:#e5e5ea;border-radius:12px;height:400px;display:flex;align-items:center;justify-content:center;font-size:80px">🧸</div>
    </div>
</section>
<section style="padding:80px 40px;background:linear-gradient(135deg,#fafafa,#f0f0f5)">
    <div style="max-width:1200px;margin:0 auto">
        <h2 style="font-size:42px;font-weight:700;text-align:center;margin:0 0 50px;color:#1a1a2e">Why Choose Us?</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:30px">
            <div style="background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);border-left:4px solid #1a1a1a"><h3 style="font-size:20px;font-weight:600;margin:0 0 15px;color:#1a1a2e">🧸 Curated for Families</h3><p style="color:#8a8a9a;margin:0;line-height:1.6">Toys and everyday products selected for quality, safety, and real value — for kids and grown-ups alike.</p></div>
            <div style="background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);border-left:4px solid #1a1a1a"><h3 style="font-size:20px;font-weight:600;margin:0 0 15px;color:#1a1a2e">✨ Quality Checked</h3><p style="color:#8a8a9a;margin:0;line-height:1.6">Every product passes a quality and safety check before it reaches your doorstep.</p></div>
            <div style="background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);border-left:4px solid #1a1a1a"><h3 style="font-size:20px;font-weight:600;margin:0 0 15px;color:#1a1a2e">↩️ Easy Returns</h3><p style="color:#8a8a9a;margin:0;line-height:1.6">Not right for you? Return it within 7 days — no questions asked.</p></div>
            <div style="background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);border-left:4px solid #1a1a1a"><h3 style="font-size:20px;font-weight:600;margin:0 0 15px;color:#1a1a2e">🚚 Free Shipping ₹499+</h3><p style="color:#8a8a9a;margin:0;line-height:1.6">Free shipping across India with fast delivery and easy 7-day returns.</p></div>
        </div>
    </div>
</section>
<section style="padding:80px 40px;background:linear-gradient(135deg,#1a1a1a,#333);color:#fff">
    <div style="max-width:1200px;margin:0 auto">
        <h2 style="font-size:42px;font-weight:700;text-align:center;margin:0 0 60px">Our Journey By The Numbers</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:40px">
            <div style="text-align:center"><div style="font-size:48px;font-weight:800;margin:0 0 10px">50K+</div><div style="font-size:16px;opacity:0.9">Total Customers</div></div>
            <div style="text-align:center"><div style="font-size:48px;font-weight:800;margin:0 0 10px">10K+</div><div style="font-size:16px;opacity:0.9">Products Sold</div></div>
            <div style="text-align:center"><div style="font-size:48px;font-weight:800;margin:0 0 10px">50K+</div><div style="font-size:16px;opacity:0.9">Orders Delivered</div></div>
            <div style="text-align:center"><div style="font-size:48px;font-weight:800;margin:0 0 10px">4.8★</div><div style="font-size:16px;opacity:0.9">Average Rating</div></div>
        </div>
    </div>
</section>',
                'meta_title' => "About {$storeName}",
                'meta_description' => "Learn about {$storeName} — your everyday mart for toys, electronics, home & more. Quality products, fair prices, and easy returns.",
                'is_published' => true,
            ]
        );

        // ─── 2. Privacy Policy ─────────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy Policy',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Privacy Policy</h1>
<p style="color:#8a8a9a;margin:0 0 40px;font-size:14px">Last updated: June 2026</p>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 30px">At ' . $storeName . ', we take your privacy seriously. This policy describes how we collect, use, and protect your personal information.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">1. Information We Collect</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">We collect information you provide when creating an account, placing an order, subscribing to our newsletter, or contacting our support team. This includes your name, email address, shipping address, phone number, and payment details.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">2. How We Use Your Information</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">Your information is used to process and fulfill orders, send order updates, provide customer support, improve our products and services, and send marketing communications (with your consent).</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">3. Data Protection</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">We implement industry-standard security measures including SSL encryption and secure payment gateways. Your payment information is never stored on our servers — it is processed directly by our payment partners (Razorpay/COD).</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">4. Your Rights</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">You have the right to access, update, or delete your personal information at any time. You can do this through your account settings or by contacting our support team.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">5. Contact</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">For privacy-related inquiries, please email us at support@dotoydo.com or contact us through our support page.</p>',
                'meta_title' => "Privacy Policy - {$storeName}",
                'meta_description' => "{$storeName} Privacy Policy. Learn how we collect, use, and protect your personal information.",
                'is_published' => true,
            ]
        );

        // ─── 3. Return & Exchange Policy ─────────────────────────
        Page::firstOrCreate(
            ['slug' => 'return-policy'],
            [
                'title' => 'Return & Exchange Policy',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Return & Exchange Policy</h1>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 30px">We want you to love your purchase. If something is not right, we are here to help.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin:0 0 40px">
    <div style="background:#f0fdf4;padding:30px;border-radius:12px;text-align:center"><div style="font-size:36px;margin:0 0 10px">🔄</div><div style="font-size:18px;font-weight:700;color:#166534">7-Day Returns</div><div style="font-size:14px;color:#166534;opacity:0.8">Return within 7 days of delivery</div></div>
    <div style="background:#eff6ff;padding:30px;border-radius:12px;text-align:center"><div style="font-size:36px;margin:0 0 10px">🚚</div><div style="font-size:18px;font-weight:700;color:#1e40af">Free Pickup</div><div style="font-size:14px;color:#1e40af;opacity:0.8">We pick up the item for free</div></div>
    <div style="background:#fef3c7;padding:30px;border-radius:12px;text-align:center"><div style="font-size:36px;margin:0 0 10px">💰</div><div style="font-size:18px;font-weight:700;color:#92400e">100% Refund</div><div style="font-size:14px;color:#92400e;opacity:0.8">Full refund to original payment</div></div>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Eligibility</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">Items must be unused and in their original packaging with all accessories intact. Returns are accepted within 7 days of delivery.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">How to Return</h2>
<ol style="font-size:16px;line-height:2;color:#4a4a5a;padding-left:20px">
    <li>Log in to your account and go to Orders</li>
    <li>Select the item you want to return</li>
    <li>Choose a reason and submit the return request</li>
    <li>Schedule a free pickup or drop off at your nearest courier</li>
    <li>We will process your refund within 5-7 business days after receiving the item</li>
</ol>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Exchanges</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">For an exchange, simply place a new order and return the original item for a full refund. This ensures faster processing and availability of your preferred variant.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Non-Returnable Items</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">For safety and hygiene reasons, items that are opened from their sealed packaging, used, or show signs of damage caused by the buyer cannot be returned unless they have a manufacturing defect.</p>',
                'meta_title' => "Return Policy - {$storeName}",
                'meta_description' => "Easy returns and exchanges at {$storeName}. 7-day return policy with free pickup. Full refund guaranteed.",
                'is_published' => true,
            ]
        );

        // ─── 4. Contact Us ─────────────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'contact'],
            [
                'title' => 'Contact Us',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Contact Us</h1>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 40px">We would love to hear from you! Choose your preferred way to reach us.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:0 0 40px">
    <div style="background:#fafafa;padding:30px;border-radius:12px;border:1px solid #e5e5ea;text-align:center">
        <div style="font-size:36px;margin:0 0 15px">📧</div>
        <h3 style="font-size:18px;font-weight:700;margin:0 0 5px;color:#1a1a2e">Email</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0"><a href="mailto:support@dotoydo.com" style="color:#1a1a1a;text-decoration:underline">support@dotoydo.com</a></p>
    </div>
    <div style="background:#fafafa;padding:30px;border-radius:12px;border:1px solid #e5e5ea;text-align:center">
        <div style="font-size:36px;margin:0 0 15px">📞</div>
        <h3 style="font-size:18px;font-weight:700;margin:0 0 5px;color:#1a1a2e">Phone</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0">+91 98765 43210</p>
        <p style="font-size:12px;color:#8a8a9a;margin:5px 0 0">Mon-Sat, 9AM-7PM</p>
    </div>
    <div style="background:#fafafa;padding:30px;border-radius:12px;border:1px solid #e5e5ea;text-align:center">
        <div style="font-size:36px;margin:0 0 15px">📍</div>
        <h3 style="font-size:18px;font-weight:700;margin:0 0 5px;color:#1a1a2e">Address</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0">Bangalore, Karnataka, India</p>
    </div>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 20px;color:#1a1a2e">Frequently Asked Questions</h2>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How long does shipping take?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Standard shipping takes 3-7 business days across India. Express shipping options are available at checkout.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">What is your return policy?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">We offer a 7-day return policy with free pickup. Items must be unused and in original packaging.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">Are the toys safe for children?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">All toys are quality checked and come with age guidance on the product page. Adult supervision is recommended for small parts.</p>
</details>',
                'meta_title' => "Contact Us - {$storeName}",
                'meta_description' => "Get in touch with {$storeName}. Email, phone, and address information for customer support.",
                'is_published' => true,
            ]
        );

        // ─── 5. Terms & Conditions ──────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'terms-and-conditions'],
            [
                'title' => 'Terms & Conditions',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Terms & Conditions</h1>
<p style="color:#8a8a9a;margin:0 0 40px;font-size:14px">Last updated: June 2026</p>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 30px">These terms and conditions govern your use of the ' . $storeName . ' website and services. By using our site, you agree to these terms.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">1. Account Registration</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">You must be 18 years or older to create an account. You are responsible for maintaining the confidentiality of your account credentials and for all activities under your account.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">2. Orders & Payments</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">All orders are subject to availability and price confirmation. We accept payment via Razorpay (credit/debit cards, UPI, net banking) and Cash on Delivery. Prices are in INR and inclusive of applicable taxes.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">3. Shipping & Delivery</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">We ship across India using trusted courier partners. Estimated delivery times are 3-7 business days. Free shipping is available on orders above ₹499.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">4. Product Safety</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">Toys and products with small parts include age guidance and safety warnings. Keep products away from children under the recommended age unless supervised by an adult.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">5. Limitation of Liability</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">' . $storeName . ' shall not be liable for any indirect, incidental, or consequential damages arising from the use of our products or services.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">6. Governing Law</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">These terms shall be governed by and construed in accordance with the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in Bangalore, Karnataka.</p>',
                'meta_title' => "Terms & Conditions - {$storeName}",
                'meta_description' => "{$storeName} Terms & Conditions. Learn about account registration, orders, payments, shipping, and our policies.",
                'is_published' => true,
            ]
        );

        // ─── 6. Shipping Information ─────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'shipping-information'],
            [
                'title' => 'Shipping Information',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Shipping Information</h1>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 40px">Everything you need to know about shipping, delivery times, and tracking.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:0 0 40px">
    <div style="background:#fafafa;padding:30px;border-radius:12px;border:1px solid #e5e5ea;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">📦</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#1a1a2e">Standard Shipping</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0">₹50 flat rate</p>
        <p style="font-size:13px;color:#8a8a9a;margin:5px 0 0">3-7 business days</p>
    </div>
    <div style="background:#f0fdf4;padding:30px;border-radius:12px;border:1px solid #bbf7d0;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">🚚</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#166534">Free Shipping</h3>
        <p style="font-size:14px;color:#166534;margin:0">On orders above ₹499</p>
        <p style="font-size:13px;color:#166534;opacity:0.8">Automatically applied</p>
    </div>
    <div style="background:#fef3c7;padding:30px;border-radius:12px;border:1px solid #fde68a;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">⚡</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#92400e">Express Shipping</h3>
        <p style="font-size:14px;color:#92400e;margin:0">₹149 flat rate</p>
        <p style="font-size:13px;color:#92400e;opacity:0.8">1-3 business days</p>
    </div>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Order Processing</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">Orders are processed within 24 hours of placement (excluding weekends and holidays). You will receive a confirmation email with your order details and tracking information once shipped.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Tracking Your Order</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">Once your order is shipped, you will receive a tracking number via email and SMS. You can also track your order in real-time from your account dashboard or our Track Order page.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Delivery Areas</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">We currently ship to all pin codes across India. International shipping will be available soon. For remote areas, additional delivery time of 2-3 days may apply.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Shipping Partners</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">We partner with trusted courier services including Delhivery, Blue Dart, and India Post to ensure reliable and timely delivery of your orders.</p>',
                'meta_title' => "Shipping Information - {$storeName}",
                'meta_description' => "{$storeName} shipping information. Learn about delivery times, shipping rates, tracking, and delivery areas across India.",
                'is_published' => true,
            ]
        );

        // ─── 7. FAQ ──────────────────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'faq'],
            [
                'title' => 'Frequently Asked Questions',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Frequently Asked Questions</h1>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 40px">Quick answers to common questions about our products, orders, and services.</p>

<h2 style="font-size:20px;font-weight:700;margin:30px 0 20px;padding-bottom:10px;border-bottom:2px solid #1a1a1a;color:#1a1a2e">🛒 Orders</h2>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How do I place an order?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Simply browse our products, pick your preferred color or variant, add items to your cart, and proceed to checkout. You can checkout as a guest or create an account for faster future purchases.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">Can I cancel my order?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Yes, orders can be cancelled within 24 hours of placement if they have not been shipped yet. Contact our support team or cancel from your account dashboard.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">What payment methods do you accept?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">We accept credit/debit cards, UPI, net banking, and Cash on Delivery (COD) — all powered by Razorpay for secure transactions.</p>
</details>

<h2 style="font-size:20px;font-weight:700;margin:30px 0 20px;padding-bottom:10px;border-bottom:2px solid #1a1a1a;color:#1a1a2e">🧸 Products</h2>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">Are your toys safe and quality checked?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Yes. Every toy and product is quality checked for safety and durability. Products with small parts include clear age guidance, and we recommend adult supervision for young children.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How do I choose the right size or variant?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Each product page lists available colors and variants. For toys, check the age guidance on the product page. If you are unsure, message us on chat and we will help you pick.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How do I care for my products?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Check the care instructions included with your product. Keep electronics away from water, clean soft toys gently, and store items in a cool, dry place.</p>
</details>

<h2 style="font-size:20px;font-weight:700;margin:30px 0 20px;padding-bottom:10px;border-bottom:2px solid #1a1a1a;color:#1a1a2e">🚚 Shipping & Returns</h2>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How long does shipping take?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Standard shipping takes 3-7 business days. Express shipping takes 1-3 business days. Free shipping on orders above ₹499.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How do I return a product?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Log into your account, go to Orders, select the item, and initiate a return. We will arrange a free pickup within 2-3 days. Refunds are processed within 5-7 business days of receiving the return.</p>
</details>',
                'meta_title' => "FAQ - {$storeName}",
                'meta_description' => "Frequently asked questions about {$storeName} — orders, products, shipping, returns, and more.",
                'is_published' => true,
            ]
        );

        // ─── 8. Size & Age Guide ────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'size-guide'],
            [
                'title' => 'Size & Age Guide',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Size & Age Guide</h1>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 30px">A quick guide to help you choose the right products. Age guidance on toys is based on safety, complexity, and suitability — it is not a measure of intelligence or skill.</p>
<div style="overflow-x:auto;margin:0 0 40px">
    <table style="width:100%;border-collapse:collapse;font-size:15px">
        <thead>
            <tr style="background:#1a1a1a;color:#fff">
                <th style="padding:12px 20px;text-align:left">Age Group</th>
                <th style="padding:12px 20px;text-align:left">Great For</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom:1px solid #e5e5ea"><td style="padding:12px 20px;font-weight:700">0-2 Years</td><td style="padding:12px 20px">Soft plush toys, sensory toys, rattles & teethers</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea;background:#fafafa"><td style="padding:12px 20px;font-weight:700">3-5 Years</td><td style="padding:12px 20px">Building blocks, simple puzzles, musical toys, pretend play</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea"><td style="padding:12px 20px;font-weight:700">6-8 Years</td><td style="padding:12px 20px">Board games, science kits, action figures, ride-ons</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea;background:#fafafa"><td style="padding:12px 20px;font-weight:700">9-12 Years</td><td style="padding:12px 20px">RC vehicles, strategy games, construction sets, puzzles</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea"><td style="padding:12px 20px;font-weight:700">Teens & Adults</td><td style="padding:12px 20px">Collectibles, speed cubes, sports gear, electronics</td></tr>
        </tbody>
    </table>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 20px;color:#1a1a2e">Small Parts Warning</h2>
<div style="background:#fef3c7;padding:20px;border-radius:12px;border:1px solid #fde68a;margin:0 0 20px">
    <p style="font-size:14px;color:#92400e;margin:0;line-height:1.6"><strong>⚠️ Safety First:</strong> Toys with small parts can be a choking hazard for children under 3 years. Always check the age label on each product and supervise younger children during play.</p>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 20px;color:#1a1a2e">Electronics & Wearables Sizing</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px">
    <div style="background:#fafafa;padding:20px;border-radius:12px;border:1px solid #e5e5ea">
        <h3 style="font-size:16px;font-weight:700;margin:0 0 10px;color:#1a1a2e">⌚ Smart Bands</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0;line-height:1.6">Straps are adjustable and fit most wrists (approx. 140-220 mm).</p>
    </div>
    <div style="background:#fafafa;padding:20px;border-radius:12px;border:1px solid #e5e5ea">
        <h3 style="font-size:16px;font-weight:700;margin:0 0 10px;color:#1a1a2e">🛏️ Bedding</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0;line-height:1.6">Bedding sets list the bed size (Single, Queen, King). Measure your mattress before ordering.</p>
    </div>
</div>',
                'meta_title' => "Size & Age Guide - {$storeName}",
                'meta_description' => "Find the right fit with {$storeName}'s size & age guide. Toy age guidance, small parts safety, and sizing for smart bands and bedding.",
                'is_published' => true,
            ]
        );

        // ─── 9. Care Instructions ─────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'care-instructions'],
            [
                'title' => 'Care Instructions',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Care Instructions</h1>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 30px">Make your ' . $storeName . ' products last longer with a little care. Follow these simple guidelines.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:0 0 40px">
    <div style="background:#f0fdf4;padding:25px;border-radius:12px;border:1px solid #bbf7d0;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">🧸</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#166534">Soft Toys & Plush</h3>
        <p style="font-size:13px;color:#166534;line-height:1.5">Spot clean or gentle machine wash in a laundry bag on a cold cycle. Air dry fully.</p>
    </div>
    <div style="background:#eff6ff;padding:25px;border-radius:12px;border:1px solid #bfdbfe;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">🔋</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#1e40af">Electronics & Gadgets</h3>
        <p style="font-size:13px;color:#1e40af;line-height:1.5">Keep away from water and extreme heat. Charge with the included cable only.</p>
    </div>
    <div style="background:#fef3c7;padding:25px;border-radius:12px;border:1px solid #fde68a;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">🍳</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#92400e">Cookware & Kitchen</h3>
        <p style="font-size:13px;color:#92400e;line-height:1.5">Use wooden or silicone utensils on non-stick surfaces. Hand wash for best results.</p>
    </div>
    <div style="background:#fef2f2;padding:25px;border-radius:12px;border:1px solid #fecaca;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">🛏️</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#991b1b">Bedding & Linen</h3>
        <p style="font-size:13px;color:#991b1b;line-height:1.5">Machine wash separately on a gentle cycle. Avoid bleach and high-heat drying.</p>
    </div>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 20px;color:#1a1a2e">Detailed Care Guide</h2>
<h3 style="font-size:18px;font-weight:600;margin:25px 0 10px;color:#1a1a2e">Toys with Batteries</h3>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 15px">Remove batteries when the toy is not in use for a long time. Never mix old and new batteries, and replace the battery cover securely after every change.</p>
<h3 style="font-size:18px;font-weight:600;margin:25px 0 10px;color:#1a1a2e">Cleaning Soft Surfaces</h3>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 15px">For plush toys and cushions, vacuum regularly and spot clean with mild soap. Machine wash only if the care tag allows it, using a cold gentle cycle.</p>
<h3 style="font-size:18px;font-weight:600;margin:25px 0 10px;color:#1a1a2e">Storing Your Products</h3>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 15px">Store toys and gadgets in a cool, dry place away from direct sunlight. Keep small parts and batteries out of reach of young children when not in use.</p>',
                'meta_title' => "Care Instructions - {$storeName}",
                'meta_description' => "Learn how to care for your {$storeName} products. Cleaning, storage, and battery safety tips for toys, electronics, and home essentials.",
                'is_published' => true,
            ]
        );

        if ($this->command) {
            $this->command->info('   ✓ ' . Page::count() . ' CMS pages created/updated with rich templates');
        }
    }
}
