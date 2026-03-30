(() => {
    const toggleButtons = Array.from(document.querySelectorAll('[data-toggle-password]'));
    if (!toggleButtons.length) {
        return;
    }

    toggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetSelector = button.getAttribute('data-target') || '';
            const input = targetSelector ? document.querySelector(targetSelector) : null;
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const nextType = input.type === 'password' ? 'text' : 'password';
            input.type = nextType;

            const isVisible = nextType === 'text';
            button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
            button.setAttribute('title', isVisible ? 'Hide password' : 'Show password');
        });
    });

    const loginForm = document.querySelector('[data-login-form]');
    if (!loginForm) {
        return;
    }

    const latitudeInput = loginForm.querySelector('[data-login-latitude]');
    const longitudeInput = loginForm.querySelector('[data-login-longitude]');
    let submittingWithGeoResolved = false;

    loginForm.addEventListener('submit', (event) => {
        if (submittingWithGeoResolved) {
            return;
        }

        const continueSubmit = () => {
            submittingWithGeoResolved = true;
            loginForm.submit();
        };

        if (!('geolocation' in navigator)) {
            return;
        }

        event.preventDefault();
        navigator.geolocation.getCurrentPosition((position) => {
            if (latitudeInput) {
                latitudeInput.value = String(position?.coords?.latitude ?? '');
            }
            if (longitudeInput) {
                longitudeInput.value = String(position?.coords?.longitude ?? '');
            }
            continueSubmit();
        }, () => {
            continueSubmit();
        }, {
            enableHighAccuracy: false,
            timeout: 8000,
            maximumAge: 0,
        });
    });
})();
