(function () {
    'use strict';

    let installPrompt;
    const buttons = Array.from(document.querySelectorAll('[data-install-app]'));
    if (!buttons.length) return;

    const hideButtons = () => {
        buttons.forEach((button) => button.classList.add('d-none'));
        document.querySelectorAll('[data-install-app-item]').forEach((item) => item.classList.add('d-none'));
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        buttons.forEach((button) => button.classList.remove('d-none'));
        document.querySelectorAll('[data-install-app-item]').forEach((item) => item.classList.remove('d-none'));
    });

    buttons.forEach((button) => button.addEventListener('click', async () => {
        if (!installPrompt) return;
        const prompt = installPrompt;
        installPrompt = null;
        hideButtons();
        await prompt.prompt();
    }));

    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        hideButtons();
    });
})();
