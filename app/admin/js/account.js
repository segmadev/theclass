document.addEventListener("DOMContentLoaded", () => {
    const batchSize = 20;
    const accountID = new URLSearchParams(window.location.search).get("id");

    // Use the existing upload button from HTML.
    const uploadButton = $("#uploadbatch");
    const jsonUpload = $("#jsonUpload");
    // Create the Stop button dynamically.
    const stopButton = $("<button id='stopUpload' class='btn btn-danger ms-2'>Stop Upload</button>").hide();
    const progressContainer = $("<div id='progressContainer' class='mt-3' style='display:none;'></div>");

    // Append controls beside the upload button.
    uploadButton.after(stopButton, progressContainer);

    // -------------------------------
    // Create a Bootstrap Accordion for Failed Logins (hidden by default)
    // -------------------------------
    const failedLoginsAccordion = $(`
        <div class="accordion mt-3" id="failedLoginsAccordion" style="display:none;">
            <div class="accordion-item">
                <h2 class="accordion-header" id="failedLoginsHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#failedLoginsBody" aria-expanded="false" aria-controls="failedLoginsBody">
                        Failed Logins <i class="fa fa-angle-down ms-1"></i>
                    </button>
                </h2>
                <div id="failedLoginsBody" class="accordion-collapse collapse" aria-labelledby="failedLoginsHeading" data-bs-parent="#failedLoginsAccordion">
                    <div class="accordion-body"></div>
                </div>
            </div>
        </div>
    `);
    // Append the accordion after the #loginInfos container.
    $("#loginInfos").after(failedLoginsAccordion);

    // -------------------------------
    // Create a container for the Failed Actions (dropdown + view link)
    // This container is not inserted initially.
    // -------------------------------
    const failedActionsContainer = $(`
        <div id="failedActionsContainer" class="d-inline-block ms-2">
            <div class="dropdown d-inline-block">
                <button class="btn btn-secondary dropdown-toggle" type="button" id="failedDropdownButton" data-bs-toggle="dropdown" aria-expanded="false">
                    Export/Copy Failed
                </button>
                <ul class="dropdown-menu" aria-labelledby="failedDropdownButton">
                    <li><a class="dropdown-item" id="copyFailedItem" href="#">Copy Failed Logins</a></li>
                    <li><a class="dropdown-item" id="exportFailedItem" href="#">Export Failed Logins</a></li>
                </ul>
            </div>
            <a href="#" id="viewFailedLink" class="btn btn-link ms-2">View Failed Logins</a>
        </div>
    `);

    // -------------------------------
    // Global variables
    // -------------------------------
    let totalSuccess = 0;
    let totalFailed = 0;
    let totalLogins = $("#loginInfos .add-new").length;
    let processing = false;
    let stopProcessing = false;

    if (!accountID) {
        showToast("Account ID is missing in the URL.", "danger");
        return;
    }

    // -------------------------------
    // Upload Button: Start Upload or Retry Failed Ones
    // -------------------------------
    uploadButton.on("click", function () {
        // If there are any failed logins, move them back to #loginInfos for retrying.
        if ($("#failedLoginsBody .accordion-body .add-new").length > 0) {
            $("#failedLoginsBody .accordion-body .add-new").each(function () {
                $(this).removeClass("failed bg-light-danger").css("border", "");
                $(this).find(".text-danger").remove();
                $("#loginInfos").append($(this).detach());
            });
            // Clear the failed logins count on retry.
            totalFailed = 0;
            updateFailedActionsVisibility();
        }

        if (!processing) {
            stopProcessing = false;
            processing = true;
            stopButton.show();
            uploadButton.prop("disabled", true);

            // Reset any failed state in #loginInfos.
            $("#loginInfos .add-new.failed").removeClass("failed bg-light-danger").css("border", "");
            $("#loginInfos .add-new .text-danger").remove();

            totalLogins = $("#loginInfos .add-new").length;
            progressContainer.show();
            updateProgress();
            processLogins();
        }
    });

    // -------------------------------
    // Stop Upload
    // -------------------------------
    stopButton.on("click", function () {
        stopProcessing = true;
        processing = false;
        stopButton.hide();
        uploadButton.prop("disabled", false);
        showToast("Upload process stopped by user.", "warning");
    });

    // -------------------------------
    // Dropdown Items: Copy and Export Failed Logins Handlers
    // -------------------------------
    $(document).on("click", "#copyFailedItem", function (e) {
        e.preventDefault();
        if ($("#failedLoginsBody .accordion-body .add-new").length === 0) {
            showToast("No failed logins to copy.", "warning");
            return;
        }
        copyFailedLogins();
    });

    $(document).on("click", "#exportFailedItem", function (e) {
        e.preventDefault();
        if ($("#failedLoginsBody .accordion-body .add-new").length === 0) {
            showToast("No failed logins to export.", "warning");
            return;
        }
        exportFailedLogins();
    });

    // -------------------------------
    // View Failed Logins Link Handler
    // -------------------------------
    $(document).on("click", "#viewFailedLink", function (e) {
        e.preventDefault();
        if ($("#failedLoginsBody .accordion-body .add-new").length === 0) {
            showToast("There are no failed logins to view.", "warning");
            return;
        }
        $("html, body").animate({
            scrollTop: $("#failedLoginsAccordion").offset().top
        }, 500);
    });

    // -------------------------------
    // Process Logins in Batch
    // -------------------------------

    function processLogins() {
        if (stopProcessing) {
            processing = false;
            uploadButton.prop("disabled", false);
            return;
        }

        // Check if JSON upload is enabled
        if ($("#jsonUpload").is(":checked")) {
            // Get all logins at once for JSON upload
            let allLogins = $("#loginInfos .add-new").not(".failed").map(function () {
                return {
                    login_details: $(this).find("textarea[name='login_details[]']").val(),
                    username: $(this).find("input[name='username[]']").val(),
                    preview_link: $(this).find("input[name='preview_link[]']").val(),
                    loginID: $(this).attr("data-loginID"),
                    element: $(this)
                };
            }).get().filter(login => login.login_details.trim() && login.username.trim());

            if (!allLogins.length) {
                processing = false;
                stopButton.hide();
                uploadButton.prop("disabled", false);
                updateProgress();
                showToast(`✅ Upload Complete! Success: ${totalSuccess} | Failed: ${totalFailed} | Total: ${totalLogins}`, "success");
                return;
            }

            
            // Create FormData and append logins as JSON string
            let formData = new FormData();
            formData.append("update_account", "account");
            
            formData.append("accountID", accountID);
            formData.append("page", "account");
            formData.append("uploadType", "json");
            formData.append("logins", JSON.stringify(allLogins.map(login => ({
                login_details: login.login_details,
                preview_link: login.preview_link,
                username: login.username,
                loginID: login.loginID
            }))));
            
            sendBatchToServer(formData, allLogins, 0, true);
        } else {
            // Original batch processing for FormData
            let logins = $("#loginInfos .add-new").not(".failed").slice(0, batchSize).map(function () {
                return {
                    login_details: $(this).find("textarea[name='login_details[]']").val(),
                    username: $(this).find("input[name='username[]']").val(),
                    preview_link: $(this).find("input[name='preview_link[]']").val(),
                    loginID: $(this).attr("data-loginID"),
                    element: $(this)
                };
            }).get().filter(login => login.login_details.trim() && login.username.trim());
            
            let totalLeft = $("#loginInfos .add-new").not(".failed").length;
            
            if (!logins.length) {
                processing = false;
                stopButton.hide();
                uploadButton.prop("disabled", false);
                updateProgress();
                showToast(`✅ Upload Complete! Success: ${totalSuccess} | Failed: ${totalFailed} | Total: ${totalLogins}`, "success");
                return;
            }

            let formData = new FormData();
            formData.append("update_account", "account");
            formData.append("accountID", accountID);
            formData.append("page", "account");
            formData.append("uploadType", "batch");
            if ($("#force_update").is(":checked")) {
                formData.append("force_update", "yes");
            }
            logins.forEach(login => {
                formData.append("login_details[]", login.login_details);
                formData.append("preview_link[]", login.preview_link);
                formData.append("username[]", login.username);
                formData.append("loginID[]", login.loginID);
            });
            
            sendBatchToServer(formData, logins, totalLeft, false);
        }
    }

    // -------------------------------
    // Send Batch and Handle Response
    // -------------------------------

    function sendBatchToServer(data, logins, totalLeft, isJson = false) {
        $.ajax({
            url: "passer",
            type: "POST",
            cache: false,
            processData: false,
            contentType: false,
            data: data,
            success: function (response) {
                try {
                    let res = JSON.parse(response);
    
                    if (isJson) {
                        // For JSON upload, show the background processing message immediately
                        processing = false;
                        stopButton.hide();
                        uploadButton.prop("disabled", false);
                        progressContainer.show();
                        progressContainer.html(`
                            <div class="alert alert-info">
                                <h5 class="alert-heading">✅ Upload Queued Successfully!</h5>
                                <p>Your upload has been queued for background processing. You will receive an email notification when the processing is complete.</p>
                                <hr>
                                <p class="mb-0">
                                    <a href="index?p=account&action=list&pendingaccount=yes" class="alert-link text-primary">
                                        <i class="ti ti-refresh"></i> Click here to check the processing status
                                    </a>
                                </p>
                            </div>
                        `);
                        return;
                    }

                    if (res.count > 0) {
                        totalSuccess += res.count;
                        // Remove the successfully processed login elements.
                        logins.slice(0, res.count).forEach(({ element }) => element.remove());
                    }
    
                    if (res.failed_logins.length > 0) {
                        totalFailed += res.failed_logins.length;
                        res.failed_logins.forEach(({ loginID, reason }) => {
                            // Find the failed element using its data-loginID attribute.
                            let failedElement = $("#loginInfos .add-new[data-loginID='" + loginID + "']");
                            if (!failedElement.length) {
                                // In case it's already been moved, try the failed container.
                                failedElement = $("#failedLoginsBody .accordion-body .add-new[data-loginID='" + loginID + "']");
                            }
                            failedElement.remove();
                            failedElement.addClass("failed bg-light-danger").css("border", "2px solid red");
                            failedElement.append(`<p class="text-danger">${reason}</p>`);
                            // Append the updated failed element into the accordion body.
                            $("#failedLoginsBody .accordion-body").append(failedElement);
                        });
                        updateFailedActionsVisibility();
                    }
    
                    updateProgress(totalLeft);
                    setTimeout(processLogins, 1000);
    
                } catch (error) {
                    showToast("Error processing server response.", "danger");
                    if (!isJson) {
                        setTimeout(processLogins, 1000);
                    } else {
                        processing = false;
                        stopButton.hide();
                        uploadButton.prop("disabled", false);
                    }
                }
            },
            error: function () {
                showToast("Upload failed. Retrying next batch...", "danger");
                if (!isJson) {
                    setTimeout(processLogins, 1000);
                } else {
                    processing = false;
                    stopButton.hide();
                    uploadButton.prop("disabled", false);
                }
            }
        });
    }

    // -------------------------------
    // Update Progress Bar and Details
    // -------------------------------
    function updateProgress(totalLeft = $("#loginInfos .add-new").not(".failed").length) {
        let progressPercent = totalLogins > 0 ? Math.round(((totalSuccess + totalFailed) / totalLogins) * 100) : 0;

        
        progressContainer.html(`
            <div class="alert alert-info">
                <p><b>Total Logins:</b> ${totalLogins}</p>
                <p><b>✅ Successfully Uploaded:</b> ${totalSuccess}</p>
                <p><b>❌ Failed Logins:</b> ${totalFailed}</p>
                <p><b>📌 Logins Left to Upload:</b> ${totalLeft}</p>
            </div>
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                     role="progressbar" 
                     style="width: ${progressPercent}%">
                    ${progressPercent}%
                </div>
            </div>
        `);
    }


    // -------------------------------
   // Toast Notification Helper (using Bootstrap Toast)
   // Toast Notification Helper (using Bootstrap Toast)
function showToast(message, type = "info") {
    let container = $("#toastContainer");
    if (!container.length) {
        // Force container to be on the top-right with inline styles.
        container = $('<div id="toastContainer" class="toast-container position-fixed" style="top: 1rem; right: 1rem!important; z-index: 1100;"></div>');
        $("body").append(container);
    }
    let toastId = "toast" + Date.now();
    let toast = $(`
      <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" aria-live="assertive" aria-atomic="true">
         <div class="d-flex">
           <div class="toast-body">
             ${message}
           </div>
           <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
         </div>
      </div>
    `);
    // For success toasts, override with a deeper green and white text.
    if (type === "success") {
        toast.css({ "background-color": "#0a4c2a", "color": "#fff" });
    }
    container.append(toast);
    let bsToast = new bootstrap.Toast(toast[0], { delay: 5000 });
    bsToast.show();
    toast.on('hidden.bs.toast', function () {
       toast.remove();
    });
}



    // -------------------------------
    // Copy Failed Logins Functionality
    // -------------------------------
    function copyFailedLogins() {
        let failedLogins = [];
        $("#failedLoginsBody").find("textarea[name='login_details[]']").each(function () {
            let val = $(this).val().trim();
            if (val) { failedLogins.push(val); }
        });
        if (failedLogins.length === 0) {
            showToast("No failed logins to copy.", "warning");
            return;
        }
        const exportText = failedLogins.join("\n");
        navigator.clipboard.writeText(exportText).then(() => {
            showToast("Failed logins copied to clipboard!", "success");
        }).catch(err => {
            showToast("Failed to copy: " + err, "danger");
        });
    }

    // -------------------------------
    // Export Failed Logins Functionality
    // -------------------------------
    function exportFailedLogins() {
        let failedLogins = [];
        $("#failedLoginsBody").find("textarea[name='login_details[]']").each(function () {
            let val = $(this).val().trim();
            if (val) { failedLogins.push(val); }
        });
        if (failedLogins.length === 0) {
            showToast("No failed logins to export.", "warning");
            return;
        }
        const exportText = failedLogins.join("\n");
        const blob = new Blob([exportText], { type: "text/plain" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "failed_logins.txt";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // -------------------------------
    // Helper: Update visibility of Failed Logins accordion and failed actions container
    // -------------------------------
    function updateFailedActionsVisibility() {
        let failedCount = $("#failedLoginsBody .accordion-body .add-new").length;
        if (failedCount > 0) {
            // Insert the failed actions container if not already in DOM.
            if ($("#failedActionsContainer").length === 0) {
                uploadButton.after(failedActionsContainer);
            }
            $("#failedLoginsAccordion").show();
            // Ensure the accordion is expanded.
            let collapseInstance = bootstrap.Collapse.getInstance(document.getElementById('failedLoginsBody'));
            if (collapseInstance) {
                collapseInstance.show();
            } else {
                new bootstrap.Collapse(document.getElementById('failedLoginsBody'), { show: true });
            }
        } else {
            $("#failedLoginsAccordion").hide();
            $("#failedActionsContainer").remove();
        }
    }

    // -------------------------------
    // (Place any other existing functions (Export All, Filter, Check Button, etc.) below.)


    
// export logins
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("Copied to clipboard!");
    }).catch(err => {
        console.error("Failed to copy:", err);
    });
}


    const exportBtn = document.getElementById("exportBtn");

    exportBtn.addEventListener("click", function () {
        exportLogins();
    });

function exportLogins() {
    const exportBtn = document.getElementById("exportBtn");

    // Disable button and change text to "Exporting..."
    exportBtn.disabled = true;
    exportBtn.textContent = "Exporting...";

    const loginElements = document.querySelectorAll(".login-data-details");
    let logins = [];

    loginElements.forEach(el => {
        let loginText = el.textContent.trim();
        if (loginText) {
            logins.push(loginText);
        }
    });

    if (logins.length === 0) {
        alert("No logins found to export.");
        resetExportState();
        return;
    }

    if (!confirm("Are you sure you want to export all logins? Make sure all logins are loaded and filtered before exporting.")) {
        resetExportState();
        return;
    }

    setTimeout(() => {
        // Get filename from name="title"
        let titleInput = document.querySelector('[name="title"]');
        let fileName = titleInput ? titleInput.value.trim() : "logins";
        if (!fileName) fileName = "logins"; // Default fallback

        // Create text file
        const blob = new Blob([logins.join("\n")], { type: "text/plain" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = fileName + ".txt";

        // Trigger download
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Reset button
        resetExportState();
    }, 1500); // Simulate loading delay
}

function resetExportState() {
    const exportBtn = document.getElementById("exportBtn");
    exportBtn.disabled = false;
    exportBtn.textContent = "Export";
}
// sumit filter
document.getElementById('loadloginForm').addEventListener('submit', function (e) {
    e.preventDefault(); // Prevent form submission

    const formData = new FormData(this); // Get form data
    const loadloginDiv = document.getElementById('loadlogin'); // Target div
    let currentDataPath = loadloginDiv.getAttribute('data-path'); // Get current data-path

    // Convert current data-path to URLSearchParams for easy manipulation
    const urlParams = new URLSearchParams(currentDataPath.split('?')[1] || '');

    // Handle form inputs including checkboxes
    this.querySelectorAll('input, select, textarea').forEach((input) => {
        if (input.type === 'checkbox') {
            // Ensure checkbox value is included, set "false" if unchecked
            urlParams.set(input.name, input.checked ? input.value || "true" : "false");
        } else if (input.type !== 'submit') {
            // Handle other input types normally
            if (formData.has(input.name)) {
                urlParams.set(input.name, formData.get(input.name));
            }
        }
    });

    // Reconstruct data-path with updated parameters
    const updatedDataPath = currentDataPath.split('?')[0] + '?' + urlParams.toString();
    loadloginDiv.setAttribute('data-path', updatedDataPath);

    // Optional: Call loadFetchData function to reload content if needed
    loadFetchData(loadloginDiv);
});


// check button js
document.addEventListener("DOMContentLoaded", function () {
    const checkbox = document.getElementById("delete_logins");
    const button = document.getElementById("fliterButton");
    let alertShown = false; // Flag to track alert state

    checkbox.addEventListener("change", function () {
        if (this.checked) {
            if (!alertShown) {
                const confirmDelete = confirm("Warning: You are about to delete logins! If you need to export, do that before checking delete. ESE.");
                if (!confirmDelete) {
                    this.checked = false; // Uncheck if user cancels
                    return;
                }
                alertShown = true; // Set flag so confirm doesn't show again
            }
            button.textContent = "Filter and Delete"; // Change button text
            button.classList.remove("btn-primary");
            button.classList.add("btn-danger"); // Make button red
        } else {
            button.textContent = "Filter"; // Reset button text
            button.classList.remove("btn-danger");
            button.classList.add("btn-primary"); // Reset button color
        }
    });
});
});
