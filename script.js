// Fonction pour afficher la carte avec Leaflet
function afficherCarte(latitude, longitude) {
    const map = L.map('map').setView([latitude, longitude], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);
    L.marker([latitude, longitude])
        .addTo(map)
        .bindPopup('Vous êtes ici')
        .openPopup();

    return map;
}

// Fonction pour créer une icône personnalisée
function createCustomIcon(type) {
    let iconHtml = '';
    let backgroundColor = '';
    switch (type) {
        case 'CONSTRUCTION':
            iconHtml = '🚧';
            backgroundColor = '#f39c12';
            break;
        case 'ACCIDENT':
            iconHtml = '⚠️';
            backgroundColor = '#e74c3c';
            break;
        case 'TRAFFIC':
            iconHtml = '🚦';
            backgroundColor = '#3498db';
            break;
        default:
            iconHtml = '❗';
            backgroundColor = '#95a5a6';
            break;
    }
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="
            background-color: ${backgroundColor}; 
            color: white; 
            border-radius: 50%; 
            width: 30px; 
            height: 30px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 16px;
            font-weight: bold;">
                ${iconHtml}
            </div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -15]
    });
}

// Fonction pour placer un marqueur à partir d'une adresse
function ajouterMarqueurParAdresse(map) {
    const searchButton = document.getElementById('address-search-button');
    const addressInput = document.getElementById('address-input');
    const feedback = document.getElementById('search-feedback');
    const apiKey = '799cab377e604a1bb9876dafd49180ea';

    searchButton.addEventListener('click', async () => {
        const address = addressInput.value.trim();
        if (!address) {
            feedback.textContent = "Veuillez entrer une adresse.";
            return;
        }

        feedback.textContent = "Recherche en cours...";

        try {
            const response = await fetch(`https://api.opencagedata.com/geocode/v1/json?q=${encodeURIComponent(address)}&key=${apiKey}`);
            if (!response.ok) {
                throw new Error("Erreur lors de la requête.");
            }

            const data = await response.json();
            if (data.results.length === 0) {
                feedback.textContent = "Adresse introuvable.";
                return;
            }

            const result = data.results[0];
            const { lat, lng } = result.geometry;
            const marker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="
                        background-color: #28a745; 
                        color: white; 
                        border-radius: 50%; 
                        width: 30px; 
                        height: 30px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        font-size: 16px;">
                            📍
                        </div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(map);
            map.setView([lat, lng], 14);

            feedback.textContent = `Marqueur ajouté pour : ${result.formatted}`;
        } catch (error) {
            feedback.textContent = "Erreur lors de la recherche.";
            console.error(error);
        }
    });
}


// Fonction pour récupérer et afficher les incidents de circulation
function afficherIncidents(map) {
    const apiUrl = 'atmosphere.php?wazeTraffic=true';

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau lors de la récupération des données');
            }
            return response.json();
        })
        .then(data => {
            console.log("Données des incidents :", data);

            data.incidents.forEach(incident => {
                const polyline = incident.location.polyline;
                const description = incident.description || 'Incident de circulation';
                const locationDesc = incident.location.location_description || 'Emplacement non spécifié';
                const type = incident.type || 'DEFAULT'; 
     
                if (polyline) {
                    const [latitude, longitude] = polyline.split(' ').map(parseFloat); 
                    if (!isNaN(latitude) && !isNaN(longitude)) {
                        L.marker([latitude, longitude], { icon: createCustomIcon(type) })
                            .addTo(map)
                            .bindPopup(`
                                <b>${type}</b><br>
                                ${description}<br>
                                <i>${locationDesc}</i>
                            `);
                    } else {
                        console.warn("Coordonnées invalides pour l'incident :", incident);
                    }
                } else {
                    console.warn("Aucun polyline pour l'incident :", incident);
                }
            });
        })
        .catch(error => console.error('Erreur lors de la récupération des incidents:', error));
}


// Fonction pour initialiser la carte
function initialiserCarte() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function (position) {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                const map = afficherCarte(latitude, longitude);
                afficherIncidents(map);
                ajouterMarqueurParAdresse(map);
            },
            function (error) {
                console.warn("Erreur de géolocalisation : ", error.message);
                // Coordonnées par défaut en cas d'erreur (Nancy)
                const defaultLat = 48.693722;
                const defaultLon = 6.184417;
                const map = afficherCarte(defaultLat, defaultLon);
                afficherIncidents(map);
                ajouterMarqueurParAdresse(map);
            }
        );
    } else {
        console.warn("La géolocalisation n'est pas supportée par ce navigateur.");
        const defaultLat = 48.693722;
        const defaultLon = 6.184417;
        const map = afficherCarte(defaultLat, defaultLon);
        afficherIncidents(map);
        ajouterMarqueurParAdresse(map);
    }
}

// Initialiser la carte au chargement de la page
window.onload = initialiserCarte;
