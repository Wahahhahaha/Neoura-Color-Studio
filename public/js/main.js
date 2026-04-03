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
        const observer = new IntersectionObserver((entries, io) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    io.unobserve(entry.target);
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
            bookingVerifyModal.hidden = false;
            document.body.style.overflow = 'hidden';
            modalLast4Input?.focus();
        };

        const closeVerifyModal = () => {
            bookingVerifyModal.hidden = true;
            document.body.style.overflow = '';
            clearModalError();
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
        let proofCloseTimer = null;

        const syncBodyScrollLock = () => {
            const hasOpenModal = Boolean(document.querySelector('.crop-modal:not([hidden])'));
            document.body.style.overflow = hasOpenModal ? 'hidden' : '';
        };

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
            const title = button.getAttribute('data-proof-title') || 'Payment Proof';

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
        let initialTitle = aboutTitleInput ? aboutTitleInput.value : '';
        let initialDescription = aboutDescriptionInput ? aboutDescriptionInput.value : '';
        let aboutCloseTimer = null;

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
            aboutEditorModal.hidden = false;
            aboutEditorModal.classList.remove('is-enter');
            window.requestAnimationFrame(() => aboutEditorModal.classList.add('is-enter'));
            document.body.style.overflow = 'hidden';
        };

        const closeAboutModal = () => {
            resetAboutModal();
            aboutEditorModal.classList.remove('is-enter');
            if (aboutCloseTimer) {
                window.clearTimeout(aboutCloseTimer);
            }
            aboutCloseTimer = window.setTimeout(() => {
                aboutEditorModal.hidden = true;
                document.body.style.overflow = '';
            }, 220);
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
                    const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : 'Failed to save About Us.';
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

                setAboutFeedback('success', payload?.message || 'About Us updated.');
                window.setTimeout(() => closeAboutModal(), 260);
            } catch (_) {
                setAboutFeedback('error', 'Network error while saving About Us.');
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
        let initialTitle = titleInput ? titleInput.value : '';
        let initialDescription = descriptionInput ? descriptionInput.value : '';
        let contactCloseTimer = null;

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
            contactEditorModal.hidden = false;
            contactEditorModal.classList.remove('is-leave');
            contactEditorModal.classList.remove('is-enter');
            window.requestAnimationFrame(() => contactEditorModal.classList.add('is-enter'));
            document.body.style.overflow = 'hidden';
        };

        const closeContactModal = () => {
            resetContactModal();
            contactEditorModal.classList.remove('is-enter');
            contactEditorModal.classList.add('is-leave');
            if (contactCloseTimer) {
                window.clearTimeout(contactCloseTimer);
            }
            contactCloseTimer = window.setTimeout(() => {
                contactEditorModal.hidden = true;
                contactEditorModal.classList.remove('is-leave');
                document.body.style.overflow = '';
            }, 220);
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
                    const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : 'Failed to save Contact section.';
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

                setContactFeedback('success', payload?.message || 'Contact section updated.');
                window.setTimeout(() => closeContactModal(), 260);
            } catch (_) {
                setContactFeedback('error', 'Network error while saving Contact section.');
            } finally {
                if (contactSaveBtn) {
                    contactSaveBtn.disabled = false;
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
        const initialSlideListMarkup = slideList ? slideList.innerHTML : '';
        const initialCarouselAutoplay = carouselAutoplayInput ? carouselAutoplayInput.value : '';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const cropModal = document.querySelector('[data-carousel-crop-modal]');
        const cropStage = cropModal?.querySelector('[data-carousel-crop-stage]');
        const cropImage = cropModal?.querySelector('[data-carousel-crop-image]');
        const cropBox = cropModal?.querySelector('[data-carousel-crop-box]');
        const cropZoomInput = cropModal?.querySelector('[data-carousel-crop-zoom]');
        const cropApplyBtn = cropModal?.querySelector('[data-apply-carousel-crop]');
        const cropCloseNodes = cropModal ? Array.from(cropModal.querySelectorAll('[data-close-carousel-crop]')) : [];
        let editorCloseTimer = null;

        const syncSlideFields = () => {
            if (!slideList) {
                return;
            }

            const cards = Array.from(slideList.querySelectorAll('[data-slide-card]'));
            cards.forEach((card, index) => {
                const heading = card.querySelector('[data-slide-heading]');
                if (heading) {
                    heading.textContent = `Slide ${index + 1}`;
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
            carouselEditorModal.hidden = false;
            carouselEditorModal.classList.remove('is-enter');
            window.requestAnimationFrame(() => carouselEditorModal.classList.add('is-enter'));
            document.body.style.overflow = 'hidden';
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
            carouselEditorModal.classList.remove('is-enter');
            if (editorCloseTimer) {
                window.clearTimeout(editorCloseTimer);
            }
            editorCloseTimer = window.setTimeout(() => {
                carouselEditorModal.hidden = true;
                document.body.style.overflow = '';
            }, 220);
        };

        const appendSlideCard = () => {
            if (!slideList || !slideTemplate) {
                return;
            }

            const fragment = slideTemplate.content.cloneNode(true);
            slideList.appendChild(fragment);
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
        editorForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            syncSlideFields();
            clearFeedback();

            if (!editorForm) {
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
            }

            try {
                const formData = new FormData(editorForm);
                const response = await fetch(editorForm.action, {
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
                    const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : 'Failed to save changes.';
                    setFeedback('error', errors);
                    return;
                }

                setFeedback('success', payload?.message || 'Saved.');
                window.setTimeout(() => window.location.reload(), 500);
            } catch (_) {
                setFeedback('error', 'Network error while saving.');
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
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
            cropModal.hidden = false;
            document.body.style.overflow = 'hidden';
        };

        const closeCropModal = () => {
            cropModal.hidden = true;
            document.body.style.overflow = '';
            cropAction = null;
            cropStage.classList.remove('is-dragging');
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
                    if (cropTargetInput) {
                        cropTargetInput.value = '';
                    }
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
                            preview.alt = 'Slide preview';
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

    const carousel = document.querySelector('[data-carousel]');
    if (!carousel) {
        return;
    }

    const slides = Array.from(carousel.querySelectorAll('.slide'));
    const dots = Array.from(carousel.querySelectorAll('.dot'));
    const nextBtn = carousel.querySelector('[data-carousel-next]');
    const prevBtn = carousel.querySelector('[data-carousel-prev]');
    const autoplayMsRaw = Number(carousel.getAttribute('data-carousel-autoplay-ms') || '5000');
    const autoplayMs = Number.isFinite(autoplayMsRaw) ? Math.max(500, Math.min(60000, autoplayMsRaw)) : 5000;
    let current = 0;
    let timer;

    const render = (index) => {
        slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index));
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
    };

    const go = (index) => {
        current = (index + slides.length) % slides.length;
        render(current);
    };

    const next = () => go(current + 1);
    const prev = () => go(current - 1);

    const startAuto = () => {
        timer = window.setInterval(next, autoplayMs);
    };

    const resetAuto = () => {
        window.clearInterval(timer);
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

    dots.forEach((dot, idx) => {
        dot.addEventListener('click', () => {
            go(idx);
            resetAuto();
        });
    });

    render(0);
    startAuto();
})();
