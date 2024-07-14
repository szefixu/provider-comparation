<?php
$jsonFile = 'vps_data.json';

function loadData() {
    global $jsonFile;
    return json_decode(file_get_contents($jsonFile), true) ?: ['dostawcy' => []];
}

function saveData($data) {
    global $jsonFile;
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
}

function calculatePrices($price, $period) {
    $monthlyPrice = $price;
    switch ($period) {
        case 'quarterly':
            $monthlyPrice = $price / 3;
            break;
        case 'semi_annually':
            $monthlyPrice = $price / 6;
            break;
        case 'annually':
            $monthlyPrice = $price / 12;
            break;
    }
    return [
        'monthly' => $monthlyPrice,
        'quarterly' => $monthlyPrice * 3,
        'semi_annually' => $monthlyPrice * 6,
        'annually' => $monthlyPrice * 12
    ];
}

$data = loadData();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_provider':
                $data['dostawcy'][] = ['nazwa' => $_POST['nazwa'], 'pakiety' => [], 'pakiety_specjalne' => []];
                break;
            case 'add_package':
                $prices = calculatePrices((float)$_POST['cena'], $_POST['okres_rozliczeniowy']);
                $data['dostawcy'][$_POST['provider']]['pakiety'][] = [
                    'nazwa' => $_POST['nazwa'],
                    'vCPU' => (int)$_POST['vCPU'],
                    'RAM' => (int)$_POST['RAM'],
                    'dysk' => (int)$_POST['dysk'],
                    'cena' => $prices,
                    'waluta' => $_POST['waluta'],
                    'procesor_typ' => $_POST['procesor_typ'],
                    'procesor_nazwa' => $_POST['procesor_nazwa']
                ];
                break;
            case 'add_special_package':
                $prices = calculatePrices((float)$_POST['cena'], $_POST['okres_rozliczeniowy']);
                $data['dostawcy'][$_POST['provider']]['pakiety_specjalne'][] = [
                    'nazwa' => $_POST['nazwa'],
                    'vCPU' => (int)$_POST['vCPU'],
                    'RAM' => (int)$_POST['RAM'],
                    'dysk' => (int)$_POST['dysk'],
                    'cena' => $prices,
                    'waluta' => $_POST['waluta'],
                    'procesor_typ' => $_POST['procesor_typ'],
                    'procesor_nazwa' => $_POST['procesor_nazwa'],
                    'ograniczenie' => $_POST['ograniczenie']
                ];
                break;
            case 'delete_provider':
                unset($data['dostawcy'][$_POST['provider']]);
                $data['dostawcy'] = array_values($data['dostawcy']);
                break;
            case 'delete_package':
                unset($data['dostawcy'][$_POST['provider']]['pakiety'][$_POST['package']]);
                $data['dostawcy'][$_POST['provider']]['pakiety'] = array_values($data['dostawcy'][$_POST['provider']]['pakiety']);
                break;
            case 'delete_special_package':
                unset($data['dostawcy'][$_POST['provider']]['pakiety_specjalne'][$_POST['package']]);
                $data['dostawcy'][$_POST['provider']]['pakiety_specjalne'] = array_values($data['dostawcy'][$_POST['provider']]['pakiety_specjalne']);
                break;
        }
        saveData($data);
    }
}

function sortPackages($packages) {
    usort($packages, function($a, $b) {
        $order = ['Intel' => 1, 'AMD' => 2, 'ARM' => 3];
        return $order[$a['procesor_typ']] - $order[$b['procesor_typ']];
    });
    return $packages;
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edytor VPS Data</title>
    <link rel="stylesheet" href="style_edytor.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Edytor VPS Data</h1>
        
        <div class="add-provider-form">
            <h2>Dodaj nowego dostawcę</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_provider">
                <input type="text" name="nazwa" placeholder="Nazwa dostawcy" required>
                <button type="submit">Dodaj dostawcę</button>
            </form>
        </div>

        <?php foreach ($data['dostawcy'] as $providerId => $provider): ?>
            <div class="provider">
                <h2><?php echo htmlspecialchars($provider['nazwa']); ?></h2>
                <form method="post" class="delete-provider-form">
                    <input type="hidden" name="action" value="delete_provider">
                    <input type="hidden" name="provider" value="<?php echo $providerId; ?>">
                    <button type="submit" class="delete-btn">Usuń dostawcę</button>
                </form>

                <div class="add-package-form">
                    <h3>Dodaj nowy pakiet</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_package">
                        <input type="hidden" name="provider" value="<?php echo $providerId; ?>">
                        <input type="text" name="nazwa" placeholder="Nazwa pakietu" required>
                        <input type="number" name="vCPU" placeholder="vCPU" required>
                        <input type="number" name="RAM" placeholder="RAM (GB)" required>
                        <input type="number" name="dysk" placeholder="Dysk (GB)" required>
                        <input type="number" name="cena" placeholder="Cena" step="0.01" required>
                        <select name="okres_rozliczeniowy" required>
                            <option value="monthly">Miesięcznie</option>
                            <option value="quarterly">Kwartalnie</option>
                            <option value="semi_annually">Półrocznie</option>
                            <option value="annually">Rocznie</option>
                        </select>
                        <select name="waluta" required>
                            <option value="EUR">EUR</option>
                            <option value="PLN">PLN</option>
                            <option value="USD">USD</option>
                        </select>
                        <select name="procesor_typ" required>
                            <option value="Intel">Intel</option>
                            <option value="AMD">AMD</option>
                            <option value="ARM">ARM</option>
                        </select>
                        <input type="text" name="procesor_nazwa" placeholder="Nazwa procesora" required>
                        <button type="submit">Dodaj pakiet</button>
                    </form>
                </div>

                <h3>Pakiety</h3>
                <div class="packages">
                    <?php
                    $sortedPackages = sortPackages($provider['pakiety']);
                    foreach ($sortedPackages as $packageId => $package):
                    ?>
                        <div class="package <?php echo strtolower($package['procesor_typ']); ?>">
                            <h4><?php echo htmlspecialchars($package['nazwa']); ?></h4>
                            <p>vCPU: <?php echo $package['vCPU']; ?></p>
                            <p>RAM: <?php echo $package['RAM']; ?> GB</p>
                            <p>Dysk: <?php echo $package['dysk']; ?> GB</p>
                            <p>Cena miesięczna: <?php echo $package['cena']['monthly'] . ' ' . $package['waluta']; ?></p>
                            <p>Cena kwartalna: <?php echo $package['cena']['quarterly'] . ' ' . $package['waluta']; ?></p>
                            <p>Cena półroczna: <?php echo $package['cena']['semi_annually'] . ' ' . $package['waluta']; ?></p>
                            <p>Cena roczna: <?php echo $package['cena']['annually'] . ' ' . $package['waluta']; ?></p>
                            <p>Procesor: <?php echo $package['procesor_typ'] . ' ' . $package['procesor_nazwa']; ?></p>
                            <form method="post" class="delete-package-form">
                                <input type="hidden" name="action" value="delete_package">
                                <input type="hidden" name="provider" value="<?php echo $providerId; ?>">
                                <input type="hidden" name="package" value="<?php echo $packageId; ?>">
                                <button type="submit" class="delete-btn">Usuń pakiet</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="add-special-package-form">
                    <h3>Dodaj pakiet specjalny</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_special_package">
                        <input type="hidden" name="provider" value="<?php echo $providerId; ?>">
                        <input type="text" name="nazwa" placeholder="Nazwa pakietu" required>
                        <input type="number" name="vCPU" placeholder="vCPU" required>
                        <input type="number" name="RAM" placeholder="RAM (GB)" required>
                        <input type="number" name="dysk" placeholder="Dysk (GB)" required>
                        <input type="number" name="cena" placeholder="Cena" step="0.01" required>
                        <select name="okres_rozliczeniowy" required>
                            <option value="monthly">Miesięcznie</option>
                            <option value="quarterly">Kwartalnie</option>
                            <option value="semi_annually">Półrocznie</option>
                            <option value="annually">Rocznie</option>
                        </select>
                        <select name="waluta" required>
                            <option value="EUR">EUR</option>
                            <option value="PLN">PLN</option>
                            <option value="USD">USD</option>
                        </select>
                        <select name="procesor_typ" required>
                            <option value="Intel">Intel</option>
                            <option value="AMD">AMD</option>
                            <option value="ARM">ARM</option>
                        </select>
                        <input type="text" name="procesor_nazwa" placeholder="Nazwa procesora" required>
                        <select name="ograniczenie" required>
                            <option value="czasowe">Ograniczenie czasowe</option>
                            <option value="ilosciowe">Ograniczenie ilościowe</option>
                        </select>
                        <button type="submit">Dodaj pakiet specjalny</button>
                    </form>
                </div>

                <h3>Pakiety specjalne</h3>
                <div class="special-packages">
                    <?php
                    $sortedSpecialPackages = sortPackages($provider['pakiety_specjalne']);
                    foreach ($sortedSpecialPackages as $packageId => $package):
                    ?>
                        <div class="package special-package <?php echo strtolower($package['procesor_typ']); ?>">
                            <h4><?php echo htmlspecialchars($package['nazwa']); ?></h4>
                            <p>vCPU: <?php echo $package['vCPU']; ?></p>
                            <p>RAM: <?php echo $package['RAM']; ?> GB</p>
                            <p>Dysk: <?php echo $package['dysk']; ?> GB</p>
                            <p>Cena miesięczna: <?php echo $package['cena']['monthly'] . ' ' . $package['waluta']; ?></p>
                            <p>Cena kwartalna: <?php echo $package['cena']['quarterly'] . ' ' . $package['waluta']; ?></p>
                            <p>Cena półroczna: <?php echo $package['cena']['semi_annually'] . ' ' . $package['waluta']; ?></p>
                            <p>Cena roczna: <?php echo $package['cena']['annually'] . ' ' . $package['waluta']; ?></p>
                            <p>Procesor: <?php echo $package['procesor_typ'] . ' ' . $package['procesor_nazwa']; ?></p>
                            <p>Ograniczenie: <?php echo $package['ograniczenie']; ?></p>
                            <form method="post" class="delete-package-form">
                                <input type="hidden" name="action" value="delete_special_package">
                                <input type="hidden" name="provider" value="<?php echo $providerId; ?>">
                                <input type="hidden" name="package" value="<?php echo $packageId; ?>">
                                <button type="submit" class="delete-btn">Usuń pakiet specjalny</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
    $(document).ready(function() {
        $('.delete-provider-form, .delete-package-form').on('submit', function(e) {
            if (!confirm('Czy na pewno chcesz usunąć ten element?')) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>