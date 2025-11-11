<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$pageTitle = 'Space Booking Portal';
$spaceTypes = fetch_space_types();
$initialSpaces = find_available_spaces([]);
$today = (new DateTime())->format('Y-m-d');

require __DIR__ . '/partials/header.php';
?>

<section class="hero">
    <h2>Find the perfect workspace</h2>
    <p>Filter by space type, availability, and capacity to book instantly.</p>
</section>

<section class="filters">
    <form id="filterForm" class="filters-form" autocomplete="off">
        <div class="form-row">
            <label for="spaceType">Space type</label>
            <select id="spaceType" name="space_type">
                <option value="">All spaces</option>
                <?php foreach ($spaceTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type['slug']); ?>">
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" min="<?php echo htmlspecialchars($today); ?>">
        </div>

        <div class="form-row">
            <label for="startTime">Time from</label>
            <input type="time" id="startTime" name="start_time" step="1800">
        </div>

        <div class="form-row">
            <label for="endTime">Time until</label>
            <input type="time" id="endTime" name="end_time" step="1800">
        </div>

        <div class="form-row">
            <label for="people">People</label>
            <input type="number" id="people" name="people" min="1" max="50" value="1">
        </div>

        <div class="form-row form-row-location">
            <label for="location">Location</label>
            <input type="text" id="location" name="location_term" placeholder="Search city or area">
            <input type="hidden" id="locationId" name="location_id">
            <div id="locationSuggestions" class="suggestions-panel" hidden></div>
        </div>

        <div class="form-row form-row-actions">
            <button type="button" id="resetFilters" class="secondary-button">Reset</button>
        </div>
    </form>
</section>

<section class="results">
    <header class="results-header">
        <div>
            <h2>Available spaces</h2>
            <p id="resultsCount">Showing 0 spaces</p>
        </div>
        <div class="results-status" id="resultsStatus" aria-live="polite"></div>
    </header>

    <div class="results-grid">
        <div class="results-list" id="resultsList" aria-live="polite"></div>
        <aside class="map-panel">
            <div class="map-placeholder">
                <h3>Map preview</h3>
                <p>A simplified list view is available on the left.</p>
            </div>
        </aside>
    </div>
</section>

<div class="modal" id="bookingModal" hidden>
    <div class="modal-backdrop" data-action="close-modal"></div>
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalSpaceName">
        <button type="button" class="modal-close" data-action="close-modal" aria-label="Close booking form">&times;</button>
        <header class="modal-header">
            <h3 id="modalSpaceName">Booking details</h3>
            <p id="modalSpaceLocation" class="modal-location"></p>
        </header>
        <section class="modal-body">
            <article class="price-summary" id="priceSummary">
                <h4>Price summary</h4>
                <ul>
                    <li><span>Date:</span> <strong id="summaryDate">-</strong></li>
                    <li><span>Time:</span> <strong id="summaryTime">-</strong></li>
                    <li><span>People:</span> <strong id="summaryPeople">-</strong></li>
                    <li><span>Duration:</span> <strong id="summaryDuration">-</strong></li>
                    <li><span>Total:</span> <strong id="summaryTotal">-</strong></li>
                </ul>
            </article>

            <form id="bookingForm" class="booking-form">
                <input type="hidden" name="space_id" id="bookingSpaceId">
                <input type="hidden" name="date" id="bookingDate">
                <input type="hidden" name="start_time" id="bookingStartTime">
                <input type="hidden" name="end_time" id="bookingEndTime">
                <input type="hidden" name="people" id="bookingPeople">

                <div class="form-row">
                    <label for="customerName">Your name</label>
                    <input type="text" id="customerName" name="customer_name" required>
                </div>

                <div class="form-row">
                    <label for="customerEmail">Email</label>
                    <input type="email" id="customerEmail" name="customer_email" required>
                </div>

                <div class="form-row">
                    <label for="customerPhone">Phone</label>
                    <input type="tel" id="customerPhone" name="customer_phone">
                </div>

                <div class="form-row">
                    <label for="bookingNotes">Notes</label>
                    <textarea id="bookingNotes" name="notes" rows="3" placeholder="Share additional details"></textarea>
                </div>

                <div class="form-row form-row-actions">
                    <button type="submit" class="primary-button">Submit booking request</button>
                </div>

                <div id="bookingFeedback" class="form-feedback" role="status" aria-live="polite"></div>
            </form>
        </section>
    </div>
</div>

<div class="modal" id="myBookingsModal" hidden>
    <div class="modal-backdrop" data-action="close-bookings"></div>
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="myBookingsTitle">
        <button type="button" class="modal-close" data-action="close-bookings" aria-label="Close my bookings">&times;</button>
        <header class="modal-header">
            <h3 id="myBookingsTitle">My bookings</h3>
        </header>
        <section class="modal-body">
            <div id="myBookingsList" class="bookings-list"></div>
        </section>
    </div>
 </div>

<script>
window.bookingPortal = {
    initialSpaces: <?php echo json_encode($initialSpaces, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
    filters: {
        minDate: '<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>'
    }
};
</script>

<?php
require __DIR__ . '/partials/footer.php';
