document.addEventListener("DOMContentLoaded", () => {
    const batchSize = 3;
    const accountID = new URLSearchParams(window.location.search).get("id");
    const uploadButton = $("#uploadbatch");
    const stopButton = $("<button id='stopUpload' class='btn btn-danger ms-2'>Stop Upload</button>").hide();
    const alertBox = $("<div id='alertBox' class='mt-3'></div>");
    const progressContainer = $("<div id='progressContainer' class='mt-3' style='display:none;'></div>");

    uploadButton.after(stopButton).after(alertBox).after(progressContainer);

    let totalSuccess = 0;
    let totalFailed = 0;
    let totalLogins = $(".add-new").length;
    let processing = false;
    let stopProcessing = false;

    if (!accountID) {
        showAlert("danger", "Account ID is missing in the URL.");
        return;
    }

    // 🚀 Start Upload or Retry Failed Ones
    uploadButton.on("click", function () {
        if (!processing) {
            stopProcessing = false;
            processing = true;
            stopButton.show();
            uploadButton.prop("disabled", true);

            // 🔄 Reset failed states when user retries
            $(".add-new.failed").removeClass("failed bg-light-danger").css("border", "");
            $(".add-new .text-danger").remove();

            totalLogins = $(".add-new").length;
            progressContainer.show();
            updateProgress();
            processLogins();
        }
    });

    // ⏹️ Stop Upload
    stopButton.on("click", function () {
        stopProcessing = true;
        processing = false;
        stopButton.hide();
        uploadButton.prop("disabled", false);
        showAlert("warning", "Upload process stopped by user.");
    });

    // ⚡ Handle Batch Processing
    function processLogins() {
        if (stopProcessing) {
            processing = false;
            uploadButton.prop("disabled", false);
            return;
        }

        let logins = $(".add-new").not(".failed").slice(0, batchSize).map(function () {
            return {
                login_details: $(this).find("textarea[name='login_details[]']").val(),
                username: $(this).find("input[name='username[]']").val(),
                preview_link: $(this).find("input[name='preview_link[]']").val(),
                element: $(this)
            };
        }).get().filter(login => login.login_details.trim() && login.username.trim());

        let totalLeft = $(".add-new").not(".failed").length;

        if (!logins.length) {
            // 🎉 All done, show summary
            processing = false;
            stopButton.hide();
            uploadButton.prop("disabled", false);
            updateProgress();
            showAlert("success", `✅ Upload Complete!<br>✨ Success: ${totalSuccess}<br>❌ Failed: ${totalFailed}<br>📌 Total Logins: ${totalLogins}`);
            return;
        }

        let formData = new FormData();
        formData.append("update_account", "account");
        formData.append("accountID", accountID);
        formData.append("page", "account");
        formData.append("uploadType", "batch");

        logins.forEach(login => {
            formData.append("login_details[]", login.login_details);
            formData.append("preview_link[]", login.preview_link);
            formData.append("username[]", login.username);
        });

        sendBatchToServer(formData, logins, totalLeft);
    }

    // 📡 Send Batch and Handle Response
    function sendBatchToServer(fd, logins, totalLeft) {
        $.ajax({
            url: "passer",
            type: "POST",
            cache: false,
            processData: false,
            contentType: false,
            data: fd,
            success: function (response) {
                try {
                    let res = JSON.parse(response);

                    if (res.count > 0) {
                        totalSuccess += res.count;
                        logins.slice(0, res.count).forEach(({ element }) => element.remove());
                    }

                    if (res.failed_logins.length > 0) {
                        totalFailed += res.failed_logins.length;
                        res.failed_logins.forEach(({ index, reason }) => {
                            let failedElement = $(".add-new").not(".failed").eq(index);
                            failedElement.remove();
                            // failedElement.addClass("failed bg-light-danger").css("border", "2px solid red");
                            // failedElement.append(`<p class="text-danger">${reason}</p>`);
                        });
                    }

                    updateProgress(totalLeft);
                    setTimeout(processLogins, 1000);

                } catch (error) {
                    showAlert("danger", "⚡ Error processing server response.");
                    setTimeout(processLogins, 1000);
                }
            },
            error: function () {
                showAlert("danger", "⚠️ Upload failed. Retrying next batch...");
                setTimeout(processLogins, 1000);
            }
        });
    }

    // 📊 Update Progress Bar and Details
    function updateProgress(totalLeft = $(".add-new").not(".failed").length) {
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

    // 📝 Bootstrap Alert Message
    function showAlert(type, message) {
        alertBox.html(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
    }
});


// export logins
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("Copied to clipboard!");
    }).catch(err => {
        console.error("Failed to copy:", err);
    });
}

document.addEventListener("DOMContentLoaded", function () {
    const exportBtn = document.getElementById("exportBtn");

    exportBtn.addEventListener("click", function () {
        exportLogins();
    });
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
                alertShown = true; // Set flag so confirm doesn’t show again
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