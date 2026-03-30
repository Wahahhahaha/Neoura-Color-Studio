(() => {
    const fileInput = document.getElementById('systemlogo');
    const modal = document.querySelector('[data-crop-modal]');
    const stage = modal?.querySelector('[data-crop-stage]');
    const image = modal?.querySelector('[data-crop-image]');
    const cropBox = modal?.querySelector('[data-crop-box]');
    const zoomInput = modal?.querySelector('[data-crop-zoom]');
    const applyBtn = modal?.querySelector('[data-apply-crop]');
    const handleNodes = cropBox ? Array.from(cropBox.querySelectorAll('[data-crop-handle]')) : [];
    const closeNodes = modal ? Array.from(modal.querySelectorAll('[data-close-crop-modal]')) : [];
    const previewImage = document.querySelector('.setting-logo-preview');
    const boldColorInput = document.getElementById('system_theme_color_bold');

    if (!fileInput || !modal || !stage || !image || !cropBox || !zoomInput || !applyBtn) {
        return;
    }

    let sourceUrl = '';
    let sourceImage = null;
    let baseScale = 1;
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let action = null;
    let crop = {
        x: 0,
        y: 0,
        width: 0,
        height: 0,
    };
    const minimumCrop = 48;
    const hexColorPattern = /^#[0-9a-fA-F]{6}$/;

    const normalizeHexColor = (value, fallback = '#8a6757') => {
        const trimmed = String(value || '').trim();
        return hexColorPattern.test(trimmed) ? trimmed : fallback;
    };

    const syncCropThemeColor = () => {
        const rootBold = getComputedStyle(document.documentElement).getPropertyValue('--accent-bold');
        const fallback = normalizeHexColor(rootBold, '#8a6757');
        const nextColor = normalizeHexColor(boldColorInput?.value, fallback);
        modal.style.setProperty('--crop-accent-bold', nextColor);
    };

    const stageRect = () => stage.getBoundingClientRect();

    const displaySize = () => {
        if (!sourceImage) {
            return { width: 0, height: 0 };
        }

        const rect = stageRect();
        return {
            width: sourceImage.naturalWidth * baseScale * zoom,
            height: sourceImage.naturalHeight * baseScale * zoom,
            stageW: rect.width,
            stageH: rect.height,
        };
    };

    const clampOffsets = () => {
        const size = displaySize();
        const limitX = Math.max(0, (size.width - size.stageW) / 2);
        const limitY = Math.max(0, (size.height - size.stageH) / 2);

        offsetX = Math.min(limitX, Math.max(-limitX, offsetX));
        offsetY = Math.min(limitY, Math.max(-limitY, offsetY));
    };

    const render = () => {
        clampOffsets();
        image.style.transform = `translate(calc(-50% + ${offsetX}px), calc(-50% + ${offsetY}px)) scale(${baseScale * zoom})`;
    };

    const initializeCropBox = () => {
        const rect = stageRect();
        const side = Math.max(minimumCrop, Math.round(Math.min(rect.width, rect.height) * 0.72));
        crop.width = side;
        crop.height = side;
        crop.x = (rect.width - side) / 2;
        crop.y = (rect.height - side) / 2;
    };

    const renderCropBox = () => {
        cropBox.style.left = `${crop.x}px`;
        cropBox.style.top = `${crop.y}px`;
        cropBox.style.width = `${crop.width}px`;
        cropBox.style.height = `${crop.height}px`;
    };

    const resetFromSource = () => {
        if (!sourceImage) {
            return;
        }

        const rect = stageRect();
        baseScale = Math.max(rect.width / sourceImage.naturalWidth, rect.height / sourceImage.naturalHeight);
        zoom = 1;
        offsetX = 0;
        offsetY = 0;
        zoomInput.value = '1';
        render();
        initializeCropBox();
        renderCropBox();
    };

    const openModal = () => {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.hidden = true;
        document.body.style.overflow = '';
        action = null;
        stage.classList.remove('is-dragging');
    };

    const loadSelectedFile = () => {
        const file = fileInput.files?.[0];

        if (!file) {
            return;
        }

        if (sourceUrl) {
            URL.revokeObjectURL(sourceUrl);
        }

        sourceUrl = URL.createObjectURL(file);
        image.src = sourceUrl;

        sourceImage = new Image();
        sourceImage.onload = () => {
            resetFromSource();
        };
        sourceImage.src = sourceUrl;
    };

    fileInput.addEventListener('change', () => {
        loadSelectedFile();
        if (!fileInput.files?.[0]) {
            return;
        }
        openModal();
    });

    syncCropThemeColor();
    if (boldColorInput) {
        boldColorInput.addEventListener('input', syncCropThemeColor);
        boldColorInput.addEventListener('change', syncCropThemeColor);
    }

    closeNodes.forEach((node) => node.addEventListener('click', closeModal));

    zoomInput.addEventListener('input', () => {
        zoom = Number(zoomInput.value || '1');
        render();
    });

    const clampCropMove = () => {
        const rect = stageRect();
        crop.x = Math.max(0, Math.min(crop.x, rect.width - crop.width));
        crop.y = Math.max(0, Math.min(crop.y, rect.height - crop.height));
    };

    const resizeCrop = (handle, dx, dy) => {
        const rect = stageRect();
        const start = action?.startCrop;
        if (!start) {
            return;
        }

        if (handle === 'nw') {
            const fixedX = start.x + start.width;
            const fixedY = start.y + start.height;
            const movingX = Math.max(0, Math.min(fixedX - minimumCrop, start.x + dx));
            const movingY = Math.max(0, Math.min(fixedY - minimumCrop, start.y + dy));
            crop.x = movingX;
            crop.y = movingY;
            crop.width = fixedX - movingX;
            crop.height = fixedY - movingY;
            return;
        }

        if (handle === 'ne') {
            const fixedX = start.x;
            const fixedY = start.y + start.height;
            const movingX = Math.max(fixedX + minimumCrop, Math.min(rect.width, start.x + start.width + dx));
            const movingY = Math.max(0, Math.min(fixedY - minimumCrop, start.y + dy));
            crop.x = fixedX;
            crop.y = movingY;
            crop.width = movingX - fixedX;
            crop.height = fixedY - movingY;
            return;
        }

        if (handle === 'sw') {
            const fixedX = start.x + start.width;
            const fixedY = start.y;
            const movingX = Math.max(0, Math.min(fixedX - minimumCrop, start.x + dx));
            const movingY = Math.max(fixedY + minimumCrop, Math.min(rect.height, start.y + start.height + dy));
            crop.x = movingX;
            crop.y = fixedY;
            crop.width = fixedX - movingX;
            crop.height = movingY - fixedY;
            return;
        }

        const fixedX = start.x;
        const fixedY = start.y;
        const movingX = Math.max(fixedX + minimumCrop, Math.min(rect.width, start.x + start.width + dx));
        const movingY = Math.max(fixedY + minimumCrop, Math.min(rect.height, start.y + start.height + dy));
        crop.x = fixedX;
        crop.y = fixedY;
        crop.width = movingX - fixedX;
        crop.height = movingY - fixedY;
    };

    const startAction = (event, nextAction) => {
        action = {
            ...nextAction,
            startX: event.clientX,
            startY: event.clientY,
            startOffsetX: offsetX,
            startOffsetY: offsetY,
            startCrop: { ...crop },
        };
        stage.setPointerCapture(event.pointerId);
        stage.classList.add('is-dragging');
    };

    stage.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('[data-crop-handle]')?.getAttribute('data-crop-handle');
        if (handle) {
            event.preventDefault();
            startAction(event, { type: 'resize-crop', handle });
            return;
        }

        if (event.target.closest('[data-crop-box]')) {
            event.preventDefault();
            startAction(event, { type: 'move-crop' });
            return;
        }

        event.preventDefault();
        startAction(event, { type: 'move-image' });
    });

    stage.addEventListener('pointermove', (event) => {
        if (!action) {
            return;
        }

        const dx = event.clientX - action.startX;
        const dy = event.clientY - action.startY;

        if (action.type === 'move-image') {
            offsetX = action.startOffsetX + dx;
            offsetY = action.startOffsetY + dy;
            render();
            return;
        }

        if (action.type === 'move-crop') {
            crop.x = action.startCrop.x + dx;
            crop.y = action.startCrop.y + dy;
            clampCropMove();
            renderCropBox();
            return;
        }

        if (action.type === 'resize-crop') {
            resizeCrop(action.handle, dx, dy);
            renderCropBox();
        }
    });

    const endAction = (event) => {
        if (!action) {
            return;
        }

        if (event.pointerId !== undefined) {
            stage.releasePointerCapture(event.pointerId);
        }
        action = null;
        stage.classList.remove('is-dragging');
    };

    stage.addEventListener('pointerup', endAction);
    stage.addEventListener('pointercancel', endAction);
    stage.addEventListener('pointerleave', endAction);

    applyBtn.addEventListener('click', () => {
        if (!sourceImage) {
            return;
        }

        const size = displaySize();
        const scale = baseScale * zoom;
        if (scale <= 0) {
            return;
        }

        const imageLeft = (size.stageW - size.width) / 2 + offsetX;
        const imageTop = (size.stageH - size.height) / 2 + offsetY;

        const sourceX = (crop.x - imageLeft) / scale;
        const sourceY = (crop.y - imageTop) / scale;
        const sourceW = crop.width / scale;
        const sourceH = crop.height / scale;

        const clippedX = Math.max(0, sourceX);
        const clippedY = Math.max(0, sourceY);
        const clippedW = Math.min(sourceImage.naturalWidth - clippedX, sourceW);
        const clippedH = Math.min(sourceImage.naturalHeight - clippedY, sourceH);

        if (clippedW <= 0 || clippedH <= 0) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(clippedW));
        canvas.height = Math.max(1, Math.round(clippedH));
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        ctx.drawImage(sourceImage, clippedX, clippedY, clippedW, clippedH, 0, 0, canvas.width, canvas.height);

        canvas.toBlob((blob) => {
            if (!blob) {
                return;
            }

            const croppedFile = new File([blob], `logo-cropped-${Date.now()}.png`, { type: 'image/png' });
            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            fileInput.files = transfer.files;

            if (previewImage) {
                const previewUrl = URL.createObjectURL(blob);
                previewImage.src = previewUrl;
            }

            loadSelectedFile();
            closeModal();
        }, 'image/png');
    });

    handleNodes.forEach((node) => {
        node.addEventListener('dragstart', (event) => event.preventDefault());
    });
})();

(() => {
    const bankList = document.querySelector('[data-bank-list]');
    const addBankBtn = document.querySelector('[data-add-bank-row]');
    const bankTemplate = document.querySelector('template[data-bank-row-template]');

    if (!bankList || !addBankBtn || !bankTemplate) {
        return;
    }

    const syncRemoveButtons = () => {
        const rows = Array.from(bankList.querySelectorAll('[data-bank-row]'));
        rows.forEach((row) => {
            const removeBtn = row.querySelector('[data-remove-bank-row]');
            if (removeBtn) {
                removeBtn.disabled = rows.length <= 1;
            }
        });
    };

    addBankBtn.addEventListener('click', () => {
        const fragment = bankTemplate.content.cloneNode(true);
        bankList.appendChild(fragment);
        syncRemoveButtons();
    });

    bankList.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('[data-remove-bank-row]');
        if (!removeBtn) {
            return;
        }

        const rows = Array.from(bankList.querySelectorAll('[data-bank-row]'));
        if (rows.length <= 1) {
            return;
        }

        removeBtn.closest('[data-bank-row]')?.remove();
        syncRemoveButtons();
    });

    syncRemoveButtons();
})();
