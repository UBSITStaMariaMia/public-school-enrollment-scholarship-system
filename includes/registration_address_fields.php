<?php
$selectedArea = $values[$addressPrefix . '_province'];
$selectedCityCode = $values[$addressPrefix . '_city'];
$selectedBarangayCode = $values[$addressPrefix . '_barangay'];
$availableCities = $addressAreas[$selectedArea] ?? [];
$selectedCity = $availableCities[$selectedCityCode] ?? null;
?>
<div class="address-fields" data-address-fields="<?= e($addressPrefix) ?>">
    <label>
        Province / Region
        <select name="<?= e($addressPrefix) ?>_province" data-address-area>
            <option value="">Select province or region</option>
            <?php foreach (array_keys($addressAreas) as $areaName): ?>
                <option value="<?= e($areaName) ?>" <?= $areaName === $selectedArea ? 'selected' : '' ?>>
                    <?= e($areaName) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        City / Municipality
        <select name="<?= e($addressPrefix) ?>_city" data-address-city>
            <option value=""><?= $availableCities ? 'Select city or municipality' : 'Choose a province or region first' ?></option>
            <?php foreach ($availableCities as $cityCode => $location): ?>
                <option value="<?= e((string) $cityCode) ?>" <?= (string) $cityCode === $selectedCityCode ? 'selected' : '' ?>>
                    <?= e($location['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Barangay
        <select name="<?= e($addressPrefix) ?>_barangay" data-address-barangay>
            <option value=""><?= $selectedCity ? 'Select barangay' : 'Choose a city or municipality first' ?></option>
            <?php foreach (($selectedCity['barangays'] ?? []) as $barangayCode => $barangay): ?>
                <option value="<?= e((string) $barangayCode) ?>" <?= (string) $barangayCode === $selectedBarangayCode ? 'selected' : '' ?>>
                    <?= e($barangay) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Sitio / Purok / Street
        <input type="text" name="<?= e($addressPrefix) ?>_street" maxlength="150" value="<?= e($values[$addressPrefix . '_street']) ?>">
    </label>
    <label>
        House / Building No.
        <input type="text" name="<?= e($addressPrefix) ?>_house" maxlength="150" value="<?= e($values[$addressPrefix . '_house']) ?>">
    </label>
    <label>
        ZIP Code
        <input type="text" name="<?= e($addressPrefix) ?>_zip" inputmode="numeric" maxlength="10" value="<?= e($values[$addressPrefix . '_zip']) ?>">
    </label>
</div>
