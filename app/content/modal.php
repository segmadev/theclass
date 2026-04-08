<div id="bs-example-modal-md" class="modal fade" tabindex="-1" aria-labelledby="bs-example-modal-md" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h6 class="modal-title" id="myModalLabel">
                    Title
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- <form action="" id="foo"> -->
            <!-- <div id="custommessage"></div> -->
            <div class="modal-body col-12" id="modal-body"></div>
            <!-- </form> -->
            <div class="modal-footer">
                <button type="button" class="btn btn-light-danger text-danger font-medium waves-effect" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

<div id="bs-image-viwer-modal-md" class="modal fade" tabindex="-1" aria-labelledby="bs-image-viwer-modal-md" aria-hidden="true">
    <div class="modal-dialog  modal-lg">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h6 class="modal-title" id="myModalLabel">
                    Image Viewer
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- <form action="" id="foo"> -->
            <!-- <div id="custommessage"></div> -->
            <div class="modal-body col-12"><img src="" style="object-fit: contain; width: 100%;" id="imageviewer" alt="loading..."></div>
            <!-- </form> -->
            <div class="modal-footer">
                <button type="button" class="btn btn-light-danger text-danger font-medium waves-effect" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>


<script>
    modalelements = document.querySelectorAll('[data-url]');
    iniModal(modalelements)
    function iniModal(modalelements){
        modalelements.forEach(element => {
        element.style.cursor = 'pointer';
        // Check if the event listener has already been added
        if (!element.dataset.listenerAdded) {
            element.addEventListener('click', function(e) {
                modalcontentv2(element);
            });

            // Mark that the listener has been added
            element.dataset.listenerAdded = 'true';
        }
    });
    }
    
    function modalcontentv2(value) {
    // Get the modal body and link from the data attributes
    let link = value.dataset.url;
    let scrollToTop = false;
    contentDivId = link.startsWith("modal") ? (value.dataset.content || "modal-body") : (value.dataset.content || "maincontentdiv");
    let contentDiv = document.getElementById(contentDivId);
    // Check if the content ID is specified in the data attributes
    if (link.startsWith("modal")) {
        const title = value.dataset.title;
        const reloadButton = document.createElement('span');
        reloadButton.className = "btn btn-tool fs-3 text-primary";
        reloadButton.innerHTML = '<li class="nav-icon fas fa-sync"></li> Reload';
        reloadButton.addEventListener('click', () => modalcontentv2(value));
        // Set the modal title
        const modaltitle = document.getElementById('myModalLabel');
        modaltitle.innerHTML = `${title} | `;
        modaltitle.appendChild(reloadButton);
    } 
    if (link.startsWith("index")) {
        scrollToTop = true;
        const sidebarLinks = document.querySelectorAll('.sidebar-item.selected');
        console.log(sidebarLinks);
        sidebarLinks.forEach(activelink => {
            activelink.classList.remove('selected');
        });
        window.history.replaceState(null, null, link);
        link = link.replace("index", "modal");
    }
    contentDiv.innerHTML = ` <div class="loading-spinner-container">
        <div class="spinner"></div>
      </div>`;
    $.ajax({
        type: 'GET',
        url: link,
        data: {},
        success: function(response) {
            contentDiv.innerHTML = response;
            if(scrollToTop) window.scrollTo({ top: 0, behavior: 'smooth' });
            // handleDynamicScripts(contentDiv)
            try {

                const mainBody = document.getElementById('main-wrapper');
                const button = document.getElementById('sidebarCollapse');
                
                if (mainBody && button && mainBody.classList.contains('show-sidebar')) {
                    button.click();
                }
            } catch (error) {
                // Silently catch any errors to prevent them from being logged
            }


            // Initialize countdowns if function exists
            if (typeof initializeCountdowns === "function") {
                initializeCountdowns();
            }

            // Load fetch data if function exists
            if (typeof loadFetchData === "function") {
                document.querySelectorAll("[data-load]").forEach(loaddata => {
                    loadFetchData(loaddata);
                });
            }

            if (typeof jsInis === "function") {
                jsInis(contentDiv);
            }
            // Initialize forms and modal elements
            document.querySelectorAll('#foo').forEach(element => iniForm(element));
            const modalelements = contentDiv.querySelectorAll('[data-url]');
            iniModal(modalelements);
        }
    });
}


async function handleDynamicScripts(targetElement) {
  // Function to load jQuery if not already present
  async function includeJQuery() {
    if (window.jQuery) {
      // jQuery is already loaded
      return;
    }

    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://code.jquery.com/jquery-3.6.0.min.js'; // Update jQuery version as needed
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Failed to load jQuery'));
      document.head.appendChild(script);
    });
  }

  // Ensure jQuery is included
  try {
    await includeJQuery();

    // Insert the HTML content into the target element
    // const targetElement = document.getElementById(targetElementId);
    // targetElement.innerHTML = htmlContent;

    // Handle external scripts
    const externalScripts = targetElement.querySelectorAll('script[src]');
    for (const script of externalScripts) {
      const newScript = document.createElement('script');
      newScript.src = script.src;
      newScript.onload = () => {
        // Remove the old script tag after loading
        script.remove();
      };
      newScript.onerror = () => {
        console.error('Failed to load external script:', script.src);
      };
      document.body.appendChild(newScript);
    }

    // Handle inline scripts
    const inlineScripts = targetElement.querySelectorAll('script:not([src])');
    for (const script of inlineScripts) {
      const newScript = document.createElement('script');
      newScript.textContent = script.textContent;
      newScript.onload = () => {
        // Optionally handle script execution completion
        console.log('Inline script executed.');
      };
      newScript.onerror = () => {
        console.error('Failed to execute inline script.');
      };
      document.body.appendChild(newScript);
      script.remove(); // Remove the old inline script tag
    }

  } catch (error) {
    console.error('Error:', error);
  }
}







    function modalcontent(id) {
        return null;
    }
</script>