<?php
// Définition des constantes
define('PROXY_URL', 'tcp://www-cache:3128');
define('IS_LOCAL', true);

// Création du contexte par défaut pour les requêtes HTTP/HTTPS
function create_default_context() {
    $opts = [
        'http' => [
            'proxy' => IS_LOCAL ? null : PROXY_URL,
            'request_fulluri' => !IS_LOCAL,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    return stream_context_create($opts);
}

// Fonction sécurisée pour récupérer les données
function safe_file_get_contents($url) {
    $context = create_default_context();
    $result = @file_get_contents($url, false, $context);
    return $result !== false ? $result : null;
}

// Fonction pour géolocaliser une IP
function geolocalisationIP($ip) {
    $apiKey = "d4909f99acdf43d786fa5d1f2e758673";
    $url = "https://ipgeolocation.abstractapi.com/v1/?api_key={$apiKey}&ip_address={$ip}";
    $response = safe_file_get_contents($url);
    return $response ? json_decode($response, true) : null;
}

// Fonction pour récupérer les données de l'API Waze
function getWazeTrafficData() {
    $url = "https://carto.g-ny.org/data/cifs/cifs_waze_v2.json";
    $response = safe_file_get_contents($url);
    if ($response === null) {
        http_response_code(500);
        echo json_encode(["error" => "Impossible de récupérer les données de l'API Waze."]);
        exit;
    }
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// Gestion des requêtes spécifiques à l'API Waze
if (isset($_GET['wazeTraffic'])) {
    getWazeTrafficData();
}

// Fonction pour obtenir la météo du jour en fonction de la géolocalisation
function getMeteo($lat, $lon) {
    $apiKey = "775786b09c177429efbfba7f94aeb3fb";
    $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&lang=fr&appid={$apiKey}";

    $response = safe_file_get_contents($url);
    if ($response === null) {
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['cod']) && $data['cod'] === 200) {
        return $data; 
    }

    return null;
}


// Fonction pour obtenir la qualité de l'air
function getAirQuality($lat, $lon) {
    $apiKey = "145b55dda74ccda038406bff403842d6e22ae1b5";
    $url = "https://api.waqi.info/feed/geo:{$lat};{$lon}/?token={$apiKey}";
    
    $response = safe_file_get_contents($url);
    if ($response === null) {
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['status']) && $data['status'] === 'ok') {
        return $data['data']; 
    }

    return null;
}


// Fonction pour récupérer l'adresse IP publique
function getPublicIP() {
    return $_SERVER['REMOTE_ADDR'] ?? null;
}


// Obtention de l'adresse IP publique
$ip = getPublicIP();
if (!$ip || $ip === '::1') {
    $ip = '127.0.0.1';
}


// Géolocalisation de l'IP
$geoData = geolocalisationIP($ip);
if ($geoData && isset($geoData['latitude'], $geoData['longitude'])) {
    $lat = (float)$geoData['latitude'];
    $lon = (float)$geoData['longitude'];
} else {
    // Coordonnées par défaut (Nancy)
    $lat = 48.693722;
    $lon = 6.184417;
}


$airQualityData = getAirQuality($lat, $lon);
$meteoData = getMeteo($lat, $lon);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atmosphere</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css">
</head>
<body>
    <header>
        <h1>Nancy - Information en temps réel</h1>
    </header>
    <main>
        <section>
            <?php if ($meteoData): ?>
                <?php
                    $temperature = round($meteoData['main']['temp']);
                    $feelsLike = round($meteoData['main']['feels_like']);
                    $humidity = $meteoData['main']['humidity'];
                    $windSpeed = round($meteoData['wind']['speed'] * 3.6);
                    $description = ucfirst($meteoData['weather'][0]['description']);
                    $city = $meteoData['name'];
                ?>
                <h2>Météo du jour à <?= htmlspecialchars($city) ?></h2>
                <div class="weather-info">
                    <p>Température : <?= $temperature ?>°C</p>
                    <p>Ressenti : <?= $feelsLike ?>°C</p>
                    <p>Humidité : <?= $humidity ?>%</p>
                    <p>Vent : <?= $windSpeed ?> km/h</p>
                    <p>Conditions : <?= htmlspecialchars($description) ?></p>
                </div>
            <?php else: ?>
                <div class="error-message">
                    Impossible de récupérer les données météo.
                </div>
            <?php endif; ?>
        </section>
        <section>
            <h2>Carte des Difficultés de Circulation</h2>
            <div id="map" style="height: 400px;"></div>
        </section>
        <section>
            <h2>Ajouter un marqueur par adresse</h2>
            <div id="address-search" style="margin-top: 20px;">
                <input type="text" id="address-input" placeholder="Entrez une adresse..." style="width: 70%; padding: 10px;">
                <button id="address-search-button" style="padding: 10px 15px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Rechercher
                </button>
                <p id="search-feedback" style="margin-top: 10px; color: red;"></p>
            </div>
        </section>
        <section id="air-quality">
            <h2>Qualité de l'air</h2>
            <?php if ($airQualityData): ?>
                <?php
                    $aqi = $airQualityData['aqi'];
                    $lastUpdate = date('d/m/Y H:i', strtotime($airQualityData['time']['iso'] ?? 'now'));
                    
                    // Déterminer le niveau de qualité de l'air
                    if ($aqi <= 50) {
                        $qualityLevel = "Bonne";
                        $color = "#009966";
                        $icon = "😊";
                    } elseif ($aqi <= 100) {
                        $qualityLevel = "Modérée";
                        $color = "#ffde33";
                        $icon = "😐";
                    } elseif ($aqi <= 150) {
                        $qualityLevel = "Mauvaise pour les groupes sensibles";
                        $color = "#ff9933";
                        $icon = "😷";
                    } else {
                        $qualityLevel = "Mauvaise";
                        $color = "#cc0033";
                        $icon = "⚠️";
                    }
                ?>
                <div style="background-color: <?= $color ?>; padding: 15px; border-radius: 8px; color: white;">
                    <h3>Qualité de l'air <?= $icon ?></h3>
                    <p>Niveau : <?= $qualityLevel ?></p>
                    <p>Indice AQI : <?= $aqi ?></p>
                    <p>Dernière mise à jour : <?= $lastUpdate ?></p>
                </div>
            <?php else: ?>
                <div class="error-message">
                    Impossible de récupérer les données de qualité de l'air.
                </div>
            <?php endif; ?>
        </section>
    </main>
    <footer>
        <h3>Sources des données :</h3>
        <ul id="api-sources">
            <li>Incidents : <a href="https://carto.g-ny.org/data/cifs/cifs_waze_v2.json" target="_blank">API Waze</a></li>
            <li>Qualité de l'air : <a href="https://aqicn.org/api" target="_blank">API WAQI</a></li>
            <li>Météo : <a href="https://openweathermap.org/api" target="_blank">OpenWeatherMap API</a></li>
            <li>Repère : <a href="https://opencagedata.com/api" target="_blank">OpenCage API</a></li>
        </ul>
        <p>
            <a href="https://github.com/1001ms/Atmosphere" target="_blank">Code source sur GitHub</a>
        </p>
    </footer>
    <script>
        const defaultLat = <?php echo $lat; ?>;
        const defaultLon = <?php echo $lon; ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>
