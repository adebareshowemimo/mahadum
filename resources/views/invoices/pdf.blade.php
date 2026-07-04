<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; color: #1f2937; }
        body { font-size: 12px; margin: 0; padding: 32px; }
        .header { border-bottom: 3px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 24px; }
        .brand { font-size: 22px; font-weight: bold; color: #1d4ed8; }
        .doc-title { font-size: 18px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; }
        .meta { width: 100%; margin-bottom: 24px; }
        .meta td { vertical-align: top; padding: 2px 0; }
        .meta .label { color: #6b7280; width: 120px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th { background: #f3f4f6; text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb; }
        table.lines td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .right { text-align: right; }
        .total-row td { font-weight: bold; font-size: 14px; border-top: 2px solid #1d4ed8; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; text-transform: uppercase; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fef3c7; color: #92400e; }
        .footer { margin-top: 40px; color: #9ca3af; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width:100%"><tr>
            <td class="brand">Mahadum.360</td>
            <td class="right doc-title">{{ ucfirst($invoice->type) }} Invoice</td>
        </tr></table>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Invoice #</td><td>INV-{{ str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT) }}</td>
            <td class="label">Status</td>
            <td>
                <span class="status status-{{ $invoice->status === 'paid' ? 'paid' : 'unpaid' }}">{{ $invoice->status }}</span>
            </td>
        </tr>
        <tr>
            <td class="label">Billed to</td><td>{{ $invoice->organization?->name ?? 'Organization' }}</td>
            <td class="label">Issued</td><td>{{ optional($invoice->issued_at)->format('d M Y') ?? optional($invoice->created_at)->format('d M Y') }}</td>
        </tr>
        @if ($invoice->paid_at)
        <tr>
            <td class="label">Paid</td><td>{{ $invoice->paid_at->format('d M Y') }}</td>
            <td></td><td></td>
        </tr>
        @endif
    </table>

    <table class="lines">
        <thead>
            <tr><th>Description</th><th class="right">Amount</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ ucfirst($invoice->type) }} — Mahadum.360 school licence</td>
                <td class="right">&#8358;{{ number_format($invoice->amount_minor / 100, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="right">Total</td>
                <td class="right">&#8358;{{ number_format($invoice->amount_minor / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Mahadum.360 · Generated {{ now()->format('d M Y H:i') }} · This is a computer-generated document.
    </div>
</body>
</html>
