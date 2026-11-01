<script>
// Theme-native single-select: the real select owns form validation and submitted values.
        (function () {
            let current = null;
            let optionsPanel = null;
            let triggerButton = null;
            let panelIndex = 0;
            function closePicker(restoreFocus) {
                if (!current) return;
                current.classList.remove('is-open');
                triggerButton.setAttribute('aria-expanded', 'false');
                if(optionsPanel.matches(':popover-open'))optionsPanel.hidePopover();
                optionsPanel.hidden = true;
                optionsPanel.style.display = 'none';
                if (restoreFocus) triggerButton.focus();
                current = null;
            }
            function positionPicker() {
                if (!current) return;
                const rect = triggerButton.getBoundingClientRect();
                optionsPanel.style.margin='0';optionsPanel.style.inset='auto';
                const below = window.innerHeight - rect.bottom;
                const height = Math.min(optionsPanel.scrollHeight, 260, window.innerHeight * .4);
                const width = Math.min(rect.width, window.innerWidth - 24);
                optionsPanel.style.width = width + 'px';
                optionsPanel.style.left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12)) + 'px';
                optionsPanel.style.top = (below >= height + 8 || below >= rect.top ? rect.bottom + 4 : Math.max(4, rect.top - height - 4)) + 'px';
            }
            function openPicker(shell) {
                if (current === shell) { closePicker(true); return; }
                closePicker(false);
                const select = shell.querySelector('select');
                const trigger = shell.querySelector('.select-trigger');
                const panel = shell._selectOptions;
                (shell.closest('dialog[open], .modal-overlay') || document.body).appendChild(panel);
                panel.replaceChildren();
                const entries = [...select.options];
                if (entries.length > 8) {
                    const search = document.createElement('input');
                    search.type = 'search';
                    search.className = 'form-control';
                    search.placeholder = 'Cari pilihan...';
                    search.setAttribute('aria-label', 'Cari pilihan');
                    panel.appendChild(search);
                    search.addEventListener('input', () => {
                        panel.querySelectorAll('.select-option').forEach(option => {
                            option.hidden = !option.textContent.toLocaleLowerCase('id').includes(search.value.toLocaleLowerCase('id'));
                        });
                    });
                }
                entries.forEach((entry, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'select-option';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', String(entry.selected));
                    option.textContent = entry.textContent;
                    option.disabled = entry.disabled;
                    option.addEventListener('click', () => {
                        select.selectedIndex = index;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        closePicker(true);
                    });
                    panel.appendChild(option);
                });
                current = shell;
                optionsPanel = panel;
                triggerButton = trigger;
                panel.hidden = false;
                panel.style.display = 'block';
                if(panel.showPopover)panel.showPopover();
                shell.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                trigger.setAttribute('aria-controls', panel.id);
                positionPicker();
                (panel.querySelector('input') || panel.querySelector('.select-option[aria-selected="true"]:not(:disabled)') || panel.querySelector('.select-option:not(:disabled)'))?.focus();
            }
            function enhanceSelects(root) {
                root.querySelectorAll('select:not([multiple]):not([data-native-select])').forEach(select => {
                    if (select.closest('.select-shell') || select.hidden || select.closest('template')) return;
                    const shell = document.createElement('div');
                    shell.className = 'select-shell';
                    select.parentNode.insertBefore(shell, select);
                    shell.appendChild(select);
                    const trigger = document.createElement('button');
                    trigger.type = 'button';
                    trigger.className = 'select-trigger';
                    trigger.setAttribute('aria-haspopup', 'listbox');
                    trigger.setAttribute('aria-expanded', 'false');
                    const label = select.id ? document.querySelector('label[for="' + CSS.escape(select.id) + '"]') : null;
                    const labelText = label?.textContent.trim() || select.getAttribute('aria-label') || select.name.replace(/\[.*$/, '').replaceAll('_', ' ');
                    trigger.setAttribute('aria-label', labelText);
                    const panel = document.createElement('div');
                    panel.className = 'select-options';
                    panel.id = 'select-options-' + (++panelIndex);
                    panel.setAttribute('role', 'listbox');
                    panel.setAttribute('aria-label', labelText);
                    panel.hidden = true;
                    panel.setAttribute('popover','manual');
                    shell._selectOptions = panel;
                    shell.appendChild(trigger);
                    shell.classList.add('is-enhanced');
                    select.tabIndex = -1;
                    select.setAttribute('aria-hidden', 'true');
                    function sync() {
                        trigger.textContent = select.selectedOptions[0]?.textContent || 'Pilih...';
                        trigger.disabled = select.disabled;
                        trigger.setAttribute('aria-label',labelText+': '+trigger.textContent);
                        shell.classList.toggle('is-invalid', !select.validity.valid);
                    }
                    select.addEventListener('change', sync);
                    new MutationObserver(sync).observe(select,{attributes:true,attributeFilter:['disabled']});
                    select.addEventListener('invalid', event => {
                        event.preventDefault();
                        sync();
                        trigger.focus();
                        if (current !== shell) openPicker(shell);
                    });
                    trigger.addEventListener('click', () => openPicker(shell));
                    trigger.addEventListener('keydown', event => {
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            if (current !== shell) openPicker(shell);
                        }
                    });
                    panel.addEventListener('keydown', event => {
                        const visible = [...panel.querySelectorAll('.select-option:not([hidden]):not(:disabled)')];
                        if (event.key === 'Tab') { closePicker(true); return; }
                        if (event.key === 'Escape') { event.stopPropagation(); closePicker(true); return; }
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            const index = visible.indexOf(document.activeElement);
                            visible[(index + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length]?.focus();
                        }
                        if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
                            event.preventDefault();
                            visible[0]?.click();
                        }
                    });
                    sync();
                });
            }
            document.addEventListener('DOMContentLoaded', () => {
                enhanceSelects(document);
                new MutationObserver(records => {
                    if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 && (node.matches?.('select') || node.querySelector?.('select'))))) {
                        enhanceSelects(document);
                    }
                }).observe(document.body, { childList: true, subtree: true });
            });
            document.addEventListener('close',event=>{if(current&&event.target.matches?.('dialog'))closePicker(false);},true);
            document.addEventListener('pointerdown', event => {
                if (current && !current.contains(event.target) && !optionsPanel.contains(event.target)) closePicker(false);
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && current) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    closePicker(true);
                }
            }, true);
            window.addEventListener('resize', positionPicker);
            window.addEventListener('scroll', positionPicker, true);
            window.enhanceSelects = enhanceSelects;
        })();
</script>
