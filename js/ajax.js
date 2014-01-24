M.local_panopto = {
    Y : null,
    transaction : [],
    init : function(Y, courseid, permstr, role_assign_bool, editing) {
        var panopto = Y.one("#panopto-text");
        var panoptofooter = Y.one("#panopto-footer");
        panopto.setHTML("Requesting data...");
        
        Y.io(M.cfg.wwwroot + "/blocks/panopto/ajax.php", {

            timeout: 8000,
            method: "GET",
            data: {
                sesskey: M.cfg.sesskey,
                courseid: courseid,
                permstr: permstr,
                role_assign_bool: role_assign_bool,
                editing: editing
            },
            on: {
                success : function (x,o) {
                    // Process the JSON data returned from the server
                    try {
                        data = Y.JSON.parse(o.responseText);
                    }
                    catch (e) {
                        panopto.setHTML(M.str.block_panopto.ajax_json_error);
                        return;
                    }

                    if (data.error) {
                        panopto.setHTML(M.str.block_panopto.ajax_data_error);
                    } else {
                        panopto.setHTML(data.text);
                        panoptofooter.setHTML(data.footer);
                        if (editing) {
                            // call local tac js
                            Y.use('local_panopto_tac', function(Y) { M.local_panopto_tac.init(Y, courseid); });
                        }
                    }
                },

                failure : function (x,o) {
                    if (o.statusText == "timeout") {
                        panopto.setHTML(M.str.block_panopto.ajax_busy);
                    } else {
                        panopto.setHTML(M.str.block_panopto.ajax_failure);
                    }
                }
            }
        });
    },
    toggleHiddenLectures : function () {
        var showAllToggle = document.getElementById("showAllToggle");
        var hiddenLecturesDiv = document.getElementById("hiddenLecturesDiv");
                
        if(hiddenLecturesDiv.style.display == "block") {
            hiddenLecturesDiv.style.display = "none";
            showAllToggle.innerHTML = M.str.block_panopto.show_all;
        } else {
            hiddenLecturesDiv.style.display = "block";
            showAllToggle.innerHTML = M.str.block_panopto.show_less;
        }
    },
    launchNotes : function (url) {
        // Open empty notes window, then POST SSO form to it.
        var notesWindow = window.open("", "PanoptoNotes", "width=500,height=800,resizable=1,scrollbars=0,status=0,location=0");
        document.SSO.action = url;
        document.SSO.target = "PanoptoNotes";
        document.SSO.submit();

        // Ensure the new window is brought to the front of the z-order.
        notesWindow.focus();
    },
    startSSO : function (linkElem) {
        document.SSO.action = linkElem.href;
        document.SSO.target = "_blank";
        document.SSO.submit();
                
        // Cancel default link navigation.
        return false;
    }
}
