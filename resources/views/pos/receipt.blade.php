<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ใบเสร็จ #{{ $sale->id }}</title>
    <style>
        /* กระดาษใบเสร็จความร้อน 58 มม. (พื้นที่พิมพ์ราว 48 มม.) */
        @page { size: 58mm auto; margin: 2mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; width: 54mm; font-family: 'Sarabun', 'Tahoma', sans-serif; font-size: 12px; line-height: 1.35; color: #000; }
        h1 { font-size: 14px; margin: 0; text-align: center; }
        .c { text-align: center; }
        .r { text-align: right; white-space: nowrap; }
        hr { border: 0; border-top: 1px dashed #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .big { font-size: 15px; font-weight: 700; }
        .void { border: 2px solid #000; padding: 2px; text-align: center; font-weight: 700; margin: 4px 0; }
        .actions { margin: 10px 0; text-align: center; }
        .actions button { font: inherit; padding: 6px 14px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <h1>{{ $sale->shop->name }}</h1>
    <div class="c">{{ school('school_name') }}</div>
    <hr>
    <div>เลขที่ {{ str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT) }} · {{ $sale->created_at->format('d/m/') }}{{ $sale->created_at->year + 543 }} {{ $sale->created_at->format('H:i') }}</div>
    <div>{{ $sale->wallet->student->fullName() }}{{ $sale->wallet->student->classroom ? ' ('.$sale->wallet->student->classroom->name().')' : '' }}</div>
    @if ($sale->voided_at)<div class="void">ยกเลิกแล้ว</div>@endif
    <hr>
    <table>
        @foreach ($sale->items as $item)
            <tr><td>{{ $item['name'] }}@if ($item['qty'] > 1)<br>&nbsp;&nbsp;{{ $item['qty'] }} × {{ baht($item['price']) }}@endif</td><td class="r">{{ baht($item['price'] * $item['qty']) }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr class="big"><td>รวม</td><td class="r">{{ baht($sale->total) }}</td></tr>
        <tr><td>ชำระด้วยกระเป๋าเงิน</td><td class="r"></td></tr>
        <tr><td>คงเหลือหลังซื้อ</td><td class="r">{{ baht($balance) }}</td></tr>
    </table>
    <hr>
    <div class="c">ผู้ขาย: {{ $sale->cashier?->name ?? '-' }}</div>
    <div class="c">ขอบคุณครับ/ค่ะ</div>
    <div class="actions"><button type="button" onclick="window.print()">พิมพ์</button> <button type="button" onclick="window.close()">ปิด</button></div>
    <script>window.addEventListener('load', () => { window.print(); });</script>
</body>
</html>
