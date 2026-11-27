@if(in_array($invoice->status, ['unpaid', 'partial'], true))
    <button type="button" class="cu-button cu-primary" data-iv-pay
        data-customer="{{ $invoice->customer_id }}"
        data-invoice="{{ $invoice->id }}"
        data-name="{{ $invoice->customer_name }}">Bayar</button>
@endif

@if($invoice->status === 'paid')
    <button type="button"
        class="cu-button cu-primary"
        data-iv-notes="{{ route('invoices.notes', $invoice) }}">Nota</button>
@endif

@if(in_array($invoice->status, ['unpaid', 'partial'], true))
    @php($wa = \App\Services\WhatsAppMessage::build($invoice, $waTemplate ?? null))
    <button type="button" class="cu-button" data-iv-wa
        data-name="{{ $invoice->customer_name }}"
        data-phone="{{ $wa['phone'] }}"
        data-url="{{ $wa['url'] }}"
        data-message="{{ $wa['message'] }}">Kirim tagihan</button>
@endif
