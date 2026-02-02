/**
 * Reorders the company_segments field in the company import form
 * to appear after the contact owner field in the first panel
 */
(function() {
    'use strict';

    function reorderCompanySegmentsField() {
        // Only run on import pages - check if the import form exists
        var importForm = document.querySelector('form[name="lead_field_import"]');
        if (!importForm) {
            return;
        }

        var companySegmentsField = document.querySelector('[id$="company_segments"]');
        if (!companySegmentsField) {
            return;
        }

        var fieldContainer = companySegmentsField.closest('.col-xs-4, .col-sm-4');
        if (!fieldContainer) {
            return;
        }

        var ownerField = document.querySelector('[id$="_owner"]');
        if (!ownerField) {
            return;
        }

        var ownerContainer = ownerField.closest('.col-xs-4, .col-sm-4');
        if (!ownerContainer) {
            return;
        }

        // Reorder the field
        if (ownerContainer.nextElementSibling) {
            ownerContainer.parentNode.insertBefore(fieldContainer, ownerContainer.nextElementSibling);
        } else {
            ownerContainer.parentNode.appendChild(fieldContainer);
        }
    }


    // Run on initial page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', reorderCompanySegmentsField);
    } else {
        reorderCompanySegmentsField();
    }

    // Run after every AJAX request (for SPA-style navigation)
    mQuery(document).ajaxComplete(function(event, xhr, settings) {
        reorderCompanySegmentsField();
    });
})();
