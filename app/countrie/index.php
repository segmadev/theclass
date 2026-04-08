<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Country Selection Dropdown</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="dropdown-container">
    <div class="dropdown-header" onclick="toggleDropdown()">Select a country</div>
    <div class="dropdown-list" id="country-list">
        <input type="text" id="search" placeholder="Search for a country..." onkeyup="filterCountries()">
        <div class="country-items">
            <?php
            // Load the countries from the JSON file
            $jsonFile = file_get_contents('countries.json');
            $countries = json_decode($jsonFile, true);

            // Display the countries without flag URLs
            foreach ($countries as $country) {
                echo '<div class="country-item" onclick="selectCountry(\'' . $country['name'] . '\')">';
                echo '<img src="" alt="' . $country['name'] . ' flag" class="flag" data-code="' . $country['code'] . '">';
                echo '<span class="country-name">' . $country['name'] . '</span>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</div>

<script src="script.js"></script>

</body>
</html>
