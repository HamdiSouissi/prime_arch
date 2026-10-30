const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

/**
 * Messages traduits fournis par le serveur (français / arabe) sous forme de JSON.
 * Voir le bloc #app-i18n dans les layouts.
 */
const messages = (() => {
    const node = document.getElementById("app-i18n");
    if (!node) return {};
    try {
        return JSON.parse(node.textContent ?? "{}");
    } catch {
        return {};
    }
})();

/** Traduit une clé JS avec repli et remplacement des jetons :name. */
function t(key, fallback = "", values = {}) {
    let text = typeof messages[key] === "string" ? messages[key] : fallback;
    Object.entries(values).forEach(([name, value]) => {
        text = text.replaceAll(`:${name}`, String(value));
    });
    return text;
}

const htmlLocale = () => document.documentElement.lang || "fr";

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
        showToast(t("js.custom_field_required", "Renseignez le nom et la valeur du champ."), "warning");
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
    remove.setAttribute("aria-label", t("js.remove_field_named", `Supprimer le champ ${name}`, { name }));
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
    showToast(t("js.custom_field_added", "Champ personnalisé ajouté."));
    return true;
}

function initCommandPalette() {
    const dialog = document.querySelector("[data-command-palette]");
    const input = dialog?.querySelector("[data-command-search]");
    const list = dialog?.querySelector(".command-list");
    const items = Array.from(dialog?.querySelectorAll("[data-command-item]") ?? []);
    const empty = dialog?.querySelector("[data-command-empty]");

    // Libellé du groupe (« Navigation », « Actions »…) : il est masqué quand aucun de
    // ses éléments ne correspond à la recherche, pour éviter les titres vides.
    const groupOf = new Map();
    items.forEach((item) => {
        let label = item.previousElementSibling;
        while (label && !label.classList.contains("command-list__label")) label = label.previousElementSibling;
        groupOf.set(item, label);
    });

    const visibleItems = () => items.filter((item) => !item.hidden);

    const filterItems = () => {
        // Recherche insensible à la casse et aux accents (« devis » trouve « Dêvis »).
        const query = normalizeSearch(input?.value.trim() ?? "");
        items.forEach((item) => {
            item.hidden = query !== "" && !normalizeSearch(item.textContent).includes(query);
        });
        const visible = visibleItems();
        if (empty) empty.hidden = visible.length > 0;
        groupOf.forEach((label, item) => {
            if (!label) return;
            const siblings = items.filter((candidate) => groupOf.get(candidate) === label);
            label.hidden = siblings.every((candidate) => candidate.hidden);
        });
        if (visible.length > 0) {
            visible[0].tabIndex = 0;
            visible.slice(1).forEach((item) => { item.tabIndex = -1; });
        }
    };

    // Navigation au clavier dans la liste : les flèches déplacent le focus réel sur les
    // liens, donc Entrée ouvre la destination choisie sans code supplémentaire.
    const focusAt = (index) => {
        const visible = visibleItems();
        if (visible.length === 0) return;
        const target = visible[(index + visible.length) % visible.length];
        visible.forEach((item) => { item.tabIndex = -1; });
        target.tabIndex = 0;
        target.focus({ preventScroll: true });
        // `scrollIntoView` n’existe pas dans tous les environnements (tests, vieux moteurs).
        target.scrollIntoView?.({ block: "nearest" });
    };

    input?.addEventListener("input", filterItems);
    input?.addEventListener("keydown", (event) => {
        if (event.key === "ArrowDown") {
            event.preventDefault();
            focusAt(0);
        } else if (event.key === "Home") {
            event.preventDefault();
            focusAt(0);
        } else if (event.key === "End") {
            event.preventDefault();
            focusAt(visibleItems().length - 1);
        }
    });

    // Sur un élément de la liste : flèches, retour à la recherche, échappement.
    if (list) {
        list.addEventListener("keydown", (event) => {
            const current = event.target instanceof Element ? event.target.closest("[data-command-item]") : null;
            if (!current) return;
            const visible = visibleItems();
            const index = visible.indexOf(current);
            if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault();
                focusAt(index + (event.key === "ArrowDown" ? 1 : -1));
            } else if (event.key === "Home" || event.key === "End") {
                event.preventDefault();
                focusAt(event.key === "Home" ? 0 : visible.length - 1);
            } else if (event.key === "Escape" && input) {
                event.preventDefault();
                input.focus();
            }
        });
    }

    dialog?.addEventListener("close", () => {
        // Le navigateur rend déjà le focus au déclencheur : on ne fait que vider la recherche.
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

    filterItems();
}

function initShortcutHints() {
    // Le raccourci réel fonctionne avec ⌘ ou Ctrl : l'affichage suit la plateforme.
    const isApple = /Mac|iPhone|iPad|iPod/i.test(navigator.userAgentData?.platform ?? navigator.platform ?? "");
    document.querySelectorAll("[data-shortcut-modifier]").forEach((element) => {
        element.textContent = isApple ? "⌘" : "Ctrl";
    });
}

function initLocalClock() {
    const clock = document.querySelector("[data-local-clock]");
    const dateElement = clock?.querySelector("[data-clock-date]");
    const timeElement = clock?.querySelector("[data-clock-time]");
    if (!(clock instanceof HTMLTimeElement) || !dateElement || !timeElement) return;

    const timezone = clock.dataset.timezone || "Africa/Tunis";
    const locale = document.documentElement.lang === "ar" ? "ar-TN" : "fr-TN";
    try {
        const dateFormatter = new Intl.DateTimeFormat(locale, {
            timeZone: timezone,
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
        });
        const timeFormatter = new Intl.DateTimeFormat(locale, {
            timeZone: timezone,
            hour: "2-digit",
            minute: "2-digit",
            hourCycle: "h23",
        });
        const update = () => {
            const now = new Date();
            const date = dateFormatter.format(now);
            const time = timeFormatter.format(now);
            dateElement.textContent = date;
            timeElement.textContent = time;
            clock.dateTime = now.toISOString();
            clock.setAttribute("aria-label", `${clock.dataset.clockLabel ?? "Local time"} · ${date} ${time}`);
        };
        update();
        window.setInterval(update, 60_000);
    } catch {
        // Le texte rendu côté serveur reste disponible si le navigateur ne gère pas le fuseau configuré.
    }
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
        toggle.querySelector("[data-timer-label]").textContent = running
            ? t("js.timer_pause", "Mettre en pause")
            : t("js.timer_start", "Démarrer le chronomètre");
        if (status) status.textContent = running ? t("js.timer_running", "Chronomètre en cours") : t("js.timer_ready", "Prêt à démarrer");
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
        showToast(t("js.timer_reset", "Chronomètre réinitialisé."));
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

    const chooseDelegation = t("js.choose_delegation", "Choisir une délégation…");
    const setPlaceholder = (select, label) => {
        select.replaceChildren(new Option(label, ""));
    };
    const loadPostalCodes = async (delegationId) => {
        if (!postalOptions) return;
        postalOptions.replaceChildren();
        if (!delegationId && !governorate.value) return;
        const endpoint = form.dataset.postalCodesUrl;
        if (!endpoint) return;
        const query = delegationId
            ? `delegation_id=${encodeURIComponent(delegationId)}`
            : `governorate_id=${encodeURIComponent(governorate.value)}`;
        try {
            const response = await fetch(`${endpoint}?${query}`, {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("postal-codes");
            const records = await response.json();
            records.forEach((record) => {
                const option = document.createElement("option");
                option.value = String(record.code ?? "");
                const locality = document.documentElement.lang === "ar"
                    ? String(record.locality_ar || record.locality_latin || "")
                    : String(record.locality_latin || "");
                option.label = [String(record.source_delegation ?? ""), locality].filter(Boolean).join(" · ");
                postalOptions.append(option);
            });
        } catch {
            showToast(t("js.postal_unavailable", "Les codes postaux ne sont pas disponibles pour ce gouvernorat ou cette délégation."), "info");
        }
    };
    const loadDelegations = async (governorateId, selectedId = "") => {
        setPlaceholder(delegation, chooseDelegation);
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
            showToast(t("js.delegations_error", "Impossible de charger les délégations. Réessayez."), "error");
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

function initTimeEntryPhases() {
    const form = document.querySelector("[data-time-entry-form]");
    const projectSelect = form?.querySelector("[data-time-project-select]");
    const phaseSelect = form?.querySelector("[data-time-phase-select]");
    if (!projectSelect || !phaseSelect) return;

    const syncPhases = () => {
        Array.from(phaseSelect.options).forEach((option) => {
            if (!option.value) return;
            const available = option.dataset.projectId === projectSelect.value;
            option.hidden = !available;
            option.disabled = !available;
        });
        const selected = phaseSelect.selectedOptions[0];
        if (selected?.disabled) phaseSelect.value = "";
    };
    projectSelect.addEventListener("change", syncPhases);
    syncPhases();
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
            const placeholder = new Option(t("js.choose_rate", "Choisir un taux"), "");
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

function initCsvImports() {
    document.querySelectorAll("[data-csv-file-input]").forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        const field = input.closest(".field");
        const status = field?.querySelector("[data-csv-file-status]");
        if (!field || !status) return;
        const maxBytes = Number(input.closest("[data-csv-import-form]")?.dataset.fileMaxBytes ?? 5_242_880);

        input.addEventListener("change", () => {
            const file = input.files?.[0];
            if (!file) {
                input.setCustomValidity("");
                input.setAttribute("aria-invalid", "false");
                field.classList.remove("is-invalid", "is-valid");
                input.classList.remove("is-invalid", "is-valid");
                status.classList.remove("field__hint--error", "field__hint--success");
                status.textContent = "";
                return;
            }

            const valid = file.name.toLowerCase().endsWith(".csv") && file.size > 0 && file.size <= maxBytes;
            if (!valid) {
                const message = input.dataset.fileInvalid ?? "Choose a valid CSV file.";
                input.value = "";
                input.setCustomValidity(message);
                input.setAttribute("aria-invalid", "true");
                field.classList.add("is-invalid");
                field.classList.remove("is-valid");
                input.classList.add("is-invalid");
                input.classList.remove("is-valid");
                status.classList.add("field__hint--error");
                status.classList.remove("field__hint--success");
                status.textContent = message;
                return;
            }

            input.setCustomValidity("");
            input.setAttribute("aria-invalid", "false");
            field.classList.remove("is-invalid");
            field.classList.add("is-valid");
            input.classList.remove("is-invalid");
            input.classList.add("is-valid");
            status.classList.remove("field__hint--error");
            status.classList.add("field__hint--success");
            status.textContent = `${input.dataset.fileSelected ?? "File ready:"} ${file.name}`;
        });
    });
}

function initTunisianPhones() {
    document.querySelectorAll("[data-tunisian-phone]").forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        const field = input.closest(".phone-field");
        const status = field?.querySelector("[data-phone-validation]");
        let pattern;
        try {
            pattern = new RegExp(input.dataset.phonePattern ?? "");
        } catch {
            return;
        }
        if (!field || !status) return;

        const update = () => {
            const value = input.value.trim();
            if (value === "") {
                input.setCustomValidity("");
                input.setAttribute("aria-invalid", "false");
                input.classList.remove("is-invalid", "is-valid");
                field.classList.remove("is-invalid", "is-valid");
                status.classList.remove("field__hint--error", "field__hint--success");
                status.textContent = "";
                status.setAttribute("role", "status");
                return;
            }

            const valid = pattern.test(value);
            const message = valid ? (input.dataset.phoneValid ?? "") : (input.dataset.phoneInvalid ?? "");
            input.setCustomValidity(valid ? "" : message);
            input.setAttribute("aria-invalid", String(!valid));
            input.classList.toggle("is-invalid", !valid);
            input.classList.toggle("is-valid", valid);
            field.classList.toggle("is-invalid", !valid);
            field.classList.toggle("is-valid", valid);
            status.classList.toggle("field__hint--error", !valid);
            status.classList.toggle("field__hint--success", valid);
            status.textContent = message;
            status.setAttribute("role", "status");
        };

        input.addEventListener("input", update);
        input.addEventListener("blur", update);
        if (input.value.trim() !== "") update();
    });
}

function initValidationFeedback() {
    document.addEventListener("invalid", (event) => {
        const input = event.target;
        if (!(input instanceof HTMLElement)) return;
        input.classList.add("is-invalid");
        input.closest(".field")?.classList.add("is-invalid");
    }, true);

    document.addEventListener("input", (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement)) return;
        if (input.validity.valid) {
            input.classList.remove("is-invalid");
            input.closest(".field")?.classList.remove("is-invalid");
        }
    });
}

function initUpload() {
    const zone = document.querySelector("[data-upload-zone]");
    const input = zone?.querySelector("input[type='file']");
    const name = zone?.querySelector("[data-upload-name]");
    const isPreview = !zone?.hasAttribute("data-upload-real");
    if (!zone || !input) return;
    const showFile = (file, fromDrop = false) => {
        if (!file) return;
        if (!file.name.toLowerCase().endsWith(".zip")) {
            input.value = "";
            if (name) name.textContent = t("js.upload_zip_only", "Fichier .zip uniquement");
            showToast(t("js.archive_zip_required", "Choisissez une archive au format .zip."), "warning");
            return;
        }
        if (file.size > 25_000_000) {
            input.value = "";
            if (name) name.textContent = t("js.archive_too_large_label", "Archive de 25 Mo maximum");
            showToast(t("js.archive_too_large", "L’archive dépasse la limite de 25 Mo."), "warning");
            return;
        }
        if (fromDrop) {
            try {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
            } catch {
                showToast(t("js.archive_browse_hint", "Utilisez le bouton Parcourir pour sélectionner l’archive."), "warning");
                return;
            }
        }
        if (name) name.textContent = file.name;
        showToast(isPreview
            ? t("js.archive_preview_ready", "Archive prête pour la prévisualisation. Aucun téléversement n’est effectué.")
            : t("js.archive_selected", "Archive sélectionnée. Elle sera vérifiée côté serveur avant l’installation."), "info");
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
    zone.addEventListener("drop", (event) => showFile(event.dataTransfer?.files?.[0], true));
}

/** Confirmation native avant une action destructive (formulaire portant data-confirm). */
function initConfirmForms() {
    document.querySelectorAll("form[data-confirm]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            const message = form.getAttribute("data-confirm") || "";
            if (message !== "" && !window.confirm(message)) event.preventDefault();
        });
    });
}

/** Boutons d’impression génériques (procès-verbaux, bordereaux des modules). */
function initPrintButtons() {
    document.querySelectorAll("[data-print]").forEach((button) => {
        button.addEventListener("click", () => window.print());
    });
}

/** Copie le contenu d’un champ ciblé (brouillon de courrier, référence à transmettre). */
function initCopyButtons() {
    document.querySelectorAll("[data-copy]").forEach((button) => {
        button.addEventListener("click", async () => {
            const selector = button.getAttribute("data-copy") || "";
            const target = selector ? document.querySelector(selector) : null;
            if (!target) return;
            const text = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement
                ? target.value
                : target.textContent ?? "";
            let copied = false;
            try {
                await navigator.clipboard.writeText(text);
                copied = true;
            } catch {
                // Repli hors HTTPS ou pour les navigateurs sans API presse-papiers.
                if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                    target.focus();
                    target.select();
                } else {
                    const range = document.createRange();
                    range.selectNodeContents(target);
                    const selection = window.getSelection();
                    selection?.removeAllRanges();
                    selection?.addRange(range);
                }
                try {
                    copied = document.execCommand("copy");
                } catch {
                    copied = false;
                }
            }
            if (copied) showToast(t("js.copied", "Texte copié."));
        });
    });
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
                switchButton.setAttribute("aria-label", checked
                    ? t("js.module_deactivate_label", `Désactiver le module ${moduleName}`, { name: moduleName })
                    : t("js.module_activate_label", `Activer le module ${moduleName}`, { name: moduleName }));
                const badge = moduleCard.querySelector(".module-card__footer .badge");
                if (badge) {
                    badge.textContent = checked ? t("js.activated", "Activé") : t("js.deactivated", "Désactivé");
                    badge.classList.toggle("badge--success", checked);
                    badge.classList.toggle("badge--neutral", !checked);
                }
            }
        }

        const toastButton = target.closest("[data-toast-message]");
        if (toastButton) {
            if (toastButton instanceof HTMLAnchorElement && toastButton.getAttribute("href")?.startsWith("#")) event.preventDefault();
            showToast(toastButton.dataset.toastMessage || t("js.action_done", "Action terminée."), toastButton.dataset.toastTone || "success");
        }

        const removeField = target.closest("[data-custom-field-remove]");
        if (removeField) {
            removeField.closest("[data-custom-field]")?.remove();
            showToast(t("js.custom_field_removed", "Champ personnalisé supprimé."));
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
        if (form.dataset.confirmMessage && !window.confirm(form.dataset.confirmMessage)) {
            event.preventDefault();
            return;
        }
        if (form.matches("[data-custom-field-form]")) {
            event.preventDefault();
            if (addCustomField(form)) closeDialog(form.closest("dialog"));
        } else if (form.matches("[data-demo-form]")) {
            event.preventDefault();
            showToast(t("js.demo_only", "Démo uniquement : aucune donnée n’a été enregistrée."), "info");
        } else {
            form.classList.add("is-submitting");
            form.setAttribute("aria-busy", "true");
            // Le bouton déclencheur n'est jamais désactivé : un contrôle désactivé est exclu des
            // données envoyées, ce qui supprimerait sa valeur (name/value) du POST.
            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            form.querySelectorAll("button[type='submit'], input[type='submit']").forEach((button) => {
                if (button === submitter) {
                    button.setAttribute("aria-disabled", "true");
                    button.setAttribute("data-submitting", "true");
                    return;
                }
                button.disabled = true;
                button.setAttribute("data-disabled-by-submit", "true");
            });
        }
    });

    // Retour arrière ou restauration depuis le cache du navigateur : ne jamais laisser
    // un formulaire bloqué dans son état d'envoi (boutons inertes, champs désactivés).
    window.addEventListener("pageshow", () => {
        document.querySelectorAll("form.is-submitting").forEach((form) => {
            form.classList.remove("is-submitting");
            form.removeAttribute("aria-busy");
            form.querySelectorAll("[data-submitting], [data-disabled-by-submit]").forEach((control) => {
                control.removeAttribute("data-submitting");
                control.removeAttribute("data-disabled-by-submit");
                control.removeAttribute("aria-disabled");
                if (control instanceof HTMLButtonElement || control instanceof HTMLInputElement) {
                    control.disabled = false;
                }
            });
        });
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
    initShortcutHints();
    initLocalClock();
    initTabKeyboard();
    initLargeTitles();
    initFilters();
    initViewToggle();
    initTimer();
    initUpload();
    initTunisianPhones();
    initCsvImports();
    initValidationFeedback();
    initGeographySelects();
    initTimeEntryPhases();
    initVatRatesSelect();
    initPrintButtons();
    initCopyButtons();
    initConfirmForms();
}
