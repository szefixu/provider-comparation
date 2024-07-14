<?php
$jsonFile = 'vps_data.json';
$data = json_decode(file_get_contents($jsonFile), true);

function calculateValue($package, $metric) {
    $monthlyPrice = $package['cena']['monthly'] ?? 0;
    if ($monthlyPrice == 0) return 0;
    switch ($metric) {
        case 'ram': return ($package['RAM'] ?? 0) / $monthlyPrice;
        case 'cpu': return ($package['vCPU'] ?? 0) / $monthlyPrice;
        case 'combined': return (($package['RAM'] ?? 0) * ($package['vCPU'] ?? 0)) / $monthlyPrice;
    }
    return 0;
}

function findBestPackage($packages, $metric) {
    return array_reduce($packages, function($carry, $package) use ($metric) {
        return (!$carry || calculateValue($package, $metric) > calculateValue($carry, $metric)) ? $package : $carry;
    });
}

$allPackages = $allSpecialPackages = [];
foreach ($data['dostawcy'] ?? [] as $provider) {
    foreach ($provider['pakiety'] ?? [] as $package) {
        $package['dostawca'] = $provider['nazwa'] ?? 'Nieznany dostawca';
        $allPackages[] = $package;
    }
    foreach ($provider['pakiety_specjalne'] ?? [] as $package) {
        $package['dostawca'] = $provider['nazwa'] ?? 'Nieznany dostawca';
        $allSpecialPackages[] = $package;
    }
}

$bestOverall = [
    'ram' => findBestPackage($allPackages, 'ram'),
    'cpu' => findBestPackage($allPackages, 'cpu'),
    'combined' => findBestPackage($allPackages, 'combined')
];

$bestSpecial = [
    'ram' => findBestPackage($allSpecialPackages, 'ram'),
    'cpu' => findBestPackage($allSpecialPackages, 'cpu'),
    'combined' => findBestPackage($allSpecialPackages, 'combined')
];

function renderPackage($package, $metric = null, $isSpecial = false) {
    if (!$package) return '';
    $monthlyPrice = $package['cena']['monthly'] ?? 0;
    $unitCost = $unitLabel = '';
    if ($metric) {
        switch ($metric) {
            case 'ram': 
                $unitCost = $package['RAM'] > 0 ? $monthlyPrice / $package['RAM'] : 0;
                $unitLabel = 'GB RAM';
                break;
            case 'cpu':
                $unitCost = $package['vCPU'] > 0 ? $monthlyPrice / $package['vCPU'] : 0;
                $unitLabel = 'vCPU';
                break;
            case 'combined':
                $combinedUnits = ($package['RAM'] ?? 0) * ($package['vCPU'] ?? 0);
                $unitCost = $combinedUnits > 0 ? $monthlyPrice / $combinedUnits : 0;
                $unitLabel = 'RAM*vCPU';
                break;
        }
    }

    // Determine processor type class
    $processorClass = '';
    if (isset($package['procesor_typ'])) {
        switch (strtolower($package['procesor_typ'])) {
            case 'intel':
                $processorClass = ' intel';
                break;
            case 'amd':
                $processorClass = ' amd';
                break;
            case 'arm':
                $processorClass = ' arm';
                break;
        }
    }

    $html = "<div class='package-card" . $processorClass . ($isSpecial ? " special" : "") . "'>";
    $html .= "<h3>" . htmlspecialchars($package['nazwa']) . "</h3>";
    $html .= "<p class='provider'>" . htmlspecialchars($package['dostawca'] ?? 'Nieznany dostawca') . "</p>";
    $html .= "<div class='specs'>";
    $html .= "<span>{$package['RAM']} GB RAM</span>";
    $html .= "<span>{$package['vCPU']} vCPU</span>";
    $html .= "<span>{$package['dysk']} GB SSD</span>";
    $html .= "</div>";
    $html .= "<p class='processor'>{$package['procesor_typ']} - {$package['procesor_nazwa']}</p>";
    $html .= "<p class='price'>" . number_format($monthlyPrice, 2) . " {$package['waluta']}/mies.</p>";
    if ($metric) {
        $html .= "<p class='metric'>Koszt za {$unitLabel}: " . number_format($unitCost, 2) . " {$package['waluta']}</p>";
    }
    if ($isSpecial) {
        $html .= "<span class='special-tag'>{$package['ograniczenie']}</span>";
    }
    $html .= "</div>";
    return $html;
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Porównywarka VPS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Porównywarka VPS</h1>
        </header>
        
        <main>
            <section id="best-offers">
                <h2>Najlepsze oferty</h2>
                <div class="packages-grid">
                    <?= renderPackage($bestOverall['ram'], 'ram') ?>
                    <?= renderPackage($bestOverall['cpu'], 'cpu') ?>
                    <?= renderPackage($bestOverall['combined'], 'combined') ?>
                </div>
            </section>

            <section id="best-special-offers">
                <h2>Najlepsze oferty specjalne</h2>
                <div class="packages-grid">
                    <?= renderPackage($bestSpecial['ram'], 'ram', true) ?>
                    <?= renderPackage($bestSpecial['cpu'], 'cpu', true) ?>
                    <?= renderPackage($bestSpecial['combined'], 'combined', true) ?>
                </div>
            </section>

            <section id="all-providers">
                <h2>Oferty dostawców</h2>
                <?php foreach ($data['dostawcy'] as $providerIndex => $provider): ?>
                    <div class="provider-section">
                        <h3><?= htmlspecialchars($provider['nazwa']) ?></h3>
                        <button class="toggle-offers" data-provider="<?= $providerIndex ?>">Pokaż oferty</button>
                        <div class="provider-offers" id="provider-<?= $providerIndex ?>" style="display:none;">
                            <h4>Standardowe pakiety</h4>
                            <div class="packages-grid">
                                <?php
                                foreach ($provider['pakiety'] as $package) {
                                    $package['dostawca'] = $provider['nazwa'];
                                    echo renderPackage($package);
                                }
                                ?>
                            </div>
                            <?php if (!empty($provider['pakiety_specjalne'])): ?>
                                <h4>Pakiety specjalne</h4>
                                <div class="packages-grid">
                                    <?php
                                    foreach ($provider['pakiety_specjalne'] as $package) {
                                        $package['dostawca'] = $provider['nazwa'];
                                        echo renderPackage($package, null, true);
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        $('.toggle-offers').click(function() {
            var providerId = $(this).data('provider');
            $('#provider-' + providerId).slideToggle();
            $(this).text(function(i, text) {
                return text === "Pokaż oferty" ? "Ukryj oferty" : "Pokaż oferty";
            });
        });
    });
    </script>
</body>
</html>