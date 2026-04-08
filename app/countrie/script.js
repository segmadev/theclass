// document.addEventListener("DOMContentLoaded", function() {
//     // Get all the country items and dynamically generate the flag URL
//     let countryItems = document.querySelectorAll('.country-item');
//     countryItems.forEach(function(item) {
//         // Get the country code from the data attribute
//         let countryCode = item.querySelector('.flag').dataset.code.toLowerCase();
        
//         // Set the flag image URL dynamically
//         let flagUrl = `https://flagcdn.com/w320/${countryCode}.png`;
//         if(countryCode == null || countryCode == "") flagUrl = `https://t2.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=hsjdhsd.com&size=24`;
//         item.querySelector('.flag').src = flagUrl;
//     });
// });

// Search function to filter countries
function filterCountries() {
    let searchInput = document.getElementById('search').value.toLowerCase();
    let countries = document.querySelectorAll('.country-item');
    
    countries.forEach(function(country) {
        let countryName = country.querySelector('.country-name').innerText.toLowerCase();
        
        // Check if the search input matches the country name
        if (countryName.includes(searchInput)) {
            country.style.display = "flex";
        } else {
            country.style.display = "none";
        }
    });
}

// Toggle dropdown open/close
function toggleDropdown() {
    let dropdownList = document.getElementById('country-list');
    dropdownList.classList.toggle('active');
}

// Select a country
function selectCountry(countryName) {
    let dropdownHeader = document.querySelector('.dropdown-header');
    dropdownHeader.innerText = countryName;
    
    // Close the dropdown after selection
    toggleDropdown();
}

// Close dropdown when clicking outside

try {
    window.onclick = function(event) {
        let dropdownList = document.getElementById('country-list');
        let dropdownContainer = document.querySelector('.dropdown-container');
        let searchInput = document.getElementById('search');
        
        // Check if the click is outside the dropdown or search input
        if (!dropdownContainer.contains(event.target) && !searchInput.contains(event.target)) {
            if (dropdownList.classList.contains('active')) {
                dropdownList.classList.remove('active');
            }
        }
    };
} catch (error) {
    
}

