document.addEventListener("DOMContentLoaded", function () {
    const categoryChecks = document.querySelectorAll(".category-check");
    const permissionChecks = document.querySelectorAll(".permission-check");
    const permissions = {};
  
    // Initialize permissions based on initial checked states
    categoryChecks.forEach((categoryCheck) => {
      const category = categoryCheck.value;
      permissions[category] = [];
  
      document
        .querySelectorAll(`.permission-check[data-category="${category}"]`)
        .forEach((permissionCheck) => {
          if (permissionCheck.checked) {
            permissions[category].push(permissionCheck.value);
          }
        });
  
      // If the category is checked but has no permissions, set full access
      if (categoryCheck.checked && permissions[category].length === 0) {
        permissions[category] = [];
      }
    });
  
    // When a category is checked/unchecked, update permissions accordingly
    categoryChecks.forEach((categoryCheck) => {
      categoryCheck.addEventListener("change", function () {
        const category = this.dataset.category;
        permissions[category] = []; // Reset permissions for this category
  
        document
          .querySelectorAll(`.permission-check[data-category="${category}"]`)
          .forEach((permissionCheck) => {
            permissionCheck.checked = this.checked;
            if (this.checked) {
              permissions[category].push(permissionCheck.value);
            }
          });
      });
    });
  
    // When a permission is checked, ensure category is updated as well
    permissionChecks.forEach((permissionCheck) => {
      permissionCheck.addEventListener("change", function () {
        const category = this.dataset.category;
        const categoryCheck = document.querySelector(
          `.category-check[data-category="${category}"]`
        );
  
        if (this.checked) {
          categoryCheck.checked = true;
          if (!permissions[category].includes(this.value)) {
            permissions[category].push(this.value);
          }
        } else {
          permissions[category] = permissions[category].filter(
            (perm) => perm !== this.value
          );
  
          // Uncheck the category if no permissions are selected
          const allUnchecked = [
            ...document.querySelectorAll(
              `.permission-check[data-category="${category}"]`
            ),
          ].every((p) => !p.checked);
          categoryCheck.checked = !allUnchecked;
        }
      });
    });
  
    // Submit form
    document
      .getElementById("roleForm")
      .addEventListener("submit", function (event) {
        event.preventDefault();
        const roleName = document.getElementById("roleName").value;
        const roleID = document.getElementById("roleID").value;
  
        // Debug the permissions object
        console.log("Permissions Object:", permissions);
  
        // Convert data to FormData for AJAX submission
        const fd = new FormData();
        fd.append("roleName", roleName);
        fd.append("ID", roleID);
        fd.append("page", "roles");
        fd.append("permissions", JSON.stringify(permissions));
  
        // Use runjax to submit the data
        runjax(event, $("#roleForm :input"), fd);
      });
  });
  