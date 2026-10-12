(function () {
    'use strict';

    var dirty = false;

    function snapshot(form) {
        return Array.from(form.elements).map(function (field, index) {
            if (field.disabled || /^(submit|button|reset)$/i.test(field.type)) return null;
            var key = field.name || field.id || 'field-' + index;
            if (field.type === 'checkbox' || field.type === 'radio') return [key, field.type, field.value, field.checked];
            if (field.type === 'file') return [key, field.type, field.files ? field.files.length : 0];
            if (field.tagName === 'SELECT' && field.multiple) {
                return [key, 'select-multiple', Array.from(field.selectedOptions).map(function (option) { return option.value; })];
            }
            return [key, field.type || field.tagName, field.value];
        }).filter(Boolean);
    }

    var baselines = new WeakMap();
    function refreshDirty() {
        dirty = Array.from(document.forms).some(function (form) {
            var baseline = baselines.get(form);
            return baseline && JSON.stringify(snapshot(form)) !== baseline;
        });
    }

    function registerForms() {
        Array.from(document.forms).forEach(function (form) {
            if (!baselines.has(form)) baselines.set(form, JSON.stringify(snapshot(form)));
        });
        refreshDirty();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerForms, { once: true });
    } else {
        registerForms();
    }

    document.addEventListener('input', refreshDirty, true);
    document.addEventListener('change', refreshDirty, true);
    document.addEventListener('reset', function (event) {
        window.setTimeout(function () {
            if (event.target && event.target.tagName === 'FORM') refreshDirty();
        }, 0);
    }, true);
    document.addEventListener('submit', function (event) {
        if (event.target && event.target.tagName === 'FORM') {
            baselines.set(event.target, JSON.stringify(snapshot(event.target)));
            refreshDirty();
        }
    }, true);

    window.addEventListener('beforeunload', function (event) {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}());
