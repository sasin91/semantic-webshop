// Embeds products batch by batch through admin/embed and shows the progress.
const form = document.getElementById('embed-form');

if (form) {
    const progress = document.getElementById('embed-progress');
    const message = document.getElementById('embed-message');
    const total = Number(form.dataset.total);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const all = event.submitter.value;
        const buttons = form.querySelectorAll('button');
        buttons.forEach((button) => button.disabled = true);

        let afterId = 0;
        let embedded = 0;
        const started = performance.now();
        if (all === '1') progress.value = 0;

        try {
            while (true) {
                const body = new FormData(form);
                body.set('all', all);
                body.set('after_id', afterId);
                const response = await fetch(form.action, { method: 'POST', body });
                const result = await response.json();
                if (result.error) throw new Error(result.error);

                embedded += result.embedded;
                afterId = result.last_id;
                progress.value = all === '1' ? embedded : total - result.pending;
                message.textContent = `Embedded ${embedded} products…`;
                if (result.done) break;
            }
            const seconds = ((performance.now() - started) / 1000).toFixed(1);
            message.textContent = `Done: embedded ${embedded} products in ${seconds} s.`;
            document.getElementById('embed-current').textContent = progress.value;
        } catch (error) {
            message.textContent = `Stopped: ${error.message}. Run it again to continue where it stopped.`;
        } finally {
            buttons.forEach((button) => button.disabled = false);
        }
    });
}
