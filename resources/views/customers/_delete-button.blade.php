<button type="button" class="cu-button cu-danger" data-cu-delete
    data-url="{{ route('customers.destroy', $customer) }}"
    data-name="{{ $customer->name }}"
    data-username="{{ $customer->pppoe_username }}"
    data-type="{{ $customer->service_type }}">
    Hapus
</button>
