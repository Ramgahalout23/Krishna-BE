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

        $storeName = 'THREVOLT';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {
            // Settings table may not exist yet
        }

        $baseUrl = url('/');

        // ─── 1. About Us ───────────────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About Us',
                'content' => '
<div class="page-sections">W3siaWQiOjEsInR5cGUiOiJoZXJvIiwiX3N0eWxlcyI6eyJiZ0NvbG9yIjoiIiwicGFkZGluZyI6Im1lZGl1bSJ9LCJ0aXRsZSI6IldlbGNvbWUgdG8gJyAuICRzdG9yZU5hbWUgLiAnIiwiY2FudGVudCI6IiIsImN0YV90ZXh0IjoiU2hvcCBOb3ciLCJjdGFfbGluayI6Ii9wcm9kdWN0cyJ9LHsiaWQiOjIsInR5cGUiOiJ0d29Db2x1bW4iLCJfc3R5bGVzIjp7ImJnQ29sb3IiOiIjZmFmYWZhIiwicGFkZGluZyI6Im1lZGl1bSJ9LCJ0aXRsZSI6Ik91ciBTdG9yeSIsImxlZnRDb250ZW50IjoiPHA+JyAuICRzdG9yZU5hbWUgLiAnIHN0YXJ0ZWQgd2l0aCBhIHNpbXBsZSBtaXNzaW9uIOKAlCBjcmVhdGUgdC1zaGlydHMgdGhhdCBtYWtlIGEgc3RhdGVtZW50LiBGcm9tIG91ciBmaXJzdCBkZXNpZ24gdG8gb3VyIGxhdGVzdCBjb2xsZWN0aW9uLCBldmVyeSBwaWVjZSBpcyBjcmFmdGVkIHdpdGggcHJlY2lzaW9uLCBjYXJlLCBhbmQgYW4gdW5hcG9sb2dldGljIGF0dGVudGlvbiB0byBkZXRhaWwuPC9wPjxwPldlIGJlbGlldmUgZmFzaGlvbiBzaG91bGQgYmUgYm9sZCwgdW5hcG9sb2dldGljYWwsIGFuZCBhY2Nlc3NpYmxlLiBPdXIgZGVzaWducyBhcmUgaW5zcGlyZWQgYnkgdGhlIGVuZXJneSBvZiBpbmRpYXnigJlzIHN0cmVldHMsIHRoZSByaWNoIGhlcml0YWdlIG9mIG91ciBjdWx0dXJlLCBhbmQgdGhlIGZvcncgY2xvdGhpbmcgdHJlbmRzIG9mIHRoZSBnbG9iYWwgc3RhZ2UuPC9wPiIsImltYWdlIjoiIn0seyJpZCI6MywidHlwZSI6ImZlYXR1cmVzIiwiX3N0eWxlcyI6eyJiZ0NvbG9yIjoiIiwicGFkZGluZyI6Im1lZGl1bSJ9LCJ0aXRsZSI6IldoeSBDaG9vc2UgVXM/Iiwic3VidGl0bGUiOiIiLCJmZWF0dXJlcyI6IlByZW1pdW0gUXVhbGl0eSBNYXRlcmlhbHNcbkJvbGQgJiBVbmlxdWUgRGVzaWduc1xuMTAwJSBTYXRpc2ZhY3Rpb24gR3VhcmFudGVlXG5GcmVlIFNoaXBwaW5nIG9uIE9yZGVycyDigLkg4oK5NDk5XG5FYXN5IDctRGF5IFJldHVybnMifSx7ImlkIjo0LCJ0eXBlIjoic3RhdHMiLCJfc3R5bGVzIjp7ImJnQ29sb3IiOiIjMWEyYTJlIiwicGFkZGluZyI6Im1lZGl1bSJ9LCJ0aXRsZSI6Ik91ciBKb3VybmV5IEJ5IFRoZSBOdW1iZXJzIiwic3RhdHMiOiJbe1wibnVtYmVyXCI6XCI1MCs1MDBcIixcImxhYmVsXCI6XCJUb3RhbCBDdXN0b21lcnNcIn0se1wibnVtYmVyXCI6XCIxMCswS1wiLFwibGFiZWxcIjpcIlByb2R1Y3RzIFNvbGRcIn0se1wibnVtYmVyXCI6XCI1MCswS1wiLFwibGFiZWxcIjpcIk9yZGVycyBEZWxpdmVyZWRcIn0se1wibnVtYmVyXCI6XCI0LjhcXHUyNjAxXCIsXCJsYWJlbFwiOlwiQXZlcmFnZSBSYXRpbmdcIn1dIn1d</div>
<h1 style="font-size:56px;font-weight:800;text-align:center;padding:100px 20px 20px;background:linear-gradient(135deg,#1a1a1a,#333);color:#fff;margin:0">Welcome to ' . $storeName . '</h1>
<p style="text-align:center;font-size:20px;color:rgba(255,255,255,0.9);padding:0 20px 60px;background:linear-gradient(135deg,#1a1a1a,#333);margin:0">
    India&#039;s boldest streetwear brand — premium quality, fearless designs.
</p>
<div style="text-align:center;padding-bottom:60px;background:linear-gradient(135deg,#1a1a1a,#333)">
    <a href="/products" style="display:inline-block;background:#fff;color:#1a1a1a;padding:14px 40px;border-radius:8px;font-weight:700;text-decoration:none">Shop Now</a>
</div>
<section style="padding:60px 40px;max-width:1200px;margin:0 auto;background:#fafafa">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:center">
        <div>
            <h2 style="font-size:42px;font-weight:700;margin:0 0 30px;color:#1a1a2e">Our Story</h2>
            <p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">' . $storeName . ' started with a simple mission — create t-shirts that make a statement. From our first design to our latest collection, every piece is crafted with precision, care, and an unapologetic attention to detail.</p>
            <p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0">We believe fashion should be bold, unapologetic, and accessible. Our designs are inspired by the energy of india&#039;s streets, the rich heritage of our culture, and the forward-thinking trends of the global stage.</p>
        </div>
        <div style="background:#e5e5ea;border-radius:12px;height:400px;display:flex;align-items:center;justify-content:center;font-size:80px">👕</div>
    </div>
</section>
<section style="padding:80px 40px;background:linear-gradient(135deg,#fafafa,#f0f0f5)">
    <div style="max-width:1200px;margin:0 auto">
        <h2 style="font-size:42px;font-weight:700;text-align:center;margin:0 0 50px;color:#1a1a2e">Why Choose Us?</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:30px">
            <div style="background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);border-left:4px solid #1a1a1a"><h3 style="font-size:20px;font-weight:600;margin:0 0 15px;color:#1a1a2e">✨ Premium Quality Materials</h3><p style="color:#8a8a9a;margin:0;line-height:1.6">100% premium combed cotton with 240 GSM — ultra-soft, durable, and built to last.</p></div>
            <div style="background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);border-left:4px solid #1a1a1a"><h3 style="font-size:20px;font-weight:600;margin:0 0 15px;color:#1a1a2e">✨ Bold & Unique Designs</h3><p style="color:#8a8a9a;margin:0;line-height:1.6">Exclusive graphic prints curated by our in-house design team. You won\'t find these anywhere else.</p></div>
            <div style="background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);border-left:4px solid #1a1a1a"><h3 style="font-size:20px;font-weight:600;margin:0 0 15px;color:#1a1a2e">✨ 100% Satisfaction Guarantee</h3><p style="color:#8a8a9a;margin:0;line-height:1.6">Love your purchase or return it within 7 days — no questions asked.</p></div>
            <div style="background:#fff;padding:40px 30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);border-left:4px solid #1a1a1a"><h3 style="font-size:20px;font-weight:600;margin:0 0 15px;color:#1a1a2e">✨ Free Shipping on Orders ₹499+</h3><p style="color:#8a8a9a;margin:0;line-height:1.6">Free shipping across India. Easy 7-day returns with free pickup.</p></div>
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
                'meta_description' => "Learn about {$storeName} — India's boldest streetwear brand. Premium quality t-shirts, fearless designs, and 100% satisfaction guaranteed.",
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
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">For privacy-related inquiries, please email us at support@threvolt.com or contact us through our support page.</p>',
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
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 30px">We want you to love your purchase. If something isn\&#039;t right, we\&#039;re here to help.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin:0 0 40px">
    <div style="background:#f0fdf4;padding:30px;border-radius:12px;text-align:center"><div style="font-size:36px;margin:0 0 10px">🔄</div><div style="font-size:18px;font-weight:700;color:#166534">7-Day Returns</div><div style="font-size:14px;color:#166534;opacity:0.8">Return within 7 days of delivery</div></div>
    <div style="background:#eff6ff;padding:30px;border-radius:12px;text-align:center"><div style="font-size:36px;margin:0 0 10px">🚚</div><div style="font-size:18px;font-weight:700;color:#1e40af">Free Pickup</div><div style="font-size:14px;color:#1e40af;opacity:0.8">We pick up the item for free</div></div>
    <div style="background:#fef3c7;padding:30px;border-radius:12px;text-align:center"><div style="font-size:36px;margin:0 0 10px">💰</div><div style="font-size:18px;font-weight:700;color:#92400e">100% Refund</div><div style="font-size:14px;color:#92400e;opacity:0.8">Full refund to original payment</div></div>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Eligibility</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">Items must be unused, unwashed, and in original condition with tags attached. Returns are accepted within 7 days of delivery.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">How to Return</h2>
<ol style="font-size:16px;line-height:2;color:#4a4a5a;padding-left:20px">
    <li>Log in to your account and go to Orders</li>
    <li>Select the item you want to return</li>
    <li>Choose a reason and submit the return request</li>
    <li>Schedule a free pickup or drop off at your nearest courier</li>
    <li>We\'ll process your refund within 5-7 business days after receiving the item</li>
</ol>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Exchanges</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">For size exchanges, simply place a new order and return the original item for a full refund. This ensures faster processing and availability of your preferred size.</p>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">Non-Returnable Items</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">Due to hygiene reasons, innerwear, socks, and masks cannot be returned unless there is a manufacturing defect.</p>',
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
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 40px">We\'d love to hear from you! Choose your preferred way to reach us.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:0 0 40px">
    <div style="background:#fafafa;padding:30px;border-radius:12px;border:1px solid #e5e5ea;text-align:center">
        <div style="font-size:36px;margin:0 0 15px">📧</div>
        <h3 style="font-size:18px;font-weight:700;margin:0 0 5px;color:#1a1a2e">Email</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0"><a href="mailto:support@threvolt.com" style="color:#1a1a1a;text-decoration:underline">support@threvolt.com</a></p>
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
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">We offer a 7-day return policy with free pickup. Items must be unworn with tags attached.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">Do you ship internationally?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Currently we ship within India only. International shipping coming soon!</p>
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
<h2 style="font-size:24px;font-weight:700;margin:40px 0 15px;color:#1a1a2e">4. Intellectual Property</h2>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 20px">All content on this website including designs, graphics, text, and logos are the exclusive property of ' . $storeName . ' and protected by applicable intellectual property laws.</p>
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
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Simply browse our products, select your size and color, add items to your cart, and proceed to checkout. You can checkout as a guest or create an account for faster future purchases.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">Can I cancel my order?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Yes, orders can be cancelled within 24 hours of placement if they haven\'t been shipped yet. Contact our support team or cancel from your account dashboard.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">What payment methods do you accept?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">We accept credit/debit cards, UPI, net banking, and Cash on Delivery (COD) — all powered by Razorpay for secure transactions.</p>
</details>

<h2 style="font-size:20px;font-weight:700;margin:30px 0 20px;padding-bottom:10px;border-bottom:2px solid #1a1a1a;color:#1a1a2e">👕 Products</h2>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">What fabric do you use?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Our t-shirts are made from 100% premium combed cotton with 240 GSM weight — ensuring durability, softness, and comfort. Each tee is pre-shrunk to maintain its fit wash after wash.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How do I find my size?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Check our Size Guide page for detailed measurements. As a general rule, our tees run true to size with a regular fit. If you prefer an oversized look, we recommend going one size up.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How should I care for my t-shirts?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Machine wash cold with similar colors. Do not bleach. Tumble dry low or hang dry. Iron on low heat. Avoid fabric softeners to maintain print quality.</p>
</details>

<h2 style="font-size:20px;font-weight:700;margin:30px 0 20px;padding-bottom:10px;border-bottom:2px solid #1a1a1a;color:#1a1a2e">🚚 Shipping & Returns</h2>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How long does shipping take?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Standard shipping takes 3-7 business days. Express shipping takes 1-3 business days. Free shipping on orders above ₹499.</p>
</details>
<details style="margin-bottom:15px;border:1px solid #e5e5ea;border-radius:8px;padding:20px;background:#fafafa">
    <summary style="font-weight:600;cursor:pointer;font-size:16px;color:#1a1a2e">How do I return a product?</summary>
    <p style="margin-top:15px;color:#4a4a5a;line-height:1.6">Log into your account, go to Orders, select the item, and initiate a return. We\'ll arrange a free pickup within 2-3 days. Refunds are processed within 5-7 business days of receiving the return.</p>
</details>',
                'meta_title' => "FAQ - {$storeName}",
                'meta_description' => "Frequently asked questions about {$storeName} — orders, products, shipping, returns, and more.",
                'is_published' => true,
            ]
        );

        // ─── 8. Size Guide ───────────────────────────────────────
        Page::firstOrCreate(
            ['slug' => 'size-guide'],
            [
                'title' => 'Size Guide',
                'content' => '
<h1 style="font-size:42px;font-weight:700;margin:0 0 10px;color:#1a1a2e">Size Guide</h1>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 30px">Find your perfect fit with our detailed size chart. Measurements are in inches.</p>
<p style="background:#fef3c7;padding:15px 20px;border-radius:8px;font-size:14px;color:#92400e;margin:0 0 40px"><strong>💡 Pro Tip:</strong> If you\'re between sizes or prefer an oversized fit, we recommend going one size up. Our tees have a regular fit — for a relaxed look, size up.</p>
<div style="overflow-x:auto;margin:0 0 40px">
    <table style="width:100%;border-collapse:collapse;font-size:15px">
        <thead>
            <tr style="background:#1a1a1a;color:#fff">
                <th style="padding:12px 20px;text-align:left">Size</th>
                <th style="padding:12px 20px;text-align:left">Chest (inches)</th>
                <th style="padding:12px 20px;text-align:left">Length (inches)</th>
                <th style="padding:12px 20px;text-align:left">Shoulder (inches)</th>
                <th style="padding:12px 20px;text-align:left">Sleeve (inches)</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom:1px solid #e5e5ea"><td style="padding:12px 20px;font-weight:700">S</td><td style="padding:12px 20px">36-38</td><td style="padding:12px 20px">27</td><td style="padding:12px 20px">16</td><td style="padding:12px 20px">8</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea;background:#fafafa"><td style="padding:12px 20px;font-weight:700">M</td><td style="padding:12px 20px">38-40</td><td style="padding:12px 20px">28</td><td style="padding:12px 20px">17</td><td style="padding:12px 20px">8.5</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea"><td style="padding:12px 20px;font-weight:700">L</td><td style="padding:12px 20px">40-42</td><td style="padding:12px 20px">29</td><td style="padding:12px 20px">18</td><td style="padding:12px 20px">9</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea;background:#fafafa"><td style="padding:12px 20px;font-weight:700">XL</td><td style="padding:12px 20px">42-44</td><td style="padding:12px 20px">30</td><td style="padding:12px 20px">19</td><td style="padding:12px 20px">9.5</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea"><td style="padding:12px 20px;font-weight:700">XXL</td><td style="padding:12px 20px">44-46</td><td style="padding:12px 20px">31</td><td style="padding:12px 20px">20</td><td style="padding:12px 20px">10</td></tr>
            <tr style="border-bottom:1px solid #e5e5ea;background:#fafafa"><td style="padding:12px 20px;font-weight:700">3XL</td><td style="padding:12px 20px">46-48</td><td style="padding:12px 20px">32</td><td style="padding:12px 20px">21</td><td style="padding:12px 20px">10.5</td></tr>
        </tbody>
    </table>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 20px;color:#1a1a2e">How to Measure</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px">
    <div style="background:#fafafa;padding:20px;border-radius:12px;border:1px solid #e5e5ea">
        <h3 style="font-size:16px;font-weight:700;margin:0 0 10px;color:#1a1a2e">📏 Chest</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0;line-height:1.6">Measure around the fullest part of your chest, keeping the measuring tape under your arms and parallel to the floor.</p>
    </div>
    <div style="background:#fafafa;padding:20px;border-radius:12px;border:1px solid #e5e5ea">
        <h3 style="font-size:16px;font-weight:700;margin:0 0 10px;color:#1a1a2e">📏 Length</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0;line-height:1.6">Measure from the highest point of the shoulder down to the bottom hem of the t-shirt.</p>
    </div>
    <div style="background:#fafafa;padding:20px;border-radius:12px;border:1px solid #e5e5ea">
        <h3 style="font-size:16px;font-weight:700;margin:0 0 10px;color:#1a1a2e">📏 Shoulder</h3>
        <p style="font-size:14px;color:#4a4a5a;margin:0;line-height:1.6">Measure from the edge of one shoulder to the edge of the other shoulder across the back.</p>
    </div>
</div>',
                'meta_title' => "Size Guide - {$storeName}",
                'meta_description' => "Find your perfect fit with {$storeName}'s detailed size chart. Chest, length, shoulder, and sleeve measurements for all sizes S-3XL.",
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
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 30px">Make your ' . $storeName . ' t-shirts last longer with proper care. Follow these simple guidelines.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:0 0 40px">
    <div style="background:#f0fdf4;padding:25px;border-radius:12px;border:1px solid #bbf7d0;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">🧺</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#166534">Machine Wash Cold</h3>
        <p style="font-size:13px;color:#166534;line-height:1.5">Wash with cold water to preserve color and print quality</p>
    </div>
    <div style="background:#fef3c7;padding:25px;border-radius:12px;border:1px solid #fde68a;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">☀️</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#92400e">Hang Dry</h3>
        <p style="font-size:13px;color:#92400e;line-height:1.5">Avoid high heat — hang dry or tumble dry on low</p>
    </div>
    <div style="background:#eff6ff;padding:25px;border-radius:12px;border:1px solid #bfdbfe;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">🚫</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#1e40af">No Bleach</h3>
        <p style="font-size:13px;color:#1e40af;line-height:1.5">Never use bleach or harsh chemicals on your tees</p>
    </div>
    <div style="background:#fef2f2;padding:25px;border-radius:12px;border:1px solid #fecaca;text-align:center">
        <div style="font-size:36px;margin:0 0 10px">🔲</div>
        <h3 style="font-size:16px;font-weight:700;margin:0 0 5px;color:#991b1b">Iron Inside Out</h3>
        <p style="font-size:13px;color:#991b1b;line-height:1.5">Iron on low heat, inside out to protect the print</p>
    </div>
</div>
<h2 style="font-size:24px;font-weight:700;margin:40px 0 20px;color:#1a1a2e">Detailed Care Guide</h2>
<h3 style="font-size:18px;font-weight:600;margin:25px 0 10px;color:#1a1a2e">Pre-Wash</h3>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 15px">Always turn your t-shirts inside out before washing. This protects the print and reduces friction on the outer fabric.</p>
<h3 style="font-size:18px;font-weight:600;margin:25px 0 10px;color:#1a1a2e">Washing</h3>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 15px">Machine wash with cold water (30°C or below) using a mild detergent. Wash with similar colors to prevent color transfer. Avoid fabric softeners as they can damage the fabric fibers and print quality.</p>
<h3 style="font-size:18px;font-weight:600;margin:25px 0 10px;color:#1a1a2e">Drying</h3>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 15px">Hang drying is best for longevity. If using a dryer, select low heat settings. High heat can cause shrinkage and damage the print. Remove from dryer while slightly damp to reduce wrinkles.</p>
<h3 style="font-size:18px;font-weight:600;margin:25px 0 10px;color:#1a1a2e">Ironing</h3>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 15px">Iron on low to medium heat. Always iron inside out and avoid ironing directly over printed areas. For stubborn wrinkles, use a steaming function instead.</p>
<h3 style="font-size:18px;font-weight:600;margin:25px 0 10px;color:#1a1a2e">Storage</h3>
<p style="font-size:16px;line-height:1.8;color:#4a4a5a;margin:0 0 15px">Fold your t-shirts instead of hanging to maintain shape. Store in a cool, dry place away from direct sunlight to prevent fading.</p>',
                'meta_title' => "Care Instructions - {$storeName}",
                'meta_description' => "Learn how to care for your {$storeName} t-shirts. Washing, drying, ironing, and storage tips to make your tees last longer.",
                'is_published' => true,
            ]
        );

        if ($this->command) {
            $this->command->info('   ✓ ' . Page::count() . ' CMS pages created/updated with rich templates');
        }
    }
}
