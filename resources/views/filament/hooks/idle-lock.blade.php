{{-- Sends the browser to the lock screen after the idle minutes in Settings without a click, key or touch. LockIdleScreen enforces the same limit on the server. --}}
<script>
    (() => {
        const lockAfterMs = {{ $lockAfterMinutes * 60 * 1000 }};
        let timer;

        const restart = () => {
            clearTimeout(timer);
            timer = setTimeout(() => window.location.assign(@js($unlockUrl)), lockAfterMs);
        };

        ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach((name) => window.addEventListener(name, restart, { passive: true }));

        restart();
    })();
</script>
