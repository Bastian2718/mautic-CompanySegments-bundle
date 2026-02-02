(function() {
    'use strict';

    function reorderCompanySegmentsField() {
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

        if (ownerContainer.nextElementSibling) {
            ownerContainer.parentNode.insertBefore(fieldContainer, ownerContainer.nextElementSibling);
        } else {
            ownerContainer.parentNode.appendChild(fieldContainer);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', reorderCompanySegmentsField);
    } else {
        reorderCompanySegmentsField();
    }

    mQuery(document).ajaxComplete(function(event, xhr, settings) {
        reorderCompanySegmentsField();
    });
})();
