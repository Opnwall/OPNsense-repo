<div class="content-box" style="padding-bottom: 1.5em;">
    <div class="alert alert-info" role="alert">
        {{ lang._('The complete Unbound configuration is validated before the service is restarted. If validation fails, the previous generated fragment remains active.') }}
    </div>
    {{ partial("layout_partials/base_form", ['fields': generalForm, 'id': 'frm_general_settings']) }}
    <div class="col-md-12">
        <hr />
        <button class="btn btn-primary" id="saveAct" type="button">
            <b>{{ lang._('Save and Apply') }}</b> <i id="saveAct_progress"></i>
        </button>
    </div>
</div>

<script>
$(function () {
    mapDataToFormUI({'frm_general_settings': '/api/unboundcustom/general/get'}).done(function () {
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    $('#saveAct').click(function () {
        var $progress = $('#saveAct_progress');
        $progress.addClass('fa fa-spinner fa-pulse');
        saveFormToEndpoint('/api/unboundcustom/general/set', 'frm_general_settings', function () {
            ajaxCall('/api/unboundcustom/service/apply', {}, function (data) {
                $progress.removeClass('fa fa-spinner fa-pulse');
                if (data.status === 'ok') {
                    BootstrapDialog.show({type: BootstrapDialog.TYPE_SUCCESS, title: '{{ lang._("Success") }}', message: data.message});
                } else {
                    BootstrapDialog.show({type: BootstrapDialog.TYPE_DANGER, title: '{{ lang._("Error") }}', message: $('<div/>').text(data.message).html().replace(/\n/g, '<br>')});
                }
            });
        });
    });
});
</script>
