function initializeProcessingForms(root = document) {
    root.querySelectorAll('[data-processing-form]').forEach((form) => {
        if (form.dataset.processingBound === 'true') return;
        form.dataset.processingBound = 'true';
        form.addEventListener('submit', () => {
        const button = form.querySelector('[data-processing-button]');
        const message = form.querySelector('[data-processing-message]');
        const idleLabel = form.querySelector('[data-idle-label]');
        const loadingLabel = form.querySelector('[data-loading-label]');

        if (button instanceof HTMLButtonElement) {
            button.disabled = true;
        }

        message?.classList.remove('d-none');
        idleLabel?.classList.add('d-none');
        loadingLabel?.classList.remove('d-none');
        });
    });
}

function initializeFilePreviews(root = document) {
    root.querySelectorAll('input[type="file"][data-preview-target], input[type="file"]#attachments').forEach((input) => {
        if (input.dataset.previewBound === 'true') return;
        input.dataset.previewBound = 'true';
        const target = input.dataset.previewTarget
            ? document.getElementById(input.dataset.previewTarget)
            : document.getElementById('attachment-preview');
        if (!target) return;

        input.addEventListener('change', () => {
            target.replaceChildren();
            [...input.files].forEach((file) => {
                const item = document.createElement('div');
                item.className = 'cp-upload-preview-item';
                const media = file.type.startsWith('video/') ? document.createElement('video') : document.createElement('img');
                media.src = URL.createObjectURL(file);
                media.className = 'cp-upload-preview-media';
                if (media instanceof HTMLVideoElement) {
                    media.controls = true;
                    media.muted = true;
                    media.preload = 'metadata';
                }
                const caption = document.createElement('span');
                caption.textContent = file.name;
                item.append(media, caption);
                target.appendChild(item);
            });
        });
    });
}

initializeProcessingForms();
initializeFilePreviews();
window.addEventListener('crisispulse:navigated', (event) => initializeProcessingForms(event.detail?.content || document));
window.addEventListener('crisispulse:navigated', (event) => initializeFilePreviews(event.detail?.content || document));
