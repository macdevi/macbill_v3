<details>
    <summary class="iv-cancel-heading">{{ $label }}</summary>
    <form class="iv-form" method="POST" action="{{ $action }}">
        @csrf
        <label for="void-{{ $id }}">Alasan pembatalan</label>
        <textarea id="void-{{ $id }}" name="reason" rows="3" minlength="5" maxlength="1000" required></textarea>
        <label class="iv-confirm">
            <input type="checkbox" name="confirmed" value="1" required>
            <span>Saya memastikan ini perlu dibatalkan. Riwayatnya tetap disimpan.</span>
        </label>
        <button class="cu-button" type="submit">Konfirmasi pembatalan</button>
    </form>
</details>
