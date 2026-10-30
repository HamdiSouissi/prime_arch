function initDocumentForms() {
    const form = document.querySelector("[data-document-form]");
    if (!(form instanceof HTMLFormElement)) return;

    const type = form.querySelector("[data-document-type]");
    const locale = form.querySelector("[data-document-locale]");
    const template = form.querySelector("[data-document-template]");
    const title = form.querySelector("[data-document-title]");
    const terms = form.querySelector("[data-document-terms]");
    const legal = form.querySelector("[data-document-legal]");
    const validityDays = form.querySelector("[name='validity_days']");
    const paymentDays = form.querySelector("[name='payment_terms_days']");
    const client = form.querySelector("[data-document-client]");
    const project = form.querySelector("[data-document-project]");
    const billingKind = form.querySelector("[data-document-billing-kind]");
    const reference = form.querySelector("[data-document-reference]");
    const referenceWrap = form.querySelector("[data-reference-field]");
    const referenceLabel = form.querySelector("[data-document-reference-label]");
    const phaseWrap = form.querySelector("[data-phase-field]");
    const phase = form.querySelector("[data-document-phase]");
    const invoiceFields = form.querySelector("[data-invoice-fields]");
    const creditNoteTaxNote = form.querySelector("[data-credit-note-tax-note]");
    const taxControls = Array.from(form.querySelectorAll("[data-document-tax-control]"));
    const lineList = form.querySelector("[data-document-lines]");
    const lineTemplate = form.querySelector("[data-document-line-template]");

    const applyTemplate = (option) => {
        if (!option || option.value === "") return;
        if (title) title.value = option.dataset.title ?? "";
        if (terms) terms.value = option.dataset.terms ?? "";
        if (legal) legal.value = option.dataset.legal ?? "";
        if (validityDays) validityDays.value = option.dataset.validityDays ?? "0";
        if (paymentDays) paymentDays.value = option.dataset.paymentDays ?? "0";
    };

    const filterTemplates = (chooseDefault = false) => {
        if (!(template instanceof HTMLSelectElement)) return;
        const selectedType = type?.value ?? "quote";
        const selectedLocale = locale?.value ?? "fr";
        const matching = Array.from(template.options).filter((option) => {
            if (!option.value) return true;
            const matches = option.dataset.documentType === selectedType && option.dataset.locale === selectedLocale;
            option.hidden = !matches;
            option.disabled = !matches;
            return matches;
        });
        const current = template.selectedOptions[0];
        const currentIsValid = current?.value === "" || (
            current?.dataset.documentType === selectedType && current?.dataset.locale === selectedLocale
        );
        if (!currentIsValid || chooseDefault) {
            const preferred = matching.find((option) => option.value && option.dataset.default === "1")
                ?? matching.find((option) => option.value);
            template.value = preferred?.value ?? "";
            if (preferred) applyTemplate(preferred);
        }
    };

    const filterProjects = () => {
        if (!(project instanceof HTMLSelectElement)) return;
        const clientId = client instanceof HTMLSelectElement ? client.value : "";
        Array.from(project.options).forEach((option) => {
            if (!option.value) return;
            const matches = clientId !== "" && option.dataset.clientId === clientId;
            option.hidden = !matches;
            option.disabled = !matches;
        });
        const selected = project.selectedOptions[0];
        if (selected?.value && (clientId === "" || selected.dataset.clientId !== clientId)) {
            project.value = "";
        }
        filterPhases();
    };

    const filterPhases = () => {
        if (!(phase instanceof HTMLSelectElement)) return;
        const projectId = project instanceof HTMLSelectElement ? project.value : "";
        Array.from(phase.options).forEach((option) => {
            if (!option.value) return;
            const matches = projectId !== "" && option.dataset.projectId === projectId;
            option.hidden = !matches;
            option.disabled = !matches;
        });
        const selected = phase.selectedOptions[0];
        if (selected?.value && (projectId === "" || selected.dataset.projectId !== projectId)) {
            phase.value = "";
        }
    };

    const filterReferences = () => {
        const selectedType = type?.value ?? "quote";
        const isInvoice = selectedType === "invoice";
        const isCreditNote = selectedType === "credit_note";
        if (invoiceFields) invoiceFields.hidden = !isInvoice;
        if (referenceWrap) referenceWrap.hidden = !isInvoice && !isCreditNote;
        if (reference instanceof HTMLSelectElement) {
            reference.required = isCreditNote;
            Array.from(reference.options).forEach((option) => {
                if (!option.value) return;
                const referenceType = option.dataset.referenceType;
                const matches = isCreditNote
                    ? referenceType === "invoice"
                    : isInvoice && ["quote", "contract"].includes(referenceType);
                option.hidden = !matches;
                option.disabled = !matches;
            });
            const selected = reference.selectedOptions[0];
            if (selected?.value && selected.disabled) reference.value = "";
        }
        if (referenceLabel) referenceLabel.textContent = isCreditNote
            ? referenceLabel.dataset.creditNoteLabel ?? referenceLabel.textContent
            : referenceLabel.dataset.referenceLabel ?? referenceLabel.textContent;
        updateReferenceDefaults(false);
    };

    const updateReferenceDefaults = (force = false) => {
        if (!(reference instanceof HTMLSelectElement)) return;
        const selected = reference.selectedOptions[0];
        if (!selected?.value) return;
        if (client instanceof HTMLSelectElement && (force || client.value === "")) {
            client.value = selected.dataset.clientId ?? "";
            client.dispatchEvent(new Event("change", { bubbles: true }));
        }
        if (project instanceof HTMLSelectElement && (force || project.value === "")) {
            project.value = selected.dataset.projectId ?? "";
            project.dispatchEvent(new Event("change", { bubbles: true }));
        }
        if (type?.value === "credit_note") {
            const taxRate = form.querySelector("[name='tax_rate']");
            const stampAmount = form.querySelector("[name='stamp_amount']");
            const withholdingRate = form.querySelector("[name='withholding_rate']");
            if (taxRate) taxRate.value = selected.dataset.taxRate ?? taxRate.value;
            if (stampAmount) stampAmount.value = selected.dataset.stampAmount ?? stampAmount.value;
            if (withholdingRate) withholdingRate.value = selected.dataset.withholdingRate ?? withholdingRate.value;
        }
    };

    const updatePhaseVisibility = () => {
        const required = type?.value === "invoice" && billingKind?.value === "phase";
        if (phaseWrap) phaseWrap.hidden = !required;
        if (phase) {
            phase.required = required;
            if (!required) phase.value = "";
        }
    };

    const updateDocumentType = (changeTemplate = true) => {
        const selectedType = type?.value ?? "quote";
        const isCreditNote = selectedType === "credit_note";
        if (billingKind && selectedType === "invoice" && billingKind.value === "") billingKind.value = "standard";
        if (billingKind && selectedType !== "invoice") billingKind.value = "";
        taxControls.forEach((control) => { control.disabled = isCreditNote; });
        if (creditNoteTaxNote) creditNoteTaxNote.hidden = !isCreditNote;
        filterReferences();
        updatePhaseVisibility();
        if (changeTemplate) filterTemplates(true);
    };

    const renumberLines = () => {
        if (!lineList) return;
        const rows = Array.from(lineList.querySelectorAll("[data-document-line]"));
        rows.forEach((row, index) => {
            row.querySelectorAll("[data-line-name]").forEach((input) => {
                const field = input.dataset.lineName;
                input.name = `lines[${index}][${field}]`;
                const id = `document-line-${field}-${index}`;
                input.id = id;
                row.querySelector(`[data-line-label='${field}']`)?.setAttribute("for", id);
            });
            row.querySelectorAll("[id^='doc-line-']").forEach((input) => {
                const field = input.name.match(/\[([^\]]+)\]$/)?.[1] ?? "field";
                input.name = `lines[${index}][${field}]`;
                input.id = `document-line-${field}-${index}`;
                row.querySelector(`label[for^='doc-line-${field}-']`)?.setAttribute("for", input.id);
            });
            const remove = row.querySelector("[data-document-remove-line]");
            if (remove) remove.disabled = rows.length <= 1;
        });
    };

    form.addEventListener("change", (event) => {
        const target = event.target;
        if (target === type || target === locale) {
            updateDocumentType(true);
        }
        if (target === template && template instanceof HTMLSelectElement) {
            applyTemplate(template.selectedOptions[0]);
        }
        if (target === client) filterProjects();
        if (target === project) filterPhases();
        if (target === billingKind) updatePhaseVisibility();
        if (target === reference) updateReferenceDefaults(true);
    });

    form.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        if (target.closest("[data-document-add-line]") && lineList && lineTemplate instanceof HTMLTemplateElement) {
            lineList.append(lineTemplate.content.cloneNode(true));
            renumberLines();
        }
        const remove = target.closest("[data-document-remove-line]");
        if (remove && lineList?.querySelectorAll("[data-document-line]").length > 1) {
            remove.closest("[data-document-line]")?.remove();
            renumberLines();
        }
    });

    filterProjects();
    filterTemplates(false);
    updateDocumentType(false);
    updateReferenceDefaults(false);
    renumberLines();
}

export function initDocuments() {
    initDocumentForms();
}
