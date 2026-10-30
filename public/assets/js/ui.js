const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

function openDialog(dialog) {
    if (!(dialog instanceof HTMLDialogElement)) return;
    if (typeof dialog.showModal === "function") {
        if (!dialog.open) dialog.showModal();
    } else {
        dialog.setAttribute("open", "");
    }
    requestAnimationFrame(() => {
        const focusable = dialog.querySelector("input:not([type=hidden]), button, a[href], [tabindex]:not([tabindex='-1'])");
        focusable?.focus();
    });
}

function closeDialog(dialog) {
    if (!(dialog instanceof HTMLDialogElement)) return;
    if (typeof dialog.close === "function" && dialog.open) {
        dialog.close();
    } else {
        dialog.removeAttribute("open");
    }
}

function showToast(message, tone = "success") {
    const toast = document.querySelector("[data-toast]");
    if (!toast) return;
    const copy = toast.querySelector("[data-toast-copy]");
    const icon = toast.querySelector("[data-toast-icon]");
    if (copy) copy.textContent = message;
    if (icon) icon.dataset.tone = tone;
    toast.hidden = false;
    requestAnimationFrame(() => toast.classList.add("is-visible"));
    window.clearTimeout(showToast.timeoutId);
    showToast.timeoutId = window.setTimeout(() => {
        toast.classList.remove("is-visible");
        window.setTimeout(() => {
            if (!toast.classList.contains("is-visible")) toast.hidden = true;
        }, reduceMotion.matches ? 0 : 240);
    }, 3200);
}

function activateTab(trigger) {
    const group = trigger.closest("[data-tab-group]");
    if (!group) return;
    const targetId = trigger.dataset.tabTarget;
    if (!targetId) return;

    group.querySelectorAll("[data-tab-target]").forEach((item) => {
        const active = item === trigger;
        item.setAttribute("aria-selected", String(active));
        item.classList.toggle("is-active", active);
        item.tabIndex = active ? 0 : -1;
    });

    const panelsRoot = group.dataset.tabPanels
        ? document.querySelector(group.dataset.tabPanels)
        : group.parentElement;
    panelsRoot?.querySelectorAll("[data-tab-panel]").forEach((panel) => {
        panel.hidden = panel.id !== targetId;
    });
}

function initTabKeyboard() {
    document.addEventListener("keydown", (event) => {
        const current = event.target.closest?.("[data-tab-target]");
        if (!current) return;
        const group = current.closest("[data-tab-group]");
        const tabs = Array.from(group?.querySelectorAll("[data-tab-target]") ?? []);
        const index = tabs.indexOf(current);
        if (index < 0) return;
        let nextIndex = index;
        const rtl = document.documentElement.dir === "rtl";
        if (event.key === "ArrowRight") nextIndex = (index + (rtl ? -1 : 1) + tabs.length) % tabs.length;
        else if (event.key === "ArrowLeft") nextIndex = (index + (rtl ? 1 : -1) + tabs.length) % tabs.length;
        else if (event.key === "Home") nextIndex = 0;
        else if (event.key === "End") nextIndex = tabs.length - 1;
        else return;
        event.preventDefault();
        tabs[nextIndex].focus();
        activateTab(tabs[nextIndex]);
    });
}

let mobileMenuTrigger = null;

function setMobileMenu(open) {
    const shell = document.querySelector("[data-app-shell]");
    const toggle = document.querySelector("[data-mobile-menu-toggle]");
    const backdrop = document.querySelector("[data-mobile-menu-backdrop]");
    const main = shell?.querySelector(".app-main");
    const tabbar = document.querySelector(".mobile-tabbar");
    const sidebar = shell?.querySelector(".sidebar");
    if (!shell) return;
    shell.classList.toggle("is-mobile-menu-open", open);
    toggle?.setAttribute("aria-expanded", String(open));
    backdrop?.setAttribute("aria-hidden", String(!open));
    if (backdrop) backdrop.tabIndex = open ? 0 : -1;
    if (main) main.inert = open;
    if (tabbar) tabbar.inert = open;
    document.body.classList.toggle("has-mobile-menu", open);
    if (open) {
        mobileMenuTrigger = toggle;
        requestAnimationFrame(() => sidebar?.querySelector("a[href], button:not([disabled])")?.focus());
    } else {
        const trigger = mobileMenuTrigger;
        mobileMenuTrigger = null;
        trigger?.focus({ preventScroll: true });
    }
}

function addCustomField(form) {
    const list = document.querySelector("[data-custom-field-list]");
    const nameInput = form.querySelector("[name='field_name']");
    const valueInput = form.querySelector("[name='field_value']");
    const name = nameInput?.value.trim();
    const value = valueInput?.value.trim();
    if (!list || !name || !value) {
        showToast("Renseignez le nom et la valeur du champ.", "warning");
        return false;
    }

    const row = document.createElement("div");
    row.className = "custom-field";
    row.dataset.customField = "";
    const label = document.createElement("span");
    label.className = "custom-field__label";
    label.textContent = name;
    const content = document.createElement("span");
    content.className = "custom-field__value";
    content.textContent = value;
    const remove = document.createElement("button");
    remove.className = "button button--ghost button--icon";
    remove.type = "button";
    remove.setAttribute("aria-label", `Supprimer le champ ${name}`);
    remove.dataset.customFieldRemove = "";
    const icon = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    icon.setAttribute("class", "icon");
    icon.setAttribute("aria-hidden", "true");
    const use = document.createElementNS("http://www.w3.org/2000/svg", "use");
    use.setAttribute("href", `${document.documentElement.dataset.icons ?? "/assets/img/icons.svg"}#trash`);
    icon.append(use);
    remove.append(icon);
    row.append(label, content, remove);
    list.append(row);
    form.reset();
    showToast("Champ personnalisé ajouté.");
    return true;
}

function initCommandPalette() {
    const dialog = document.querySelector("[data-command-palette]");
    const input = dialog?.querySelector("[data-command-search]");
    const items = Array.from(dialog?.querySelectorAll("[data-command-item]") ?? []);
    const empty = dialog?.querySelector("[data-command-empty]");

    const filterItems = () => {
        const query = input?.value.trim().toLocaleLowerCase("fr") ?? "";
        let visibleCount = 0;
        items.forEach((item) => {
            const matches = item.textContent.toLocaleLowerCase("fr").includes(query);
            item.hidden = !matches;
            if (matches) visibleCount += 1;
        });
        if (empty) empty.hidden = visibleCount > 0;
    };

    input?.addEventListener("input", filterItems);
    dialog?.addEventListener("close", () => {
        if (input) input.value = "";
        filterItems();
    });

    document.addEventListener("keydown", (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
            event.preventDefault();
            openDialog(dialog);
            window.setTimeout(() => input?.focus(), 0);
        }
    });
}

function initLargeTitles() {
    const titles = document.querySelectorAll("[data-large-title]");
    if (!titles.length) return;
    let scheduled = false;
    const update = () => {
        titles.forEach((title) => title.classList.toggle("is-condensed", window.scrollY > 72));
        scheduled = false;
    };
    window.addEventListener("scroll", () => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(update);
    }, { passive: true });
    update();
}

function normalizeSearch(value) {
    return value.toLocaleLowerCase("fr").normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

function initFilters() {
    const controls = document.querySelectorAll("[data-table-search], [data-card-search], [data-filter-select]");
    const refresh = (selector) => {
        if (!selector) return;
        const container = document.querySelector(selector);
        if (!container) return;
        const rows = Array.from(container.querySelectorAll("[data-filter-row], [data-project-card]"));
        const search = document.querySelector(`[data-table-search='${selector}'], [data-card-search='${selector}']`);
        const select = document.querySelector(`[data-filter-select='${selector}']`);
        const query = normalizeSearch(search?.value.trim() ?? "");
        const filter = normalizeSearch(select?.value.trim() ?? "");
        rows.forEach((row) => {
            const matchesText = !query || normalizeSearch(row.textContent).includes(query);
            const matchesFilter = !filter || normalizeSearch(row.dataset.filterValue ?? row.dataset.status ?? "").includes(filter);
            row.hidden = !(matchesText && matchesFilter);
        });
    };
    controls.forEach((control) => {
        const selector = control.dataset.tableSearch ?? control.dataset.cardSearch ?? control.dataset.filterSelect;
        control.addEventListener(control.matches("select") ? "change" : "input", () => refresh(selector));
    });
}

function initViewToggle() {
    document.querySelectorAll("[data-view-toggle]").forEach((toggle) => {
        toggle.addEventListener("click", (event) => {
            const button = event.target.closest("[data-view-mode]");
            if (!button) return;
            const container = document.querySelector(toggle.dataset.viewToggle);
            if (!container) return;
            const isList = button.dataset.viewMode === "list";
            container.classList.toggle("is-list", isList);
            toggle.querySelectorAll("[data-view-mode]").forEach((item) => {
                item.setAttribute("aria-pressed", String(item === button));
            });
        });
    });
}

function initTimer() {
    const timer = document.querySelector("[data-timer]");
    const display = timer?.querySelector("[data-timer-display]");
    const toggle = timer?.querySelector("[data-timer-toggle]");
    const status = timer?.querySelector("[data-timer-status]");
    if (!timer || !display || !toggle) return;

    let seconds = Number(timer.dataset.elapsed ?? 0);
    let intervalId = 0;
    const render = () => {
        const hours = String(Math.floor(seconds / 3600)).padStart(2, "0");
        const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, "0");
        const remaining = String(seconds % 60).padStart(2, "0");
        display.textContent = `${hours}:${minutes}:${remaining}`;
    };
    const setRunning = (running) => {
        timer.classList.toggle("is-running", running);
        toggle.setAttribute("aria-pressed", String(running));
        toggle.querySelector("[data-timer-label]").textContent = running ? "Mettre en pause" : "Démarrer le chronomètre";
        if (status) status.textContent = running ? "Chronomètre en cours" : "Prêt à démarrer";
        if (running) intervalId = window.setInterval(() => { seconds += 1; render(); }, 1000);
        else window.clearInterval(intervalId);
    };
    toggle.addEventListener("click", () => {
        const running = toggle.getAttribute("aria-pressed") === "true";
        setRunning(!running);
    });
    timer.querySelector("[data-timer-reset]")?.addEventListener("click", () => {
        setRunning(false);
        seconds = 0;
        render();
        showToast("Chronomètre réinitialisé.");
    });
    render();
}

function initGeographySelects() {
    const form = document.querySelector("[data-geography-form]");
    const governorate = form?.querySelector("[data-governorate-select]");
    const delegation = form?.querySelector("[data-delegation-select]");
    const postalInput = form?.querySelector("[data-postal-input]");
    const postalOptions = form?.querySelector("[data-postal-options]");
    if (!form || !governorate || !delegation) return;

    const setPlaceholder = (select, label) => {
        select.replaceChildren(new Option(label, ""));
    };
    const loadPostalCodes = async (delegationId) => {
        if (!postalOptions) return;
        postalOptions.replaceChildren();
        if (!delegationId) return;
        const endpoint = form.dataset.postalCodesUrl;
        if (!endpoint) return;
        try {
            const response = await fetch(`${endpoint}?delegation_id=${encodeURIComponent(delegationId)}`, {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("postal-codes");
            const records = await response.json();
            records.forEach((record) => {
                const option = document.createElement("option");
                option.value = String(record.code ?? "");
                option.label = String(document.documentElement.lang === "ar" ? (record.locality_ar ?? "") : (record.locality_fr ?? ""));
                postalOptions.append(option);
            });
        } catch {
            showToast("Les codes postaux ne sont pas disponibles pour cette délégation.", "info");
        }
    };
    const loadDelegations = async (governorateId, selectedId = "") => {
        setPlaceholder(delegation, "Choisir une délégation");
        delegation.disabled = !governorateId;
        if (!governorateId) {
            if (postalInput) postalInput.value = "";
            await loadPostalCodes("");
            return;
        }

        const endpoint = form.dataset.delegationsUrl;
        if (!endpoint) return;
        try {
            const response = await fetch(`${endpoint}?governorate_id=${encodeURIComponent(governorateId)}`, {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("delegations");
            const records = await response.json();
            const options = document.createDocumentFragment();
            records.forEach((record) => {
                const option = document.createElement("option");
                option.value = String(record.id);
                option.textContent = String(document.documentElement.lang === "ar" ? (record.name_ar ?? "") : (record.name_latin ?? ""));
                options.append(option);
            });
            delegation.append(options);
            delegation.disabled = false;
            if (selectedId && records.some((record) => String(record.id) === String(selectedId))) {
                delegation.value = String(selectedId);
            }
            if (postalInput && !selectedId) postalInput.value = "";
            await loadPostalCodes(delegation.value);
        } catch {
            delegation.disabled = true;
            showToast("Impossible de charger les délégations. Réessayez.", "error");
        }
    };

    const initialGovernorate = governorate.value;
    const initialDelegation = delegation.dataset.selected || delegation.value;
    const initialPostalCode = postalInput?.value ?? "";
    loadDelegations(initialGovernorate, initialDelegation).then(() => {
        if (postalInput) postalInput.value = initialPostalCode;
    });
    governorate.addEventListener("change", () => loadDelegations(governorate.value));
    delegation.addEventListener("change", () => {
        if (postalInput) postalInput.value = "";
        loadPostalCodes(delegation.value);
    });
}

function initVatRatesSelect() {
    const ratesInput = document.querySelector("[name='vat_rates']");
    const defaultSelect = document.querySelector("[name='vat_default']");
    if (!(ratesInput instanceof HTMLInputElement) || !(defaultSelect instanceof HTMLSelectElement)) return;

    const refreshOptions = () => {
        const previous = defaultSelect.value;
        const rates = [...new Set(ratesInput.value.split(",").map((rate) => rate.trim()))]
            .filter((rate) => /^(?:100|[1-9]?[0-9])$/.test(rate));
        defaultSelect.replaceChildren();
        if (rates.length === 0) {
            const placeholder = new Option("Choisir un taux", "");
            placeholder.disabled = true;
            placeholder.selected = true;
            defaultSelect.append(placeholder);
            return;
        }
        rates.forEach((rate) => defaultSelect.append(new Option(`${rate} %`, rate)));
        defaultSelect.value = rates.includes(previous) ? previous : rates[0];
    };

    ratesInput.addEventListener("input", refreshOptions);
}

function initUpload() {
    const zone = document.querySelector("[data-upload-zone]");
    const input = zone?.querySelector("input[type='file']");
    const name = zone?.querySelector("[data-upload-name]");
    if (!zone || !input) return;
    const showFile = (file) => {
        if (!file) return;
        if (!file.name.toLowerCase().endsWith(".zip")) {
            showToast("Choisissez une archive au format .zip.", "warning");
            return;
        }
        if (name) name.textContent = file.name;
        showToast("Archive prête pour la prévisualisation. Aucun téléversement n'est effectué.", "info");
    };
    input.addEventListener("change", () => showFile(input.files?.[0]));
    ["dragenter", "dragover"].forEach((type) => zone.addEventListener(type, (event) => {
        event.preventDefault();
        zone.classList.add("is-dragover");
    }));
    ["dragleave", "drop"].forEach((type) => zone.addEventListener(type, (event) => {
        event.preventDefault();
        zone.classList.remove("is-dragover");
    }));
    zone.addEventListener("drop", (event) => showFile(event.dataTransfer?.files?.[0]));
}

export function initUI() {
    document.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const openButton = target.closest("[data-modal-open], [data-command-open]");
        if (openButton) {
            const selector = openButton.dataset.modalOpen ?? "[data-command-palette]";
            openDialog(document.querySelector(selector));
        }

        const closeButton = target.closest("[data-modal-close]");
        if (closeButton) closeDialog(closeButton.closest("dialog"));

        const tab = target.closest("[data-tab-target]");
        if (tab) activateTab(tab);

        const dropdownButton = target.closest("[data-dropdown-toggle]");
        if (dropdownButton) {
            const dropdown = dropdownButton.closest("[data-dropdown]");
            const menu = dropdown?.querySelector("[data-dropdown-menu]");
            const expanded = dropdownButton.getAttribute("aria-expanded") === "true";
            dropdownButton.setAttribute("aria-expanded", String(!expanded));
            if (menu) menu.hidden = expanded;
        }
        if (!target.closest("[data-dropdown]") || target.closest(".dropdown__item")) {
            document.querySelectorAll("[data-dropdown-toggle][aria-expanded='true']").forEach((button) => {
                button.setAttribute("aria-expanded", "false");
                const menu = button.closest("[data-dropdown]")?.querySelector("[data-dropdown-menu]");
                if (menu) menu.hidden = true;
            });
        }

        const switchButton = target.closest("[data-switch]");
        if (switchButton) {
            const checked = switchButton.getAttribute("aria-checked") !== "true";
            switchButton.setAttribute("aria-checked", String(checked));
            const moduleCard = switchButton.closest(".module-card");
            const moduleName = moduleCard?.querySelector(".module-card__title")?.textContent.trim();
            if (moduleName) {
                switchButton.setAttribute("aria-label", `${checked ? "Désactiver" : "Activer"} le module ${moduleName}`);
                const badge = moduleCard.querySelector(".module-card__footer .badge");
                if (badge) {
                    badge.textContent = checked ? "Activé" : "Désactivé";
                    badge.classList.toggle("badge--success", checked);
                    badge.classList.toggle("badge--neutral", !checked);
                }
            }
        }

        const toastButton = target.closest("[data-toast-message]");
        if (toastButton) {
            if (toastButton instanceof HTMLAnchorElement && toastButton.getAttribute("href")?.startsWith("#")) event.preventDefault();
            showToast(toastButton.dataset.toastMessage || "Action terminée.", toastButton.dataset.toastTone || "success");
        }

        const removeField = target.closest("[data-custom-field-remove]");
        if (removeField) {
            removeField.closest("[data-custom-field]")?.remove();
            showToast("Champ personnalisé supprimé.");
        }

        const printButton = target.closest("[data-print]");
        if (printButton) window.print();

        if (target.closest("[data-mobile-menu-toggle]")) {
            setMobileMenu(!document.querySelector("[data-app-shell]")?.classList.contains("is-mobile-menu-open"));
        }
        if (target.closest("[data-mobile-menu-backdrop]")) setMobileMenu(false);
        if (target.closest(".sidebar-link") && window.matchMedia("(max-width: 760px)").matches) setMobileMenu(false);
    });

    document.addEventListener("submit", (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.matches("[data-custom-field-form]")) {
            event.preventDefault();
            if (addCustomField(form)) closeDialog(form.closest("dialog"));
        } else if (form.matches("[data-demo-form]")) {
            event.preventDefault();
            showToast("Démo uniquement : aucune donnée n'a été enregistrée.", "info");
        }
    });

    document.addEventListener("keydown", (event) => {
        const shell = document.querySelector("[data-app-shell]");
        if (event.key === "Escape") {
            setMobileMenu(false);
            document.querySelectorAll("[data-dropdown-toggle][aria-expanded='true']").forEach((button) => {
                button.setAttribute("aria-expanded", "false");
                const menu = button.closest("[data-dropdown]")?.querySelector("[data-dropdown-menu]");
                if (menu) menu.hidden = true;
            });
        }
        if (event.key === "Tab" && shell?.classList.contains("is-mobile-menu-open")) {
            const focusable = Array.from(shell.querySelectorAll(".sidebar a[href], .sidebar button:not([disabled])"));
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });

    document.querySelectorAll("[data-toast-close]").forEach((button) => {
        button.addEventListener("click", () => {
            const toast = button.closest("[data-toast]");
            toast?.classList.remove("is-visible");
            if (toast) toast.hidden = true;
        });
    });

    initCommandPalette();
    initTabKeyboard();
    initLargeTitles();
    initFilters();
    initViewToggle();
    initTimer();
    initUpload();
    initGeographySelects();
    initVatRatesSelect();
}
