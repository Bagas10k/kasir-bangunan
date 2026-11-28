<div class="hc-search-area">
    <div class="hc-search-label">
        <label for="hc-search">Cari di panduan</label>
        <x-ui.info-tip :dark="$isHome ?? false" placement="bottom" label="Cara memakai pencarian" text="Saran muncul saat mengetik. Pilih dengan panah atas atau bawah, lalu tekan Enter. Tekan / atau Ctrl+K untuk langsung ke kolom cari." />
    </div>
    <form class="hc-search" role="search" action="{{ route('help.index') }}" method="get">
        <input id="hc-search" type="search" name="q" maxlength="100" autocomplete="off" placeholder="Contoh: nota atau bukti transfer" value="{{ $query }}">
        <button class="hc-button" type="submit">Cari</button>
        <ul id="hc-suggest" class="hc-suggest" role="listbox" aria-label="Saran panduan" hidden></ul>
    </form>
</div>
