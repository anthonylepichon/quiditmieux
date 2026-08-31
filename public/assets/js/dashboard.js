/**
 * Description générale : Actualisations périodiques du tableau de bord privé.
 * Rôle : Rafraîchir séparément les ventes et les participations sans recharger la page.
 * Tâches : Éviter les requêtes simultanées, ignorer les réponses obsolètes et reconstruire les cartes en sécurité.
 * Liens avec les autres fichiers : Est chargé par dashboard.php et appelle les routes JSON de UserController.php.
 */

const qdmDashboardStatus = document.querySelector('[data-dashboard-status]');
const qdmDashboardStreams = {
    sales: {busy: false, sequence: 0},
    participations: {busy: false, sequence: 0}
};

/**
 * Rôle : Créer un élément HTML avec une classe et un texte facultatifs.
 * Paramètres : Nom de balise, classe CSS et texte éventuel.
 * Retour : Élément DOM créé sans interpréter de code HTML.
 */
function qdmCreateElement(tagName, className, textContent) {
    const element = document.createElement(tagName);

    if (className) {
        element.className = className;
    }

    if (typeof textContent === 'string') {
        element.textContent = textContent;
    }

    return element;
}

/**
 * Rôle : Construire une carte à partir des seules informations JSON attendues.
 * Paramètres : Données contrôlées de l'annonce.
 * Retour : Article prêt à être inséré dans une zone du tableau de bord.
 */
function qdmBuildDashboardCard(listing) {
    const article = qdmCreateElement('article', 'dashboard-card');
    article.dataset.listingId = String(listing.id);
    const mediaLink = qdmCreateElement('a', 'dashboard-card__media');
    mediaLink.href = listing.detail_url;
    const image = document.createElement('img');

    if (typeof listing.photo_url === 'string') {
        image.src = listing.photo_url;
        image.alt = 'Photographie de ' + listing.title;
    } else {
        image.src = 'public/assets/images/illustrations/shopping-cart.png';
        image.alt = 'Aucune photographie disponible';
    }

    mediaLink.appendChild(image);
    const body = qdmCreateElement('div', 'dashboard-card__body');
    body.appendChild(qdmCreateElement('p', 'eyebrow', listing.category));
    const title = qdmCreateElement('h3');
    const titleLink = qdmCreateElement('a', '', listing.title);
    titleLink.href = listing.detail_url;
    title.appendChild(titleLink);
    body.appendChild(title);
    body.appendChild(qdmCreateElement('p', 'dashboard-card__price', listing.current_price));
    body.appendChild(qdmCreateElement('p', '', String(listing.bid_count) + ' enchère(s)'));

    if (typeof listing.user_best_bid === 'string') {
        body.appendChild(qdmCreateElement('p', '', 'Votre meilleure enchère : ' + listing.user_best_bid));
    }

    let deadlinePrefix = 'Terminée le ';

    if (listing.is_active) {
        deadlinePrefix = 'Fin le ';
    }

    body.appendChild(qdmCreateElement('p', '', deadlinePrefix + listing.deadline));
    article.appendChild(mediaLink);
    article.appendChild(body);
    return article;
}

/**
 * Rôle : Remplacer le contenu d'une zone par ses cartes ou son message vide.
 * Paramètres : Clé de zone, liste d'annonces et message à afficher lorsque la liste est vide.
 * Retour : Aucun.
 */
function qdmRenderDashboardZone(zoneKey, listings, emptyMessage) {
    const zone = document.querySelector('[data-dashboard-zone="' + zoneKey + '"]');

    if (!zone || !Array.isArray(listings)) {
        return;
    }

    zone.replaceChildren();

    if (listings.length === 0) {
        const empty = qdmCreateElement('p', 'dashboard-zone__empty', emptyMessage);
        empty.dataset.emptyMessage = '';
        zone.appendChild(empty);
        return;
    }

    listings.forEach(function appendListing(listing) {
        zone.appendChild(qdmBuildDashboardCard(listing));
    });
}

/**
 * Rôle : Charger une famille de données sans chevauchement et sans appliquer une réponse dépassée.
 * Paramètres : Clé du flux et adresse JSON.
 * Retour : Aucun.
 */
async function qdmRefreshDashboard(streamKey, url) {
    const stream = qdmDashboardStreams[streamKey];

    if (stream.busy) {
        return;
    }

    stream.busy = true;
    stream.sequence += 1;
    const requestSequence = stream.sequence;

    try {
        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
        const data = await response.json();

        if (requestSequence !== stream.sequence || !data || data.success !== true) {
            if (data && typeof data.message === 'string') {
                qdmDashboardStatus.textContent = data.message;
            }
            return;
        }

        if (streamKey === 'sales') {
            qdmRenderDashboardZone('sales', data.sales, 'Vous n’avez publié aucune annonce.');
        } else {
            qdmRenderDashboardZone('participations', data.participations, 'Vous ne suivez aucune vente active et n’avez aucune enchère à afficher.');
            qdmRenderDashboardZone('wins', data.wins, 'Vous n’avez encore remporté aucune enchère.');
        }

        qdmDashboardStatus.textContent = '';
    } catch (error) {
        qdmDashboardStatus.textContent = 'L’actualisation automatique est momentanément indisponible.';
    } finally {
        stream.busy = false;
    }
}

window.setInterval(function refreshSales() {
    qdmRefreshDashboard('sales', 'index.php?route=dashboard_sales');
}, 10000);

window.setInterval(function refreshParticipations() {
    qdmRefreshDashboard('participations', 'index.php?route=dashboard_participations');
}, 2000);
