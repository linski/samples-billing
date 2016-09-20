<!DOCTYPE html>
<html>
    <head>
        <?php require(RWS_COMMON_TEMPLATE_PATH . '/head.php'); ?>
        <link rel="stylesheet" href="<?= $this->static_url; ?>/vendor/selectize/dist/css/selectize.bootstrap3.css<?= _CB; ?>" />
        <style>.ui-datepicker-calendar {display: none;} .reports_notification{color:#4086AF;font-weight: bold;}</style>
        <script type="text/javascript" src="<?= $this->static_url; ?>/js/jquery.rwsTablesorter.js<?= _CB; ?>"></script>
        <script type="text/javascript" src="<?= $this->static_url; ?>/vendor/selectize/dist/js/standalone/selectize.min.js<?= _CB; ?>"></script>
        <script type="text/javascript">
        $(function() {

            $.when($.ajax('/json/user_assistance/billing_reports/index')).done(function (data) {
                reports = data.data.reports;
                var html = _.template($('#billing_reports_panel').html(), {data: reports});

                $("#billing_reports_html").html(html);
                $('#list_of_reports').selectize({
                    create: true,
                    persist: false,
                    highlight: true,
                    hideSelected: true,
                    onInitialize: function() {
                        $("div .selectize-dropdown-content").css("max-height","31.25em");
                    },
                    onChange: function(value) {
                        pickDatesForReports(value);
                    }
                });

                $('.datepicker').datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showButtonPanel: true,
                    yearRange: (new Date().getFullYear() - 10) + ':' + (new Date().getFullYear() + 2),
                    dateFormat: "MM yy",
                    viewMode: "months",
                    minViewMode: "months",
                    onClose: function() {
                        var month = $("#ui-datepicker-div .ui-datepicker-month :selected").val();
                        var year = $("#ui-datepicker-div .ui-datepicker-year :selected").val();
                        $(this).datepicker('setDate', new Date(year, month, 1));
                    }
                });
            });
        });

        var pickDatesForReports = function(value) {
            var pencode_classes = ["GW2CMS6SalesItems","GW2AllAccounts","GW2NewBrokerageAccounts"];
            if (value == "AllGW2andCMS6Payments")
                $("#start_date").val("July 2015");
            else if (value == "AllReceivables")
                $("#start_date").val("January 2015");
            else if (value == "ThisMonthSales")
                $("#start_date").val("<?php echo date('F Y'); ?>");
            else if (value == "LastMonthSales")
                $("#start_date").val("<?php echo date('F Y', strtotime("-1 months")); ?>");
            else
                $("#start_date").val("");

            if ($.inArray(value,pencode_classes) == -1)
                $("#for_pencode").hide();
            else
                $("#for_pencode").show();

        };

        $(document).on('click', '#generate_report', function() {
            if (!$("#list_of_reports").val()) {
                new RWS.Dialog.alert("<span class='reports_notification'>Please select a Report first</span>").show();
                return;
            }

            var data = $("#reports_form").serializeArray();

            $('#reports_processing').dialog({
                autoOpen: false,
                width: 500,
                modal: true,
                buttons: {
                    "Close": function() {
                        $(this).dialog("close");
                        window.location.reload(true);
                    }
                }
            });

            $("#reports_processing").dialog("open");
            $("div .ui-dialog-buttonpane").hide();
                $.ajax({
                    type: "POST",
                    url: '/json/user_assistance/billing_reports/create',
                    dataType: "json",
                    data: data
                }).done(function(data, textStatus, jqXHR) {
                    switch (data.response_code) {
                        case 200:
                            $("#reports_processing #reports_wait").hide();
                            $("div .ui-dialog-buttonpane").show();
                            $("#reports_processing #reports_done").show();
                            break;
                        default:
                            RWS.utils.show_error_dialog(data.response_message);
                            break;
                    }
                });

        });

        </script>
    </head>
    <body>
        <?php require(RWS_COMMON_TEMPLATE_PATH . '/header.php'); ?>
        <div class="page_title_header" style="line-height: 1.8em;padding-left: 0.9em;"><h1>Billing Reports</h1>
            <p>Please select a Billing Report from the drop-down menu below first, choose filters and click Generate Report button.
                <br>It will take some time to generate a report, then it will be sent to <span class="reports_notification"><?php echo $this->sentry->get_current_user()->getUsername(); ?></span><br><br>
            </p>
        </div>

        <div id="billing_reports_html"></div>
        <!--<legend>Summary</legend>-->

        <?php $this->footer(); ?>

        <div id="reports_processing" title="Generating Billing Report" style="display: none;">
            <div id="reports_wait" style="display: table;">
                <div style="float:left;line-height: 1.98em;">Please wait while we generate Billing Report</div>
                <div style="float:left;padding-left:1.56em;"><image src="<?= $this->static_url; ?>/images/32x32-bx_loader.gif" width="16" height="16"></div>
            </div>
            <div id="reports_done" style="display:none;">
                <br>
                <p><b>Your Billing Report has been generated and sent to your email.</b></p>
            </div>
        </div>

        <script type="text/template" id="billing_reports_panel">
            <div style="display: block;">
                <form class="rlgn-cms-form rlgn-cms-modal-form" role="form" id="reports_form">
                    <div class="form-group" style="padding-bottom: 2.2em;">
                        <label for="list_of_reports" class="col-sm-2 control-label">Choose Report:</label>
                        <div class="col-sm-10">
                            <select name="list_of_reports" id="list_of_reports" placeholder="Select a report...">
                                <option value="">
                                <% for (i = 0; i < data.length; ++i) { %>
                                    <option value="<%= data[i].class %>">
                                        <%= data[i].title %>
                                    </option>
                                <% } %>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="padding-bottom: 2.2em;">
                        <label for="start_date" class="col-sm-2 control-label">Invoice Starts From</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control datepicker" name="start_date" id="start_date" value="" />
                        </div>
                    </div>
                    <div class="form-group" style="padding-bottom: 2.2em;" id="for_pencode">
                        <label for="pencode_filter" class="col-sm-2 control-label">Pencode Filter</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" name="pencode_filter" value="" />
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="button" id="generate_report" class="btn btn-info">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
        </script>    
    </body>
</html>
