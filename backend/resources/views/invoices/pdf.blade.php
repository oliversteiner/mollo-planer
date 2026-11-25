{{-- Letter pages of an invoice (Auftrag 39 C). Everything printed is escaped; no HTML of users. --}}
<style>
    @page { margin-top: {{ $layout->topFollowing }}mm; }
    @page :first { margin-top: {{ $layout->topFirst }}mm; }
    body { font-size: 10pt; line-height: 1.4; color: #1a1a1a; }
    .address { position: absolute; left: {{ $layout->addressLeft }}mm; top: {{ $layout->addressTop }}mm; width: {{ $layout->addressWidth }}mm; height: {{ $layout->addressHeight }}mm; overflow: hidden; font-size: 10pt; line-height: 1.3; }
    .sender-line { font-size: 7pt; color: #555; border-bottom: 0.3pt solid #999; padding-bottom: 1pt; margin-bottom: 2mm; }
    h1 { font-size: 16pt; margin: 0 0 3mm; }
    .facts { width: 100%; border-collapse: collapse; margin-bottom: 6mm; font-size: 9.5pt; }
    .facts td { padding: 0.6mm 0; vertical-align: top; }
    .facts td.label { width: 38mm; color: #555; }
    .items { width: 100%; border-collapse: collapse; }
    .items th { text-align: left; font-size: 9pt; color: #555; border-bottom: 0.5pt solid #999; padding: 1.5mm 0; }
    .items th.amount, .items td.amount { text-align: right; width: 32mm; white-space: nowrap; }
    .items td { padding: 1.8mm 0; vertical-align: top; border-bottom: 0.3pt solid #ddd; }
    .items tr.project td { font-weight: bold; padding-top: 4mm; border-bottom: 0.5pt solid #999; }
    .items .description { color: #444; font-size: 9pt; margin-top: 0.8mm; }
    .items tr.total td { font-weight: bold; font-size: 11pt; border-top: 1pt solid #1a1a1a; border-bottom: 0; padding-top: 2.5mm; }
    .terms { margin-top: 8mm; font-size: 9.5pt; }
    .notes { margin-top: 4mm; font-size: 9.5pt; }
</style>

<div class="address">
    <div class="sender-line">{{ collect([$sheet->sender['name'] ?? null, trim(($sheet->sender['street'] ?? '').' '.($sheet->sender['houseNumber'] ?? '')), trim(($sheet->sender['postalCode'] ?? '').' '.($sheet->sender['city'] ?? ''))])->filter()->implode(' · ') }}</div>
    @if (filled($sheet->recipient['company'] ?? null))
        <div>{{ $sheet->recipient['company'] }}</div>
    @endif
    <div>{{ $sheet->recipient['name'] }}</div>
    <div>{{ trim($sheet->recipient['street'].' '.($sheet->recipient['houseNumber'] ?? '')) }}</div>
    <div>{{ ($sheet->recipient['country'] !== 'CH' ? $sheet->recipient['country'].'-' : '').$sheet->recipient['postalCode'] }} {{ $sheet->recipient['city'] }}</div>
</div>

<h1>{{ $sheet->number !== null ? $texts('title_number', ['number' => $sheet->number]) : $texts('title') }}</h1>

<table class="facts">
    <tr><td class="label">{{ $texts('issue_date') }}</td><td>{{ $date($sheet->issueDate) }}</td></tr>
    <tr><td class="label">{{ $texts('due_date') }}</td><td>{{ $date($sheet->dueDate) }}</td></tr>
    @if ($reference !== null)
        <tr><td class="label">{{ $texts('reference') }}</td><td>{{ $reference }}</td></tr>
    @endif
    @if (filled($sheet->sender['uid'] ?? null))
        <tr><td class="label">{{ $texts('uid') }}</td><td>{{ $sheet->sender['uid'] }}</td></tr>
    @endif
</table>

<table class="items">
    <thead>
        <tr><th>{{ $texts('item') }}</th><th class="amount">{{ $texts('amount') }}</th></tr>
    </thead>
    <tbody>
        @foreach ($sheet->groups() as $group)
            <tr class="project"><td colspan="2">{{ $group['projectName'] }}</td></tr>
            @foreach ($group['items'] as $item)
                <tr>
                    <td>
                        <div>{{ $item['title'] }}</div>
                        @if (filled($item['description']))
                            <div class="description">{{ $item['description'] }}</div>
                        @endif
                    </td>
                    <td class="amount">{{ $money($item['amountCents']) }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr class="total"><td>{{ $texts('total') }}</td><td class="amount">CHF {{ $money($sheet->totalCents) }}</td></tr>
    </tbody>
</table>

<div class="terms">{{ $texts('terms', ['days' => $sheet->paymentTermsDays, 'date' => $date($sheet->dueDate)]) }} {{ $texts('no_vat') }}</div>
@if (filled($sheet->notes))
    <div class="notes">{!! nl2br(e($sheet->notes)) !!}</div>
@endif
