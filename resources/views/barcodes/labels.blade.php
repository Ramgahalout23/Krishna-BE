<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Barcode Labels</title>
    <style>
        @page {
            margin: 10mm 12mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .page-header {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            color: #555;
            padding-bottom: 6px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 8px;
        }
        .page-header span {
            color: #999;
            font-weight: normal;
        }

        /* Label table: 3 columns x 8 rows per page */
        table.label-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        table.label-table td {
            width: 33.33%;
            border: 1px solid #ccc;
            padding: 5px 6px;
            text-align: center;
            vertical-align: middle;
            page-break-inside: avoid;
            height: 62px;
        }
        table.label-table td.empty-cell {
            border: none;
            height: 0;
            padding: 0;
        }

        .label-card .barcode-img {
            margin: 1px auto;
            max-height: 38px;
            overflow: hidden;
            text-align: center;
        }
        .label-card .barcode-img img {
            max-height: 38px;
            max-width: 100%;
            height: auto;
        }

        .label-card .sku {
            font-family: 'Courier New', 'DejaVu Sans Mono', monospace;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.04em;
            color: #1a1a1a;
            margin: 1px 0;
        }

        .label-card .name {
            font-size: 7px;
            color: #555;
            line-height: 1.15;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-height: 18px;
        }

        .label-card .type-badge {
            display: inline-block;
            font-size: 5.5px;
            padding: 0 3px;
            border-radius: 2px;
            margin-top: 1px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1.5;
        }
        .type-badge.product {
            background: #e8f5e9;
            color: #388e3c;
        }
        .type-badge.variant {
            background: #e3f2fd;
            color: #1976d2;
        }

        .footer-note {
            text-align: center;
            font-size: 6px;
            color: #aaa;
            margin-top: 4px;
        }


    </style>
</head>
<body>
    @php
        $totalLabels = count($labels);

        // Choose layout based on how many labels we have
        if ($totalLabels <= 4) {
            // Compact: 2 columns, up to 2 rows
            $cols = 2;
            $rowsPerPage = 2;
        } elseif ($totalLabels <= 12) {
            // Medium: 3 columns, up to 4 rows
            $cols = 3;
            $rowsPerPage = 4;
        } else {
            // Full page: 3 columns x 8 rows = 24 per page
            $cols = 3;
            $rowsPerPage = 8;
        }

        $perPage = $cols * $rowsPerPage;
        $chunks = array_chunk($labels, $perPage);
        $totalPages = count($chunks);
    @endphp

    @foreach ($chunks as $pageIndex => $pageLabels)
        @php
            $labelsOnPage = count($pageLabels);
            $actualRows = min($rowsPerPage, (int) ceil($labelsOnPage / $cols));
        @endphp

        <div class="page-header">
            Barcode Labels — Generated {{ $generatedAt }}
            <span>| Page {{ $pageIndex + 1 }} of {{ $totalPages }} | {{ $labelsOnPage }} labels</span>
        </div>

        <table class="label-table">
            @for ($r = 0; $r < $actualRows; $r++)
                <tr>
                    @for ($c = 0; $c < $cols; $c++)
                        @php $idx = $r * $cols + $c; @endphp
                        <td @if(!isset($pageLabels[$idx])) class="empty-cell" @endif>
                            @if (isset($pageLabels[$idx]))
                                @php $label = $pageLabels[$idx]; @endphp
                                <div class="label-card">
                                    <div class="barcode-img">
                                        {!! $label['barcode_img'] !!}
                                    </div>
                                    <div class="sku">{{ $label['sku'] }}</div>
                                    <div class="name">
                                        @if ($label['type'] === 'variant' && !empty($label['product_name']))
                                            {{ $label['product_name'] }} — {{ $label['name'] }}
                                        @else
                                            {{ $label['name'] }}
                                        @endif
                                    </div>
                                    <div>
                                        <span class="type-badge {{ $label['type'] }}">
                                            {{ $label['type'] === 'variant' ? 'Variant' : 'Product' }}
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>

        <div class="footer-note">
            Barcode labels for inventory management — {{ $generatedAt }}
        </div>

        @if (!$loop->last)
            <div style="page-break-before: always;"></div>
        @endif
    @endforeach
</body>
</html>
