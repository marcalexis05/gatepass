// Gatepass Pro Global Scripts
document.addEventListener('DOMContentLoaded', () => {
    // Utility functions for UI animations and notifications
    console.log("GatePass Pro client-side engine loaded.");
    
    // Auto-dismiss alert notifications after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 500ms ease';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // Initialize custom select dropdowns globally
    initCustomSelects();
    initCustomDatePickers();
});

function initCustomSelects() {
    // Find all select elements that haven't been customized yet
    const selectElements = document.querySelectorAll('select:not([data-customized])');
    
    selectElements.forEach(select => {
        // Mark select as customized to prevent double initialization
        select.setAttribute('data-customized', 'true');
        
        // Hide native select
        select.style.display = 'none';
        
        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select-wrapper';
        if (select.className) {
            // Copy width and custom alignment classes if any
            if (select.classList.contains('w-full')) wrapper.classList.add('w-full');
        }
        
        // Insert wrapper before select in DOM and move select inside it
        // Check BEFORE moving: does parent have a left absolute icon?
        const parentEl = select.parentNode;
        const leftIconSpan = parentEl ? parentEl.querySelector(':scope > span.absolute') : null;
        const hasLeftIcon = !!leftIconSpan;

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        // Ensure left icon stays above the wrapper (z-index)
        if (leftIconSpan) {
            leftIconSpan.style.zIndex = '2';
            leftIconSpan.style.pointerEvents = 'none';
        }
        
        // Create trigger button
        const trigger = document.createElement('div');
        trigger.className = 'custom-select-trigger';

        // If there's a left icon, add matching left padding so text doesn't overlap
        if (hasLeftIcon) {
            trigger.style.paddingLeft = '2.25rem'; // equivalent to pl-9
        }
        
        const triggerText = document.createElement('span');
        triggerText.className = 'trigger-text';
        
        // Get initial text
        const selectedOption = select.options[select.selectedIndex];
        triggerText.textContent = selectedOption ? selectedOption.textContent : 'Select option';
        
        const arrow = document.createElement('i');
        arrow.className = 'fa-solid fa-chevron-down text-slate-500 text-[10px] transition-transform duration-255';
        
        trigger.appendChild(triggerText);
        trigger.appendChild(arrow);
        wrapper.appendChild(trigger);
        
        // Create options container dropdown
        const dropdown = document.createElement('div');
        dropdown.className = 'custom-select-dropdown';
        wrapper.appendChild(dropdown);
        
        // Populate options
        Array.from(select.options).forEach((opt, idx) => {
            const optionDiv = document.createElement('div');
            optionDiv.className = 'custom-select-option';
            optionDiv.textContent = opt.textContent;
            
            if (opt.selected) {
                optionDiv.classList.add('selected');
            }
            
            optionDiv.addEventListener('click', (e) => {
                e.stopPropagation();
                
                // Update select value
                select.selectedIndex = idx;
                
                // Dispatch change event to notify any listeners (AJAX scripts)
                select.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Update trigger text
                triggerText.textContent = opt.textContent;
                
                // Update selection visual classes
                dropdown.querySelectorAll('.custom-select-option').forEach(o => o.classList.remove('selected'));
                optionDiv.classList.add('selected');
                
                // Close dropdown
                closeDropdown();
            });
            
            dropdown.appendChild(optionDiv);
        });
        
        // Toggle dropdown open/close
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            
            const isOpen = dropdown.classList.contains('show');
            
            // Close all other dropdowns first
            document.querySelectorAll('.custom-select-dropdown').forEach(d => d.classList.remove('show'));
            document.querySelectorAll('.custom-select-trigger').forEach(t => t.classList.remove('open'));
            document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open-wrapper'));
            document.querySelectorAll('.custom-select-trigger i').forEach(a => a.style.transform = 'rotate(0deg)');
            
            document.querySelectorAll('.custom-date-dropdown').forEach(d => d.classList.remove('show'));
            document.querySelectorAll('.custom-date-trigger').forEach(t => t.classList.remove('open'));
            document.querySelectorAll('.custom-date-wrapper').forEach(w => w.classList.remove('open-wrapper'));
            
            if (!isOpen) {
                dropdown.classList.add('show');
                trigger.classList.add('open');
                wrapper.classList.add('open-wrapper');
                arrow.style.transform = 'rotate(180deg)';
            }
        });
        
        function closeDropdown() {
            dropdown.classList.remove('show');
            trigger.classList.remove('open');
            wrapper.classList.remove('open-wrapper');
            arrow.style.transform = 'rotate(0deg)';
        }
    });
    
    // Close all custom select dropdowns when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('.custom-select-dropdown').forEach(d => d.classList.remove('show'));
        document.querySelectorAll('.custom-select-trigger').forEach(t => t.classList.remove('open'));
        document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open-wrapper'));
        document.querySelectorAll('.custom-select-trigger i').forEach(a => a.style.transform = 'rotate(0deg)');
        
        document.querySelectorAll('.custom-date-dropdown').forEach(d => d.classList.remove('show'));
        document.querySelectorAll('.custom-date-trigger').forEach(t => t.classList.remove('open'));
        document.querySelectorAll('.custom-date-wrapper').forEach(w => w.classList.remove('open-wrapper'));
    });
}

function initCustomDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]:not([data-customized])');
    
    dateInputs.forEach(input => {
        // Skip inputs explicitly marked as no-picker (e.g. readonly today-only fields)
        if (input.hasAttribute('data-no-picker')) return;
        input.setAttribute('data-customized', 'true');
        input.style.display = 'none';
        
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-date-wrapper';
        if (input.classList.contains('w-full')) wrapper.classList.add('w-full');
        
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        const trigger = document.createElement('div');
        trigger.className = 'custom-date-trigger';
        
        const triggerText = document.createElement('span');
        triggerText.className = 'trigger-text';
        
        const calendarIcon = document.createElement('i');
        calendarIcon.className = 'fa-solid fa-calendar text-slate-500 text-[10px]';
        
        trigger.appendChild(triggerText);
        trigger.appendChild(calendarIcon);
        wrapper.appendChild(trigger);
        
        const dropdown = document.createElement('div');
        dropdown.className = 'custom-date-dropdown';
        wrapper.appendChild(dropdown);
        
        let currentYear, currentMonth;
        
        const initialDate = input.value ? new Date(input.value) : new Date();
        currentYear = initialDate.getFullYear();
        currentMonth = initialDate.getMonth();
        
        updateTriggerText();
        
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = dropdown.classList.contains('show');
            
            // Close all select and date dropdowns
            document.querySelectorAll('.custom-select-dropdown').forEach(d => d.classList.remove('show'));
            document.querySelectorAll('.custom-select-trigger').forEach(t => t.classList.remove('open'));
            document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open-wrapper'));
            document.querySelectorAll('.custom-select-trigger i').forEach(a => a.style.transform = 'rotate(0deg)');
            
            document.querySelectorAll('.custom-date-dropdown').forEach(d => d.classList.remove('show'));
            document.querySelectorAll('.custom-date-trigger').forEach(t => t.classList.remove('open'));
            document.querySelectorAll('.custom-date-wrapper').forEach(w => w.classList.remove('open-wrapper'));
            
            if (!isOpen) {
                dropdown.classList.add('show');
                trigger.classList.add('open');
                wrapper.classList.add('open-wrapper');
                renderCalendar();
            }
        });
        
        function updateTriggerText() {
            if (input.value) {
                const dateObj = new Date(input.value);
                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                triggerText.textContent = dateObj.toLocaleDateString('en-US', options);
            } else {
                triggerText.textContent = 'mm/dd/yyyy';
            }
        }
        
        function renderCalendar() {
            dropdown.innerHTML = '';
            
            const header = document.createElement('div');
            header.className = 'calendar-header';
            
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'calendar-btn';
            prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
            prevBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                renderCalendar();
            });
            
            const title = document.createElement('span');
            title.className = 'calendar-title';
            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            title.textContent = `${monthNames[currentMonth]} ${currentYear}`;
            
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'calendar-btn';
            nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
            nextBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                renderCalendar();
            });
            
            header.appendChild(prevBtn);
            header.appendChild(title);
            header.appendChild(nextBtn);
            dropdown.appendChild(header);
            
            const weekdays = document.createElement('div');
            weekdays.className = 'calendar-weekdays';
            ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"].forEach(day => {
                const dayDiv = document.createElement('div');
                dayDiv.textContent = day;
                weekdays.appendChild(dayDiv);
            });
            dropdown.appendChild(weekdays);
            
            const daysGrid = document.createElement('div');
            daysGrid.className = 'calendar-days';
            
            const firstDayIndex = new Date(currentYear, currentMonth, 1).getDay();
            const lastDay = new Date(currentYear, currentMonth + 1, 0).getDate();
            
            for (let i = 0; i < firstDayIndex; i++) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'calendar-day empty';
                daysGrid.appendChild(emptyDiv);
            }
            
            const today = new Date();
            const selectedDate = input.value ? new Date(input.value) : null;
            
            for (let day = 1; day <= lastDay; day++) {
                const dayDiv = document.createElement('div');
                dayDiv.className = 'calendar-day';
                dayDiv.textContent = day;
                
                if (today.getDate() === day && today.getMonth() === currentMonth && today.getFullYear() === currentYear) {
                    dayDiv.classList.add('today');
                }
                
                if (selectedDate && selectedDate.getDate() === day && selectedDate.getMonth() === currentMonth && selectedDate.getFullYear() === currentYear) {
                    dayDiv.classList.add('selected');
                }
                
                dayDiv.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const pad = (num) => String(num).padStart(2, '0');
                    const formattedDate = `${currentYear}-${pad(currentMonth + 1)}-${pad(day)}`;
                    input.value = formattedDate;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    updateTriggerText();
                    closeDropdown();
                });
                
                daysGrid.appendChild(dayDiv);
            }
            dropdown.appendChild(daysGrid);
            
            const footer = document.createElement('div');
            footer.className = 'calendar-footer';
            
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'calendar-footer-btn';
            clearBtn.textContent = 'Clear';
            clearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                updateTriggerText();
                closeDropdown();
            });
            
            const todayBtn = document.createElement('button');
            todayBtn.type = 'button';
            todayBtn.className = 'calendar-footer-btn';
            todayBtn.textContent = 'Today';
            todayBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const pad = (num) => String(num).padStart(2, '0');
                const formattedDate = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
                input.value = formattedDate;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                updateTriggerText();
                closeDropdown();
            });
            
            footer.appendChild(clearBtn);
            footer.appendChild(todayBtn);
            dropdown.appendChild(footer);
        }
        
        function closeDropdown() {
            dropdown.classList.remove('show');
            trigger.classList.remove('open');
            wrapper.classList.remove('open-wrapper');
        }
    });
}

function showConfirmModal(message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.className = 'custom-modal-overlay';
    
    const card = document.createElement('div');
    card.className = 'custom-modal-card';
    
    const icon = document.createElement('div');
    icon.className = 'custom-modal-icon';
    icon.innerHTML = '<i class="fa-solid fa-box-archive"></i>';
    
    const title = document.createElement('h3');
    title.className = 'custom-modal-title';
    title.textContent = 'Confirm Action';
    
    const msg = document.createElement('p');
    msg.className = 'custom-modal-message';
    msg.textContent = message;
    
    const actions = document.createElement('div');
    actions.className = 'custom-modal-actions';
    
    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'custom-modal-btn custom-modal-btn-cancel';
    cancelBtn.textContent = 'Cancel';
    
    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'button';
    confirmBtn.className = 'custom-modal-btn custom-modal-btn-confirm';
    confirmBtn.textContent = 'Confirm';
    
    actions.appendChild(cancelBtn);
    actions.appendChild(confirmBtn);
    
    card.appendChild(icon);
    card.appendChild(title);
    card.appendChild(msg);
    card.appendChild(actions);
    overlay.appendChild(card);
    
    document.body.appendChild(overlay);
    
    setTimeout(() => overlay.classList.add('show'), 10);
    
    cancelBtn.addEventListener('click', () => {
        closeModal();
    });
    
    confirmBtn.addEventListener('click', () => {
        closeModal();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    });
    
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closeModal();
        }
    });
    
    function closeModal() {
        overlay.classList.remove('show');
        setTimeout(() => {
            overlay.remove();
        }, 300);
    }
}

function showSuccessToast(message = 'Action completed successfully.', title = 'Success') {
    // Remove any existing toasts to avoid stacking
    document.querySelectorAll('.success-toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = 'success-toast';

    toast.innerHTML = `
        <div class="success-toast-icon">
            <i class="fa-solid fa-check"></i>
        </div>
        <div class="success-toast-body">
            <p class="success-toast-title">${title}</p>
            <p class="success-toast-message">${message}</p>
        </div>
        <button class="success-toast-close" onclick="this.closest('.success-toast').remove()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="success-toast-progress"></div>
    `;

    document.body.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });
    });

    // Auto-dismiss after 4 seconds
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}
