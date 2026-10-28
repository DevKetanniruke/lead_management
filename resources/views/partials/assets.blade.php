{{-- No-build frontend: Tailwind (Play CDN) + Alpine + Inter, all from CDNs. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
            },
        },
    };
</script>
<style>
    [x-cloak] { display: none !important; }
    body { font-feature-settings: 'cv11', 'ss01'; -webkit-font-smoothing: antialiased; }

    /* Hide scrollbar while preserving scroll functionality */
    .no-scrollbar::-webkit-scrollbar,
    aside nav::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
    }
    .no-scrollbar,
    aside nav {
        -ms-overflow-style: none; /* IE and legacy Edge */
        scrollbar-width: none;    /* Firefox and modern Chrome/Edge */
    }
</style>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
