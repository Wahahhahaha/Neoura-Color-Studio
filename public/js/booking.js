(() => {
    const bookingConfirmModal = document.querySelector('[data-booking-confirm-modal]');
    const bookingConfirmDialog = bookingConfirmModal?.querySelector('.booking-confirm-dialog');
    const closeConfirmNodes = bookingConfirmModal ? Array.from(bookingConfirmModal.querySelectorAll('[data-close-booking-confirm]')) : [];
    const submitConfirmBtn = bookingConfirmModal?.querySelector('[data-submit-booking-confirm]');
    const confirmName = bookingConfirmModal?.querySelector('[data-confirm-name]');
    const confirmPhone = bookingConfirmModal?.querySelector('[data-confirm-phone]');
    const confirmEmail = bookingConfirmModal?.querySelector('[data-confirm-email]');
    const confirmDate = bookingConfirmModal?.querySelector('[data-confirm-date]');
    const confirmTime = bookingConfirmModal?.querySelector('[data-confirm-time]');
    const confirmBank = bookingConfirmModal?.querySelector('[data-confirm-bank]');

    let isConfirmedSubmit = false;
    let confirmCloseTimer = null;

    const openBookingConfirmModal = () => {
        if (!bookingConfirmModal) {
            return;
        }
        bookingConfirmModal.hidden = false;
        bookingConfirmModal.classList.remove('is-enter');
        window.requestAnimationFrame(() => bookingConfirmModal.classList.add('is-enter'));
        document.body.style.overflow = 'hidden';
        submitConfirmBtn?.focus();
    };

    const closeBookingConfirmModal = () => {
        if (!bookingConfirmModal) {
            return;
        }
        bookingConfirmModal.classList.remove('is-enter');
        if (confirmCloseTimer) {
            window.clearTimeout(confirmCloseTimer);
        }
        confirmCloseTimer = window.setTimeout(() => {
            bookingConfirmModal.hidden = true;
            if (!document.querySelector('[data-booking-success-modal]:not([hidden])')) {
                document.body.style.overflow = '';
            }
        }, 220);
    };

    const bookingSuccessModal = document.querySelector('[data-booking-success-modal]');
    if (bookingSuccessModal) {
        const closeSuccessNodes = Array.from(bookingSuccessModal.querySelectorAll('[data-close-booking-success]'));
        let closeTimer = null;

        const closeSuccessModal = () => {
            bookingSuccessModal.classList.remove('is-enter');
            if (closeTimer) {
                window.clearTimeout(closeTimer);
            }
            closeTimer = window.setTimeout(() => {
                bookingSuccessModal.hidden = true;
                document.body.style.overflow = '';
            }, 220);
        };

        if (!bookingSuccessModal.hidden) {
            document.body.style.overflow = 'hidden';
            bookingSuccessModal.classList.remove('is-enter');
            window.requestAnimationFrame(() => bookingSuccessModal.classList.add('is-enter'));
        }

        closeSuccessNodes.forEach((node) => node.addEventListener('click', closeSuccessModal));
    }

    const form = document.getElementById('bookingForm');
    if (!form) {
        return;
    }

    const message = document.getElementById('formMessage');
    const dateInput = document.getElementById('booking_date');
    const timeSelect = document.getElementById('time_slot');
    const bankSelect = document.getElementById('payment_bank');
    const proofInput = document.getElementById('payment_proof');
    const durationMinutes = Number(form.dataset.durationMinutes || '60');
    const msgTimeFull = form.dataset.msgTimeFull || 'Selected time is already full. Please choose another time.';
    const msgRequired = form.dataset.msgRequired || 'Please complete all required fields before submitting.';
    const msgInvalidEmail = form.dataset.msgInvalidEmail || 'Please enter a valid email address.';
    const msgInvalidPhone = form.dataset.msgInvalidPhone || 'Please enter a valid phone number.';
    const labelFull = form.dataset.labelFull || '(Full)';
    const schedule = (() => {
        try {
            return JSON.parse(form.dataset.schedule || '{}');
        } catch (error) {
            return {};
        }
    })();
    const bookingRecords = Array.isArray(schedule.records) ? schedule.records : [];

    const normalizeTime = (value) => {
        const match = /^(\d{1,2}):(\d{2})$/.exec((value || '').trim());
        if (!match) {
            return '';
        }

        const hours = Number(match[1]);
        const minutes = Number(match[2]);
        if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
            return '';
        }

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    };

    const toMinutes = (hhmm) => {
        const normalized = normalizeTime(hhmm);
        if (!normalized) {
            return -1;
        }

        const [hours, minutes] = normalized.split(':').map(Number);
        return (hours * 60) + minutes;
    };

    const toHm = (minutes) => {
        if (!Number.isFinite(minutes) || minutes < 0) {
            return '';
        }

        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${String(hours).padStart(2, '0')}.${String(mins).padStart(2, '0')}`;
    };

    const toRangeLabel = (startMinutes, duration) => {
        if (!Number.isFinite(startMinutes) || !Number.isFinite(duration)) {
            return '';
        }

        const endMinutes = startMinutes + duration;
        const startLabel = toHm(startMinutes);
        const endLabel = toHm(endMinutes);
        if (!startLabel || !endLabel) {
            return '';
        }

        return `${startLabel}-${endLabel}`;
    };

    const openMinutes = toMinutes(schedule.open || '10:00');
    const closeMinutes = toMinutes(schedule.close || '22:00');
    const overlaps = (startA, endA, startB, endB) => startA < endB && startB < endA;

    const bookingCropModal = document.querySelector('[data-booking-crop-modal]');
    const bookingCropStage = bookingCropModal?.querySelector('[data-booking-crop-stage]');
    const bookingCropImage = bookingCropModal?.querySelector('[data-booking-crop-image]');
    const bookingCropBox = bookingCropModal?.querySelector('[data-booking-crop-box]');
    const bookingCropZoom = bookingCropModal?.querySelector('[data-booking-crop-zoom]');
    const bookingCropApplyBtn = bookingCropModal?.querySelector('[data-apply-booking-crop]');
    const bookingCropCloseNodes = bookingCropModal ? Array.from(bookingCropModal.querySelectorAll('[data-close-booking-crop]')) : [];
    const bookingCropHandles = bookingCropBox ? Array.from(bookingCropBox.querySelectorAll('[data-booking-crop-handle]')) : [];

    let cropSourceUrl = '';
    let cropSourceImage = null;
    let cropBaseScale = 1;
    let cropZoom = 1;
    let cropOffsetX = 0;
    let cropOffsetY = 0;
    let cropAction = null;
    let cropCloseTimer = null;
    let isApplyingCrop = false;
    let cropSelection = { x: 0, y: 0, width: 0, height: 0 };
    const minCropSize = 48;

    const anyPersistentModalOpen = () => Boolean(
        document.querySelector('[data-booking-success-modal]:not([hidden])')
        || document.querySelector('[data-booking-confirm-modal]:not([hidden])')
    );

    const closeBookingCropModal = () => {
        if (!bookingCropModal) {
            return;
        }
        bookingCropModal.classList.remove('is-enter');
        if (cropCloseTimer) {
            window.clearTimeout(cropCloseTimer);
        }
        cropCloseTimer = window.setTimeout(() => {
            bookingCropModal.hidden = true;
            if (!anyPersistentModalOpen()) {
                document.body.style.overflow = '';
            }
        }, 220);
        cropAction = null;
        bookingCropStage?.classList.remove('is-dragging');
    };

    const openBookingCropModal = () => {
        if (!bookingCropModal) {
            return;
        }
        bookingCropModal.hidden = false;
        bookingCropModal.classList.remove('is-enter');
        window.requestAnimationFrame(() => bookingCropModal.classList.add('is-enter'));
        document.body.style.overflow = 'hidden';
    };

    const cropStageRect = () => bookingCropStage.getBoundingClientRect();
    const cropDisplaySize = () => {
        if (!cropSourceImage) {
            return { width: 0, height: 0, stageW: 0, stageH: 0 };
        }

        const rect = cropStageRect();
        return {
            width: cropSourceImage.naturalWidth * cropBaseScale * cropZoom,
            height: cropSourceImage.naturalHeight * cropBaseScale * cropZoom,
            stageW: rect.width,
            stageH: rect.height,
        };
    };

    const clampCropOffsets = () => {
        const size = cropDisplaySize();
        const limitX = Math.max(0, (size.width - size.stageW) / 2);
        const limitY = Math.max(0, (size.height - size.stageH) / 2);
        cropOffsetX = Math.min(limitX, Math.max(-limitX, cropOffsetX));
        cropOffsetY = Math.min(limitY, Math.max(-limitY, cropOffsetY));
    };

    const renderCropImage = () => {
        if (!bookingCropImage) {
            return;
        }
        clampCropOffsets();
        bookingCropImage.style.transform = `translate(calc(-50% + ${cropOffsetX}px), calc(-50% + ${cropOffsetY}px)) scale(${cropBaseScale * cropZoom})`;
    };

    const initCropBox = () => {
        const rect = cropStageRect();
        const side = Math.max(minCropSize, Math.round(Math.min(rect.width, rect.height) * 0.72));
        cropSelection.width = side;
        cropSelection.height = side;
        cropSelection.x = (rect.width - side) / 2;
        cropSelection.y = (rect.height - side) / 2;
    };

    const renderCropBox = () => {
        if (!bookingCropBox) {
            return;
        }
        bookingCropBox.style.left = `${cropSelection.x}px`;
        bookingCropBox.style.top = `${cropSelection.y}px`;
        bookingCropBox.style.width = `${cropSelection.width}px`;
        bookingCropBox.style.height = `${cropSelection.height}px`;
    };

    const resetCropState = () => {
        if (!cropSourceImage || !bookingCropZoom) {
            return false;
        }
        const rect = cropStageRect();
        if (rect.width < 10 || rect.height < 10) {
            return false;
        }
        cropBaseScale = Math.max(rect.width / cropSourceImage.naturalWidth, rect.height / cropSourceImage.naturalHeight);
        cropZoom = 1;
        cropOffsetX = 0;
        cropOffsetY = 0;
        bookingCropZoom.value = '1';
        renderCropImage();
        initCropBox();
        renderCropBox();
        return true;
    };

    const clampCropMove = () => {
        const rect = cropStageRect();
        cropSelection.x = Math.max(0, Math.min(cropSelection.x, rect.width - cropSelection.width));
        cropSelection.y = Math.max(0, Math.min(cropSelection.y, rect.height - cropSelection.height));
    };

    const resizeCropSelection = (handle, dx, dy) => {
        const rect = cropStageRect();
        const start = cropAction?.startCrop;
        if (!start) {
            return;
        }

        if (handle === 'nw') {
            const fixedX = start.x + start.width;
            const fixedY = start.y + start.height;
            const movingX = Math.max(0, Math.min(fixedX - minCropSize, start.x + dx));
            const movingY = Math.max(0, Math.min(fixedY - minCropSize, start.y + dy));
            cropSelection.x = movingX;
            cropSelection.y = movingY;
            cropSelection.width = fixedX - movingX;
            cropSelection.height = fixedY - movingY;
            return;
        }

        if (handle === 'ne') {
            const fixedX = start.x;
            const fixedY = start.y + start.height;
            const movingX = Math.max(fixedX + minCropSize, Math.min(rect.width, start.x + start.width + dx));
            const movingY = Math.max(0, Math.min(fixedY - minCropSize, start.y + dy));
            cropSelection.x = fixedX;
            cropSelection.y = movingY;
            cropSelection.width = movingX - fixedX;
            cropSelection.height = fixedY - movingY;
            return;
        }

        if (handle === 'sw') {
            const fixedX = start.x + start.width;
            const fixedY = start.y;
            const movingX = Math.max(0, Math.min(fixedX - minCropSize, start.x + dx));
            const movingY = Math.max(fixedY + minCropSize, Math.min(rect.height, start.y + start.height + dy));
            cropSelection.x = movingX;
            cropSelection.y = fixedY;
            cropSelection.width = fixedX - movingX;
            cropSelection.height = movingY - fixedY;
            return;
        }

        const fixedX = start.x;
        const fixedY = start.y;
        const movingX = Math.max(fixedX + minCropSize, Math.min(rect.width, start.x + start.width + dx));
        const movingY = Math.max(fixedY + minCropSize, Math.min(rect.height, start.y + start.height + dy));
        cropSelection.x = fixedX;
        cropSelection.y = fixedY;
        cropSelection.width = movingX - fixedX;
        cropSelection.height = movingY - fixedY;
    };

    const startCropAction = (event, nextAction) => {
        cropAction = {
            ...nextAction,
            startX: event.clientX,
            startY: event.clientY,
            startOffsetX: cropOffsetX,
            startOffsetY: cropOffsetY,
            startCrop: { ...cropSelection },
        };
        bookingCropStage.setPointerCapture(event.pointerId);
        bookingCropStage.classList.add('is-dragging');
    };

    const applyCroppedImage = () => {
        if (!cropSourceImage || !proofInput) {
            return;
        }

        const size = cropDisplaySize();
        const scale = cropBaseScale * cropZoom;
        if (scale <= 0) {
            return;
        }

        const imageLeft = (size.stageW - size.width) / 2 + cropOffsetX;
        const imageTop = (size.stageH - size.height) / 2 + cropOffsetY;

        const sourceX = (cropSelection.x - imageLeft) / scale;
        const sourceY = (cropSelection.y - imageTop) / scale;
        const sourceW = cropSelection.width / scale;
        const sourceH = cropSelection.height / scale;

        const clippedX = Math.max(0, sourceX);
        const clippedY = Math.max(0, sourceY);
        const clippedW = Math.min(cropSourceImage.naturalWidth - clippedX, sourceW);
        const clippedH = Math.min(cropSourceImage.naturalHeight - clippedY, sourceH);

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

        ctx.drawImage(cropSourceImage, clippedX, clippedY, clippedW, clippedH, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) {
                return;
            }

            const croppedFile = new File([blob], `proof-cropped-${Date.now()}.png`, { type: 'image/png' });
            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            proofInput.files = transfer.files;
            closeBookingCropModal();
        }, 'image/png');
    };

    if (proofInput && bookingCropModal && bookingCropStage && bookingCropImage && bookingCropBox && bookingCropZoom && bookingCropApplyBtn) {
        proofInput.addEventListener('change', () => {
            if (isApplyingCrop) {
                isApplyingCrop = false;
                return;
            }

            const file = proofInput.files?.[0];
            if (!file) {
                return;
            }

            const isImage = /^image\//i.test(file.type);
            if (!isImage) {
                return;
            }

            if (cropSourceUrl) {
                URL.revokeObjectURL(cropSourceUrl);
            }
            cropSourceUrl = URL.createObjectURL(file);
            bookingCropImage.src = cropSourceUrl;

            cropSourceImage = new Image();
            cropSourceImage.onload = () => {
                openBookingCropModal();
                const tryRenderCrop = (attempt = 0) => {
                    if (resetCropState()) {
                        return;
                    }
                    if (attempt < 8) {
                        window.requestAnimationFrame(() => tryRenderCrop(attempt + 1));
                    }
                };
                window.requestAnimationFrame(() => tryRenderCrop());
            };
            cropSourceImage.src = cropSourceUrl;
        });

        bookingCropCloseNodes.forEach((node) => node.addEventListener('click', closeBookingCropModal));
        bookingCropZoom.addEventListener('input', () => {
            cropZoom = Number(bookingCropZoom.value || '1');
            renderCropImage();
        });

        bookingCropStage.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest('[data-booking-crop-handle]')?.getAttribute('data-booking-crop-handle');
            if (handle) {
                event.preventDefault();
                startCropAction(event, { type: 'resize-crop', handle });
                return;
            }

            if (event.target.closest('[data-booking-crop-box]')) {
                event.preventDefault();
                startCropAction(event, { type: 'move-crop' });
                return;
            }

            event.preventDefault();
            startCropAction(event, { type: 'move-image' });
        });

        bookingCropStage.addEventListener('pointermove', (event) => {
            if (!cropAction) {
                return;
            }

            const dx = event.clientX - cropAction.startX;
            const dy = event.clientY - cropAction.startY;

            if (cropAction.type === 'move-image') {
                cropOffsetX = cropAction.startOffsetX + dx;
                cropOffsetY = cropAction.startOffsetY + dy;
                renderCropImage();
                return;
            }

            if (cropAction.type === 'move-crop') {
                cropSelection.x = cropAction.startCrop.x + dx;
                cropSelection.y = cropAction.startCrop.y + dy;
                clampCropMove();
                renderCropBox();
                return;
            }

            if (cropAction.type === 'resize-crop') {
                resizeCropSelection(cropAction.handle, dx, dy);
                renderCropBox();
            }
        });

        const endCropAction = (event) => {
            if (!cropAction) {
                return;
            }
            if (event.pointerId !== undefined) {
                bookingCropStage.releasePointerCapture(event.pointerId);
            }
            cropAction = null;
            bookingCropStage.classList.remove('is-dragging');
        };

        bookingCropStage.addEventListener('pointerup', endCropAction);
        bookingCropStage.addEventListener('pointercancel', endCropAction);
        bookingCropStage.addEventListener('pointerleave', endCropAction);

        bookingCropApplyBtn.addEventListener('click', () => {
            isApplyingCrop = true;
            applyCroppedImage();
        });

        bookingCropHandles.forEach((node) => {
            node.addEventListener('dragstart', (event) => event.preventDefault());
        });
    }

    if (!dateInput || !timeSelect || Number.isNaN(durationMinutes)) {
        return;
    }

    const updateTimeAvailability = () => {
        const selectedDate = dateInput.value;
        const options = Array.from(timeSelect.options).filter((option) => option.value);
        const oldValue = timeSelect.value;
        const now = new Date();
        const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        const nowMinutes = (now.getHours() * 60) + now.getMinutes();

        options.forEach((option) => {
            const start = toMinutes(option.value);
            const end = start + durationMinutes;
            const rangeLabel = toRangeLabel(start, durationMinutes) || option.value;
            const baseLabel = rangeLabel;
            option.setAttribute('data-base-label', baseLabel);
            const isPastTimeToday = selectedDate === today && start < nowMinutes;
            const isAlignedToPackage = (
                start >= 0
                && openMinutes >= 0
                && durationMinutes > 0
                && (start - openMinutes) % durationMinutes === 0
            );
            let unavailable = false;

            if (start < openMinutes || end > closeMinutes) {
                unavailable = true;
            } else {
                unavailable = bookingRecords.some((record) => {
                    if ((record.booking_date || '') !== selectedDate) {
                        return false;
                    }

                    const existingStart = toMinutes(record.start_time || '');
                    const existingEnd = toMinutes(record.end_time || '');
                    if (existingStart < 0 || existingEnd <= existingStart) {
                        return false;
                    }

                    return overlaps(start, end, existingStart, existingEnd);
                });
            }

            option.hidden = isPastTimeToday || !isAlignedToPackage;
            option.disabled = unavailable || isPastTimeToday || !isAlignedToPackage;
            option.textContent = unavailable && !isPastTimeToday ? `${baseLabel} ${labelFull}` : baseLabel;
        });

        if (oldValue && (timeSelect.selectedOptions[0]?.disabled || timeSelect.selectedOptions[0]?.hidden)) {
            timeSelect.value = '';
        }
    };

    dateInput.addEventListener('change', updateTimeAvailability);
    updateTimeAvailability();

    closeConfirmNodes.forEach((node) => node.addEventListener('click', closeBookingConfirmModal));
    submitConfirmBtn?.addEventListener('click', () => {
        isConfirmedSubmit = true;
        closeBookingConfirmModal();
        window.setTimeout(() => form.requestSubmit(), 60);
    });

    form.addEventListener('submit', (event) => {
        if (isConfirmedSubmit) {
            isConfirmedSubmit = false;
            return;
        }

        const selectedOption = timeSelect.selectedOptions[0];
        if (selectedOption?.disabled) {
            event.preventDefault();
            message.textContent = msgTimeFull;
            message.className = 'form-message error';
            return;
        }

        const fullName = form.full_name.value.trim();
        const email = form.email.value.trim();
        const phone = form.phone.value.trim();
        const timeSlot = timeSelect.value.trim();
        const bookingDate = dateInput.value.trim();
        const proof = form.payment_proof.files[0];

        if (!fullName || !email || !phone || !bookingDate || !timeSlot || !proof) {
            event.preventDefault();
            message.textContent = msgRequired;
            message.className = 'form-message error';
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            event.preventDefault();
            message.textContent = msgInvalidEmail;
            message.className = 'form-message error';
            return;
        }

        if (!/^\+?[0-9\s-]{8,20}$/.test(phone)) {
            event.preventDefault();
            message.textContent = msgInvalidPhone;
            message.className = 'form-message error';
            return;
        }

        event.preventDefault();
        message.textContent = '';
        message.className = 'form-message';

        if (confirmName) {
            confirmName.textContent = fullName;
        }
        if (confirmPhone) {
            confirmPhone.textContent = phone;
        }
        if (confirmEmail) {
            confirmEmail.textContent = email;
        }
        if (confirmDate) {
            confirmDate.textContent = bookingDate;
        }
        if (confirmTime) {
            const displayTime = selectedOption?.getAttribute('data-base-label') || timeSlot;
            confirmTime.textContent = displayTime;
        }
        if (confirmBank) {
            const bankLabel = bankSelect?.selectedOptions?.[0]?.textContent?.trim() || '-';
            confirmBank.textContent = bankLabel;
        }

        openBookingConfirmModal();
    });

    bookingConfirmDialog?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeBookingConfirmModal();
        }
    });
})();
