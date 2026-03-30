(() => {
    const link = document.querySelector('[data-success-redirect-link]');
    const countdownNode = document.querySelector('[data-redirect-countdown]');
    const progressNode = document.querySelector('[data-redirect-progress]');
    const redirectUrl = link?.getAttribute('data-redirect-url') || '';
    const rawSeconds = Number(link?.getAttribute('data-redirect-seconds') || '5');
    const totalSeconds = Number.isFinite(rawSeconds) ? Math.max(1, Math.trunc(rawSeconds)) : 5;
    let seconds = totalSeconds;

    if (!redirectUrl) {
        return;
    }

    if (countdownNode) {
        countdownNode.textContent = String(seconds);
    }
    if (progressNode) {
        progressNode.style.transform = 'scaleX(1)';
    }

    const timer = window.setInterval(() => {
        seconds -= 1;
        if (countdownNode) {
            countdownNode.textContent = String(Math.max(0, seconds));
        }
        if (progressNode) {
            const progress = Math.max(0, seconds) / totalSeconds;
            progressNode.style.transform = `scaleX(${progress})`;
        }
        if (seconds <= 0) {
            window.clearInterval(timer);
            window.location.href = redirectUrl;
        }
    }, 1000);
})();
