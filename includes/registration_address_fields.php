<?php
$addressRequired = in_array($addressPrefix, ['current', 'permanent'], true);
$copyCurrentAddress = ($values[$addressPrefix . '_same_as_current'] ?? '0') === '1';
$selectedArea = $values[$addressPrefix . '_province'];
$selectedCityCode = $values[$addressPrefix . '_city'];
$selectedBarangayCode = $values[$addressPrefix . '_barangay'];
$availableCities = $addressAreas[$selectedArea] ?? [];
$selectedCity = $availableCities[$selectedCityCode] ?? null;
?>
<div class="address-fields" data-address-fields="<?= e($addressPrefix) ?>">
    <label>
        <span><?php if ($addressRequired): ?><span class="registration-required" aria-hidden="true">*</span> <?php endif; ?>Province / Region</span>
        <select name="<?= e($addressPrefix) ?>_province" data-address-area <?= $addressRequired ? 'required' : '' ?> <?= $copyCurrentAddress ? 'disabled' : '' ?>>
            <option value="">Select province or region</option>
            <?php foreach (array_keys($addressAreas) as $areaName): ?>
                <option value="<?= e($areaName) ?>" <?= $areaName === $selectedArea ? 'selected' : '' ?>>
                    <?= e($areaName) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span><?php if ($addressRequired): ?><span class="registration-required" aria-hidden="true">*</span> <?php endif; ?>City / Municipality</span>
        <select name="<?= e($addressPrefix) ?>_city" data-address-city <?= $addressRequired ? 'required' : '' ?> <?= $copyCurrentAddress ? 'disabled' : '' ?>>
            <option value=""><?= $availableCities ? 'Select city or municipality' : 'Choose a province or region first' ?></option>
            <?php foreach ($availableCities as $cityCode => $location): ?>
                <option value="<?= e((string) $cityCode) ?>" <?= (string) $cityCode === $selectedCityCode ? 'selected' : '' ?>>
                    <?= e($location['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span><?php if ($addressRequired): ?><span class="registration-required" aria-hidden="true">*</span> <?php endif; ?>Barangay</span>
        <select name="<?= e($addressPrefix) ?>_barangay" data-address-barangay <?= $addressRequired ? 'required' : '' ?> <?= $copyCurrentAddress ? 'disabled' : '' ?>>
            <option value=""><?= $selectedCity ? 'Select barangay' : 'Choose a city or municipality first' ?></option>
            <?php foreach (($selectedCity['barangays'] ?? []) as $barangayCode => $barangay): ?>
                <option value="<?= e((string) $barangayCode) ?>" <?= (string) $barangayCode === $selectedBarangayCode ? 'selected' : '' ?>>
                    <?= e($barangay) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span><?php if ($addressRequired): ?><span class="registration-required" aria-hidden="true">*</span> <?php endif; ?>Sitio / Purok / Street</span>
        <input type="text" name="<?= e($addressPrefix) ?>_street" maxlength="150" value="<?= e($values[$addressPrefix . '_street']) ?>" <?= $addressRequired ? 'required' : '' ?> <?= $copyCurrentAddress ? 'disabled' : '' ?>>
    </label>
    <label>
        <span><?php if ($addressRequired): ?><span class="registration-required" aria-hidden="true">*</span> <?php endif; ?>House / Building No.</span>
        <input type="text" name="<?= e($addressPrefix) ?>_house" maxlength="150" value="<?= e($values[$addressPrefix . '_house']) ?>" <?= $addressRequired ? 'required' : '' ?> <?= $copyCurrentAddress ? 'disabled' : '' ?>>
    </label>
    <label>
        <span><?php if ($addressRequired): ?><span class="registration-required" aria-hidden="true">*</span> <?php endif; ?>ZIP Code</span>
        <input type="text" name="<?= e($addressPrefix) ?>_zip" inputmode="numeric" maxlength="10" value="<?= e($values[$addressPrefix . '_zip']) ?>" <?= $addressRequired ? 'required' : '' ?> <?= $copyCurrentAddress ? 'disabled' : '' ?>>
    </label>
</div>
