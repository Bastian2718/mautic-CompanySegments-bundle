Mautic.company_segmentsOnLoad = function(container, response) {
    const segmentCountElem = mQuery('a.col-count');

    if (segmentCountElem.length) {
        segmentCountElem.each(function() {
            const elem = mQuery(this);
            const id = elem.attr('data-id');

            Mautic.ajaxActionRequest(
                'plugin:LeuchtfeuerCompanySegments:getCompaniesCount',
                {id: id},
                function (response) {
                    elem.html(response.html);
                },
                false,
                true,
                "GET"
            );
        });
    }

    if (mQuery(container + ' #list-search').length) {
        Mautic.activateSearchAutocomplete('list-search', 'company_segments.company_segment');
    }

    var prefix = 'company_segments';
    var parent = mQuery('.dynamic-content-filter, .dwc-filter');
    if (parent.length) {
        prefix = parent.attr('id');
    }

    if (mQuery('#' + prefix + '_filters').length) {
        mQuery('#available_' + prefix + '_filters').on('change', function() {
            if (mQuery(this).val()) {
                Mautic.addCompanySegmentsFilter(mQuery(this).val(),mQuery('option:selected',this).data('field-object'));
                mQuery(this).val('');
                mQuery(this).trigger('chosen:updated');
            }
        });

        mQuery('#' + prefix + '_filters .remove-selected').each( function (index, el) {
            mQuery(el).on('click', function () {
                mQuery(this).closest('.panel').animate(
                    {'opacity': 0},
                    'fast',
                    function () {
                        mQuery(this).remove();
                        Mautic.reorderCompanySegmentFilters();
                    }
                );

                if (!mQuery('#' + prefix + '_filters li:not(.placeholder)').length) {
                    mQuery('#' + prefix + '_filters li.placeholder').removeClass('hide');
                } else {
                    mQuery('#' + prefix + '_filters li.placeholder').addClass('hide');
                }
            });
        });

        var bodyOverflow = {};
        mQuery('#' + prefix + '_filters').sortable({
            items: '.panel',
            helper: function(e, ui) {
                ui.children().each(function() {
                    if (mQuery(this).is(":visible")) {
                        mQuery(this).width(mQuery(this).width());
                    }
                });

                // Fix body overflow that messes sortable up
                bodyOverflow.overflowX = mQuery('body').css('overflow-x');
                bodyOverflow.overflowY = mQuery('body').css('overflow-y');
                mQuery('body').css({
                    overflowX: 'visible',
                    overflowY: 'visible'
                });

                return ui;
            },
            scroll: true,
            axis: 'y',
            stop: function(e, ui) {
                // Restore original overflow
                mQuery('body').css(bodyOverflow);

                // First in the list should be an "and"
                ui.item.find('select.glue-select').first().val('and');

                Mautic.reorderCompanySegmentFilters();
            }
        });

    }

    jQuery(document).ajaxComplete(function(){
        Mautic.ajaxifyForm('daterange');
    });

    Mautic.attachJsUiOnCompanySegmentsFilterForms();
};

Mautic.addCompanySegmentsFilter = function (elId, elObj) {
    var filterId = '#available_' + elObj + '_' + elId;
    var filterOption = mQuery(filterId);
    var label = filterOption.text();

    // Create a new filter

    var filterNum = parseInt(mQuery('.available-filters').data('index'));
    mQuery('.available-filters').data('index', filterNum + 1);

    var prototypeStr = mQuery('.available-filters').data('prototype');
    var fieldType = filterOption.data('field-type');
    var fieldObject = filterOption.data('field-object');

    prototypeStr = prototypeStr.replace(/__name__/g, filterNum);
    prototypeStr = prototypeStr.replace(/__label__/g, label);

    // Convert to DOM
    var prototype = mQuery(prototypeStr);

    var prefix = 'company_segments';
    var parent = mQuery(filterId).parents('.dynamic-content-filter, .dwc-filter');
    if (parent.length) {
        prefix = parent.attr('id');
    }

    var filterBase  = prefix + "[filters][" + filterNum + "]";
    var filterIdBase = prefix + "_filters_" + filterNum + "_";

    if (mQuery('#' + prefix + '_filters div.panel').length === 0) {
        // First filter so hide the glue footer
        prototype.find(".panel-heading").addClass('hide');
    }

    if (fieldObject === 'company') {
        prototype.find(".object-icon").removeClass('fa-user').addClass('fa-building');
    } else {
        prototype.find(".object-icon").removeClass('fa-building').addClass('fa-user');
    }
    prototype.find(".inline-spacer").append(fieldObject);

    prototype.find("a.remove-selected").on('click', function() {
        mQuery(this).closest('.panel').animate(
            {'opacity': 0},
            'fast',
            function () {
                mQuery(this).remove();
                Mautic.reorderCompanySegmentFilters();
            }
        );
    });

    prototype.find("input[name='" + filterBase + "[field]']").val(elId);
    prototype.find("input[name='" + filterBase + "[type]']").val(fieldType);
    prototype.find("input[name='" + filterBase + "[object]']").val(fieldObject);
    prototype.appendTo('#' + prefix + '_filters');

    var operators = filterOption.data('field-operators');
    mQuery('#' + filterIdBase + 'operator').html('');
    mQuery.each(operators, function (label, value) {
        var newOption = mQuery('<option/>').val(value).text(label);
        newOption.appendTo(mQuery('#' + filterIdBase + 'operator'));
    });

    // Convert based on first option in list
    Mautic.convertCompanySegmentFilterInput('#' + filterIdBase + 'operator');

    // Reposition if applicable
    Mautic.updateCompanySegmentFilterPositioning(mQuery('#' + filterIdBase + 'glue'));
};

Mautic.convertCompanySegmentFilterInput = function(el) {
    var operatorSelect = mQuery(el);

    // Extract the filter number
    var regExp = /_filters_(\d+)_operator/;
    var matches = regExp.exec(operatorSelect.attr('id'));
    var filterNum = matches[1];
    var fieldAlias = mQuery('#company_segments_filters_'+filterNum+'_field');
    var fieldObject = mQuery('#company_segments_filters_'+filterNum+'_object');
    var filterValue = mQuery('#company_segments_filters_'+filterNum+'_properties_filter').val();
    var filterId  = '#company_segments_filters_' + filterNum + '_properties_filter';

    Mautic.loadCompanyFilterForm(filterNum, fieldObject.val(), fieldAlias.val(), operatorSelect.val(), function(propertiesFields) {
        var selector = '#company_segments_filters_'+filterNum;
        mQuery(selector+'_properties').html(propertiesFields);

        Mautic.triggerOnCompanySegmentPropertiesFormLoadedEvent(selector, filterValue);
    });

    Mautic.setProcessorForFilterValue(filterId, operatorSelect.val());
};

Mautic.loadCompanyFilterForm = function(filterNum, fieldObject, fieldAlias, operator, resultHtml) {
    mQuery.ajax({
        showLoadingBar: true,
        url: mauticAjaxUrl,
        type: 'POST',
        data: {
            action: 'plugin:LeuchtfeuerCompanySegments:loadCompanySegmentFilterForm',
            fieldAlias: fieldAlias,
            fieldObject: fieldObject,
            operator: operator,
            filterNum: filterNum,
        },
        dataType: 'json',
        success: function (response) {
            Mautic.stopPageLoadingBar();
            resultHtml(response.viewParameters.form);
        },
        error: function (request, textStatus, errorThrown) {
            Mautic.processAjaxError(request, textStatus, errorThrown);
        }
    });
}

Mautic.triggerOnCompanySegmentPropertiesFormLoadedEvent = function(selector, filterValue) {
    mQuery('#company_segments_filters').trigger('filter.properties.form.loaded', [selector, filterValue]);
};

Mautic.attachJsUiOnCompanySegmentsFilterForms = function() {
    mQuery('#company_segments_filters').on('filter.properties.form.loaded', function(event, selector, filterValue) {
        Mautic.activateChosenSelect(selector + '_properties select');
        var fieldType = mQuery(selector + '_type').val();
        var fieldAlias = mQuery(selector + '_field').val();
        var filterFieldEl = mQuery(selector + '_properties_filter');

        if (filterValue) {
            filterFieldEl.val(filterValue);
            if (filterFieldEl.is('select')) {
                filterFieldEl.trigger('chosen:updated');
            }
        }

        if (fieldType === 'lookup') {
            Mautic.activateLookupTypeahead(filterFieldEl.parent());
        } else if (fieldType === 'datetime') {
            filterFieldEl.datetimepicker({
                format: 'Y-m-d H:i',
                lazyInit: true,
                validateOnBlur: false,
                allowBlank: true,
                scrollMonth: false,
                scrollInput: false
            });
        } else if (fieldType === 'date') {
            filterFieldEl.datetimepicker({
                timepicker: false,
                format: 'Y-m-d',
                lazyInit: true,
                validateOnBlur: false,
                allowBlank: true,
                scrollMonth: false,
                scrollInput: false,
                closeOnDateSelect: true
            });
        } else if (fieldType === 'time') {
            filterFieldEl.datetimepicker({
                datepicker: false,
                format: 'H:i',
                lazyInit: true,
                validateOnBlur: false,
                allowBlank: true,
                scrollMonth: false,
                scrollInput: false
            });
        } else if (fieldType === 'lookup_id') {
            var displayFieldEl = mQuery(selector + '_properties_display');
            var fieldCallback = displayFieldEl.attr('data-field-callback');
            if (fieldCallback && typeof Mautic[fieldCallback] === 'function') {
                var fieldOptions = displayFieldEl.attr('data-field-list');
                Mautic[fieldCallback](selector.replace('#', '') + '_properties_display', fieldAlias, fieldOptions);
            }
        }
    });

    // Trigger event so plugins could attach other JS magic to the form.
    mQuery('#company_segments_filters .panel').each(function() {
        Mautic.triggerOnCompanySegmentPropertiesFormLoadedEvent('#' + mQuery(this).attr('id'));
    });
};

Mautic.reorderCompanySegmentFilters = function() {
    // Update the filter numbers sot that they are ordered correctly when processed and grouped server side
    var counter = 0;

    var prefix = 'company_segments';
    var parent = mQuery('.dynamic-content-filter, .dwc-filter');
    if (parent.length) {
        prefix = parent.attr('id');
    }

    mQuery('#' + prefix + '_filters .panel').each(function() {
        Mautic.updateCompanySegmentFilterPositioning(mQuery(this).find('select.glue-select').first());
        mQuery(this).find('[id^="' + prefix + '_filters_"]').each(function() {
            var id     = mQuery(this).attr('id');
            var name   = mQuery(this).attr('name');
            var suffix = id.split(/[_]+/).pop();

            var isProperties = id.includes("_properties_");

            if (prefix + '_filters___name___filter' === id) {
                return true;
            }

            if (name) {
                if (isProperties){
                    var newName    = prefix + '[filters][' + counter + '][properties][' + suffix + ']';
                    var properties = 'properties_';
                }
                else {
                    var newName = prefix + '[filters][' + counter + '][' + suffix + ']';
                    var properties = '';
                }
                if (name.slice(-2) === '[]') {
                    newName += '[]';
                }

                mQuery(this).attr('name', newName);
                mQuery(this).attr('id', prefix + '_filters_' + counter + '_' + properties + suffix);
            }

            mQuery(this).attr('name', newName);
            mQuery(this).attr('id', prefix + '_filters_'+counter+'_'+suffix);

            // Destroy the chosen and recreate
            if (mQuery(this).is('select') && suffix == "filter") {
                Mautic.destroyChosen(mQuery(this));
                Mautic.activateChosenSelect(mQuery(this));
            }
        });

        ++counter;
    });

    mQuery('#' + prefix + '_filters .panel-heading').removeClass('hide');
    mQuery('#' + prefix + '_filters .panel-heading').first().addClass('hide');
};

Mautic.updateCompanySegmentFilterPositioning = function (el) {
    var $el       = mQuery(el);
    var $parentEl = $el.closest('.panel');
    var list      = $parentEl.parent().children('.panel');
    const isFirst = list.index($parentEl) === 0;

    if (isFirst) {
        $el.val('and');
    }

    if ($el.val() === 'and' && !isFirst) {
        $parentEl.addClass('in-group');
    } else {
        $parentEl.removeClass('in-group');
    }
};
