<?php
// Define painting rules as constants for easy modification
define('MIN_TEMP_DIFF_DEW_POINT_C', 3); // 3°C difference
define('MIN_TEMP_DIFF_DEW_POINT_F', 5); // 5°F difference
define('MAX_RELATIVE_HUMIDITY', 85); // 85%
define('MIN_APPLICATION_TEMP_C', 5);
define('MAX_APPLICATION_TEMP_C', 40);
define('MIN_APPLICATION_TEMP_F', 41);
define('MAX_APPLICATION_TEMP_F', 104);

/**
 * Calculates the Dew Point in Celsius using the Magnus-Tetens approximation.
 * This is a standard and accurate formula for this purpose.
 *
 * @param float $tempC Temperature in Celsius.
 * @param float $rh Relative Humidity in %.
 * @return float The calculated Dew Point in Celsius.
 */
function calculateDewPointC(float $tempC, float $rh): float
{
    if ($tempC >= 0 && $tempC <= 50) {
        $a = 17.27;
        $b = 237.7;
    } else { // For temperatures below 0°C
        $a = 22.46;
        $b = 272.62;
    }
    
    $alpha = (($a * $tempC) / ($b + $tempC)) + log($rh / 100);
    $dewPoint = ($b * $alpha) / ($a - $alpha);
    
    return round($dewPoint, 2);
}

/**
 * Main function to analyze conditions and provide a painting decision.
 *
 * @param float $steelTemp Steel Surface Temperature.
 * @param float $airTemp Air Temperature.
 * @param float $rh Relative Humidity.
 * @param string $unit 'C' for Celsius or 'F' for Fahrenheit.
 * @return array An array containing the decision status, message, and details.
 */
function getPaintingDecision(float $steelTemp, float $airTemp, float $rh, string $unit): array
{
    // Normalize all values to Celsius for calculation
    $isMetric = ($unit === 'C');
    $airTempC = $isMetric ? $airTemp : ($airTemp - 32) * 5 / 9;
    $steelTempC = $isMetric ? $steelTemp : ($steelTemp - 32) * 5 / 9;
    
    $dewPointC = calculateDewPointC($airTempC, $rh);
    $tempDifference = $steelTempC - $dewPointC;
    $minDiffC = MIN_TEMP_DIFF_DEW_POINT_C;
    
    $result = [
        'status' => 'GO',
        'statusClass' => 'success',
        'message' => 'Conditions are suitable for painting.',
        'details' => []
    ];

    // Rule 1: Check temperature difference vs. dew point (Most Critical)
    if ($tempDifference < $minDiffC) {
        $result['status'] = 'NO-GO';
        $result['statusClass'] = 'danger';
        $result['message'] = 'DO NOT PAINT. Steel temperature is too close to or below the dew point.';
        $result['details'][] = "Critical: Steel Temp must be at least {$minDiffC}°C above the Dew Point.";
    } elseif ($tempDifference < $minDiffC + 1) { // Borderline
        $result['status'] = 'CAUTION';
        $result['statusClass'] = 'warning';
        $result['message'] = 'CAUTION: Conditions are borderline. Monitor closely.';
        $result['details'][] = "Warning: Temperature difference is very close to the minimum {$minDiffC}°C.";
    }
    
    // Rule 2: Check Relative Humidity
    if ($rh > MAX_RELATIVE_HUMIDITY) {
        if ($result['status'] === 'GO') {
            $result['status'] = 'NO-GO';
            $result['statusClass'] = 'danger';
            $result['message'] = 'DO NOT PAINT. Relative Humidity is too high.';
        }
        $result['details'][] = "Warning: Relative Humidity ({$rh}%) exceeds the maximum limit of " . MAX_RELATIVE_HUMIDITY . "%.";
    }

    // Rule 3: Check Application Temperature Limits
    $minTemp = $isMetric ? MIN_APPLICATION_TEMP_C : MIN_APPLICATION_TEMP_F;
    $maxTemp = $isMetric ? MAX_APPLICATION_TEMP_C : MAX_APPLICATION_TEMP_F;
    $currentSteelTemp = $isMetric ? $steelTempC : $steelTemp;

    if ($currentSteelTemp < $minTemp || $currentSteelTemp > $maxTemp) {
        if ($result['status'] === 'GO') {
            $result['status'] = 'NO-GO';
            $result['statusClass'] = 'danger';
            $result['message'] = 'DO NOT PAINT. Steel temperature is outside the application range.';
        }
        $result['details'][] = "Warning: Steel Temperature ({$currentSteelTemp}°{$unit}) is outside the typical range of {$minTemp}°{$unit} to {$maxTemp}°{$unit}.";
    }

    // Prepare details for display
    $displayDewPoint = $isMetric ? round($dewPointC, 1) : round($dewPointC * 9 / 5 + 32, 1);
    $displayTempDiff = $isMetric ? round($tempDifference, 1) : round($tempDifference * 9 / 5, 1);
    $minDiffDisplay = $isMetric ? $minDiffC : MIN_TEMP_DIFF_DEW_POINT_F;

    $result['details'] = array_merge([
        "Calculated Dew Point: {$displayDewPoint}°{$unit}",
        "Steel Temp minus Dew Point: {$displayTempDiff}°{$unit} (Minimum Required: {$minDiffDisplay}°{$unit})",
        "Relative Humidity: {$rh}%",
        "Steel Temperature: {$currentSteelTemp}°{$unit}"
    ], $result['details']);

    return $result;
}

// --- PHP POST Handling for server-side validation (if JS is disabled) ---
 $result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $airTemp = filter_input(INPUT_POST, 'airTemp', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $steelTemp = filter_input(INPUT_POST, 'steelTemp', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $rh = filter_input(INPUT_POST, 'rh', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $unit = filter_input(INPUT_POST, 'unit', FILTER_SANITIZE_STRING);

    if ($airTemp !== null && $steelTemp !== null && $rh !== null) {
        $result = getPaintingDecision($steelTemp, $airTemp, $rh, $unit);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Paint Condition Checker</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .container { max-width: 700px; }
        .card { border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .card-header { background-color: #007bff; color: white; font-weight: bold; }
        .form-label { font-weight: 600; }
        .results-container { margin-top: 2rem; }
        .disclaimer { font-size: 0.8em; color: #6c757d; margin-top: 2rem; }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="card">
        <div class="card-header text-center">
            <h1 class="h3 mb-0">Marine Industry Paint Condition Checker</h1>
        </div>
        <div class="card-body">
            <p class="text-center text-muted">Enter the current site conditions to get a professional painting recommendation.</p>
            
            <form id="paintForm" method="post" action="paint_checker.php">
                <div class="row g-3">
                    <!-- Unit Toggle -->
                    <div class="col-12 text-center mb-3">
                        <div class="btn-group" role="group" aria-label="Unit toggle">
                            <input type="radio" class="btn-check" name="unit" id="unitC" value="C" autocomplete="off" checked>
                            <label class="btn btn-outline-primary" for="unitC">Metric (°C)</label>
                            <input type="radio" class="btn-check" name="unit" id="unitF" value="F" autocomplete="off">
                            <label class="btn btn-outline-primary" for="unitF">Imperial (°F)</label>
                        </div>
                    </div>

                    <!-- Air Temperature -->
                    <div class="col-md-4">
                        <label for="airTemp" class="form-label">Air Temperature</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="airTemp" name="airTemp" step="0.1" required>
                            <span class="input-group-text" id="airTempUnit">°C</span>
                        </div>
                    </div>

                    <!-- Relative Humidity -->
                    <div class="col-md-4">
                        <label for="rh" class="form-label">Relative Humidity (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="rh" name="rh" min="0" max="100" step="1" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <!-- Steel Temperature -->
                    <div class="col-md-4">
                        <label for="steelTemp" class="form-label">Steel Surface Temp</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="steelTemp" name="steelTemp" step="0.1" required>
                            <span class="input-group-text" id="steelTempUnit">°C</span>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Results Section -->
            <div id="resultsContainer" class="results-container" style="display: none;">
                <hr>
                <div id="resultsAlert" class="alert" role="alert">
                    <h4 id="resultsTitle" class="alert-heading"></h4>
                    <p id="resultsMessage"></p>
                    <hr>
                    <h5>Details:</h5>
                    <ul id="resultsDetails"></ul>
                </div>
            </div>
            
            <?php if ($result): ?>
            <!-- Server-side rendered results (if JS is off) -->
            <div class="results-container">
                <hr>
                <div class="alert alert-<?= htmlspecialchars($result['statusClass']) ?>" role="alert">
                    <h4 class="alert-heading">Decision: <?= htmlspecialchars($result['status']) ?></h4>
                    <p><?= htmlspecialchars($result['message']) ?></p>
                    <hr>
                    <h5>Details:</h5>
                    <ul>
                        <?php foreach ($result['details'] as $detail): ?>
                            <li><?= htmlspecialchars($detail) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <div class="disclaimer text-center">
        <strong>Disclaimer:</strong> This tool is a guide based on common industry standards (ISO 8502-4). Always defer to the specific paint manufacturer's Technical Data Sheet (TDS) and a certified inspector's judgment. Surface preparation and other factors are also critical.
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('paintForm');
    const airTempInput = document.getElementById('airTemp');
    const steelTempInput = document.getElementById('steelTemp');
    const rhInput = document.getElementById('rh');
    const unitCInput = document.getElementById('unitC');
    const unitFInput = document.getElementById('unitF');
    const resultsContainer = document.getElementById('resultsContainer');
    const resultsAlert = document.getElementById('resultsAlert');
    const resultsTitle = document.getElementById('resultsTitle');
    const resultsMessage = document.getElementById('resultsMessage');
    const resultsDetails = document.getElementById('resultsDetails');
    const airTempUnit = document.getElementById('airTempUnit');
    const steelTempUnit = document.getElementById('steelTempUnit');

    // --- JavaScript version of the PHP logic for real-time updates ---
    function calculateDewPointC(tempC, rh) {
        let a, b;
        if (tempC >= 0 && tempC <= 50) {
            a = 17.27; b = 237.7;
        } else {
            a = 22.46; b = 272.62;
        }
        const alpha = ((a * tempC) / (b + tempC)) + Math.log(rh / 100);
        return (b * alpha) / (a - alpha);
    }

    function getPaintingDecisionJS(steelTemp, airTemp, rh, unit) {
        const isMetric = (unit === 'C');
        const airTempC = isMetric ? airTemp : (airTemp - 32) * 5 / 9;
        const steelTempC = isMetric ? steelTemp : (steelTemp - 32) * 5 / 9;
        
        const dewPointC = calculateDewPointC(airTempC, rh);
        const tempDifference = steelTempC - dewPointC;
        const minDiffC = 3; // From PHP constant

        let status = 'GO', statusClass = 'success', message = 'Conditions are suitable for painting.', details = [];

        if (tempDifference < minDiffC) {
            status = 'NO-GO'; statusClass = 'danger'; message = 'DO NOT PAINT. Steel temperature is too close to or below the dew point.';
            details.push(`Critical: Steel Temp must be at least ${minDiffC}°C above the Dew Point.`);
        } else if (tempDifference < minDiffC + 1) {
            status = 'CAUTION'; statusClass = 'warning'; message = 'CAUTION: Conditions are borderline. Monitor closely.';
            details.push(`Warning: Temperature difference is very close to the minimum ${minDiffC}°C.`);
        }
        if (rh > 85) { // From PHP constant
            if (status === 'GO') { status = 'NO-GO'; statusClass = 'danger'; message = 'DO NOT PAINT. Relative Humidity is too high.'; }
            details.push(`Warning: Relative Humidity (${rh}%) exceeds the maximum limit of 85%.`);
        }
        const minTemp = isMetric ? 5 : 41; // From PHP constants
        const maxTemp = isMetric ? 40 : 104;
        const currentSteelTemp = isMetric ? steelTempC : steelTemp;
        if (currentSteelTemp < minTemp || currentSteelTemp > maxTemp) {
            if (status === 'GO') { status = 'NO-GO'; statusClass = 'danger'; message = 'DO NOT PAINT. Steel temperature is outside the application range.'; }
            details.push(`Warning: Steel Temperature (${currentSteelTemp.toFixed(1)}°${unit}) is outside the typical range of ${minTemp}°${unit} to ${maxTemp}°${unit}.`);
        }

        const displayDewPoint = isMetric ? dewPointC.toFixed(1) : (dewPointC * 9 / 5 + 32).toFixed(1);
        const displayTempDiff = isMetric ? tempDifference.toFixed(1) : (tempDifference * 9 / 5).toFixed(1);
        const minDiffDisplay = isMetric ? minDiffC : 5;

        details.unshift(
            `Calculated Dew Point: ${displayDewPoint}°${unit}`,
            `Steel Temp minus Dew Point: ${displayTempDiff}°${unit} (Minimum Required: ${minDiffDisplay}°${unit})`,
            `Relative Humidity: ${rh}%`,
            `Steel Temperature: ${currentSteelTemp.toFixed(1)}°${unit}`
        );

        return { status, statusClass, message, details };
    }

    function updateResults() {
        const airTemp = parseFloat(airTempInput.value);
        const steelTemp = parseFloat(steelTempInput.value);
        const rh = parseFloat(rhInput.value);
        const unit = unitCInput.checked ? 'C' : 'F';

        if (isNaN(airTemp) || isNaN(steelTemp) || isNaN(rh)) {
            resultsContainer.style.display = 'none';
            return;
        }

        const result = getPaintingDecisionJS(steelTemp, airTemp, rh, unit);

        resultsTitle.textContent = `Decision: ${result.status}`;
        resultsMessage.textContent = result.message;
        
        resultsDetails.innerHTML = '';
        result.details.forEach(detail => {
            const li = document.createElement('li');
            li.textContent = detail;
            resultsDetails.appendChild(li);
        });

        resultsAlert.className = `alert alert-${result.statusClass}`;
        resultsContainer.style.display = 'block';
    }
    
    function updateUnits() {
        const unit = unitCInput.checked ? 'C' : 'F';
        airTempUnit.textContent = `°${unit}`;
        steelTempUnit.textContent = `°${unit}`;
        updateResults();
    }

    // Event Listeners for real-time updates
    form.addEventListener('input', updateResults);
    unitCInput.addEventListener('change', updateUnits);
    unitFInput.addEventListener('change', updateUnits);
});
</script>

</body>
</html>