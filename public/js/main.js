(() => {
    const langSwitcher = document.querySelector('.lang-switcher');
    const navMenuForLangAlign = document.querySelector('.site-header .nav-links');

    const syncLangSwitcherWithNav = () => {
        if (!langSwitcher || !navMenuForLangAlign) {
            return;
        }

        if (window.innerWidth <= 760) {
            langSwitcher.style.top = '';
            langSwitcher.style.transform = '';
            return;
        }

        const rect = navMenuForLangAlign.getBoundingClientRect();
        if (!rect || rect.height <= 0) {
            return;
        }

        const midY = rect.top + (rect.height / 2);
        langSwitcher.style.top = `${Math.round(midY)}px`;
        langSwitcher.style.transform = 'translateY(-50%)';
    };

    syncLangSwitcherWithNav();
    window.addEventListener('resize', syncLangSwitcherWithNav);
    const navToggle = document.querySelector('[data-nav-toggle]');
    const navMenu = document.querySelector('[data-nav-menu]');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('open');
        });

        navMenu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => navMenu.classList.remove('open'));
        });
    }

    const adminLogo = document.querySelector('[data-admin-logo]');
    if (adminLogo) {
        adminLogo.addEventListener('click', async (event) => {
            event.preventDefault();
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                const response = await fetch('/admin/logo-click', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (payload?.unlocked && payload?.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }
            } catch (_) {
                // Silent fail to keep normal user flow unaffected.
            }
        });
    }

    const adminSidebar = document.querySelector('[data-admin-sidebar]');
    if (adminSidebar) {
        const homeLayout = adminSidebar.closest('[data-home-layout]');
        const adminHeaderShell = document.querySelector('[data-admin-header-shell]');
        const adminFooterShell = document.querySelector('[data-admin-footer-shell]');
        const sidebarToggle = document.querySelector('[data-admin-sidebar-toggle]') || adminSidebar.querySelector('[data-sidebar-toggle]');
        const sidebarScrollArea = adminSidebar.querySelector('.admin-sidebar-dual');
        const iconLinks = Array.from(adminSidebar.querySelectorAll('.admin-tier-one [data-menu-key]'));
        const textLinks = Array.from(adminSidebar.querySelectorAll('.admin-tier-two [data-menu-key]'));
        const storageKey = 'adminSidebarCollapsed';
        const isMobileViewport = () => window.innerWidth <= 980;

        const syncAdminShellState = (collapsed) => {
            adminHeaderShell?.classList.toggle('is-sidebar-collapsed', collapsed);
            adminFooterShell?.classList.toggle('is-sidebar-collapsed', collapsed);
        };

        const syncAdminHeaderOffset = () => {
            if (!homeLayout || !adminHeaderShell) {
                return;
            }

            const headerHeight = Math.max(74, Math.ceil(adminHeaderShell.getBoundingClientRect().height || 0));
            homeLayout.style.setProperty('--admin-header-offset', `${headerHeight}px`);
        };

        const setActive = (menuKey) => {
            iconLinks.forEach((link) => link.classList.toggle('is-active', link.getAttribute('data-menu-key') === menuKey));
            textLinks.forEach((link) => link.classList.toggle('is-active', link.getAttribute('data-menu-key') === menuKey));
        };

        const applyCollapsedState = (collapsed, persist = true) => {
            if (collapsed) {
                adminSidebar.classList.add('is-collapsed');
                homeLayout?.classList.add('sidebar-collapsed');
                sidebarToggle?.setAttribute('aria-expanded', 'false');
                syncAdminShellState(true);
            } else {
                adminSidebar.classList.remove('is-collapsed');
                homeLayout?.classList.remove('sidebar-collapsed');
                sidebarToggle?.setAttribute('aria-expanded', 'true');
                syncAdminShellState(false);
            }

            if (persist) {
                window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
            }
        };

        const savedState = window.localStorage.getItem(storageKey);
        if (isMobileViewport()) {
            applyCollapsedState(true, false);
        } else if (savedState === '1') {
            applyCollapsedState(true, false);
        } else {
            applyCollapsedState(false, false);
        }

        sidebarToggle?.addEventListener('click', () => {
            const collapsed = !adminSidebar.classList.contains('is-collapsed');
            applyCollapsedState(collapsed);
        });

        iconLinks.forEach((link) => {
            link.addEventListener('click', () => setActive(link.getAttribute('data-menu-key')));
        });

        textLinks.forEach((link) => {
            link.addEventListener('click', () => setActive(link.getAttribute('data-menu-key')));
        });

        if (sidebarScrollArea) {
            adminSidebar.addEventListener('wheel', (event) => {
                if (window.innerWidth <= 980) {
                    return;
                }

                const maxScroll = sidebarScrollArea.scrollHeight - sidebarScrollArea.clientHeight;
                if (maxScroll <= 0) {
                    return;
                }

                event.preventDefault();
                sidebarScrollArea.scrollTop += event.deltaY;
            }, { passive: false });
        }

        syncAdminHeaderOffset();
        window.addEventListener('resize', syncAdminHeaderOffset);

        let wasMobileViewport = isMobileViewport();
        window.addEventListener('resize', () => {
            const isMobileNow = isMobileViewport();
            if (isMobileNow === wasMobileViewport) {
                return;
            }

            wasMobileViewport = isMobileNow;
            if (isMobileNow) {
                applyCollapsedState(true, false);
                return;
            }

            applyCollapsedState(window.localStorage.getItem(storageKey) === '1', false);
        });

        if ('ResizeObserver' in window && adminHeaderShell) {
            const headerResizeObserver = new ResizeObserver(() => syncAdminHeaderOffset());
            headerResizeObserver.observe(adminHeaderShell);
        }

        const geoStorageKey = 'adminActivityGeoSentAt';
        const nowTs = Date.now();
        const lastSentTs = Number(window.localStorage.getItem(geoStorageKey) || '0');
        const oneHourMs = 60 * 60 * 1000;
        if ('geolocation' in navigator && (!Number.isFinite(lastSentTs) || nowTs - lastSentTs > oneHourMs)) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            navigator.geolocation.getCurrentPosition((position) => {
                fetch('/activity-log/location', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        latitude: position?.coords?.latitude ?? '',
                        longitude: position?.coords?.longitude ?? '',
                    }),
                }).catch(() => {});

                window.localStorage.setItem(geoStorageKey, String(Date.now()));
            }, () => {}, {
                enableHighAccuracy: false,
                timeout: 8000,
                maximumAge: 10 * 60 * 1000,
            });
        }
    }

    const samePageLinks = document.querySelectorAll('a[href^="#"]');
    samePageLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const targetId = link.getAttribute('href');
            const target = document.querySelector(targetId);

            if (!target) {
                return;
            }

            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    const fadeNodes = document.querySelectorAll('.fade-in');
    if ('IntersectionObserver' in window && fadeNodes.length) {
        let lastScrollY = window.scrollY || window.pageYOffset || 0;
        let isScrollingDown = true;
        window.addEventListener('scroll', () => {
            const currentY = window.scrollY || window.pageYOffset || 0;
            isScrollingDown = currentY >= lastScrollY;
            lastScrollY = currentY;
        }, { passive: true });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    if (isScrollingDown) {
                        entry.target.classList.remove('no-anim');
                    } else {
                        entry.target.classList.add('no-anim');
                    }
                    entry.target.classList.add('visible');
                } else {
                    entry.target.classList.remove('no-anim');
                    entry.target.classList.remove('visible');
                }
            });
        }, { threshold: 0.12 });

        fadeNodes.forEach((node) => observer.observe(node));
    } else {
        fadeNodes.forEach((node) => node.classList.add('visible'));
    }

    const cards = document.querySelectorAll('[data-card-link]');
    cards.forEach((card) => {
        card.addEventListener('click', (event) => {
            if (event.target.closest('.btn')) {
                return;
            }

            const link = card.getAttribute('data-card-link');
            if (link) {
                if (link.startsWith('#')) {
                    const target = document.querySelector(link);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        return;
                    }
                }
                window.location.href = link;
            }
        });
    });

    const MODAL_FADE_MS = 240;
    const modalCloseTimers = new WeakMap();
    const syncBodyScrollLock = () => {
        const hasOpenModal = Boolean(document.querySelector('.crop-modal:not([hidden])'));
        document.body.style.overflow = hasOpenModal ? 'hidden' : '';
    };

    const openFadeModal = (targetModal) => {
        if (!targetModal) {
            return;
        }

        const timerId = modalCloseTimers.get(targetModal);
        if (timerId) {
            window.clearTimeout(timerId);
            modalCloseTimers.delete(targetModal);
        }

        targetModal.hidden = false;
        targetModal.classList.remove('is-leave');
        targetModal.classList.remove('is-enter');
        window.requestAnimationFrame(() => targetModal.classList.add('is-enter'));
        syncBodyScrollLock();
    };

    const closeFadeModal = (targetModal, onAfter = null) => {
        if (!targetModal || targetModal.hidden) {
            if (typeof onAfter === 'function') {
                onAfter();
            }
            return;
        }

        targetModal.classList.remove('is-enter');
        targetModal.classList.add('is-leave');

        const timerId = window.setTimeout(() => {
            targetModal.hidden = true;
            targetModal.classList.remove('is-leave');
            modalCloseTimers.delete(targetModal);
            syncBodyScrollLock();
            if (typeof onAfter === 'function') {
                onAfter();
            }
        }, MODAL_FADE_MS);

        modalCloseTimers.set(targetModal, timerId);
    };

    const bookingStatusForm = document.querySelector('.booking-status-form');
    const bookingVerifyModal = document.querySelector('[data-booking-verify-modal]');
    const openBookingVerifyBtn = document.querySelector('[data-open-booking-verify]');
    if (bookingStatusForm && bookingVerifyModal && openBookingVerifyBtn) {
        const hiddenLast4 = bookingStatusForm.querySelector('[data-phone-last4-hidden]');
        const modalLast4Input = bookingVerifyModal.querySelector('#phone_last4_modal');
        const modalError = bookingVerifyModal.querySelector('[data-booking-verify-error]');
        const closeModalNodes = Array.from(bookingVerifyModal.querySelectorAll('[data-close-booking-verify]'));
        const submitVerifyBtn = bookingVerifyModal.querySelector('[data-submit-booking-verify]');

        const clearModalError = () => {
            if (!modalError) {
                return;
            }
            modalError.hidden = true;
        };

        const openVerifyModal = () => {
            clearModalError();
            if (modalLast4Input && hiddenLast4) {
                modalLast4Input.value = hiddenLast4.value || '';
            }
            openFadeModal(bookingVerifyModal);
            modalLast4Input?.focus();
        };

        const closeVerifyModal = () => {
            closeFadeModal(bookingVerifyModal, clearModalError);
        };

        openBookingVerifyBtn.addEventListener('click', () => {
            const bookingCodeInput = bookingStatusForm.querySelector('#booking_code_lookup');
            if (!bookingCodeInput || !bookingCodeInput.value.trim()) {
                bookingCodeInput?.focus();
                return;
            }
            openVerifyModal();
        });

        closeModalNodes.forEach((node) => node.addEventListener('click', closeVerifyModal));

        submitVerifyBtn?.addEventListener('click', () => {
            const value = (modalLast4Input?.value || '').trim();
            if (!/^\d{4}$/.test(value)) {
                if (modalError) {
                    modalError.hidden = false;
                }
                modalLast4Input?.focus();
                return;
            }

            if (hiddenLast4) {
                hiddenLast4.value = value;
            }
            closeVerifyModal();
            bookingStatusForm.submit();
        });
    }

    const proofModal = document.querySelector('[data-proof-modal]');
    if (proofModal) {
        const closeProofNodes = Array.from(proofModal.querySelectorAll('[data-close-proof-modal]'));
        const proofTitle = proofModal.querySelector('[data-proof-title]');
        const proofImage = proofModal.querySelector('[data-proof-image]');
        const proofDefaultTitle = proofModal.getAttribute('data-proof-default-title') || 'Payment Proof';
        let proofCloseTimer = null;

        const closeProofModal = () => {
            if (proofModal.hidden) {
                return;
            }

            proofModal.classList.remove('is-enter');
            proofModal.classList.add('is-leave');

            if (proofCloseTimer) {
                window.clearTimeout(proofCloseTimer);
            }

            proofCloseTimer = window.setTimeout(() => {
                proofModal.hidden = true;
                proofModal.classList.remove('is-leave');
                if (proofImage) {
                    proofImage.src = '';
                    proofImage.hidden = true;
                }
                syncBodyScrollLock();
                proofCloseTimer = null;
            }, 240);
        };

        const openProofModal = (button) => {
            const url = button.getAttribute('data-proof-url') || '';
            const title = button.getAttribute('data-proof-title') || proofDefaultTitle;

            if (proofCloseTimer) {
                window.clearTimeout(proofCloseTimer);
                proofCloseTimer = null;
            }

            if (proofTitle) {
                proofTitle.textContent = title;
            }

            if (proofImage) {
                proofImage.hidden = false;
                proofImage.src = url;
            }

            proofModal.hidden = false;
            proofModal.classList.remove('is-leave');
            proofModal.classList.remove('is-enter');
            window.requestAnimationFrame(() => proofModal.classList.add('is-enter'));
            syncBodyScrollLock();
        };

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-open-proof-modal]');
            if (!button) {
                return;
            }
            openProofModal(button);
        });
        closeProofNodes.forEach((node) => node.addEventListener('click', closeProofModal));
    }

    const aboutEditorModal = document.querySelector('[data-about-editor-modal]');
    const openAboutEditor = document.querySelector('[data-open-about-editor]');
    if (aboutEditorModal && openAboutEditor) {
        const closeAboutNodes = Array.from(aboutEditorModal.querySelectorAll('[data-close-about-editor]'));
        const aboutForm = aboutEditorModal.querySelector('[data-about-editor-form]');
        const aboutSaveBtn = aboutEditorModal.querySelector('[data-about-editor-save]');
        const aboutFeedback = aboutEditorModal.querySelector('[data-about-editor-feedback]');
        const aboutTitleInput = aboutEditorModal.querySelector('#about_editor_title');
        const aboutDescriptionInput = aboutEditorModal.querySelector('#about_editor_description');
        const aboutTitleTarget = document.querySelector('[data-about-title]');
        const aboutDescriptionTarget = document.querySelector('[data-about-description]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const aboutSaveFailedText = aboutForm?.getAttribute('data-i18n-about-save-failed') || 'Failed to save About Us.';
        const aboutUpdatedText = aboutForm?.getAttribute('data-i18n-about-updated') || 'About Us updated.';
        const aboutNetworkErrorText = aboutForm?.getAttribute('data-i18n-about-network-error') || 'Network error while saving About Us.';
        let initialTitle = aboutTitleInput ? aboutTitleInput.value : '';
        let initialDescription = aboutDescriptionInput ? aboutDescriptionInput.value : '';
        const setAboutFeedback = (type, text) => {
            if (!aboutFeedback) {
                return;
            }
            aboutFeedback.hidden = false;
            aboutFeedback.classList.remove('success', 'error');
            aboutFeedback.classList.add(type);
            aboutFeedback.textContent = text;
        };

        const clearAboutFeedback = () => {
            if (!aboutFeedback) {
                return;
            }
            aboutFeedback.hidden = true;
            aboutFeedback.classList.remove('success', 'error');
            aboutFeedback.textContent = '';
        };

        const resetAboutModal = () => {
            if (aboutTitleInput) {
                aboutTitleInput.value = initialTitle;
            }
            if (aboutDescriptionInput) {
                aboutDescriptionInput.value = initialDescription;
            }
            clearAboutFeedback();
        };

        const openAboutModal = () => {
            openFadeModal(aboutEditorModal);
        };

        const closeAboutModal = () => {
            resetAboutModal();
            closeFadeModal(aboutEditorModal);
        };

        openAboutEditor.addEventListener('click', openAboutModal);
        closeAboutNodes.forEach((node) => node.addEventListener('click', closeAboutModal));

        aboutForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearAboutFeedback();
            if (!aboutForm) {
                return;
            }

            if (aboutSaveBtn) {
                aboutSaveBtn.disabled = true;
            }

            try {
                const formData = new FormData(aboutForm);
                const response = await fetch(aboutForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : aboutSaveFailedText;
                    setAboutFeedback('error', errors);
                    return;
                }

                if (aboutTitleTarget) {
                    aboutTitleTarget.textContent = payload?.about?.title || aboutTitleInput?.value || '';
                }
                if (aboutDescriptionTarget) {
                    aboutDescriptionTarget.textContent = payload?.about?.description || aboutDescriptionInput?.value || '';
                }

                initialTitle = aboutTitleInput ? aboutTitleInput.value : initialTitle;
                initialDescription = aboutDescriptionInput ? aboutDescriptionInput.value : initialDescription;

                setAboutFeedback('success', payload?.message || aboutUpdatedText);
                window.setTimeout(() => closeAboutModal(), 260);
            } catch (_) {
                setAboutFeedback('error', aboutNetworkErrorText);
            } finally {
                if (aboutSaveBtn) {
                    aboutSaveBtn.disabled = false;
                }
            }
        });
    }

    const contactEditorModal = document.querySelector('[data-contact-editor-modal]');
    const openContactEditor = document.querySelector('[data-open-contact-editor]');
    if (contactEditorModal && openContactEditor) {
        const closeContactNodes = Array.from(contactEditorModal.querySelectorAll('[data-close-contact-editor]'));
        const contactForm = contactEditorModal.querySelector('[data-contact-editor-form]');
        const contactSaveBtn = contactEditorModal.querySelector('[data-contact-editor-save]');
        const contactFeedback = contactEditorModal.querySelector('[data-contact-editor-feedback]');
        const titleInput = contactEditorModal.querySelector('#contact_editor_title');
        const descriptionInput = contactEditorModal.querySelector('#contact_editor_description');
        const titleTarget = document.querySelector('[data-contact-section-title]');
        const descriptionTarget = document.querySelector('[data-contact-section-description]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const contactSaveFailedText = contactForm?.getAttribute('data-i18n-contact-save-failed') || 'Failed to save Contact section.';
        const contactUpdatedText = contactForm?.getAttribute('data-i18n-contact-updated') || 'Contact section updated.';
        const contactNetworkErrorText = contactForm?.getAttribute('data-i18n-contact-network-error') || 'Network error while saving Contact section.';
        let initialTitle = titleInput ? titleInput.value : '';
        let initialDescription = descriptionInput ? descriptionInput.value : '';

        const setContactFeedback = (type, text) => {
            if (!contactFeedback) {
                return;
            }
            contactFeedback.hidden = false;
            contactFeedback.classList.remove('success', 'error');
            contactFeedback.classList.add(type);
            contactFeedback.textContent = text;
        };

        const clearContactFeedback = () => {
            if (!contactFeedback) {
                return;
            }
            contactFeedback.hidden = true;
            contactFeedback.classList.remove('success', 'error');
            contactFeedback.textContent = '';
        };

        const resetContactModal = () => {
            if (titleInput) {
                titleInput.value = initialTitle;
            }
            if (descriptionInput) {
                descriptionInput.value = initialDescription;
            }
            clearContactFeedback();
        };

        const openContactModal = () => {
            openFadeModal(contactEditorModal);
        };

        const closeContactModal = () => {
            resetContactModal();
            closeFadeModal(contactEditorModal);
        };

        openContactEditor.addEventListener('click', openContactModal);
        closeContactNodes.forEach((node) => node.addEventListener('click', closeContactModal));

        contactForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearContactFeedback();
            if (!contactForm) {
                return;
            }

            if (contactSaveBtn) {
                contactSaveBtn.disabled = true;
            }

            try {
                const formData = new FormData(contactForm);
                const response = await fetch(contactForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : contactSaveFailedText;
                    setContactFeedback('error', errors);
                    return;
                }

                const sectionTitle = (payload?.contact_section?.title || titleInput?.value || '').trim();
                const sectionDescription = (payload?.contact_section?.description || descriptionInput?.value || '').trim();

                if (titleTarget) {
                    titleTarget.textContent = sectionTitle;
                }
                if (descriptionTarget) {
                    descriptionTarget.textContent = sectionDescription;
                }

                initialTitle = titleInput ? titleInput.value : initialTitle;
                initialDescription = descriptionInput ? descriptionInput.value : initialDescription;

                setContactFeedback('success', payload?.message || contactUpdatedText);
                window.setTimeout(() => closeContactModal(), 260);
            } catch (_) {
                setContactFeedback('error', contactNetworkErrorText);
            } finally {
                if (contactSaveBtn) {
                    contactSaveBtn.disabled = false;
                }
            }
        });
    }

    const aboutImageEditorModal = document.querySelector('[data-about-image-editor-modal]');
    const openAboutImageEditor = document.querySelector('[data-open-about-image-editor]');
    if (aboutImageEditorModal && openAboutImageEditor) {
        const closeNodes = Array.from(aboutImageEditorModal.querySelectorAll('[data-close-about-image-editor]'));
        const form = aboutImageEditorModal.querySelector('[data-about-image-editor-form]');
        const list = aboutImageEditorModal.querySelector('[data-about-image-list]');
        const template = aboutImageEditorModal.querySelector('[data-about-image-template]');
        const addBtn = aboutImageEditorModal.querySelector('[data-add-about-image]');
        const saveBtn = aboutImageEditorModal.querySelector('[data-about-image-editor-save]');
        const feedback = aboutImageEditorModal.querySelector('[data-about-image-editor-feedback]');
        const switcherTrack = document.querySelector('[data-switcher-track]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const saveFailedText = form?.getAttribute('data-i18n-save-failed') || 'Failed to save image switcher.';
        const updatedText = form?.getAttribute('data-i18n-updated') || 'Images updated.';
        const networkErrorText = form?.getAttribute('data-i18n-network-error') || 'Network error while saving images.';
        const requiredText = form?.getAttribute('data-i18n-required') || 'Please add at least one photo.';
        const imageLabelTemplate = form?.getAttribute('data-i18n-image-label') || 'Photo :number';
        const previewAltText = form?.getAttribute('data-i18n-preview-alt') || 'Image preview';
        const maxCards = 12;
        let initialMarkup = list ? list.innerHTML : '';
        const aboutCropModal = document.querySelector('[data-about-image-crop-modal]');
        const aboutCropStage = aboutCropModal?.querySelector('[data-about-image-crop-stage]');
        const aboutCropImage = aboutCropModal?.querySelector('[data-about-image-crop-image]');
        const aboutCropBox = aboutCropModal?.querySelector('[data-about-image-crop-box]');
        const aboutCropZoomInput = aboutCropModal?.querySelector('[data-about-image-crop-zoom]');
        const aboutCropApplyBtn = aboutCropModal?.querySelector('[data-apply-about-image-crop]');
        const aboutCropCloseNodes = aboutCropModal ? Array.from(aboutCropModal.querySelectorAll('[data-close-about-image-crop]')) : [];

        const labelFor = (index) => imageLabelTemplate.replace(':number', String(index + 1));

        const setFeedback = (type, text) => {
            if (!feedback) {
                return;
            }
            feedback.hidden = false;
            feedback.classList.remove('success', 'error');
            feedback.classList.add(type);
            feedback.textContent = text;
        };

        const clearFeedback = () => {
            if (!feedback) {
                return;
            }
            feedback.hidden = true;
            feedback.classList.remove('success', 'error');
            feedback.textContent = '';
        };

        const createPreview = (card, src) => {
            if (!card || !src) {
                return;
            }
            let preview = card.querySelector('.carousel-editor-preview');
            if (!preview) {
                preview = document.createElement('img');
                preview.className = 'carousel-editor-preview';
                preview.alt = previewAltText;
                const head = card.querySelector('.carousel-editor-card-head');
                if (head) {
                    head.insertAdjacentElement('afterend', preview);
                } else {
                    card.prepend(preview);
                }
            }
            preview.src = src;
        };

        const bindCardEvents = (card) => {
            if (!card) {
                return;
            }

            const removeBtn = card.querySelector('[data-remove-about-image]');
            const fileInput = card.querySelector('[data-field="image"]');

            removeBtn?.addEventListener('click', () => {
                card.remove();
                syncCards();
            });
        };

        const syncCards = () => {
            if (!list) {
                return;
            }

            const cards = Array.from(list.querySelectorAll('[data-about-image-card]'));
            cards.forEach((card, index) => {
                const heading = card.querySelector('[data-about-image-heading]');
                const existingInput = card.querySelector('[data-field="existing_image"]');
                const fileInput = card.querySelector('[data-field="image"]');
                const removeBtn = card.querySelector('[data-remove-about-image]');

                if (heading) {
                    heading.textContent = labelFor(index);
                }
                if (existingInput) {
                    existingInput.name = `about_images[${index}][existing_image]`;
                }
                if (fileInput) {
                    fileInput.name = `about_images[${index}][image]`;
                }
                if (removeBtn) {
                    removeBtn.hidden = cards.length <= 1;
                }
            });

            if (addBtn) {
                addBtn.disabled = cards.length >= maxCards;
            }
        };

        const appendNewCard = (slide = null) => {
            if (!list || !template) {
                return;
            }

            const cards = Array.from(list.querySelectorAll('[data-about-image-card]'));
            if (cards.length >= maxCards) {
                return;
            }

            const fragment = template.content.cloneNode(true);
            const card = fragment.querySelector('[data-about-image-card]');
            if (!card) {
                return;
            }

            const existingInput = card.querySelector('[data-field="existing_image"]');
            if (existingInput) {
                existingInput.value = slide?.image_path || '';
            }
            if (slide?.image_url) {
                createPreview(card, slide.image_url);
            }

            list.appendChild(fragment);
            const appendedCard = list.querySelectorAll('[data-about-image-card]')[cards.length];
            bindCardEvents(appendedCard);
            syncCards();
        };

        const resetEditor = () => {
            if (!list) {
                return;
            }
            list.innerHTML = initialMarkup;
            Array.from(list.querySelectorAll('[data-about-image-card]')).forEach((card) => bindCardEvents(card));
            syncCards();
            clearFeedback();
        };

        const renderSwitcher = (slides) => {
            if (!switcherTrack) {
                return;
            }

            Array.from(switcherTrack.querySelectorAll('.about-image-switch-item, .about-image-switch-fallback')).forEach((node) => node.remove());

            if (!slides.length) {
                const fallback = document.createElement('div');
                fallback.className = 'about-image-switch-fallback is-active';
                switcherTrack.appendChild(fallback);
                switcherTrack.dispatchEvent(new CustomEvent('about-image-switcher:reset'));
                return;
            }

            slides.forEach((slide, index) => {
                if (!slide?.image_url) {
                    return;
                }
                const img = document.createElement('img');
                img.src = slide.image_url;
                img.alt = labelFor(index);
                img.className = `about-image-switch-item ${index === 0 ? 'is-active' : ''}`;
                img.loading = 'lazy';
                img.setAttribute('data-switcher-image-path', slide.image_path || '');
                switcherTrack.appendChild(img);
            });

            switcherTrack.dispatchEvent(new CustomEvent('about-image-switcher:reset'));
        };

        const applySlidesToEditor = (slides) => {
            if (!list) {
                return;
            }

            list.innerHTML = '';
            slides.forEach((slide) => appendNewCard(slide));
            if (!slides.length) {
                appendNewCard();
            }
            syncCards();
            initialMarkup = list.innerHTML;
        };

        openAboutImageEditor.addEventListener('click', () => {
            resetEditor();
            openFadeModal(aboutImageEditorModal);
        });

        closeNodes.forEach((node) => {
            node.addEventListener('click', () => {
                resetEditor();
                if (aboutCropModal && !aboutCropModal.hidden) {
                    closeAboutCropModal(true);
                }
                closeFadeModal(aboutImageEditorModal);
            });
        });

        addBtn?.addEventListener('click', () => appendNewCard());

        Array.from(list?.querySelectorAll('[data-about-image-card]') || []).forEach((card) => bindCardEvents(card));
        if (!list?.querySelector('[data-about-image-card]')) {
            appendNewCard();
        } else {
            syncCards();
        }

        let aboutCropSourceUrl = '';
        let aboutCropSourceImage = null;
        let aboutCropBaseScale = 1;
        let aboutCropZoom = 1;
        let aboutCropOffsetX = 0;
        let aboutCropOffsetY = 0;
        let aboutCropAction = null;
        let aboutCropTargetInput = null;
        let aboutCropTargetCard = null;
        let aboutCrop = { x: 0, y: 0, width: 0, height: 0 };
        const aboutCropMinimum = 48;
        const canUseAboutCrop = Boolean(
            aboutCropModal
            && aboutCropStage
            && aboutCropImage
            && aboutCropBox
            && aboutCropZoomInput
            && aboutCropApplyBtn
        );

        const aboutCropStageRect = () => aboutCropStage.getBoundingClientRect();
        const aboutCropDisplaySize = () => {
            if (!aboutCropSourceImage) {
                return { width: 0, height: 0, stageW: 0, stageH: 0 };
            }

            const rect = aboutCropStageRect();
            return {
                width: aboutCropSourceImage.naturalWidth * aboutCropBaseScale * aboutCropZoom,
                height: aboutCropSourceImage.naturalHeight * aboutCropBaseScale * aboutCropZoom,
                stageW: rect.width,
                stageH: rect.height,
            };
        };

        const clampAboutCropOffsets = () => {
            const size = aboutCropDisplaySize();
            const limitX = Math.max(0, (size.width - size.stageW) / 2);
            const limitY = Math.max(0, (size.height - size.stageH) / 2);
            aboutCropOffsetX = Math.min(limitX, Math.max(-limitX, aboutCropOffsetX));
            aboutCropOffsetY = Math.min(limitY, Math.max(-limitY, aboutCropOffsetY));
        };

        const renderAboutCropImage = () => {
            clampAboutCropOffsets();
            aboutCropImage.style.transform = `translate(calc(-50% + ${aboutCropOffsetX}px), calc(-50% + ${aboutCropOffsetY}px)) scale(${aboutCropBaseScale * aboutCropZoom})`;
        };

        const initAboutCropBox = () => {
            const rect = aboutCropStageRect();
            const side = Math.max(aboutCropMinimum, Math.round(Math.min(rect.width, rect.height) * 0.72));
            aboutCrop = {
                x: (rect.width - side) / 2,
                y: (rect.height - side) / 2,
                width: side,
                height: side,
            };
        };

        const renderAboutCropBox = () => {
            aboutCropBox.style.left = `${aboutCrop.x}px`;
            aboutCropBox.style.top = `${aboutCrop.y}px`;
            aboutCropBox.style.width = `${aboutCrop.width}px`;
            aboutCropBox.style.height = `${aboutCrop.height}px`;
        };

        const resetAboutCropFromSource = () => {
            if (!aboutCropSourceImage) {
                return;
            }

            const rect = aboutCropStageRect();
            aboutCropBaseScale = Math.max(rect.width / aboutCropSourceImage.naturalWidth, rect.height / aboutCropSourceImage.naturalHeight);
            aboutCropZoom = 1;
            aboutCropOffsetX = 0;
            aboutCropOffsetY = 0;
            aboutCropZoomInput.value = '1';
            renderAboutCropImage();
            initAboutCropBox();
            renderAboutCropBox();
        };

        const closeAboutCropModal = (clearFile = false) => {
            closeFadeModal(aboutCropModal, () => {
                if (clearFile && aboutCropTargetInput) {
                    aboutCropTargetInput.value = '';
                }
                aboutCropAction = null;
                aboutCropStage.classList.remove('is-dragging');
            });
        };

        const clampAboutCropMove = () => {
            const rect = aboutCropStageRect();
            aboutCrop.x = Math.max(0, Math.min(aboutCrop.x, rect.width - aboutCrop.width));
            aboutCrop.y = Math.max(0, Math.min(aboutCrop.y, rect.height - aboutCrop.height));
        };

        const resizeAboutCropBox = (handle, dx, dy) => {
            const rect = aboutCropStageRect();
            const start = aboutCropAction?.startCrop;
            if (!start) {
                return;
            }

            if (handle === 'nw') {
                const fx = start.x + start.width;
                const fy = start.y + start.height;
                const mx = Math.max(0, Math.min(fx - aboutCropMinimum, start.x + dx));
                const my = Math.max(0, Math.min(fy - aboutCropMinimum, start.y + dy));
                aboutCrop = { x: mx, y: my, width: fx - mx, height: fy - my };
                return;
            }

            if (handle === 'ne') {
                const fx = start.x;
                const fy = start.y + start.height;
                const mx = Math.max(fx + aboutCropMinimum, Math.min(rect.width, start.x + start.width + dx));
                const my = Math.max(0, Math.min(fy - aboutCropMinimum, start.y + dy));
                aboutCrop = { x: fx, y: my, width: mx - fx, height: fy - my };
                return;
            }

            if (handle === 'sw') {
                const fx = start.x + start.width;
                const fy = start.y;
                const mx = Math.max(0, Math.min(fx - aboutCropMinimum, start.x + dx));
                const my = Math.max(fy + aboutCropMinimum, Math.min(rect.height, start.y + start.height + dy));
                aboutCrop = { x: mx, y: fy, width: fx - mx, height: my - fy };
                return;
            }

            const fx = start.x;
            const fy = start.y;
            const mx = Math.max(fx + aboutCropMinimum, Math.min(rect.width, start.x + start.width + dx));
            const my = Math.max(fy + aboutCropMinimum, Math.min(rect.height, start.y + start.height + dy));
            aboutCrop = { x: fx, y: fy, width: mx - fx, height: my - fy };
        };

        if (canUseAboutCrop) {
            list?.addEventListener('change', (event) => {
                const input = event.target.closest('input[type="file"][data-field="image"]');
                const file = input?.files?.[0];
                if (!input || !file) {
                    return;
                }

                if (aboutCropSourceUrl) {
                    URL.revokeObjectURL(aboutCropSourceUrl);
                }

                aboutCropTargetInput = input;
                aboutCropTargetCard = input.closest('[data-about-image-card]');
                aboutCropSourceUrl = URL.createObjectURL(file);
                aboutCropImage.src = aboutCropSourceUrl;

                aboutCropSourceImage = new Image();
                aboutCropSourceImage.onload = () => {
                    resetAboutCropFromSource();
                };
                aboutCropSourceImage.src = aboutCropSourceUrl;
                openFadeModal(aboutCropModal);
            });

            aboutCropCloseNodes.forEach((node) => {
                node.addEventListener('click', () => closeAboutCropModal(false));
            });

            aboutCropZoomInput.addEventListener('input', () => {
                aboutCropZoom = Number(aboutCropZoomInput.value || '1');
                renderAboutCropImage();
            });

            const startAboutCropAction = (event, nextAction) => {
                aboutCropAction = {
                    ...nextAction,
                    startX: event.clientX,
                    startY: event.clientY,
                    startOffsetX: aboutCropOffsetX,
                    startOffsetY: aboutCropOffsetY,
                    startCrop: { ...aboutCrop },
                };
                aboutCropStage.setPointerCapture(event.pointerId);
                aboutCropStage.classList.add('is-dragging');
            };

            aboutCropStage.addEventListener('pointerdown', (event) => {
                const handle = event.target.closest('[data-about-image-crop-handle]')?.getAttribute('data-about-image-crop-handle');
                if (handle) {
                    event.preventDefault();
                    startAboutCropAction(event, { type: 'resize', handle });
                    return;
                }

                if (event.target.closest('[data-about-image-crop-box]')) {
                    event.preventDefault();
                    startAboutCropAction(event, { type: 'move-box' });
                    return;
                }

                event.preventDefault();
                startAboutCropAction(event, { type: 'move-image' });
            });

            aboutCropStage.addEventListener('pointermove', (event) => {
                if (!aboutCropAction) {
                    return;
                }

                const dx = event.clientX - aboutCropAction.startX;
                const dy = event.clientY - aboutCropAction.startY;

                if (aboutCropAction.type === 'move-image') {
                    aboutCropOffsetX = aboutCropAction.startOffsetX + dx;
                    aboutCropOffsetY = aboutCropAction.startOffsetY + dy;
                    renderAboutCropImage();
                    return;
                }

                if (aboutCropAction.type === 'move-box') {
                    aboutCrop.x = aboutCropAction.startCrop.x + dx;
                    aboutCrop.y = aboutCropAction.startCrop.y + dy;
                    clampAboutCropMove();
                    renderAboutCropBox();
                    return;
                }

                resizeAboutCropBox(aboutCropAction.handle, dx, dy);
                renderAboutCropBox();
            });

            const endAboutCropAction = (event) => {
                if (!aboutCropAction) {
                    return;
                }
                if (event.pointerId !== undefined) {
                    aboutCropStage.releasePointerCapture(event.pointerId);
                }
                aboutCropAction = null;
                aboutCropStage.classList.remove('is-dragging');
            };

            aboutCropStage.addEventListener('pointerup', endAboutCropAction);
            aboutCropStage.addEventListener('pointercancel', endAboutCropAction);
            aboutCropStage.addEventListener('pointerleave', endAboutCropAction);

            aboutCropApplyBtn.addEventListener('click', () => {
                if (!aboutCropSourceImage || !aboutCropTargetInput) {
                    return;
                }

                const size = aboutCropDisplaySize();
                const scale = aboutCropBaseScale * aboutCropZoom;
                if (scale <= 0) {
                    return;
                }

                const imageLeft = (size.stageW - size.width) / 2 + aboutCropOffsetX;
                const imageTop = (size.stageH - size.height) / 2 + aboutCropOffsetY;

                const sourceX = (aboutCrop.x - imageLeft) / scale;
                const sourceY = (aboutCrop.y - imageTop) / scale;
                const sourceW = aboutCrop.width / scale;
                const sourceH = aboutCrop.height / scale;

                const clippedX = Math.max(0, sourceX);
                const clippedY = Math.max(0, sourceY);
                const clippedW = Math.min(aboutCropSourceImage.naturalWidth - clippedX, sourceW);
                const clippedH = Math.min(aboutCropSourceImage.naturalHeight - clippedY, sourceH);
                if (clippedW <= 0 || clippedH <= 0) {
                    return;
                }

                const maxOutputSide = 2400;
                const widthRatio = maxOutputSide / Math.max(1, clippedW);
                const heightRatio = maxOutputSide / Math.max(1, clippedH);
                const resizeRatio = Math.min(1, widthRatio, heightRatio);
                const outputWidth = Math.max(1, Math.round(clippedW * resizeRatio));
                const outputHeight = Math.max(1, Math.round(clippedH * resizeRatio));

                const canvas = document.createElement('canvas');
                canvas.width = outputWidth;
                canvas.height = outputHeight;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    return;
                }

                ctx.drawImage(aboutCropSourceImage, clippedX, clippedY, clippedW, clippedH, 0, 0, outputWidth, outputHeight);
                canvas.toBlob((blob) => {
                    if (!blob || !aboutCropTargetInput) {
                        return;
                    }

                    const croppedFile = new File([blob], `about-cropped-${Date.now()}.jpg`, { type: 'image/jpeg' });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    aboutCropTargetInput.files = transfer.files;

                    const existingInput = aboutCropTargetCard?.querySelector('[data-field="existing_image"]');
                    if (existingInput) {
                        existingInput.value = '';
                    }

                    if (aboutCropTargetCard) {
                        createPreview(aboutCropTargetCard, URL.createObjectURL(blob));
                    }

                    closeAboutCropModal(false);
                }, 'image/jpeg', 0.86);
            });
        }

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearFeedback();

            const cards = Array.from(list?.querySelectorAll('[data-about-image-card]') || []);
            if (!cards.length) {
                setFeedback('error', requiredText);
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
            }

            if (!form) {
                return;
            }

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : saveFailedText;
                    setFeedback('error', errors);
                    return;
                }

                const slides = Array.isArray(payload?.slides) ? payload.slides : [];
                renderSwitcher(slides);
                applySlidesToEditor(slides);
                setFeedback('success', payload?.message || updatedText);
                window.setTimeout(() => {
                    clearFeedback();
                    closeFadeModal(aboutImageEditorModal);
                }, 260);
            } catch (_) {
                setFeedback('error', networkErrorText);
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
            }
        });
    }

    const carouselEditorModal = document.querySelector('[data-carousel-editor-modal]');
    const openCarouselEditor = document.querySelector('[data-open-carousel-editor]');
    if (carouselEditorModal && openCarouselEditor) {
        const closeCarouselEditorNodes = Array.from(carouselEditorModal.querySelectorAll('[data-close-carousel-editor]'));
        const addSlideBtn = carouselEditorModal.querySelector('[data-add-slide]');
        const slideList = carouselEditorModal.querySelector('[data-slide-list]');
        const slideTemplate = carouselEditorModal.querySelector('template[data-slide-template]');
        const editorForm = carouselEditorModal.querySelector('form');
        const saveBtn = carouselEditorModal.querySelector('[data-carousel-editor-save]');
        const feedback = carouselEditorModal.querySelector('[data-carousel-editor-feedback]');
        const carouselAutoplayInput = carouselEditorModal.querySelector('#carousel_autoplay_ms');
        let initialSlideListMarkup = slideList ? slideList.innerHTML : '';
        let initialCarouselAutoplay = carouselAutoplayInput ? carouselAutoplayInput.value : '';
        const slideLabelTemplate = carouselEditorModal.getAttribute('data-i18n-slide-label') || 'Slide :number';
        const carouselSaveFailedText = carouselEditorModal.getAttribute('data-i18n-carousel-save-failed') || 'Failed to save changes.';
        const savedText = carouselEditorModal.getAttribute('data-i18n-saved') || 'Saved.';
        const carouselNetworkErrorText = carouselEditorModal.getAttribute('data-i18n-carousel-network-error') || 'Network error while saving.';
        const slidePreviewAltText = carouselEditorModal.getAttribute('data-i18n-slide-preview-alt') || 'Slide preview';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const cropModal = document.querySelector('[data-carousel-crop-modal]');
        const cropStage = cropModal?.querySelector('[data-carousel-crop-stage]');
        const cropImage = cropModal?.querySelector('[data-carousel-crop-image]');
        const cropBox = cropModal?.querySelector('[data-carousel-crop-box]');
        const cropZoomInput = cropModal?.querySelector('[data-carousel-crop-zoom]');
        const cropApplyBtn = cropModal?.querySelector('[data-apply-carousel-crop]');
        const cropCloseNodes = cropModal ? Array.from(cropModal.querySelectorAll('[data-close-carousel-crop]')) : [];

        const syncSlideFields = () => {
            if (!slideList) {
                return;
            }

            const cards = Array.from(slideList.querySelectorAll('[data-slide-card]'));
            cards.forEach((card, index) => {
                const heading = card.querySelector('[data-slide-heading]');
                if (heading) {
                    heading.textContent = slideLabelTemplate.replace(':number', String(index + 1));
                }

                const fields = Array.from(card.querySelectorAll('[data-field]'));
                fields.forEach((field) => {
                    const key = field.getAttribute('data-field');
                    if (!key) {
                        return;
                    }
                    field.setAttribute('name', `slides[${index}][${key}]`);
                });

                const removeBtn = card.querySelector('[data-remove-slide]');
                if (removeBtn) {
                    removeBtn.disabled = cards.length <= 1;
                }
            });
        };

        const openEditor = () => {
            openFadeModal(carouselEditorModal);
        };

        const setFeedback = (type, text) => {
            if (!feedback) {
                return;
            }
            feedback.hidden = false;
            feedback.classList.remove('success', 'error');
            feedback.classList.add(type);
            feedback.textContent = text;
        };

        const clearFeedback = () => {
            if (!feedback) {
                return;
            }
            feedback.hidden = true;
            feedback.classList.remove('success', 'error');
            feedback.textContent = '';
        };

        const resetEditorState = () => {
            if (slideList) {
                slideList.innerHTML = initialSlideListMarkup;
            }
            if (carouselAutoplayInput) {
                carouselAutoplayInput.value = initialCarouselAutoplay;
            }
            editorForm?.reset();
            syncSlideFields();
            clearFeedback();
        };

        const closeEditor = () => {
            resetEditorState();
            closeFadeModal(carouselEditorModal);
        };

        const appendSlideCard = () => {
            if (!slideList || !slideTemplate) {
                return;
            }

            const fragment = slideTemplate.content.cloneNode(true);
            slideList.appendChild(fragment);
            syncSlideFields();
        };

        const applySlidesToEditor = (slidesPayload) => {
            if (!slideList || !slideTemplate) {
                return;
            }

            const items = Array.isArray(slidesPayload) ? slidesPayload : [];
            if (!items.length) {
                return;
            }

            slideList.innerHTML = '';
            items.forEach((slide) => {
                const fragment = slideTemplate.content.cloneNode(true);
                const card = fragment.querySelector('[data-slide-card]');
                if (!card) {
                    return;
                }

                const existingImageInput = card.querySelector('input[data-field="existing_image"]');
                if (existingImageInput) {
                    existingImageInput.value = slide?.image_path || '';
                }

                const titleInput = card.querySelector('input[data-field="title"]');
                if (titleInput) {
                    titleInput.value = slide?.title || '';
                }

                const descriptionInput = card.querySelector('textarea[data-field="description"]');
                if (descriptionInput) {
                    descriptionInput.value = slide?.description || '';
                }

                const imageUrl = slide?.image_url || '';
                if (imageUrl) {
                    const preview = document.createElement('img');
                    preview.className = 'carousel-editor-preview';
                    preview.alt = slidePreviewAltText;
                    preview.src = imageUrl;

                    const head = card.querySelector('.carousel-editor-card-head');
                    if (head) {
                        head.insertAdjacentElement('afterend', preview);
                    } else {
                        card.prepend(preview);
                    }
                }

                slideList.appendChild(fragment);
            });

            syncSlideFields();
        };

        slideList?.addEventListener('click', (event) => {
            const removeBtn = event.target.closest('[data-remove-slide]');
            if (!removeBtn || !slideList) {
                return;
            }

            const cards = Array.from(slideList.querySelectorAll('[data-slide-card]'));
            if (cards.length <= 1) {
                return;
            }

            removeBtn.closest('[data-slide-card]')?.remove();
            syncSlideFields();
        });

        addSlideBtn?.addEventListener('click', appendSlideCard);
        editorForm?.addEventListener('submit', () => {
            syncSlideFields();
            clearFeedback();

            if (!editorForm) {
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
            }
        });
        syncSlideFields();

        openCarouselEditor.addEventListener('click', openEditor);
        closeCarouselEditorNodes.forEach((node) => node.addEventListener('click', closeEditor));

        let cropSourceUrl = '';
        let cropSourceImage = null;
        let cropBaseScale = 1;
        let cropZoom = 1;
        let cropOffsetX = 0;
        let cropOffsetY = 0;
        let cropAction = null;
        let cropTargetInput = null;
        let crop = { x: 0, y: 0, width: 0, height: 0 };
        const cropMinimum = 48;

        const canUseCrop = Boolean(cropModal && cropStage && cropImage && cropBox && cropZoomInput && cropApplyBtn);
        const cropStageRect = () => cropStage.getBoundingClientRect();

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
            clampCropOffsets();
            cropImage.style.transform = `translate(calc(-50% + ${cropOffsetX}px), calc(-50% + ${cropOffsetY}px)) scale(${cropBaseScale * cropZoom})`;
        };

        const initCropBox = () => {
            const rect = cropStageRect();
            const side = Math.max(cropMinimum, Math.round(Math.min(rect.width, rect.height) * 0.72));
            crop = {
                x: (rect.width - side) / 2,
                y: (rect.height - side) / 2,
                width: side,
                height: side,
            };
        };

        const renderCropBox = () => {
            cropBox.style.left = `${crop.x}px`;
            cropBox.style.top = `${crop.y}px`;
            cropBox.style.width = `${crop.width}px`;
            cropBox.style.height = `${crop.height}px`;
        };

        const resetCropFromSource = () => {
            if (!cropSourceImage) {
                return;
            }

            const rect = cropStageRect();
            cropBaseScale = Math.max(rect.width / cropSourceImage.naturalWidth, rect.height / cropSourceImage.naturalHeight);
            cropZoom = 1;
            cropOffsetX = 0;
            cropOffsetY = 0;
            cropZoomInput.value = '1';
            renderCropImage();
            initCropBox();
            renderCropBox();
        };

        const openCropModal = () => {
            openFadeModal(cropModal);
        };

        const closeCropModal = () => {
            closeFadeModal(cropModal, () => {
                cropAction = null;
                cropStage.classList.remove('is-dragging');
            });
        };

        const clampCropMove = () => {
            const rect = cropStageRect();
            crop.x = Math.max(0, Math.min(crop.x, rect.width - crop.width));
            crop.y = Math.max(0, Math.min(crop.y, rect.height - crop.height));
        };

        const resizeCropBox = (handle, dx, dy) => {
            const rect = cropStageRect();
            const start = cropAction?.startCrop;
            if (!start) {
                return;
            }

            if (handle === 'nw') {
                const fx = start.x + start.width;
                const fy = start.y + start.height;
                const mx = Math.max(0, Math.min(fx - cropMinimum, start.x + dx));
                const my = Math.max(0, Math.min(fy - cropMinimum, start.y + dy));
                crop = { x: mx, y: my, width: fx - mx, height: fy - my };
                return;
            }

            if (handle === 'ne') {
                const fx = start.x;
                const fy = start.y + start.height;
                const mx = Math.max(fx + cropMinimum, Math.min(rect.width, start.x + start.width + dx));
                const my = Math.max(0, Math.min(fy - cropMinimum, start.y + dy));
                crop = { x: fx, y: my, width: mx - fx, height: fy - my };
                return;
            }

            if (handle === 'sw') {
                const fx = start.x + start.width;
                const fy = start.y;
                const mx = Math.max(0, Math.min(fx - cropMinimum, start.x + dx));
                const my = Math.max(fy + cropMinimum, Math.min(rect.height, start.y + start.height + dy));
                crop = { x: mx, y: fy, width: fx - mx, height: my - fy };
                return;
            }

            const fx = start.x;
            const fy = start.y;
            const mx = Math.max(fx + cropMinimum, Math.min(rect.width, start.x + start.width + dx));
            const my = Math.max(fy + cropMinimum, Math.min(rect.height, start.y + start.height + dy));
            crop = { x: fx, y: fy, width: mx - fx, height: my - fy };
        };

        if (canUseCrop) {
            slideList?.addEventListener('change', (event) => {
                const input = event.target.closest('input[type="file"][data-field="image"]');
                const file = input?.files?.[0];
                if (!input || !file) {
                    return;
                }

                if (cropSourceUrl) {
                    URL.revokeObjectURL(cropSourceUrl);
                }

                cropTargetInput = input;
                cropSourceUrl = URL.createObjectURL(file);
                cropImage.src = cropSourceUrl;

                cropSourceImage = new Image();
                cropSourceImage.onload = () => {
                    resetCropFromSource();
                };
                cropSourceImage.src = cropSourceUrl;
                openCropModal();
            });

            cropCloseNodes.forEach((node) => {
                node.addEventListener('click', () => {
                    closeCropModal();
                });
            });

            cropZoomInput.addEventListener('input', () => {
                cropZoom = Number(cropZoomInput.value || '1');
                renderCropImage();
            });

            const startCropAction = (event, nextAction) => {
                cropAction = {
                    ...nextAction,
                    startX: event.clientX,
                    startY: event.clientY,
                    startOffsetX: cropOffsetX,
                    startOffsetY: cropOffsetY,
                    startCrop: { ...crop },
                };
                cropStage.setPointerCapture(event.pointerId);
                cropStage.classList.add('is-dragging');
            };

            cropStage.addEventListener('pointerdown', (event) => {
                const handle = event.target.closest('[data-carousel-crop-handle]')?.getAttribute('data-carousel-crop-handle');
                if (handle) {
                    event.preventDefault();
                    startCropAction(event, { type: 'resize', handle });
                    return;
                }

                if (event.target.closest('[data-carousel-crop-box]')) {
                    event.preventDefault();
                    startCropAction(event, { type: 'move-box' });
                    return;
                }

                event.preventDefault();
                startCropAction(event, { type: 'move-image' });
            });

            cropStage.addEventListener('pointermove', (event) => {
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

                if (cropAction.type === 'move-box') {
                    crop.x = cropAction.startCrop.x + dx;
                    crop.y = cropAction.startCrop.y + dy;
                    clampCropMove();
                    renderCropBox();
                    return;
                }

                resizeCropBox(cropAction.handle, dx, dy);
                renderCropBox();
            });

            const endCropAction = (event) => {
                if (!cropAction) {
                    return;
                }
                if (event.pointerId !== undefined) {
                    cropStage.releasePointerCapture(event.pointerId);
                }
                cropAction = null;
                cropStage.classList.remove('is-dragging');
            };

            cropStage.addEventListener('pointerup', endCropAction);
            cropStage.addEventListener('pointercancel', endCropAction);
            cropStage.addEventListener('pointerleave', endCropAction);

            cropApplyBtn.addEventListener('click', () => {
                if (!cropSourceImage || !cropTargetInput) {
                    return;
                }

                const size = cropDisplaySize();
                const scale = cropBaseScale * cropZoom;
                if (scale <= 0) {
                    return;
                }

                const imageLeft = (size.stageW - size.width) / 2 + cropOffsetX;
                const imageTop = (size.stageH - size.height) / 2 + cropOffsetY;

                const sourceX = (crop.x - imageLeft) / scale;
                const sourceY = (crop.y - imageTop) / scale;
                const sourceW = crop.width / scale;
                const sourceH = crop.height / scale;

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
                    if (!blob || !cropTargetInput) {
                        return;
                    }

                    const croppedFile = new File([blob], `slide-cropped-${Date.now()}.png`, { type: 'image/png' });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    cropTargetInput.files = transfer.files;

                    const card = cropTargetInput.closest('[data-slide-card]');
                    if (card) {
                        let preview = card.querySelector('.carousel-editor-preview');
                        if (!preview) {
                            preview = document.createElement('img');
                            preview.className = 'carousel-editor-preview';
                            preview.alt = slidePreviewAltText;
                            const header = card.querySelector('.carousel-editor-card-head');
                            if (header) {
                                header.insertAdjacentElement('afterend', preview);
                            } else {
                                card.prepend(preview);
                            }
                        }
                        preview.src = URL.createObjectURL(blob);
                    }

                    closeCropModal();
                }, 'image/png');
            });
        }
    }

    const imageSwitcher = document.querySelector('[data-image-switcher]');
    if (imageSwitcher) {
        const switchDelayRaw = Number(imageSwitcher.getAttribute('data-image-switcher-ms') || '3200');
        const switchDelay = Number.isFinite(switchDelayRaw) ? Math.max(1200, Math.min(12000, switchDelayRaw)) : 3200;
        let switchIndex = 0;

        const renderSwitch = (activeIndex = 0) => {
            const switchItems = Array.from(imageSwitcher.querySelectorAll('.about-image-switch-item'));
            if (!switchItems.length) {
                switchIndex = 0;
                return;
            }

            switchIndex = (activeIndex + switchItems.length) % switchItems.length;
            switchItems.forEach((item, idx) => item.classList.toggle('is-active', idx === switchIndex));
        };

        const nextSwitch = () => {
            const switchItems = Array.from(imageSwitcher.querySelectorAll('.about-image-switch-item'));
            if (switchItems.length <= 1) {
                return;
            }
            renderSwitch(switchIndex + 1);
        };

        imageSwitcher.addEventListener('about-image-switcher:reset', () => renderSwitch(0));
        renderSwitch(0);
        window.setInterval(nextSwitch, switchDelay);
    }

    const carousel = document.querySelector('[data-carousel]');
    if (!carousel) {
        return;
    }

    const carouselTrack = carousel.querySelector('[data-carousel-track]');
    const carouselDots = carousel.querySelector('[data-carousel-dots]');
    let slides = Array.from(carousel.querySelectorAll('.slide'));
    let dots = Array.from(carousel.querySelectorAll('.dot'));
    const defaultEyebrowText = carousel.querySelector('.slide-stack-card .eyebrow')?.textContent?.trim() || 'Color Notes';
    const firstDotLabel = dots[0]?.getAttribute('aria-label') || 'Slide 1';
    const dotLabelPrefix = firstDotLabel.replace(/\d+/g, '').trim() || 'Slide';
    const nextBtn = carousel.querySelector('[data-carousel-next]');
    const prevBtn = carousel.querySelector('[data-carousel-prev]');
    const readAutoplay = (rawValue) => {
        const raw = Number(rawValue || '5000');
        return Number.isFinite(raw) ? Math.max(500, Math.min(60000, Math.round(raw))) : 5000;
    };
    let autoplayMs = readAutoplay(carousel.getAttribute('data-carousel-autoplay-ms'));
    let current = 0;
    let timer;

    const syncSlidesAndDots = () => {
        slides = Array.from(carousel.querySelectorAll('.slide'));
        dots = Array.from(carousel.querySelectorAll('.dot'));
    };

    const render = (index) => {
        if (!slides.length) {
            return;
        }
        slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index));
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
    };

    const go = (index) => {
        if (!slides.length) {
            return;
        }
        current = (index + slides.length) % slides.length;
        render(current);
    };

    const next = () => go(current + 1);
    const prev = () => go(current - 1);

    const startAuto = () => {
        window.clearInterval(timer);
        if (slides.length <= 1) {
            return;
        }
        timer = window.setInterval(next, autoplayMs);
    };

    const resetAuto = () => {
        startAuto();
    };

    nextBtn?.addEventListener('click', () => {
        next();
        resetAuto();
    });

    prevBtn?.addEventListener('click', () => {
        prev();
        resetAuto();
    });

    carouselDots?.addEventListener('click', (event) => {
        const targetDot = event.target.closest('.dot');
        if (!targetDot || !carouselDots.contains(targetDot)) {
            return;
        }

        const idx = dots.indexOf(targetDot);
        if (idx < 0) {
            return;
        }

        go(idx);
        resetAuto();
    });

    const renderCarouselItems = (nextSlides, nextAutoplayMs) => {
        if (!carouselTrack || !carouselDots || !Array.isArray(nextSlides) || !nextSlides.length) {
            return;
        }

        carouselTrack.innerHTML = '';
        carouselDots.innerHTML = '';

        nextSlides.forEach((slide, index) => {
            const article = document.createElement('article');
            article.className = `slide${index === 0 ? ' is-active' : ''}`;

            const imageUrl = slide?.image_url || '';
            if (imageUrl) {
                const image = document.createElement('img');
                image.src = imageUrl;
                image.alt = slide?.title || '';
                article.appendChild(image);
            } else {
                const solid = document.createElement('div');
                solid.className = `slide-solid ${slide?.solid_class || 'slide-solid-1'}`;
                article.appendChild(solid);
            }

            const stack = document.createElement('div');
            stack.className = 'slide-stack-card';

            const eyebrow = document.createElement('p');
            eyebrow.className = 'eyebrow';
            eyebrow.textContent = defaultEyebrowText;

            const title = document.createElement('h2');
            title.textContent = slide?.title || '';

            const description = document.createElement('p');
            description.textContent = slide?.description || '';

            stack.appendChild(eyebrow);
            stack.appendChild(title);
            stack.appendChild(description);
            article.appendChild(stack);
            carouselTrack.appendChild(article);

            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = `dot${index === 0 ? ' is-active' : ''}`;
            dot.setAttribute('data-slide', String(index));
            dot.setAttribute('aria-label', `${dotLabelPrefix} ${index + 1}`);
            carouselDots.appendChild(dot);
        });

        autoplayMs = readAutoplay(nextAutoplayMs);
        carousel.setAttribute('data-carousel-autoplay-ms', String(autoplayMs));
        syncSlidesAndDots();
        go(0);
        resetAuto();
    };

    window.addEventListener('carousel:updated', (event) => {
        const nextSlides = Array.isArray(event.detail?.slides) ? event.detail.slides : [];
        const nextAutoplayMs = event.detail?.autoplayMs;
        if (!nextSlides.length) {
            return;
        }
        renderCarouselItems(nextSlides, nextAutoplayMs);
    });

    syncSlidesAndDots();
    render(0);
    startAuto();
})();
