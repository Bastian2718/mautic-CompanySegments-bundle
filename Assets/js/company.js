(function($) {
    'use strict';

    function reorderCompanySegmentsImportField() {
        if (!$('form[name="lead_field_import"]').length) return;

        var $segments = $('#lead_field_import_company_segments').closest('.col-xs-4, .col-sm-4');
        var $owner = $('#lead_field_import_owner').closest('.col-xs-4, .col-sm-4');

        if ($segments.length && $owner.length) {
            $owner.after($segments);
        }
    }

    function repositionCompanySegmentsField() {
        var $sidebar = $('form[name="company"] > .box-layout > .col-md-3');
        if (!$sidebar.length) return;

        var $companySegments = $('#company_company_segments').closest('.row');
        if (!$companySegments.length) return;

        var $hr = $sidebar.find('hr').first();
        if ($hr.length) {
            $hr.after($companySegments);
        }
    }

    $(function() {
        reorderCompanySegmentsImportField();
        repositionCompanySegmentsField();
    });
    $(document).ajaxComplete(function(event, xhr, settings) {
        reorderCompanySegmentsImportField();
        repositionCompanySegmentsField();
    });
})(mQuery);


