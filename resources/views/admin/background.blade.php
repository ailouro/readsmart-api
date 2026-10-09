{{-- Admin pages background (San Vicente Elementary School watermark).
     Image lives at public/images/admin-bg.png. !important is used so it wins
     over any plain `body { background: ... }` already in the layout. --}}
<style>
    html { min-height: 100%; }
    body {
        background-color: #f8faf6 !important;
        background-image: url('{{ asset('images/admin-bg.png') }}') !important;
        background-repeat: no-repeat !important;
        background-position: center !important;
        background-size: cover !important;
        background-attachment: fixed !important;
    }
    @media print {
        body { background: #fff !important; }
    }
</style>