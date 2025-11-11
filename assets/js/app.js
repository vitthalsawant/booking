const bookingPortalState = {
    spaces: Array.isArray(window.bookingPortal?.initialSpaces)
        ? window.bookingPortal.initialSpaces
        : [],
    spacesById: {},
    filters: {
        space_type: '',
        date: '',
        start_time: '',
        end_time: '',
        people: 1,
        location_term: '',
        location_id: ''
    },
    locationSelection: null,
    selectedSpace: null,
    myBookings: [],
    loading: false
};

const elements = {};
let suggestionDebounce = null;

document.addEventListener('DOMContentLoaded', () => {
    cacheElements();
    hydrateInitialFilters();
    mapInitialSpaces(bookingPortalState.spaces);
    renderSpaces(bookingPortalState.spaces);
    updateResultsCount();
    renderMyBookings();
    attachEventListeners();
});

function cacheElements() {
    elements.filterForm = document.getElementById('filterForm');
    elements.resultsList = document.getElementById('resultsList');
    elements.resultsCount = document.getElementById('resultsCount');
    elements.resultsStatus = document.getElementById('resultsStatus');
    elements.locationInput = document.getElementById('location');
    elements.locationIdInput = document.getElementById('locationId');
    elements.locationSuggestions = document.getElementById('locationSuggestions');
    elements.resetButton = document.getElementById('resetFilters');
    elements.bookingModal = document.getElementById('bookingModal');
    elements.bookingForm = document.getElementById('bookingForm');
    elements.bookingFeedback = document.getElementById('bookingFeedback');
    elements.summaryDate = document.getElementById('summaryDate');
    elements.summaryTime = document.getElementById('summaryTime');
    elements.summaryPeople = document.getElementById('summaryPeople');
    elements.summaryDuration = document.getElementById('summaryDuration');
    elements.summaryTotal = document.getElementById('summaryTotal');
    elements.modalSpaceName = document.getElementById('modalSpaceName');
    elements.modalSpaceLocation = document.getElementById('modalSpaceLocation');
    elements.bookingSpaceId = document.getElementById('bookingSpaceId');
    elements.bookingDate = document.getElementById('bookingDate');
    elements.bookingStart = document.getElementById('bookingStartTime');
    elements.bookingEnd = document.getElementById('bookingEndTime');
    elements.bookingPeople = document.getElementById('bookingPeople');
    elements.myBookingsModal = document.getElementById('myBookingsModal');
    elements.myBookingsList = document.getElementById('myBookingsList');
}

function hydrateInitialFilters() {
    const form = elements.filterForm;
    if (!form) {
        return;
    }

    bookingPortalState.filters.space_type = form.space_type?.value || '';
    bookingPortalState.filters.date = form.date?.value || '';
    bookingPortalState.filters.start_time = form.start_time?.value || '';
    bookingPortalState.filters.end_time = form.end_time?.value || '';
    bookingPortalState.filters.people = Number(form.people?.value || 1);
    bookingPortalState.filters.location_term = form.location_term?.value || '';
    bookingPortalState.filters.location_id = form.location_id?.value || '';
}

function mapInitialSpaces(spaces) {
    bookingPortalState.spacesById = {};
    spaces.forEach((space) => {
        if (!space) {
            return;
        }
        const id = Number(space.id);
        bookingPortalState.spacesById[id] = normaliseSpace(space);
    });
}

function normaliseSpace(space) {
    return {
        id: Number(space.id),
        name: space.name,
        capacity: Number(space.capacity),
        hourly_rate: Number(space.hourly_rate),
        description: space.description || '',
        type_name: space.type_name || '',
        type_slug: space.type_slug || '',
        city: space.city || '',
        area: space.area || '',
        location_label: space.location_label || '',
        duration_hours: space.duration_hours !== null && space.duration_hours !== undefined
            ? Number(space.duration_hours)
            : null,
        total_price: space.total_price !== null && space.total_price !== undefined
            ? Number(space.total_price)
            : null,
        pricing: space.pricing
            ? {
                base_price: Number(space.pricing.base_price ?? 0),
                total_price: Number(space.pricing.total_price ?? 0),
                duration: Number(space.pricing.duration ?? 0),
                category_multiplier: Number(space.pricing.category_multiplier ?? 1),
                duration_multiplier: Number(space.pricing.duration_multiplier ?? 1)
            }
            : null
    };
}

function attachEventListeners() {
    if (!elements.filterForm) {
        return;
    }

    elements.filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
    });

    Array.from(elements.filterForm.querySelectorAll('input, select'))
        .forEach((field) => {
            field.addEventListener('change', handleFilterChange);
            if (field.type === 'text') {
                field.addEventListener('input', handleFilterChange);
            }
        });

    if (elements.locationInput) {
        elements.locationInput.addEventListener('input', handleLocationInput);
        elements.locationInput.addEventListener('focus', showLocationSuggestions);
    }

    document.addEventListener('click', (event) => {
        if (!elements.locationSuggestions) {
            return;
        }

        if (elements.locationSuggestions.contains(event.target)) {
            return;
        }

        if (event.target === elements.locationInput) {
            return;
        }

        hideLocationSuggestions();
    });

    if (elements.resetButton) {
        elements.resetButton.addEventListener('click', resetFilters);
    }

    if (elements.resultsList) {
        elements.resultsList.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-action="open-booking"]');
            if (!trigger) {
                return;
            }
            const spaceId = Number(trigger.getAttribute('data-space-id'));
            openBookingForSpace(spaceId);
        });
    }

    document.querySelectorAll('[data-action="close-modal"]').forEach((node) => {
        node.addEventListener('click', closeBookingModal);
    });

    if (elements.bookingModal) {
        elements.bookingModal.addEventListener('click', (event) => {
            if (event.target === elements.bookingModal) {
                closeBookingModal();
            }
        });
    }

    if (elements.bookingForm) {
        elements.bookingForm.addEventListener('submit', handleBookingSubmit);
    }

    document.querySelectorAll('[data-action="close-bookings"]').forEach((node) => {
        node.addEventListener('click', closeBookingsModal);
    });

    if (elements.myBookingsModal) {
        elements.myBookingsModal.addEventListener('click', (event) => {
            if (event.target === elements.myBookingsModal) {
                closeBookingsModal();
            }
        });
    }
}

function handleFilterChange(event) {
    const field = event.target;
    if (!field?.name) {
        return;
    }

    if (field.name === 'people') {
        bookingPortalState.filters.people = Number(field.value || 1);
    } else if (field.name in bookingPortalState.filters) {
        bookingPortalState.filters[field.name] = field.value;
    }

    if (field.name === 'date') {
        enforceDateConstraints(field.value);
    }

    if (field.name === 'start_time' || field.name === 'end_time') {
        const isValid = validateTimeRange(
            elements.filterForm.start_time.value,
            elements.filterForm.end_time.value
        );
        if (!isValid) {
            updateStatus('Select an end time that is later than the start time.', 'error');
            return;
        }
    }

    fetchSpacesWithCurrentFilters();
}

function enforceDateConstraints(value) {
    if (!elements.filterForm) {
        return;
    }
    const min = elements.filterForm.date?.getAttribute('min');
    if (min && value && value < min) {
        elements.filterForm.date.value = min;
        bookingPortalState.filters.date = min;
    }
}

function validateTimeRange(startTime, endTime) {
    if (!startTime || !endTime) {
        return true;
    }
    return startTime < endTime;
}

function handleLocationInput(event) {
    const term = event.target.value.trim();
    bookingPortalState.filters.location_term = term;
    bookingPortalState.filters.location_id = '';
    elements.locationIdInput.value = '';

    if (term.length < 2) {
        hideLocationSuggestions();
        return;
    }

    showLocationSuggestions();

    if (suggestionDebounce) {
        clearTimeout(suggestionDebounce);
    }

    suggestionDebounce = setTimeout(() => {
        fetchLocationSuggestions(term);
    }, 200);
}

async function fetchLocationSuggestions(term) {
    try {
        const response = await fetch(`api/filter.php?action=suggest&term=${encodeURIComponent(term)}`);
        if (!response.ok) {
            throw new Error('Unable to fetch locations');
        }
        const payload = await response.json();
        renderLocationSuggestions(payload.suggestions || []);
    } catch (error) {
        console.error(error);
    }
}

function renderLocationSuggestions(suggestions) {
    if (!elements.locationSuggestions) {
        return;
    }

    if (!suggestions.length) {
        elements.locationSuggestions.innerHTML = `<div class="suggestion-item">No matches</div>`;
        elements.locationSuggestions.hidden = false;
        return;
    }

    elements.locationSuggestions.innerHTML = suggestions.map((suggestion) => {
        return `
            <div class="suggestion-item" data-suggestion-id="${suggestion.id}" data-label="${escapeHtml(suggestion.label)}">
                ${escapeHtml(suggestion.label)}
            </div>
        `;
    }).join('');
    elements.locationSuggestions.hidden = false;

    Array.from(elements.locationSuggestions.querySelectorAll('.suggestion-item'))
        .forEach((item) => {
            item.addEventListener('click', () => {
                const id = item.getAttribute('data-suggestion-id');
                const label = item.getAttribute('data-label');
                bookingPortalState.filters.location_id = id;
                bookingPortalState.filters.location_term = label;
                elements.locationInput.value = label;
                elements.locationIdInput.value = id;
                hideLocationSuggestions();
                fetchSpacesWithCurrentFilters();
            });
        });
}

function showLocationSuggestions() {
    if (elements.locationSuggestions) {
        elements.locationSuggestions.hidden = false;
    }
}

function hideLocationSuggestions() {
    if (elements.locationSuggestions) {
        elements.locationSuggestions.hidden = true;
    }
}

function resetFilters() {
    if (!elements.filterForm) {
        return;
    }

    elements.filterForm.reset();
    elements.locationIdInput.value = '';
    bookingPortalState.filters = {
        space_type: '',
        date: '',
        start_time: '',
        end_time: '',
        people: 1,
        location_term: '',
        location_id: ''
    };

    fetchSpacesWithCurrentFilters();
}

async function fetchSpacesWithCurrentFilters() {
    if (!elements.filterForm) {
        return;
    }

    const formData = new FormData(elements.filterForm);
    formData.set('people', String(bookingPortalState.filters.people));

    updateStatus('Updating results...');
    bookingPortalState.loading = true;

    try {
        const response = await fetch('api/filter.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Unable to fetch spaces.');
        }

        const payload = await response.json();
        if (!payload.success) {
            throw new Error(payload.message || 'Unable to filter spaces.');
        }

        const spaces = Array.isArray(payload.spaces) ? payload.spaces.map(normaliseSpace) : [];
        mapInitialSpaces(spaces);
        bookingPortalState.spaces = spaces;
        renderSpaces(spaces);
        updateResultsCount();
        updateStatus(spaces.length ? 'Results refreshed.' : 'No spaces match your filters.', spaces.length ? 'success' : 'info');
    } catch (error) {
        console.error(error);
        updateStatus(error.message, 'error');
    } finally {
        bookingPortalState.loading = false;
    }
}

function renderSpaces(spaces) {
    if (!elements.resultsList) {
        return;
    }

    if (!spaces.length) {
        elements.resultsList.innerHTML = `
            <div class="space-card">
                <p>No spaces available. Adjust your filters to try again.</p>
            </div>
        `;
        return;
    }

    elements.resultsList.innerHTML = spaces.map((space) => {
        const priceText = space.total_price
            ? `${formatCurrency(space.total_price)} total`
            : `${formatCurrency(space.hourly_rate)} per hour`;
        const durationText = space.total_price && space.duration_hours
            ? `${space.duration_hours} hrs`
            : 'Select date & time for pricing';

        const categoryMultiplier = space.pricing ? Number(space.pricing.category_multiplier || 1) : 1;
        const durationMultiplier = space.pricing ? Number(space.pricing.duration_multiplier || 1) : 1;
        const multiplierText = space.pricing
            ? `Base ${formatCurrency(space.pricing.base_price)} × Category ${categoryMultiplier.toFixed(2)} × Duration ${durationMultiplier.toFixed(2)}`
            : 'Standard rate applies';

        const description = space.description
            ? `<p>${escapeHtml(space.description)}</p>`
            : '';

        return `
            <article class="space-card" data-space-id="${space.id}">
                <div>
                    <h3>${escapeHtml(space.name)}</h3>
                    <p class="space-location">${escapeHtml(space.location_label || `${space.area}, ${space.city}`)}</p>
                </div>
                <div class="space-meta">
                    <span>${escapeHtml(space.type_name)}</span>
                    <span>${space.capacity} people</span>
                </div>
                ${description}
                <div class="space-pricing">
                    <div>
                        <strong>${priceText}</strong>
                        <p>${durationText}</p>
                        <p class="space-pricing-note">${multiplierText}</p>
                    </div>
                    <button type="button" class="primary-button" data-action="open-booking" data-space-id="${space.id}">
                        Book now
                    </button>
                </div>
            </article>
        `;
    }).join('');
}

function updateResultsCount() {
    if (!elements.resultsCount) {
        return;
    }
    const count = bookingPortalState.spaces.length;
    elements.resultsCount.textContent = `Showing ${count} space${count === 1 ? '' : 's'}`;
}

function updateStatus(message, type = 'info') {
    if (!elements.resultsStatus) {
        return;
    }
    elements.resultsStatus.textContent = message;
    elements.resultsStatus.dataset.type = type;
}

function openBookingForSpace(spaceId) {
    if (!elements.filterForm) {
        return;
    }

    const filters = bookingPortalState.filters;
    if (!filters.date || !filters.start_time || !filters.end_time) {
        updateStatus('Select a date and time range to book a space.', 'error');
        return;
    }

    const space = bookingPortalState.spacesById[spaceId];
    if (!space) {
        updateStatus('Unable to find the selected space.', 'error');
        return;
    }

    bookingPortalState.selectedSpace = space;
    populateBookingModal(space);
    showBookingModal();
}

function populateBookingModal(space) {
    if (elements.bookingForm) {
        elements.bookingForm.reset();
    }

    elements.modalSpaceName.textContent = space.name;
    elements.modalSpaceLocation.textContent = space.location_label || `${space.area}, ${space.city}`;

    elements.bookingSpaceId.value = String(space.id);
    elements.bookingDate.value = bookingPortalState.filters.date;
    elements.bookingStart.value = bookingPortalState.filters.start_time;
    elements.bookingEnd.value = bookingPortalState.filters.end_time;
    elements.bookingPeople.value = String(bookingPortalState.filters.people || 1);

    elements.summaryDate.textContent = bookingPortalState.filters.date || '-';
    elements.summaryTime.textContent = bookingPortalState.filters.start_time && bookingPortalState.filters.end_time
        ? `${bookingPortalState.filters.start_time} -> ${bookingPortalState.filters.end_time}`
        : '-';
    elements.summaryPeople.textContent = bookingPortalState.filters.people || '-';

    if (space.total_price && space.duration_hours) {
        const categoryMultiplier = space.pricing ? Number(space.pricing.category_multiplier || 1) : 1;
        const durationMultiplier = space.pricing ? Number(space.pricing.duration_multiplier || 1) : 1;
        elements.summaryDuration.textContent = `${space.duration_hours} hrs`;
        elements.summaryTotal.textContent = `${formatCurrency(space.total_price)} (Category ×${categoryMultiplier.toFixed(2)}, Duration ×${durationMultiplier.toFixed(2)})`;
    } else {
        elements.summaryDuration.textContent = '-';
        elements.summaryTotal.textContent = 'Calculated at submission';
    }

    if (elements.bookingFeedback) {
        elements.bookingFeedback.textContent = '';
        elements.bookingFeedback.className = 'form-feedback';
    }
}

function showBookingModal() {
    if (!elements.bookingModal) {
        return;
    }
    elements.bookingModal.hidden = false;
    elements.bookingModal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeBookingModal() {
    if (!elements.bookingModal) {
        return;
    }
    elements.bookingModal.hidden = true;
    elements.bookingModal.classList.remove('show');
    document.body.style.overflow = '';
    bookingPortalState.selectedSpace = null;
}

function closeBookingsModal() {
    if (!elements.myBookingsModal) {
        return;
    }
    elements.myBookingsModal.hidden = true;
    elements.myBookingsModal.classList.remove('show');
    document.body.style.overflow = '';
}

async function handleBookingSubmit(event) {
    event.preventDefault();

    if (!elements.bookingForm) {
        return;
    }

    const formData = new FormData(elements.bookingForm);

    if (!formData.get('space_id')) {
        setBookingFeedback('Please select a space to book.', 'error');
        return;
    }

    if (!formData.get('people')) {
        formData.set('people', String(bookingPortalState.filters.people || 1));
    }

    setBookingFeedback('Submitting booking...', 'info');

    // Debug: Log form data being sent
    const formDataEntries = {};
    for (const [key, value] of formData.entries()) {
        formDataEntries[key] = value;
    }
    console.log('Submitting booking with data:', formDataEntries);

    try {
        const response = await fetch('api/booking.php', {
            method: 'POST',
            body: formData
        });

        // Read response as text first, then parse as JSON
        const responseText = await response.text();
        let payload;
        
        try {
            payload = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('Invalid JSON response:', responseText);
            throw new Error('Server returned an invalid response. Please try again.');
        }

        if (!response.ok || !payload.success) {
            const errorMsg = payload.message || 'Booking failed. Please try again.';
            if (payload.debug) {
                console.error('Booking API error:', payload.debug);
            }
            throw new Error(errorMsg);
        }

        if (payload.booking) {
            addBookingToState(payload.booking);
        }

        setBookingFeedback('Booking confirmed! Opening your bookings...', 'success');

        setTimeout(() => {
            closeBookingModal();
            renderMyBookings();
            openMyBookingsModal();
        }, 600);

        fetchSpacesWithCurrentFilters();
    } catch (error) {
        console.error('Booking submission error:', error);
        setBookingFeedback(error.message || 'An unexpected error occurred. Please try again.', 'error');
    }
}

function setBookingFeedback(message, type) {
    if (!elements.bookingFeedback) {
        return;
    }
    elements.bookingFeedback.textContent = message;
    elements.bookingFeedback.className = `form-feedback ${type}`;
}

function addBookingToState(booking) {
    if (!booking) {
        return;
    }

    const existingIndex = bookingPortalState.myBookings.findIndex(
        (item) => Number(item.id) === Number(booking.id)
    );

    if (existingIndex >= 0) {
        bookingPortalState.myBookings.splice(existingIndex, 1);
    }

    bookingPortalState.myBookings.unshift(booking);
}

function renderMyBookings() {
    if (!elements.myBookingsList) {
        return;
    }

    if (!bookingPortalState.myBookings.length) {
        elements.myBookingsList.innerHTML = `
            <div class="booking-chip">
                <p>No bookings yet. Confirm a space to see it here.</p>
            </div>
        `;
        return;
    }

    elements.myBookingsList.innerHTML = bookingPortalState.myBookings.map((booking) => {
        const reference = booking.reference
            ? `<span>${escapeHtml(booking.reference)}</span>`
            : '';
        const categoryMultiplier = booking.pricing ? Number(booking.pricing.category_multiplier || 1) : 1;
        const durationMultiplier = booking.pricing ? Number(booking.pricing.duration_multiplier || 1) : 1;
        const basePrice = booking.pricing ? Number(booking.pricing.base_price || 0) : null;
        const multipliers = `Category ×${categoryMultiplier.toFixed(2)}, Duration ×${durationMultiplier.toFixed(2)}`;
        return `
            <div class="booking-chip">
                <h4>${escapeHtml(booking.space_name)} ${reference}</h4>
                <p>${escapeHtml(booking.location || '')}</p>
                <p>${booking.date} | ${booking.start_time} -> ${booking.end_time}</p>
                <p>${booking.people} people | ${formatCurrency(Number(booking.total_price))}</p>
                ${basePrice ? `<p class="booking-breakdown">Base ${formatCurrency(basePrice)} · ${multipliers}</p>` : ''}
            </div>
        `;
    }).join('');
}

function openMyBookingsModal() {
    if (!elements.myBookingsModal) {
        return;
    }
    elements.myBookingsModal.hidden = false;
    elements.myBookingsModal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function formatCurrency(value) {
    if (Number.isNaN(value)) {
        return '₹0.00';
    }
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2
    }).format(value);
}
