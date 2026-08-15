<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        @page { margin: 20px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        .header { text-align: center; padding-bottom: 10px; border-bottom: 3px solid #e94560; margin-bottom: 20px; }
        .header h1 { color: #1a1a2e; font-size: 22px; margin: 0; }
        .header .tagline { color: #6c757d; font-size: 9px; margin: 3px 0 0; }
        .invoice-title { font-size: 18px; color: #e94560; font-weight: bold; }
        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { vertical-align: top; padding: 3px 8px; font-size: 9px; }
        .info-table .label { font-weight: bold; color: #1a1a2e; width: 90px; }
        .address-block { margin-bottom: 10px; }
        .address-block h3 { font-size: 10px; color: #1a1a2e; margin: 0 0 3px; padding: 0; }
        .address-block p { margin: 0; font-size: 9px; color: #555; }
        .items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .items-table th { background: #1a1a2e; color: #fff; font-size: 9px; padding: 6px 8px; text-align: left; }
        .items-table td { padding: 5px 8px; border-bottom: 1px solid #dee2e6; font-size: 9px; }
        .items-table tr:nth-child(even) { background: #f8f9fa; }
        .items-table .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }
        .totals { margin-left: auto; width: 45%; }
        .totals td { padding: 3px 8px; font-size: 9px; }
        .totals .total-row td { border-top: 2px solid #1a1a2e; font-size: 12px; font-weight: bold; color: #e94560; padding-top: 5px; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #dee2e6; text-align: center; font-size: 7px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company['name'] }}</h1>
        <p class="tagline">{{ $company['tagline'] }}</p>
    </div>

    <table style="width:100%;">
        <tr>
            <td style="width:50%;">
                <span class="invoice-title">INVOICE</span>
                <p style="font-size:9px;color:#6c757d;margin:3px 0;">{{ $invoiceNumber }}</p>
                <p style="font-size:9px;color:#6c757d;margin:0;">Date: {{ $generatedAt }}</p>
            </td>
            <td style="width:50%;text-align:right;">
                <p style="font-size:9px;color:#6c757d;margin:2px 0;">{{ $company['email'] }}</p>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td style="width:50%;">
                <div class="address-block">
                    <h3>BILL TO</h3>
                    @if($order->billingAddress)
                        <p>{{ $order->billingAddress->first_name }} {{ $order->billingAddress->last_name }}</p>
                        <p>{{ $order->billingAddress->address_line1 }}</p>
                        @if($order->billingAddress->address_line2)
                            <p>{{ $order->billingAddress->address_line2 }}</p>
                        @endif
                        <p>{{ $order->billingAddress->city }}, {{ $order->billingAddress->state }} {{ $order->billingAddress->zip_code }}</p>
                        <p>{{ $order->billingAddress->country }}</p>
                        @if($order->billingAddress->phone_number)
                            <p>Phone: {{ $order->billingAddress->phone_number }}</p>
                        @endif
                    @else
                        <p>N/A</p>
                    @endif
                </div>
            </td>
            <td style="width:50%;">
                <div class="address-block">
                    <h3>SHIP TO</h3>
                    @if($order->shippingAddress)
                        <p>{{ $order->shippingAddress->first_name }} {{ $order->shippingAddress->last_name }}</p>
                        <p>{{ $order->shippingAddress->address_line1 }}</p>
                        @if($order->shippingAddress->address_line2)
                            <p>{{ $order->shippingAddress->address_line2 }}</p>
                        @endif
                        <p>{{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }} {{ $order->shippingAddress->zip_code }}</p>
                        <p>{{ $order->shippingAddress->country }}</p>
                    @else
                        <p>N/A</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr><td class="label">Order #</td><td>{{ $order->order_number ?? $order->id }}</td></tr>
        <tr><td class="label">Order Date</td><td>@formatDate($order->created_at)</td></tr>
        <tr><td class="label">Status</td><td>{{ $order->status }}</td></tr>
        @if($order->payment)
        <tr><td class="label">Payment</td><td>{{ $order->payment->method }} — {{ $order->payment->status }}</td></tr>
        @if($order->payment->transaction_id)
        <tr><td class="label">Transaction</td><td>{{ $order->payment->transaction_id }}</td></tr>
        @endif
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width:40%;">Item</th>
                <th style="width:15%;">SKU</th>
                <th style="width:10%;text-align:center;">Qty</th>
                <th style="width:15%;text-align:right;">Price</th>
                <th style="width:20%;text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->items as $item)
            <tr>
                <td>{{ $item->product->name ?? 'Product' }}</td>
                <td>{{ $item->product->sku ?? $item->variant_id ?? '—' }}</td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-right">${{ number_format($item->price, 2) }}</td>
                <td class="text-right">${{ number_format($item->total ?? ($item->price * $item->quantity), 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center">No items found</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td style="width:60%;">Subtotal</td><td class="text-right">${{ number_format($order->subtotal ?? 0, 2) }}</td></tr>
        <tr><td>Tax</td><td class="text-right">${{ number_format($order->tax ?? 0, 2) }}</td></tr>
        <tr><td>Shipping</td><td class="text-right">${{ number_format($order->shipping_cost ?? 0, 2) }}</td></tr>
        @if($order->discount > 0)
        <tr><td>Discount</td><td class="text-right">-${{ number_format($order->discount, 2) }}</td></tr>
        @endif
        <tr class="total-row"><td>Total</td><td class="text-right">${{ number_format($order->total ?? 0, 2) }}</td></tr>
    </table>

    <div class="footer">
        <p>Thank you for your purchase!</p>
        <p>{{ $company['name'] }} — {{ $company['tagline'] }} | {{ $company['email'] }}</p>
        <p>This is a computer-generated invoice. No signature is required.</p>
    </div>
</body>
</html>
