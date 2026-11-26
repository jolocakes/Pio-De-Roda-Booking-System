<?php
/**
 * Shared admin alert popup modal.
 * Separate UI component (not top page-alert bar).
 * Include once per admin page and load admin-alert.css + admin-alert.js.
 */
?>
<div class="pdr-alert-overlay" id="pdrAlertModal" aria-hidden="true">
    <div class="pdr-alert-card" role="dialog" aria-modal="true" aria-labelledby="pdrAlertTitle">
        <div class="pdr-alert-head">
            <div class="pdr-alert-title-wrap">
                <span class="pdr-alert-icon" id="pdrAlertIcon"><i class="fa-solid fa-circle-info"></i></span>
                <h3 class="pdr-alert-title" id="pdrAlertTitle">Notice</h3>
            </div>
            <button type="button" class="pdr-alert-close" id="pdrAlertCloseX" aria-label="Close">&times;</button>
        </div>
        <div class="pdr-alert-body">
            <p class="pdr-alert-message" id="pdrAlertMessage"></p>
        </div>
        <div class="pdr-alert-footer" id="pdrAlertFooter">
            <button type="button" class="pdr-alert-btn pdr-alert-btn-primary" id="pdrAlertOkBtn">OK</button>
            <button type="button" class="pdr-alert-btn pdr-alert-btn-secondary" id="pdrAlertCancelBtn" style="display:none;">Cancel</button>
            <button type="button" class="pdr-alert-btn pdr-alert-btn-primary" id="pdrAlertConfirmBtn" style="display:none;">Confirm</button>
        </div>
    </div>
</div>
