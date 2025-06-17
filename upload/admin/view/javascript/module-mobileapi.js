$(document).ready(function () {
    $("body").delegate("table.apimodule button.delete", "click", function () {
        var data = {
            'device_id': $(this).data('device_id'),
            'site_id': $('#site_id').val()
        };
        var tr = $(this).closest("tr");
        var spinner = tr.find(".spinner");
        spinner.show();
        $.ajax({
            type: "POST",
            url: $('#delete_action').val(),
            data: data,
            success: function (response) {
                if (response.status == 'success') {
                    tr.remove();
                    $("div.deleted").show();
                } else if (response.status == 'error') {
                    $("div.not_deleted").show();
                }
            },
            error: function (response) {
            },
            complete: function () {
                $(".spinner").hide();
            },
            dataType: 'json'
        });
    });

    $('button.close').on('click', function () {
        $(this).closest('div').hide();
    });
});
